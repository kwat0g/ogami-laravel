<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Domains\QC\Models\Inspection;
use App\Domains\QC\Models\NonConformanceReport;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quality Analytics Service — defect rate analysis, supplier quality, and cost of quality.
 */
final class QualityAnalyticsService implements ServiceContract
{
    /**
     * Defect rate trend — pass/fail percentages by month.
     *
     * @return Collection<int, array{
     *     month: string,
     *     total_inspected: float,
     *     total_passed: float,
     *     total_failed: float,
     *     pass_rate_pct: float,
     *     defect_rate_pct: float,
     * }>
     */
    public function defectRateTrend(int $year, ?string $stage = null): Collection
    {
        return Inspection::query()
            ->whereYear('created_at', $year)
            ->whereIn('status', ['passed', 'failed_hold', 'reworked', 'scrap'])
            ->when($stage, fn ($q, $s) => $q->where('stage', $s))
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('COALESCE(SUM(CAST(qty_inspected AS numeric)), 0) as total_inspected'),
                DB::raw('COALESCE(SUM(CAST(qty_passed AS numeric)), 0) as total_passed'),
                DB::raw('COALESCE(SUM(CAST(qty_failed AS numeric)), 0) as total_failed'),
            )
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                $inspected = (float) $row->total_inspected;
                $passed = (float) $row->total_passed;
                $failed = (float) $row->total_failed;

                return [
                    'month' => $row->month,
                    'total_inspected' => $inspected,
                    'total_passed' => $passed,
                    'total_failed' => $failed,
                    'pass_rate_pct' => $inspected > 0 ? round(($passed / $inspected) * 100, 2) : 0.0,
                    'defect_rate_pct' => $inspected > 0 ? round(($failed / $inspected) * 100, 2) : 0.0,
                ];
            });
    }

    /**
     * Supplier quality — incoming inspection pass/fail rate by vendor.
     *
     * Only considers 'receiving' stage inspections linked to goods receipts.
     *
     * @return Collection<int, array{
     *     vendor_id: int|null,
     *     vendor_name: string,
     *     total_inspections: int,
     *     total_inspected_qty: float,
     *     total_passed_qty: float,
     *     total_failed_qty: float,
     *     pass_rate_pct: float,
     * }>
     */
    public function supplierQuality(?int $year = null): Collection
    {
        $query = Inspection::query()
            ->where('stage', 'receiving')
            ->whereIn('status', ['passed', 'failed_hold', 'reworked', 'scrap'])
            ->whereNotNull('goods_receipt_id')
            ->when($year, fn ($q, $y) => $q->whereYear('created_at', $y));

        // Join through goods_receipts to get vendor_id
        return $query
            ->join('goods_receipts', 'goods_receipts.id', '=', 'inspections.goods_receipt_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'purchase_orders.vendor_id')
            ->select(
                'purchase_orders.vendor_id',
                DB::raw("COALESCE(vendors.name, 'Unknown Vendor') as vendor_name"),
                DB::raw('count(*) as total_inspections'),
                DB::raw('COALESCE(SUM(CAST(inspections.qty_inspected AS numeric)), 0) as total_inspected_qty'),
                DB::raw('COALESCE(SUM(CAST(inspections.qty_passed AS numeric)), 0) as total_passed_qty'),
                DB::raw('COALESCE(SUM(CAST(inspections.qty_failed AS numeric)), 0) as total_failed_qty'),
            )
            ->groupBy('purchase_orders.vendor_id', 'vendors.name')
            ->orderByDesc('total_inspections')
            ->get()
            ->map(function ($row) {
                $inspected = (float) $row->total_inspected_qty;
                $passed = (float) $row->total_passed_qty;

                return [
                    'vendor_id' => $row->vendor_id,
                    'vendor_name' => $row->vendor_name,
                    'total_inspections' => (int) $row->total_inspections,
                    'total_inspected_qty' => $inspected,
                    'total_passed_qty' => $passed,
                    'total_failed_qty' => (float) $row->total_failed_qty,
                    'pass_rate_pct' => $inspected > 0 ? round(($passed / $inspected) * 100, 2) : 0.0,
                ];
            });
    }

    /**
     * NCR aging — open non-conformance reports with days open.
     *
     * @return Collection<int, array{ncr_id: int, description: string, severity: string, status: string, days_open: int, product: string|null}>
     */
    public function openNcrAging(): Collection
    {
        return NonConformanceReport::query()
            ->whereIn('status', ['raised', 'under_investigation', 'corrective_action_assigned'])
            ->with('inspection.itemMaster')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($ncr) => [
                'ncr_id' => $ncr->id,
                'description' => $ncr->description ?? '—',
                'severity' => $ncr->severity ?? '—',
                'status' => $ncr->status,
                'days_open' => (int) $ncr->created_at->diffInDays(now()),
                'product' => $ncr->inspection?->itemMaster?->name ?? '—',
            ]);
    }

    /**
     * Quality KPI summary.
     *
     * @return array{
     *     overall_pass_rate_pct: float,
     *     open_ncrs: int,
     *     inspections_this_month: int,
     *     avg_defect_rate_pct: float,
     * }
     */
    public function kpiSummary(): array
    {
        $thisMonth = Inspection::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', ['passed', 'failed_hold', 'reworked', 'scrap'])
            ->select(
                DB::raw('COALESCE(SUM(CAST(qty_inspected AS numeric)), 0) as total_inspected'),
                DB::raw('COALESCE(SUM(CAST(qty_passed AS numeric)), 0) as total_passed'),
                DB::raw('COALESCE(SUM(CAST(qty_failed AS numeric)), 0) as total_failed'),
                DB::raw('count(*) as inspection_count'),
            )
            ->first();

        $inspected = (float) ($thisMonth->total_inspected ?? 0);
        $passed = (float) ($thisMonth->total_passed ?? 0);
        $failed = (float) ($thisMonth->total_failed ?? 0);

        $openNcrs = NonConformanceReport::query()
            ->whereIn('status', ['raised', 'under_investigation', 'corrective_action_assigned'])
            ->count();

        return [
            'overall_pass_rate_pct' => $inspected > 0 ? round(($passed / $inspected) * 100, 2) : 100.0,
            'open_ncrs' => $openNcrs,
            'inspections_this_month' => (int) ($thisMonth->inspection_count ?? 0),
            'avg_defect_rate_pct' => $inspected > 0 ? round(($failed / $inspected) * 100, 2) : 0.0,
        ];
    }
}
