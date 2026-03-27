<?php

declare(strict_types=1);

namespace App\Http\Controllers\AR;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Models\CustomerPayment;
use App\Domains\AR\Services\ArAgingService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * AR Reports — aging analysis and statement of account generation.
 *
 * Routes (all GET, authenticated):
 *   GET /api/v1/ar/reports/aging?as_of=&customer_id=
 *   GET /api/v1/ar/reports/aging/{customer}/detail?as_of=
 *   GET /api/v1/ar/reports/aging/pdf?as_of=
 *   GET /api/v1/ar/customers/{customer}/statement?as_of=
 *   GET /api/v1/ar/customers/{customer}/statement/pdf?as_of=
 */
final class ArReportsController extends Controller
{
    public function __construct(
        private readonly ArAgingService $agingService,
    ) {}

    /**
     * GET /api/v1/ar/reports/aging
     * JSON aging summary grouped by customer with bucket totals.
     */
    public function agingSummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerInvoice::class);

        $filters = $request->only(['as_of', 'customer_id']);
        $summary = $this->agingService->agingSummary($filters);
        $totals = $this->agingService->agingTotals($filters);

        return response()->json([
            'data' => $summary,
            'totals' => $totals,
            'as_of' => Carbon::parse($filters['as_of'] ?? now())->toDateString(),
        ]);
    }

    /**
     * GET /api/v1/ar/reports/aging/{customer}/detail
     * Individual invoice-level aging for one customer.
     */
    public function agingDetail(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewAny', CustomerInvoice::class);

        $detail = $this->agingService->agingDetail(
            $customer,
            $request->input('as_of'),
        );

        return response()->json([
            'data' => $detail,
            'customer' => [
                'id' => $customer->id,
                'ulid' => $customer->ulid ?? null,
                'name' => $customer->name,
                'credit_limit' => (float) $customer->credit_limit,
                'current_outstanding' => $customer->current_outstanding,
            ],
            'as_of' => Carbon::parse($request->input('as_of') ?? now())->toDateString(),
        ]);
    }

    /**
     * GET /api/v1/ar/customers/{customer}/statement
     * JSON statement of account for a single customer.
     */
    public function statement(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $asOf = Carbon::parse($request->input('as_of') ?? now())->endOfDay();
        $invoices = $this->agingService->agingDetail($customer, $asOf->toDateString());
        $totalOutstanding = $invoices->sum('balance_due');

        $recentPayments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_date', '>=', $asOf->copy()->subDays(90))
            ->with('invoice')
            ->orderByDesc('payment_date')
            ->limit(20)
            ->get();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'ulid' => $customer->ulid ?? null,
                'name' => $customer->name,
                'contact_person' => $customer->contact_person,
                'address' => $customer->address,
                'tin' => $customer->tin,
                'credit_limit' => (float) $customer->credit_limit,
            ],
            'as_of' => $asOf->toDateString(),
            'invoices' => $invoices,
            'total_outstanding' => round($totalOutstanding, 2),
            'recent_payments' => $recentPayments,
        ]);
    }

    /**
     * GET /api/v1/ar/customers/{customer}/statement/pdf
     * Stream a PDF statement of account for a single customer.
     */
    public function statementPdf(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $asOf = Carbon::parse($request->input('as_of') ?? now())->endOfDay();
        $invoices = $this->agingService->agingDetail($customer, $asOf->toDateString());
        $totalOutstanding = $invoices->sum('balance_due');

        // Build aging buckets from the detail data
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

        $settings = $this->companySettings();

        $pdf = Pdf::loadView('ar.statement-of-account', [
            'customer' => $customer,
            'asOf' => $asOf,
            'invoices' => $invoices,
            'totalOutstanding' => $totalOutstanding,
            'agingBuckets' => $agingBuckets,
            'recentPayments' => $recentPayments,
            'settings' => $settings,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'soa-%s-%s.pdf',
            str_replace(' ', '-', strtolower($customer->name)),
            $asOf->format('Y-m-d'),
        );

        return $pdf->stream($filename);
    }

    /**
     * Load company settings from the system_settings table.
     *
     * @return array<string, string>
     */
    private function companySettings(): array
    {
        $settings = \DB::table('system_settings')
            ->whereIn('key', ['company_name', 'company_address', 'company_tin'])
            ->pluck('value', 'key')
            ->toArray();

        return $settings;
    }
}
