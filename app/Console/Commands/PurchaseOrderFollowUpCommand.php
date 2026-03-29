<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * REC-15: Detect POs stuck at 'sent' status without vendor response.
 *
 * When a PO is sent to a vendor but not acknowledged, there is no timeout
 * mechanism — the PO can stay at 'sent' indefinitely. This command
 * detects stale POs and alerts the procurement team.
 *
 * Schedule: daily at 9am.
 */
final class PurchaseOrderFollowUpCommand extends Command
{
    protected $signature = 'procurement:po-followup
        {--threshold=3 : Days after which a sent PO is considered stale}';

    protected $description = 'Alert procurement team about POs awaiting vendor response';

    public function handle(): int
    {
        $thresholdDays = (int) $this->option('threshold');
        $threshold = now()->subDays($thresholdDays);

        $stalePOs = PurchaseOrder::where('status', 'sent')
            ->where('sent_at', '<', $threshold)
            ->with('vendor:id,name,email')
            ->get();

        if ($stalePOs->isEmpty()) {
            $this->info('No stale POs awaiting vendor response.');

            return self::SUCCESS;
        }

        $this->warn("Found {$stalePOs->count()} PO(s) awaiting vendor response for >{$thresholdDays} days:");

        $table = [];
        foreach ($stalePOs as $po) {
            $daysWaiting = (int) now()->diffInDays($po->sent_at);
            $table[] = [
                $po->po_reference,
                $po->vendor?->name ?? 'N/A',
                $po->sent_at?->toDateString() ?? 'N/A',
                "{$daysWaiting} days",
                number_format((float) $po->total_po_amount / 100, 2),
            ];
        }

        $this->table(
            ['PO Reference', 'Vendor', 'Sent Date', 'Waiting', 'Amount'],
            $table,
        );

        Log::info('PO follow-up: stale POs detected', [
            'count' => $stalePOs->count(),
            'threshold_days' => $thresholdDays,
            'po_references' => $stalePOs->pluck('po_reference')->toArray(),
        ]);

        // Notify procurement officers
        $this->notifyProcurementTeam($stalePOs, $thresholdDays);

        return self::SUCCESS;
    }

    private function notifyProcurementTeam(mixed $stalePOs, int $thresholdDays): void
    {
        try {
            $procurementUsers = User::role(['officer', 'manager'])
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('procurement.create')
                    || $u->hasPermissionTo('procurement.manage'));

            if ($procurementUsers->isEmpty()) {
                $this->warn('No procurement officers found to notify.');

                return;
            }

            $poList = $stalePOs->map(fn ($po) => "{$po->po_reference} ({$po->vendor?->name})")->implode(', ');

            foreach ($procurementUsers as $user) {
                $user->notify(new \Illuminate\Notifications\DatabaseNotification);
                // Use direct database notification for simplicity
                $user->notifications()->create([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\Notifications\Procurement\StalePONotification',
                    'data' => [
                        'title' => 'POs Awaiting Vendor Response',
                        'message' => "{$stalePOs->count()} PO(s) have been waiting for vendor response for more than {$thresholdDays} days: {$poList}",
                        'type' => 'stale_po_followup',
                        'count' => $stalePOs->count(),
                        'po_references' => $stalePOs->pluck('po_reference')->toArray(),
                        'severity' => 'warning',
                    ],
                ]);
            }

            $this->info("Notified {$procurementUsers->count()} procurement officer(s).");
        } catch (\Throwable $e) {
            Log::warning('Failed to notify procurement team about stale POs', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
