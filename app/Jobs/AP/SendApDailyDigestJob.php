<?php

declare(strict_types=1);

namespace App\Jobs\AP;

use App\Models\User;
use App\Notifications\ApDailyDigestNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AP Daily Digest — weekday morning summary for the Accounting Manager.
 *
 * Generates a structured digest of:
 *   - Total outstanding AP balance (pending + approved invoices)
 *   - Invoices awaiting approval (pending status)
 *   - Invoices due within 7 days
 *   - Invoices overdue
 *
 * Phase 1D documents this job. Scheduled weekdays at 08:00 AM.
 * Full email delivery wired in Sprint 17; currently writes structured logs.
 */
final class SendApDailyDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $today = now()->toDateString();

        // Query uses the actual schema: net_amount, vat_amount, ewt_amount in pesos (decimal)
        // net_payable = net_amount + vat_amount - ewt_amount
        // balance_due = net_payable - total_paid (computed in PHP)
        $summary = DB::selectOne("
            SELECT
                COUNT(*) FILTER (WHERE status = 'pending_approval') AS pending_count,
                COUNT(*) FILTER (WHERE status = 'approved')         AS approved_count,
                COUNT(*) FILTER (WHERE status IN ('pending_approval','approved','partially_paid') AND due_date < :today)
                                                                    AS overdue_count,
                COUNT(*) FILTER (WHERE status IN ('pending_approval','approved','partially_paid') AND due_date BETWEEN :today2 AND :week)
                                                                    AS due_this_week_count,
                COALESCE(SUM(
                    CASE WHEN status IN ('pending_approval','approved','partially_paid')
                    THEN (net_amount + vat_amount - ewt_amount)
                    ELSE 0 END
                ), 0) AS net_payable_total,
                COALESCE(SUM(
                    CASE WHEN status IN ('pending_approval','approved','partially_paid')
                    THEN (
                        (net_amount + vat_amount - ewt_amount) -
                        COALESCE((SELECT SUM(amount) FROM vendor_payments WHERE vendor_invoice_id = vi.id), 0)
                    )
                    ELSE 0 END
                ), 0) AS outstanding_balance
            FROM vendor_invoices vi
            WHERE deleted_at IS NULL
        ", [
            'today' => $today,
            'today2' => $today,
            'week' => now()->addDays(7)->toDateString(),
        ]);

        Log::info('[AP Daily Digest]', [
            'date' => $today,
            'pending_count' => (int) ($summary->pending_count ?? 0),
            'approved_count' => (int) ($summary->approved_count ?? 0),
            'overdue_count' => (int) ($summary->overdue_count ?? 0),
            'due_this_week_count' => (int) ($summary->due_this_week_count ?? 0),
            'outstanding_balance_pesos' => (float) ($summary->outstanding_balance ?? 0),
        ]);

        // Send notifications to Accounting Managers
        $accountingManagers = User::permission('vendor_invoices.approve')->get();
        $notification = new ApDailyDigestNotification($summary, $today);

        foreach ($accountingManagers as $manager) {
            $manager->notify($notification);
        }

        Log::info('[SendApDailyDigestJob] Sent daily digest to '.$accountingManagers->count().' accounting manager(s).');
    }
}
