<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Services\CostingService;
use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Profit Margin Service — thesis-grade ERP alignment.
 *
 * In a real-world ERP, Sales must know the production cost of what they're
 * selling. This service bridges the gap between Production/BOM costing and
 * Sales pricing by calculating profit margins per line item.
 *
 * Without this, Sales staff can set prices below cost, quotations have no
 * cost basis, and profitability analysis requires manual spreadsheet work.
 *
 * Use cases:
 *   - Quotation margin analysis before sending to customer
 *   - Sales Order profitability check before confirmation
 *   - Per-item margin visibility in the pricing workflow
 */
final class ProfitMarginService implements ServiceContract
{
    public function __construct(
        private readonly CostingService $costingService,
    ) {}

    /**
     * Calculate profit margin for each line item in a Quotation.
     *
     * For each item that has an active BOM, computes:
     *   - unit_cost_centavos: BOM standard cost per unit
     *   - unit_price_centavos: quoted selling price
     *   - margin_centavos: price - cost per unit
     *   - margin_pct: (price - cost) / price * 100
     *   - line_margin_centavos: total margin for the line (margin * qty)
     *
     * Items without BOMs show cost as 0 (service/non-manufactured items).
     *
     * @return array{
     *     quotation_id: int,
     *     quotation_number: string,
     *     total_revenue_centavos: int,
     *     total_cost_centavos: int,
     *     total_margin_centavos: int,
     *     overall_margin_pct: float,
     *     lines: list<array>,
     * }
     */
    public function quotationMargin(Quotation $quotation): array
    {
        $quotation->loadMissing('items.item');

        $lines = [];
        $totalRevenue = 0;
        $totalCost = 0;

        foreach ($quotation->items as $qi) {
            $item = $qi->item;
            $qty = (float) $qi->quantity;
            $unitPrice = (int) $qi->unit_price_centavos;
            $lineRevenue = (int) round($qty * $unitPrice);
            $totalRevenue += $lineRevenue;

            $unitCost = $this->getItemStandardCost($item);
            $lineCost = (int) round($qty * $unitCost);
            $totalCost += $lineCost;

            $marginPerUnit = $unitPrice - $unitCost;
            $marginPct = $unitPrice > 0
                ? round(($marginPerUnit / $unitPrice) * 100, 2)
                : 0.0;

            $lines[] = [
                'item_id' => $qi->item_id,
                'item_name' => $item?->name ?? '-',
                'item_code' => $item?->item_code ?? '-',
                'quantity' => round($qty, 4),
                'unit_price_centavos' => $unitPrice,
                'unit_cost_centavos' => $unitCost,
                'margin_per_unit_centavos' => $marginPerUnit,
                'margin_pct' => $marginPct,
                'line_revenue_centavos' => $lineRevenue,
                'line_cost_centavos' => $lineCost,
                'line_margin_centavos' => $lineRevenue - $lineCost,
                'has_bom' => $unitCost > 0,
                'below_cost' => $marginPerUnit < 0,
            ];
        }

        $overallMargin = $totalRevenue > 0
            ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 2)
            : 0.0;

