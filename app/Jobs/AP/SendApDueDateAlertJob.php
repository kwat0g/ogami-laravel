<?php

declare(strict_types=1);

namespace App\Jobs\AP;

use App\Domains\AP\Models\VendorInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily job that alerts stakeholders about AP invoices that are overdue
 * or coming due within the configurable alert window.
 *
 * Thresholds are read from system_settings (zero hardcoding, Sprint 14):
 *   ap.due_date_alert_days  — days-before-due to start alerting (default 7)
 *
 * Scheduled at 08:00 AM daily via routes/console.php.
 * Notifications: currently writes structured log lines; full email/
 * in-app notifications will be wired in Sprint 17.
 */
final class SendApDueDateAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $alertDays = $this->loadAlertDays();

        // ── Overdue invoices ─────────────────────────────────────────────────
        $overdueInvoices = VendorInvoice::with('vendor')
            ->overdue()
            ->orderBy('due_date')
            ->get();

        if ($overdueInvoices->isNotEmpty()) {
            Log::warning('[SendApDueDateAlertJob] Overdue AP invoices detected.', [
                'count' => $overdueInvoices->count(),
                'invoices' => $overdueInvoices->map(fn ($inv) => [
                    'id' => $inv->id,
                    'vendor' => $inv->vendor->name,
                    'due_date' => $inv->due_date->toDateString(),
                    'balance_due' => $inv->balance_due,
                    'days_overdue' => (int) $inv->due_date->diffInDays(now(), absolute: true),
                ])->toArray(),
            ]);

            // TODO Sprint 17: dispatch Mail/Notification to accounting manager
        }

        // ── Upcoming due invoices ────────────────────────────────────────────
        $dueSoonInvoices = VendorInvoice::with('vendor')
            ->dueSoon($alertDays)
            ->orderBy('due_date')
            ->get();

        if ($dueSoonInvoices->isNotEmpty()) {
            Log::info('[SendApDueDateAlertJob] Invoices due soon.', [
                'alert_window_days' => $alertDays,
                'count' => $dueSoonInvoices->count(),
                'invoices' => $dueSoonInvoices->map(fn ($inv) => [
                    'id' => $inv->id,
                    'vendor' => $inv->vendor->name,
                    'due_date' => $inv->due_date->toDateString(),
                    'balance_due' => $inv->balance_due,
                    'days_until' => (int) now()->diffInDays($inv->due_date, absolute: true),
                ])->toArray(),
            ]);

            // TODO Sprint 17: dispatch Mail/Notification to accounting staff
        }

        Log::info(sprintf(
            '[SendApDueDateAlertJob] Done. Overdue: %d, Due soon (≤%dd): %d.',
            $overdueInvoices->count(),
            $alertDays,
            $dueSoonInvoices->count(),
        ));
    }

    // ── Settings helper ───────────────────────────────────────────────────────

    private function loadAlertDays(): int
    {
        $row = DB::table('system_settings')
            ->where('key', 'ap.due_date_alert_days')
            ->value('value');

        return $row !== null ? (int) json_decode($row, true) : 7;
    }
}
