<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\DunningLevel;
use App\Domains\AR\Models\DunningNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automated Dunning Batch Run — A6.
 *
 * Creates dunning notices for all overdue customer invoices, matching
 * the dunning level to the number of days overdue.
 *
 * Designed to run daily via scheduler.
 *
 * Flexibility:
 *   - Dunning levels define escalation tiers (e.g., 30 days = reminder, 60 = warning, 90 = legal)
 *   - Idempotent: won't create duplicate notices for same invoice + level
 *   - Configurable via system_setting 'automation.ar_overdue.auto_create_dunning'
 */
final class RunDunningBatchCommand extends Command
{
    protected $signature = 'ar:run-dunning';

    protected $description = 'Auto-create dunning notices for overdue AR invoices based on dunning levels';

    public function handle(): int
    {
        // Check if automation is enabled
        $enabled = (bool) (DB::table('system_settings')
            ->where('key', 'automation.ar_overdue.auto_create_dunning')
            ->value('value') ?? true);

        if (! $enabled) {
            $this->info('Automated dunning is disabled via system_settings.');

            return self::SUCCESS;
        }

        $today = now()->toDateString();

        // Get all dunning levels ordered by days_overdue
        $levels = DunningLevel::orderBy('days_overdue')->get();

        if ($levels->isEmpty()) {
            $this->warn('No dunning levels configured. Skipping.');

            return self::SUCCESS;
        }

        // Get all overdue invoices
        $overdueInvoices = CustomerInvoice::query()
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('due_date', '<', $today)
            ->whereNull('deleted_at')
            ->with('customer')
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No overdue invoices found.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($overdueInvoices as $invoice) {
            $daysOverdue = (int) now()->diffInDays($invoice->due_date);

            // Find the appropriate dunning level for this overdue duration
            $matchedLevel = $levels->filter(fn ($l) => $daysOverdue >= $l->days_overdue)
                ->last(); // Take the highest matching level

            if ($matchedLevel === null) {
                continue;
            }

            // Check idempotency: don't create duplicate for same invoice + level
            $exists = DunningNotice::where('customer_invoice_id', $invoice->id)
                ->where('dunning_level_id', $matchedLevel->id)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            try {
                DunningNotice::create([
                    'customer_id' => $invoice->customer_id,
                    'customer_invoice_id' => $invoice->id,
                    'dunning_level_id' => $matchedLevel->id,
                    'notice_date' => $today,
                    'days_overdue' => $daysOverdue,
                    'amount_due' => $invoice->balance_due ?? $invoice->subtotal,
                    'status' => 'pending',
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::warning('[Dunning] Failed to create notice', [
                    'invoice_id' => $invoice->id,
                    'level_id' => $matchedLevel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Dunning batch complete: {$created} notices created, {$skipped} skipped (already exists).");

        return self::SUCCESS;
    }
}
