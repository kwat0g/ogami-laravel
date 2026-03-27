<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\DunningLevel;
use App\Domains\AR\Models\DunningNotice;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dunning Service — automated collection management.
 *
 * Generates dunning notices for overdue invoices based on configured
 * dunning levels (e.g., 30 days = Level 1 reminder, 60 days = Level 2, etc.)
 */
final class DunningService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginateNotices(array $filters = []): LengthAwarePaginator
    {
        return DunningNotice::with(['customer', 'invoice', 'dunningLevel'])
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Generate dunning notices for all overdue invoices.
     *
     * Scans approved/partially_paid invoices past due date, matches to the
     * highest applicable dunning level, and creates notices where one does
     * not already exist for that invoice+level combination.
     *
     * @return Collection<int, DunningNotice>
     */
    public function generateNotices(User $actor): Collection
    {
        $levels = DunningLevel::where('is_active', true)
            ->orderByDesc('days_overdue')
            ->get();

        if ($levels->isEmpty()) {
            return collect();
        }

        $today = Carbon::today();
        $overdueInvoices = CustomerInvoice::query()
            ->whereIn('status', ['approved', 'partially_paid'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->with('customer')
            ->get();

        $generated = collect();

        DB::transaction(function () use ($overdueInvoices, $levels, $today, $actor, &$generated): void {
            foreach ($overdueInvoices as $invoice) {
                $daysOverdue = (int) Carbon::parse($invoice->due_date)->diffInDays($today);
                $balanceDue = (int) (($invoice->balance_due ?? 0) * 100);

                if ($balanceDue <= 0) {
                    continue;
                }

                // Find the highest applicable dunning level
                $applicableLevel = null;
                foreach ($levels as $level) {
                    if ($daysOverdue >= $level->days_overdue) {
                        $applicableLevel = $level;
                        break;
                    }
                }

                if ($applicableLevel === null) {
                    continue;
                }

                // Check if a notice already exists for this invoice+level
                $exists = DunningNotice::where('customer_invoice_id', $invoice->id)
                    ->where('dunning_level_id', $applicableLevel->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $notice = DunningNotice::create([
                    'customer_id' => $invoice->customer_id,
                    'customer_invoice_id' => $invoice->id,
                    'dunning_level_id' => $applicableLevel->id,
                    'amount_due_centavos' => $balanceDue,
                    'days_overdue' => $daysOverdue,
                    'status' => 'generated',
                    'created_by_id' => $actor->id,
                ]);

                $generated->push($notice);
            }
        });

        return $generated;
    }

    public function markSent(DunningNotice $notice): DunningNotice
    {
        $notice->update(['status' => 'sent', 'sent_at' => now()]);

        return $notice->fresh() ?? $notice;
    }

    public function escalate(DunningNotice $notice): DunningNotice
    {
        $notice->update(['status' => 'escalated']);

        return $notice->fresh() ?? $notice;
    }

    public function resolve(DunningNotice $notice, string $notes): DunningNotice
    {
        $notice->update(['status' => 'resolved', 'notes' => $notes]);

        return $notice->fresh() ?? $notice;
    }
}
