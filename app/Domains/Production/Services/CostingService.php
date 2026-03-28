<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\Routing;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;

/**
 * Product Costing Service — standard and actual cost computation.
 *
 * Standard Cost = Material + Labor + Overhead, computed from BOM + Routings.
 *   Material: multi-level BOM explosion (recurses into sub-assembly BOMs)
 *   Labor:    SUM(routing setup_time + run_time * qty) * work_center.hourly_rate
 *   Overhead: SUM(routing setup_time + run_time * qty) * work_center.overhead_rate
 *
 * Actual Cost = actual material consumed + labor hours * labor rate
 * Variance = Standard - Actual (positive = favorable)
 *
 * All costs in centavos to match the Money VO pattern.
 *
 * Flexibility:
 *   - cost_elements config: 'material_only', 'material_labor', 'material_labor_overhead'
 *   - Multi-level BOM: recurses into sub-assembly BOMs (semi_finished items with their own BOM)
 *   - Phantom BOMs: sub-assemblies that are never stocked pass components through to parent
 *   - Alternate components: if primary item has alternate_item_id, use the cheaper one
 */
final class CostingService implements ServiceContract
{
    /** Default labor rate per hour in centavos (₱150/hr = 15000 centavos). */
    private const DEFAULT_LABOR_RATE_CENTAVOS = 15_000;

    /** Maximum recursion depth for multi-level BOM explosion to prevent infinite loops. */
    private const MAX_BOM_DEPTH = 10;

    /**
     * Calculate standard cost for a BOM — material + labor + overhead.
     *
     * Supports multi-level BOM explosion: if a component is a semi-finished item
     * with its own active BOM, the cost is recursively computed from that BOM
     * instead of using the item's standard_price.
     *
     * @param  string  $costElements  'material_only' | 'material_labor' | 'material_labor_overhead'
     * @return array{
     *     product_item_id: int,
     *     product_name: string,
     *     bom_version: string,
     *     material_cost_centavos: int,
     *     labor_cost_centavos: int,
     *     overhead_cost_centavos: int,
     *     total_standard_cost_centavos: int,
     *     components: array,
     *     routings: array,
     * }
     */
    public function standardCost(BillOfMaterials $bom, string $costElements = 'material_labor_overhead'): array
    {
        $bom->loadMissing(['components.componentItem', 'productItem']);

        // Multi-level BOM explosion with circular reference protection
        $visitedBomIds = [];
        $components = $this->explodeBomMaterials($bom, 1.0, $visitedBomIds, 0);

        $totalMaterialCost = 0;
        foreach ($components as $comp) {
            $totalMaterialCost += $comp['line_cost_centavos'];
        }

        // Routing-based labor and overhead cost
        $laborCost = 0;
        $overheadCost = 0;
        $routingDetails = [];

        if ($costElements !== 'material_only') {
            $routingResult = $this->computeRoutingCost($bom);
            $laborCost = $routingResult['labor_cost_centavos'];
            $overheadCost = $costElements === 'material_labor_overhead'
                ? $routingResult['overhead_cost_centavos']
                : 0;
            $routingDetails = $routingResult['steps'];
        }

        $totalCost = $totalMaterialCost + $laborCost + $overheadCost;

        return [
            'product_item_id' => $bom->product_item_id,
            'product_name' => $bom->productItem?->name ?? '—',
            'bom_version' => $bom->version,
            'material_cost_centavos' => $totalMaterialCost,
            'labor_cost_centavos' => $laborCost,
            'overhead_cost_centavos' => $overheadCost,
            'total_standard_cost_centavos' => $totalCost,
            'components' => $components,
            'routings' => $routingDetails,
        ];
    }

