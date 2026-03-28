<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Product Costing Service — standard and actual cost computation.
 *
 * Standard Cost = sum of (component qty × component standard_price_centavos) through BOM
 * Actual Cost = actual material consumed + labor hours × labor rate
 * Variance = Standard - Actual (positive = favorable)
 *
 * All costs in centavos to match the Money VO pattern.
 */
final class CostingService implements ServiceContract
{
    /** Default labor rate per hour in centavos (₱150/hr = 15000 centavos). */
    private const DEFAULT_LABOR_RATE_CENTAVOS = 15_000;

    /**
     * Calculate standard cost for a BOM (material cost only, single-level).
     *
     * @return array{
     *     product_item_id: int,
     *     product_name: string,
     *     bom_version: string,
     *     material_cost_centavos: int,
     *     components: array,
     * }
     */
    public function standardCost(BillOfMaterials $bom): array
    {
        $bom->loadMissing(['components.componentItem', 'productItem']);

        $components = [];
        $totalMaterialCost = 0;

        foreach ($bom->components as $comp) {
            $item = $comp->componentItem;
            $unitCost = (int) ($item->standard_price_centavos ?? 0);
            $qtyPerUnit = (float) $comp->qty_per_unit;
            $scrapFactor = 1 + ((float) $comp->scrap_factor_pct / 100);
            $grossQty = $qtyPerUnit * $scrapFactor;
            $componentCost = (int) round($grossQty * $unitCost);

            $totalMaterialCost += $componentCost;

            $components[] = [
                'item_id' => $comp->component_item_id,
                'item_code' => $item?->item_code ?? '—',
                'item_name' => $item?->name ?? '—',
                'qty_per_unit' => round($qtyPerUnit, 4),
                'scrap_factor_pct' => (float) $comp->scrap_factor_pct,
                'gross_qty' => round($grossQty, 4),
                'unit_cost_centavos' => $unitCost,
                'line_cost_centavos' => $componentCost,
            ];
        }

        return [
            'product_item_id' => $bom->product_item_id,
            'product_name' => $bom->productItem?->name ?? '—',
            'bom_version' => $bom->version,
            'material_cost_centavos' => $totalMaterialCost,
            'components' => $components,
        ];
    }

    /**
     * Calculate actual cost for a production order.
     *
     * Material cost: from stock ledger entries linked to this order's MRQ
     * Labor cost: from maintenance work orders linked to the production order
     *
     * @return array{
     *     production_order_id: int,
     *     quantity_produced: float,
     *     material_cost_centavos: int,
     *     labor_cost_centavos: int,
     *     total_cost_centavos: int,
     *     unit_cost_centavos: int,
     * }
     */
    public function actualCost(ProductionOrder $order): array
    {
        // Material cost: sum of component standard prices × qty consumed
        // from the MRQ items that were issued for this order
        $materialCost = 0;

        $mrqs = \App\Domains\Inventory\Models\MaterialRequisition::query()
            ->where('production_order_id', $order->id)
            ->where('status', 'issued')
            ->with('items.itemMaster')
            ->get();

        foreach ($mrqs as $mrq) {
            foreach ($mrq->items as $item) {
                $unitPrice = (int) ($item->itemMaster?->standard_price_centavos ?? 0);
                $qty = (float) ($item->quantity_issued ?? $item->quantity_requested ?? 0);
                $materialCost += (int) round($qty * $unitPrice);
            }
        }

        // Labor cost: sum of labor_hours from related work orders × labor rate
        $laborHours = (float) MaintenanceWorkOrder::query()
            ->where('reference_type', ProductionOrder::class)
            ->where('reference_id', $order->id)
            ->sum('labor_hours');

        // Also count production output log implicit labor (qty * standard production days)
        $bom = $order->bom;
        $standardDays = $bom ? $bom->standard_production_days : 0;
        $qtyProduced = (float) $order->quantity_produced;

        // If no explicit labor hours logged, estimate from standard production days
        if ($laborHours <= 0 && $standardDays > 0 && $qtyProduced > 0) {
            $laborHours = $standardDays * 8; // 8 hours per day
        }

        $laborCost = (int) round($laborHours * self::DEFAULT_LABOR_RATE_CENTAVOS);
        $totalCost = $materialCost + $laborCost;
        $unitCost = $qtyProduced > 0 ? (int) round($totalCost / $qtyProduced) : 0;

        return [
            'production_order_id' => $order->id,
            'quantity_produced' => $qtyProduced,
            'material_cost_centavos' => $materialCost,
            'labor_cost_centavos' => $laborCost,
            'total_cost_centavos' => $totalCost,
            'unit_cost_centavos' => $unitCost,
        ];
    }

    /**
     * Cost variance analysis: standard vs actual for a production order.
     *
     * @return array{
     *     standard_unit_cost_centavos: int,
     *     actual_unit_cost_centavos: int,
     *     variance_centavos: int,
     *     variance_pct: float,
     *     favorable: bool,
     * }
     */
    public function costVariance(ProductionOrder $order): array
    {
        $bom = $order->bom;
        if ($bom === null) {
            return [
                'standard_unit_cost_centavos' => 0,
                'actual_unit_cost_centavos' => 0,
                'variance_centavos' => 0,
                'variance_pct' => 0.0,
                'favorable' => true,
            ];
        }

        $standard = $this->standardCost($bom);
        $actual = $this->actualCost($order);

        $standardUnit = $standard['material_cost_centavos']; // per single unit
        $actualUnit = $actual['unit_cost_centavos'];
        $variance = $standardUnit - $actualUnit;
        $variancePct = $standardUnit > 0 ? round(($variance / $standardUnit) * 100, 2) : 0.0;

        return [
            'standard_unit_cost_centavos' => $standardUnit,
            'actual_unit_cost_centavos' => $actualUnit,
            'variance_centavos' => $variance,
            'variance_pct' => $variancePct,
            'favorable' => $variance >= 0,
        ];
    }

    /**
     * Full cost sheet for a finished product (from active BOM).
     *
     * @return array{product_name: string, bom_version: string, material_breakdown: array, total_material_centavos: int, estimated_labor_centavos: int, total_standard_cost_centavos: int}|null
     */
    public function costSheet(ItemMaster $product): ?array
    {
        $bom = BillOfMaterials::query()
            ->where('product_item_id', $product->id)
            ->where('is_active', true)
            ->with(['components.componentItem'])
            ->first();

        if ($bom === null) {
            return null;
        }

        $standard = $this->standardCost($bom);
        $estimatedLabor = (int) round(
            $bom->standard_production_days * 8 * self::DEFAULT_LABOR_RATE_CENTAVOS,
        );

        return [
            'product_name' => $product->name,
            'bom_version' => $bom->version,
            'material_breakdown' => $standard['components'],
            'total_material_centavos' => $standard['material_cost_centavos'],
            'estimated_labor_centavos' => $estimatedLabor,
            'total_standard_cost_centavos' => $standard['material_cost_centavos'] + $estimatedLabor,
        ];
    }
}
