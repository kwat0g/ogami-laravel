<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\CustomerPayment;
use App\Shared\Contracts\ServiceContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class ClientPortalBillingService implements ServiceContract
{
    public function __construct(
        private readonly ArAgingService $agingService,
    ) {}

    public function summaryForClientId(int $clientId): array
    {
        $today = CarbonImmutable::today();
        $dueSoonEnd = $today->addDays(7);

        $customer = Customer::query()
            ->select(['id', 'ulid', 'name'])
            ->findOrFail($clientId);

        $invoices = CustomerInvoice::query()
            ->where('customer_id', $clientId)
            ->whereIn('status', ['approved', 'partially_paid', 'paid', 'written_off'])
            ->withSum('payments as total_paid', 'amount')
            ->orderByDesc('invoice_date')
            ->limit(100)
            ->get();

        $rows = $invoices->map(function (CustomerInvoice $invoice) use ($today, $dueSoonEnd): array {
            $totalAmount = round((float) $invoice->total_amount, 2);
            $totalPaid = round((float) ($invoice->total_paid ?? 0), 2);
            $balanceDue = max(0.0, round($totalAmount - $totalPaid, 2));
            $isOverdue = $balanceDue > 0
                && ! in_array($invoice->status, ['paid', 'written_off', 'cancelled'], true)
                && $invoice->due_date->lt($today);

            $isDueSoon = $balanceDue > 0
                && $invoice->due_date->betweenIncluded($today, $dueSoonEnd);

            return [
                'ulid' => $invoice->ulid,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => (string) $invoice->invoice_date,
                'due_date' => (string) $invoice->due_date,
                'status' => $invoice->status,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'balance_due' => $balanceDue,
                'is_overdue' => $isOverdue,
                'is_due_soon' => $isDueSoon,
            ];
        })->values();

        $openRows = $rows->filter(fn (array $row): bool => $row['balance_due'] > 0);

        return [
            'customer' => [
                'ulid' => $customer->ulid,
                'name' => $customer->name,
                'company_name' => null,
            ],
            'totals' => [
                'outstanding' => round((float) $openRows->sum('balance_due'), 2),
                'overdue' => round((float) $openRows->where('is_overdue', true)->sum('balance_due'), 2),
                'due_soon' => round((float) $openRows->where('is_due_soon', true)->sum('balance_due'), 2),
                'open_invoices' => $openRows->count(),
                'invoices_returned' => $rows->count(),
            ],
            'invoices' => $rows,
            'as_of' => $today->toDateString(),
        ];
    }

    public function statementOfAccountDataForClientId(int $clientId, ?string $asOfDate = null): array
    {
        $customer = Customer::query()->findOrFail($clientId);
        $asOf = Carbon::parse($asOfDate ?? now())->endOfDay();

        $invoices = $this->agingService->agingDetail($customer, $asOf->toDateString());
        $totalOutstanding = $invoices->sum('balance_due');

        $agingBuckets = [
            'current' => 0.0,
            'bucket_31_60' => 0.0,
            'bucket_61_90' => 0.0,
            'bucket_91_120' => 0.0,
            'over_120' => 0.0,
        ];
        foreach ($invoices as $inv) {
            $bucket = $inv['bucket'] ?? 'current';
            $key = $bucket === 'current' ? 'current' : $bucket;
            if (isset($agingBuckets[$key])) {
                $agingBuckets[$key] += $inv['balance_due'];
            }
        }

        $recentPayments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_date', '>=', $asOf->copy()->subDays(90))
            ->with('invoice')
            ->orderByDesc('payment_date')
            ->limit(20)
            ->get();

        return [
            'customer' => $customer,
            'asOf' => $asOf,
            'invoices' => $invoices,
            'totalOutstanding' => $totalOutstanding,
            'agingBuckets' => $agingBuckets,
            'recentPayments' => $recentPayments,
        ];
    }
}