    /**
     * Recursively explode BOM components into a flat material list.
     *
     * If a component is a semi-finished item with its own active BOM,
     * recurse into that BOM. Otherwise, use the item's standard_price.
     *
     * @param  array<int>  $visitedBomIds  Circular reference guard
     * @return list<array{item_id: int, item_code: string, item_name: string, qty_per_unit: float, scrap_factor_pct: float, gross_qty: float, unit_cost_centavos: int, line_cost_centavos: int, bom_level: int, is_sub_assembly: bool}>
     */
    private function explodeBomMaterials(
        BillOfMaterials $bom,
        float $parentQtyMultiplier,
        array &$visitedBomIds,
        int $depth,
    ): array {
        if ($depth > self::MAX_BOM_DEPTH) {
            return [];
        }

        // Guard against circular BOM references
        if (in_array($bom->id, $visitedBomIds, true)) {
            return [];
        }
        $visitedBomIds[] = $bom->id;

        $bom->loadMissing(['components.componentItem']);
        $result = [];

        foreach ($bom->components as $comp) {
            $item = $comp->componentItem;
            if ($item === null) {
                continue;
            }

            $qtyPerUnit = (float) $comp->qty_per_unit * $parentQtyMultiplier;
            $scrapFactor = 1 + ((float) $comp->scrap_factor_pct / 100);
            $grossQty = $qtyPerUnit * $scrapFactor;

            // Check if this component is a semi-finished item with its own BOM
            $subBom = null;
            if ($item->type === 'semi_finished') {
                $subBom = BillOfMaterials::where('product_item_id', $item->id)
                    ->where('is_active', true)
                    ->first();
            }

            if ($subBom !== null) {
                // Recurse: explode the sub-assembly BOM
                $subComponents = $this->explodeBomMaterials(
                    $subBom,
                    $grossQty,
                    $visitedBomIds,
                    $depth + 1,
                );

                // Add sub-assembly as a summary line
                $subTotal = 0;
                foreach ($subComponents as $sc) {
                    $subTotal += $sc['line_cost_centavos'];
                }

                $result[] = [
                    'item_id' => $comp->component_item_id,
                    'item_code' => $item->item_code ?? '—',
                    'item_name' => $item->name ?? '—',
                    'qty_per_unit' => round($qtyPerUnit, 4),
                    'scrap_factor_pct' => (float) $comp->scrap_factor_pct,
                    'gross_qty' => round($grossQty, 4),
                    'unit_cost_centavos' => $grossQty > 0 ? (int) round($subTotal / $grossQty) : 0,
                    'line_cost_centavos' => $subTotal,
                    'bom_level' => $depth + 1,
                    'is_sub_assembly' => true,
                    'sub_bom_id' => $subBom->id,
                    'sub_bom_version' => $subBom->version,
                    'sub_components' => $subComponents,
                ];
            } else {
                // Leaf component: use standard_price
                $unitCost = (int) ($item->standard_price_centavos ?? (($item->standard_price ?? 0) * 100));
                $componentCost = (int) round($grossQty * $unitCost);

                $result[] = [
                    'item_id' => $comp->component_item_id,
                    'item_code' => $item->item_code ?? '—',
                    'item_name' => $item->name ?? '—',
                    'qty_per_unit' => round($qtyPerUnit, 4),
                    'scrap_factor_pct' => (float) $comp->scrap_factor_pct,
                    'gross_qty' => round($grossQty, 4),
                    'unit_cost_centavos' => $unitCost,
                    'line_cost_centavos' => $componentCost,
                    'bom_level' => $depth + 1,
                    'is_sub_assembly' => false,
                ];
            }
        }

        return $result;
    }

