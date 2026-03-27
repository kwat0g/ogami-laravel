<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Supplier Quality Management Service.
 *
 * Aggregates vendor inspection pass rates, NCR counts, and quality scores
 * to provide a comprehensive supplier quality view.
 */
final class SupplierQualityService implements ServiceContract
{
    /**
     * Get quality summary per vendor based on incoming inspections.
     *
     * @return Collection<int, array{vendor_id: int, vendor_name: string, total_inspections: int, passed: int, failed: int, pass_rate_pct: float, ncr_count: int, quality_score: float}>
     */
    public function vendorQualitySummary(?int $vendorId = null, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = DB::table('inspections')
            ->join('goods_receipts', function ($join): void {
                $join->on('inspections.inspectable_id', '=', 'goods_receipts.id')
                    ->where('inspections.inspectable_type', '=', 'goods_receipts');
            })
            ->join('purchase_orders', 'goods_receipts.purchase_order_id', '=', 'purchase_orders.id')
            ->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id')
            ->whereNull('inspections.deleted_at')
            ->when($vendorId, fn ($q, $v) => $q->where('purchase_orders.vendor_id', $v))
            ->when($fromDate, fn ($q, $v) => $q->where('inspections.created_at', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->where('inspections.created_at', '<=', $v . ' 23:59:59'))
            ->select(
                'vendors.id as vendor_id',
                'vendors.name as vendor_name',
                DB::raw('COUNT(*) as total_inspections'),
                DB::raw("SUM(CASE WHEN inspections.result = 'pass' THEN 1 ELSE 0 END) as passed"),
                DB::raw("SUM(CASE WHEN inspections.result = 'fail' THEN 1 ELSE 0 END) as failed"),
            )
            ->groupBy('vendors.id', 'vendors.name')
            ->get();

        // Get NCR counts per vendor
        $ncrCounts = DB::table('ncrs')
            ->join('inspections', 'ncrs.inspection_id', '=', 'inspections.id')
            ->join('goods_receipts', function ($join): void {
                $join->on('inspections.inspectable_id', '=', 'goods_receipts.id')
                    ->where('inspections.inspectable_type', '=', 'goods_receipts');
            })
            ->join('purchase_orders', 'goods_receipts.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNull('ncrs.deleted_at')
            ->select('purchase_orders.vendor_id', DB::raw('COUNT(*) as ncr_count'))
            ->groupBy('purchase_orders.vendor_id')
            ->pluck('ncr_count', 'vendor_id');

        return $query->map(function ($row) use ($ncrCounts) {
            $passRate = $row->total_inspections > 0
                ? round(($row->passed / $row->total_inspections) * 100, 2)
                : 0.0;

            // Quality score: weighted combination of pass rate and NCR frequency
            $ncrCount = $ncrCounts[$row->vendor_id] ?? 0;
            $ncrPenalty = min(20, $ncrCount * 2); // Max 20% penalty
            $qualityScore = max(0, $passRate - $ncrPenalty);

            return [
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $row->vendor_name,
                'total_inspections' => $row->total_inspections,
                'passed' => $row->passed,
                'failed' => $row->failed,
                'pass_rate_pct' => $passRate,
                'ncr_count' => $ncrCount,
                'quality_score' => round($qualityScore, 2),
            ];
        });
    }
}