        return [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'total_revenue_centavos' => $totalRevenue,
            'total_cost_centavos' => $totalCost,
            'total_margin_centavos' => $totalRevenue - $totalCost,
            'overall_margin_pct' => $overallMargin,
            'lines' => $lines,
        ];
    }

    /**
     * Calculate profit margin for each line item in a Sales Order.
     *
     * Same logic as quotationMargin but for confirmed/in-progress orders.
     * Useful for profitability dashboards and executive reporting.
     *
     * @return array{
     *     sales_order_id: int,
     *     order_number: string,
     *     total_revenue_centavos: int,
     *     total_cost_centavos: int,
     *     total_margin_centavos: int,
     *     overall_margin_pct: float,
     *     lines: list<array>,
     * }
     */
    public function salesOrderMargin(SalesOrder $order): array
    {
        $order->loadMissing('items.item');

        $lines = [];
        $totalRevenue = 0;
        $totalCost = 0;

        foreach ($order->items as $soItem) {
            $item = $soItem->item;
            $qty = (float) $soItem->quantity;
            $unitPrice = (int) $soItem->unit_price_centavos;
            $lineRevenue = (int) round($qty * $unitPrice);
            $totalRevenue += $lineRevenue;

            $unitCost = $this->getItemStandardCost($item);
            $lineCost = (int) round($qty * $unitCost);
            $totalCost += $lineCost;

            $marginPerUnit = $unitPrice - $unitCost;
            $marginPct = $unitPrice > 0
                ? round(($marginPerUnit / $unitPrice) * 100, 2)
                : 0.0;

            $lines[] = [
                'item_id' => $soItem->item_id,
                'item_name' => $item?->name ?? '-',
                'item_code' => $item?->item_code ?? '-',
                'quantity' => round($qty, 4),
                'unit_price_centavos' => $unitPrice,
                'unit_cost_centavos' => $unitCost,
                'margin_per_unit_centavos' => $marginPerUnit,
                'margin_pct' => $marginPct,
                'line_revenue_centavos' => $lineRevenue,
                'line_cost_centavos' => $lineCost,
                'line_margin_centavos' => $lineRevenue - $lineCost,
                'has_bom' => $unitCost > 0,
                'below_cost' => $marginPerUnit < 0,
            ];
        }

        $overallMargin = $totalRevenue > 0
            ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 2)
            : 0.0;

        return [
            'sales_order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_revenue_centavos' => $totalRevenue,
            'total_cost_centavos' => $totalCost,
            'total_margin_centavos' => $totalRevenue - $totalCost,
            'overall_margin_pct' => $overallMargin,
            'lines' => $lines,
        ];
    }

    /**
     * Suggest minimum price for an item to achieve a target margin.
     *
     * @return array{item_id: int, item_name: string, unit_cost_centavos: int, target_margin_pct: float, suggested_price_centavos: int}
     */
    public function suggestPrice(int $itemId, float $targetMarginPct = 30.0): array
    {
        $item = ItemMaster::find($itemId);
        $unitCost = $this->getItemStandardCost($item);

        // Price = Cost / (1 - margin%)
        // e.g., cost=100, margin=30% => price = 100 / 0.70 = 143
        $marginFactor = 1 - ($targetMarginPct / 100);
        $suggestedPrice = $marginFactor > 0
            ? (int) ceil($unitCost / $marginFactor)
            : 0;

        return [
            'item_id' => $itemId,
            'item_name' => $item?->name ?? '-',
            'unit_cost_centavos' => $unitCost,
            'target_margin_pct' => $targetMarginPct,
            'suggested_price_centavos' => $suggestedPrice,
        ];
    }

    /**
     * Get the standard cost for an item from its active BOM.
     *
     * Falls back to item standard_price if no BOM exists (for raw materials
     * or items sold without manufacturing).
     */
    private function getItemStandardCost(?ItemMaster $item): int
    {
        if ($item === null) {
            return 0;
        }

        // Try to get cost from the active BOM (manufactured items)
        $bom = BillOfMaterials::query()
            ->where('product_item_id', $item->id)
            ->where('is_active', true)
            ->first();

        if ($bom !== null) {
            // Use persisted standard cost if available (from auto-rollup)
            if (($bom->standard_cost_centavos ?? 0) > 0) {
                return (int) $bom->standard_cost_centavos;
            }

            // Compute on-the-fly if no persisted cost yet
            try {
                $result = $this->costingService->standardCost($bom, 'material_labor_overhead');

                return $result['total_standard_cost_centavos'];
            } catch (\Throwable) {
                // Fall through to item standard price
            }
        }

        // Fallback: use item's standard price (non-manufactured items)
        return (int) ($item->standard_price_centavos ?? (($item->standard_price ?? 0) * 100));
    }
}