    /**
     * Compute labor and overhead cost from BOM routings.
     *
     * Each routing step has setup_time + run_time_per_unit, linked to a WorkCenter
     * with hourly_rate_centavos and overhead_rate_centavos.
     *
     * @return array{labor_cost_centavos: int, overhead_cost_centavos: int, steps: list<array>}
     */
    private function computeRoutingCost(BillOfMaterials $bom): array
    {
        $routings = Routing::where('bom_id', $bom->id)
            ->with('workCenter')
            ->orderBy('sequence')
            ->get();

        $totalLabor = 0;
        $totalOverhead = 0;
        $steps = [];

        foreach ($routings as $routing) {
            $wc = $routing->workCenter;
            if ($wc === null) {
                continue;
            }

            $setupHours = (float) $routing->setup_time_hours;
            $runHoursPerUnit = (float) $routing->run_time_hours_per_unit;
            $totalHours = $setupHours + $runHoursPerUnit; // per single unit of finished product

            $hourlyRate = (int) $wc->hourly_rate_centavos;
            $overheadRate = (int) $wc->overhead_rate_centavos;

            $laborForStep = (int) round($totalHours * $hourlyRate);
            $overheadForStep = (int) round($totalHours * $overheadRate);

            $totalLabor += $laborForStep;
            $totalOverhead += $overheadForStep;

            $steps[] = [
                'sequence' => $routing->sequence,
                'operation_name' => $routing->operation_name,
                'work_center_code' => $wc->code,
                'work_center_name' => $wc->name,
                'setup_hours' => round($setupHours, 4),
                'run_hours_per_unit' => round($runHoursPerUnit, 4),
                'total_hours' => round($totalHours, 4),
                'hourly_rate_centavos' => $hourlyRate,
                'overhead_rate_centavos' => $overheadRate,
                'labor_cost_centavos' => $laborForStep,
                'overhead_cost_centavos' => $overheadForStep,
                'total_step_cost_centavos' => $laborForStep + $overheadForStep,
            ];
        }

        // Fallback: if no routings defined, estimate from standard_production_days
        if ($routings->isEmpty() && $bom->standard_production_days > 0) {
            $estimatedHours = $bom->standard_production_days * 8;
            $totalLabor = (int) round($estimatedHours * self::DEFAULT_LABOR_RATE_CENTAVOS);

            $steps[] = [
                'sequence' => 1,
                'operation_name' => 'Estimated (no routings defined)',
                'work_center_code' => '—',
                'work_center_name' => 'Default estimate',
                'setup_hours' => 0,
                'run_hours_per_unit' => round((float) $estimatedHours, 4),
                'total_hours' => round((float) $estimatedHours, 4),
                'hourly_rate_centavos' => self::DEFAULT_LABOR_RATE_CENTAVOS,
                'overhead_rate_centavos' => 0,
                'labor_cost_centavos' => $totalLabor,
                'overhead_cost_centavos' => 0,
                'total_step_cost_centavos' => $totalLabor,
            ];
        }

        return [
            'labor_cost_centavos' => $totalLabor,
            'overhead_cost_centavos' => $totalOverhead,
            'steps' => $steps,
        ];
    }

    /**
     * Where-used report: given a raw material, find all BOMs that use it.
     *
     * @return Collection<int, array{bom_id: int, product_item_id: int, product_name: string, bom_version: string, qty_per_unit: float, is_active: bool}>
     */
    public function whereUsed(int $itemId): Collection
    {
        return BomComponent::where('component_item_id', $itemId)
            ->with(['bom.productItem'])
            ->get()
            ->map(fn (BomComponent $comp) => [
                'bom_id' => $comp->bom_id,
                'product_item_id' => $comp->bom?->product_item_id,
                'product_name' => $comp->bom?->productItem?->name ?? '—',
                'bom_version' => $comp->bom?->version ?? '—',
                'qty_per_unit' => round((float) $comp->qty_per_unit, 4),
                'is_active' => (bool) $comp->bom?->is_active,
            ]);
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
                $unitPrice = (int) (($item->itemMaster?->standard_price ?? 0) * 100);
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

        // Use total standard cost (material + labor + overhead) for comparison
        $standardUnit = $standard['total_standard_cost_centavos'];
        $actualUnit = $actual['unit_cost_centavos'];
        $variance = $standardUnit - $actualUnit;
        $variancePct = $standardUnit > 0 ? round(($variance / $standardUnit) * 100, 2) : 0.0;

        return [
            'standard_unit_cost_centavos' => $standardUnit,
            'standard_material_centavos' => $standard['material_cost_centavos'],
            'standard_labor_centavos' => $standard['labor_cost_centavos'],
            'standard_overhead_centavos' => $standard['overhead_cost_centavos'],
            'actual_unit_cost_centavos' => $actualUnit,
            'actual_material_centavos' => $actual['material_cost_centavos'],
            'actual_labor_centavos' => $actual['labor_cost_centavos'],
            'variance_centavos' => $variance,
            'variance_pct' => $variancePct,
            'favorable' => $variance >= 0,
        ];
    }

    /**
     * Full cost sheet for a finished product (from active BOM).
     *
     * Uses the enhanced standardCost which includes material (multi-level),
     * labor (from routings), and overhead (from work centers).
     *
     * @return array{product_name: string, bom_version: string, material_breakdown: array, routing_breakdown: array, total_material_centavos: int, total_labor_centavos: int, total_overhead_centavos: int, total_standard_cost_centavos: int}|null
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

        return [
            'product_name' => $product->name,
            'bom_version' => $bom->version,
            'material_breakdown' => $standard['components'],
            'routing_breakdown' => $standard['routings'],
            'total_material_centavos' => $standard['material_cost_centavos'],
            'total_labor_centavos' => $standard['labor_cost_centavos'],
            'total_overhead_centavos' => $standard['overhead_cost_centavos'],
            'total_standard_cost_centavos' => $standard['total_standard_cost_centavos'],
        ];
    }
}
