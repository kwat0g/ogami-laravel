<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\AP\Models\Vendor;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\QC\Models\Inspection;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendor Scoring Service — composite scorecard based on quality, delivery, and price.
 *
 * Score = (Quality Weight × Pass Rate) + (Delivery Weight × On-Time Rate) + (Price Weight × Price Score)
 *
 * Default weights: Quality 40%, Delivery 35%, Price 25%
 */
final class VendorScoringService implements ServiceContract
{
    private const WEIGHT_QUALITY = 0.40;

    private const WEIGHT_DELIVERY = 0.35;

    private const WEIGHT_PRICE = 0.25;

    /**
     * Calculate composite score for a single vendor.
     *
     * @return array{
     *     vendor_id: int,
     *     vendor_name: string,
     *     quality_score: float,
     *     delivery_score: float,
     *     price_score: float,
     *     composite_score: float,
     *     grade: string,
     *     total_pos: int,
     *     total_grs: int,
     *     total_inspections: int,
     * }
     */
    public function score(Vendor $vendor, ?int $year = null): array
    {
        $quality = $this->qualityScore($vendor, $year);
        $delivery = $this->deliveryScore($vendor, $year);
        $price = $this->priceScore($vendor, $year);

        $composite = round(
            (self::WEIGHT_QUALITY * $quality['score'])
            + (self::WEIGHT_DELIVERY * $delivery['score'])
            + (self::WEIGHT_PRICE * $price['score']),
            1,
        );

        $grade = match (true) {
            $composite >= 90 => 'A',
            $composite >= 75 => 'B',
            $composite >= 60 => 'C',
            $composite >= 40 => 'D',
            default => 'F',
        };

        return [
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'quality_score' => $quality['score'],
            'delivery_score' => $delivery['score'],
            'price_score' => $price['score'],
            'composite_score' => $composite,
            'grade' => $grade,
            'total_pos' => $delivery['total_pos'],
            'total_grs' => $delivery['total_grs'],
            'total_inspections' => $quality['total_inspections'],
        ];
    }

    /**
     * Scorecard for all active vendors.
     *
     * @return Collection<int, array>
     */
    public function allVendorScores(?int $year = null): Collection
    {
        return Vendor::where('is_active', true)
            ->get()
            ->map(fn (Vendor $v) => $this->score($v, $year))
            ->sortByDesc('composite_score')
            ->values();
    }

    // ── Component Scores ──────────────────────────────────────────────────

    /**
     * Quality score: incoming QC inspection pass rate (0-100).
     */
    private function qualityScore(Vendor $vendor, ?int $year): array
    {
        $query = Inspection::query()
            ->where('stage', 'receiving')
            ->whereIn('status', ['passed', 'failed_hold', 'reworked', 'scrap'])
            ->whereHas('goodsReceipt.purchaseOrder', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($year, fn ($q, $y) => $q->whereYear('created_at', $y));

        $total = (clone $query)->count();
        $passed = (clone $query)->where('status', 'passed')->count();
        $score = $total > 0 ? round(($passed / $total) * 100, 1) : 100.0; // no inspections = benefit of doubt

        return ['score' => $score, 'total_inspections' => $total, 'passed' => $passed];
    }

    /**
     * Delivery score: on-time goods receipt rate (0-100).
     *
     * A GR is "on time" if receipt_date <= PO expected_delivery_date.
     */
    private function deliveryScore(Vendor $vendor, ?int $year): array
    {
        $pos = PurchaseOrder::query()
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', ['received_in_full', 'partially_received', 'closed'])
            ->when($year, fn ($q, $y) => $q->whereYear('created_at', $y))
            ->get();

        $totalGrs = 0;
        $onTimeGrs = 0;

        foreach ($pos as $po) {
            $grs = GoodsReceipt::where('purchase_order_id', $po->id)
                ->where('status', 'confirmed')
                ->get();

            foreach ($grs as $gr) {
                $totalGrs++;
                // If PO has delivery_date and GR was received on or before it
                if ($po->delivery_date && $gr->receipt_date <= $po->delivery_date) {
                    $onTimeGrs++;
                } elseif (! $po->delivery_date) {
                    $onTimeGrs++; // no deadline = on time by default
                }
            }
        }

        $score = $totalGrs > 0 ? round(($onTimeGrs / $totalGrs) * 100, 1) : 100.0;

        return ['score' => $score, 'total_pos' => $pos->count(), 'total_grs' => $totalGrs];
    }

    /**
     * Price score: vendor's avg price vs overall avg for same items (0-100).
     *
     * 100 = cheapest, 0 = most expensive.
     */
    private function priceScore(Vendor $vendor, ?int $year): array
    {
        // Get vendor's average PO unit prices
        $vendorAvg = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->where('po.vendor_id', $vendor->id)
            ->when($year, fn ($q, $y) => $q->whereYear('po.created_at', $y))
            ->whereNull('po.deleted_at')
            ->avg('poi.unit_price');

        // Get overall average across all vendors for same items
        $overallAvg = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->when($year, fn ($q, $y) => $q->whereYear('po.created_at', $y))
            ->whereNull('po.deleted_at')
            ->avg('poi.unit_price');

        if (! $vendorAvg || ! $overallAvg || $overallAvg == 0) {
            return ['score' => 75.0]; // neutral score when no data
        }

        // Score: if vendor is cheaper than average, score > 50; if more expensive, < 50
        $ratio = (float) $vendorAvg / (float) $overallAvg;
        $score = max(0, min(100, round((2 - $ratio) * 50, 1)));

        return ['score' => $score];
    }
}
