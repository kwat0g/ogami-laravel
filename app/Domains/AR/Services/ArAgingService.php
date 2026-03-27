<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AR Aging Report Service — generates aging analysis for accounts receivable.
 *
 * Buckets: Current (0–30), 31–60, 61–90, 91–120, 120+ days past due.
 * All amounts returned in centavos (float from DB, but the source columns are decimal).
 */
final class ArAgingService implements ServiceContract
{
    /** Standard aging bucket boundaries (days past due). */
    private const BUCKETS = [
        'current'  => [0, 30],
        '31_60'    => [31, 60],
        '61_90'    => [61, 90],
        '91_120'   => [91, 120],
        'over_120' => [121, null],
    ];

    /**
     * Generate aging summary grouped by customer.
     *
     * @param array{as_of?: string, customer_id?: int} $filters
     * @return Collection<int, array{
     *     customer_id: int,
     *     customer_name: string,
     *     current: float,
     *     bucket_31_60: float,
     *     bucket_61_90: float,
     *     bucket_91_120: float,
     *     over_120: float,
     *     total_outstanding: float,
     * }>
     */
    public function agingSummary(array $filters = []): Collection
    {
        $asOf = Carbon::parse($filters['as_of'] ?? now())->endOfDay();

        $invoices = CustomerInvoice::query()
            ->with('customer')
            ->whereIn('status', ['approved', 'partially_paid'])
            ->when(
                $filters['customer_id'] ?? null,
                fn ($q, $v) => $q->where('customer_id', $v),
            )
            ->get();

        // Group by customer and bucket each invoice
        return $invoices
            ->groupBy('customer_id')
            ->map(function (Collection $customerInvoices) use ($asOf) {
                $customer = $customerInvoices->first()->customer;

                $buckets = [
                    'current'    => 0.0,
                    'bucket_31_60'  => 0.0,
                    'bucket_61_90'  => 0.0,
                    'bucket_91_120' => 0.0,
                    'over_120'      => 0.0,
                ];

                foreach ($customerInvoices as $invoice) {
                    $balanceDue = $invoice->balance_due;
                    if ($balanceDue <= 0) {
                        continue;
                    }

                    $daysPastDue = $this->daysPastDue($invoice->due_date, $asOf);
                    $bucket = $this->resolveBucket($daysPastDue);
                    $buckets[$bucket] += $balanceDue;
                }

                return [
                    'customer_id'      => $customer->id,
                    'customer_ulid'    => $customer->ulid ?? null,
                    'customer_name'    => $customer->name,
                    'current'          => round($buckets['current'], 2),
                    'bucket_31_60'     => round($buckets['bucket_31_60'], 2),
                    'bucket_61_90'     => round($buckets['bucket_61_90'], 2),
                    'bucket_91_120'    => round($buckets['bucket_91_120'], 2),
                    'over_120'         => round($buckets['over_120'], 2),
                    'total_outstanding' => round(array_sum($buckets), 2),
                ];
            })
            ->filter(fn (array $row) => $row['total_outstanding'] > 0)
            ->sortByDesc('total_outstanding')
            ->values();
    }

    /**
     * Aging detail for a single customer — returns individual invoices with aging info.
     *
     * @return Collection<int, array{
     *     invoice_id: int,
     *     invoice_number: string|null,
     *     invoice_date: string,
     *     due_date: string,
     *     days_past_due: int,
     *     bucket: string,
     *     total_amount: float,
     *     total_paid: float,
     *     balance_due: float,
     * }>
     */
    public function agingDetail(Customer $customer, ?string $asOf = null): Collection
    {
        $asOfDate = Carbon::parse($asOf ?? now())->endOfDay();

        return CustomerInvoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn ($invoice) => $invoice->balance_due > 0)
            ->map(function (CustomerInvoice $invoice) use ($asOfDate) {
                $daysPastDue = $this->daysPastDue($invoice->due_date, $asOfDate);

                return [
                    'invoice_id'     => $invoice->id,
                    'invoice_ulid'   => $invoice->ulid ?? null,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date'   => (string) $invoice->invoice_date,
                    'due_date'       => (string) $invoice->due_date,
                    'days_past_due'  => $daysPastDue,
                    'bucket'         => $this->resolveBucket($daysPastDue),
                    'total_amount'   => (float) $invoice->total_amount,
                    'total_paid'     => (float) $invoice->total_paid,
                    'balance_due'    => (float) $invoice->balance_due,
                ];
            })
            ->values();
    }

    /**
     * Grand totals across all customers for the aging report header.
     *
     * @return array{current: float, bucket_31_60: float, bucket_61_90: float, bucket_91_120: float, over_120: float, grand_total: float}
     */
    public function agingTotals(array $filters = []): array
    {
        $summary = $this->agingSummary($filters);

        return [
            'current'      => round($summary->sum('current'), 2),
            'bucket_31_60' => round($summary->sum('bucket_31_60'), 2),
            'bucket_61_90' => round($summary->sum('bucket_61_90'), 2),
            'bucket_91_120' => round($summary->sum('bucket_91_120'), 2),
            'over_120'     => round($summary->sum('over_120'), 2),
            'grand_total'  => round($summary->sum('total_outstanding'), 2),
        ];
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    private function daysPastDue(mixed $dueDate, Carbon $asOf): int
    {
        $due = Carbon::parse($dueDate);

        return max(0, (int) $due->diffInDays($asOf, false));
    }

    private function resolveBucket(int $daysPastDue): string
    {
        if ($daysPastDue <= 30) {
            return 'current';
        }
        if ($daysPastDue <= 60) {
            return 'bucket_31_60';
        }
        if ($daysPastDue <= 90) {
            return 'bucket_61_90';
        }
        if ($daysPastDue <= 120) {
            return 'bucket_91_120';
        }

        return 'over_120';
    }
}
