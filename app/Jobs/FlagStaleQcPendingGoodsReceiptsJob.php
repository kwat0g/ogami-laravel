<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * GAP 7: Flag stale QC-pending Goods Receipts.
 *
 * Runs on a schedule (e.g. hourly) to detect GRs that have been in
 * pending_qc status beyond the configured deadline (default: 24 hours).
 *
 * Actions taken:
 *   1. Logs a warning for each stale GR
 *   2. Sends a database notification to users with procurement.goods-receipt.confirm permission
 *
 * Schedule in app/Console/Kernel.php:
 *   $schedule->job(new FlagStaleQcPendingGoodsReceiptsJob)->hourly();
 */
final class FlagStaleQcPendingGoodsReceiptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $deadlineHours = (int) (DB::table('system_settings')
            ->where('key', 'qc.pending_deadline_hours')
            ->value('value') ?? 24);

        $cutoff = now()->subHours($deadlineHours);

        $staleGrs = GoodsReceipt::where('status', 'pending_qc')
            ->where('submitted_for_qc_at', '<', $cutoff)
            ->with(['purchaseOrder.vendor'])
            ->get();

        if ($staleGrs->isEmpty()) {
            return;
        }

        Log::warning('[QC-Deadline] Found stale pending_qc Goods Receipts', [
            'count' => $staleGrs->count(),
            'deadline_hours' => $deadlineHours,
            'gr_ids' => $staleGrs->pluck('id')->all(),
        ]);

        // Build notification payload
        $grSummaries = $staleGrs->map(fn (GoodsReceipt $gr) => [
            'gr_id' => $gr->id,
            'gr_reference' => $gr->gr_reference,
            'po_reference' => $gr->purchaseOrder?->po_reference ?? 'N/A',
            'vendor_name' => $gr->purchaseOrder?->vendor?->name ?? 'Unknown',
            'submitted_at' => $gr->submitted_for_qc_at?->toIso8601String(),
            'hours_pending' => $gr->submitted_for_qc_at
                ? (int) $gr->submitted_for_qc_at->diffInHours(now())
                : 0,
        ])->all();

        // Notify users with procurement confirm permission
        $usersToNotify = User::permission('procurement.goods-receipt.confirm')->get();

        foreach ($usersToNotify as $user) {
            $user->notify(new \Illuminate\Notifications\Messages\SimpleMessage(
                // Use database notification directly
            ));
        }

        // Use raw database notifications for simplicity
        $notificationData = [
            'type' => 'procurement.stale_qc_pending',
            'title' => "QC Deadline: {$staleGrs->count()} GR(s) pending inspection > {$deadlineHours}h",
            'message' => $staleGrs->take(3)->map(fn ($gr) => "{$gr->gr_reference} ({$gr->submitted_for_qc_at?->diffForHumans()})")->implode(', '),
            'stale_grs' => $grSummaries,
        ];

        foreach ($usersToNotify as $user) {
            DB::table('notifications')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\Procurement\\StaleQcPendingNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => json_encode($notificationData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('[QC-Deadline] Notified users about stale QC-pending GRs', [
            'users_notified' => $usersToNotify->count(),
            'stale_gr_count' => $staleGrs->count(),
        ]);
    }
}
