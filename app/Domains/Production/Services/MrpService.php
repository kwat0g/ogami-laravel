<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\BomComponent;
use App\Domains\Production\Models\ProductionOrder;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Material Requirements Planning (MRP) Service.
 *
 * Provides:
 *   - summary(): dashboard-level overview of planned orders, shortages, capacity
 *   - explodeRequirements(): for a product + qty, explode BOM to raw materials,
 *     check stock, and identify shortages with procurement suggestions
 *   - timePhased(): project material needs over time based on open PO dates
 */
final class MrpService implements ServiceContract
{
    /**
     * Dashboard summary: counts of planned orders, material shortages, and capacity utilization.
     *
     * @return array{planned_orders: int, material_shortages: int, capacity_utilization: float, open_orders: int, released_orders: int, shortage_items: list<array>}
     */
    public function summary(): array
    {
        $openOrders = ProductionOrder::whereIn('status', ['draft', 'released'])->count();
        $releasedOrders = ProductionOrder::where('status', 'released')->count();
        $inProgressOrders = ProductionOrder::where('status', 'in_progress')->count();

        // Find material shortages across all released/draft production orders
        $shortages = $this->findMaterialShortages();

        // Calculate capacity utilization from active work centers
        $capacityUtil = $this->calculateCapacityUtilization();

        return [
            'planned_orders' => $openOrders,
            'open_orders' => $openOrders,
            'released_orders' => $releasedOrders,
            'in_progress_orders' => $inProgressOrders,
            'material_shortages' => count($shortages),
            'shortage_items' => array_slice($shortages, 0, 20), // Top 20 shortages
            'capacity_utilization' => $capacityUtil,
        ];
    }

    /**
     * Explode material requirements for a product and quantity.
     *
     * Returns the full BOM explosion with stock availability check per component.
     *
     * @return array{product_item_id: int, qty: float, components: list<array{item_id: int, item_code: string, item_name: string, required_qty: float, available_stock: float, reserved_qty: float, net_available: float, shortage: float, sufficient: bool}>, total_components: int, shortage_count: int}
     */
    public function explodeRequirements(int $productItemId, float $qty): array
    {
        $bom = BillOfMaterials::where('product_item_id', $productItemId)
            ->where('is_active', true)
            ->with('components.componentItem')
            ->first();

        if ($bom === null) {
            return [
                'product_item_id' => $productItemId,
                'qty' => $qty,
                'components' => [],
                'total_components' => 0,
                'shortage_count' => 0,
                'error' => 'No active BOM found for this product.',
            ];
        }

        $components = [];
        $shortageCount = 0;

        foreach ($bom->components as $comp) {
            $item = $comp->componentItem;
            if ($item === null) {
                continue;
            }

            $requiredQty = round(
                (float) $comp->qty_per_unit * $qty * (1 + (float) $comp->scrap_factor_pct / 100),
                4
            );

            $availableStock = (float) StockBalance::where('item_id', $comp->component_item_id)
                ->sum('quantity_on_hand');

            $reservedQty = (float) StockReservation::where('item_id', $comp->component_item_id)
                ->where('status', 'active')
                ->sum('quantity');

            $netAvailable = max(0, $availableStock - $reservedQty);
            $shortage = max(0, $requiredQty - $netAvailable);
            $sufficient = $netAvailable >= $requiredQty;

            if (! $sufficient) {
                $shortageCount++;
            }

            $components[] = [
                'item_id' => $comp->component_item_id,
                'item_code' => $item->item_code ?? '',
                'item_name' => $item->name ?? '',
                'unit_of_measure' => $comp->unit_of_measure,
                'qty_per_unit' => (float) $comp->qty_per_unit,
                'scrap_factor_pct' => (float) $comp->scrap_factor_pct,
                'required_qty' => $requiredQty,
                'available_stock' => $availableStock,
                'reserved_qty' => $reservedQty,
                'net_available' => $netAvailable,
                'shortage' => $shortage,
                'sufficient' => $sufficient,
            ];
        }

        return [
            'product_item_id' => $productItemId,
            'qty' => $qty,
            'bom_id' => $bom->id,
            'bom_version' => $bom->version,
            'components' => $components,
            'total_components' => count($components),
            'shortage_count' => $shortageCount,
        ];
    }

    /**
     * Time-phased material requirements based on open production orders.
     *
     * Groups material needs by week based on PO target start dates.
     *
     * @return list<array{week_start: string, week_end: string, orders: list<array>, material_needs: list<array>}>
     */
    public function timePhased(): array
    {
        $orders = ProductionOrder::with('bom.components.componentItem')
            ->whereIn('status', ['draft', 'released'])
            ->whereNotNull('bom_id')
            ->orderBy('target_start_date')
            ->get();

        $weeks = [];

        foreach ($orders as $order) {
            $weekStart = $order->target_start_date
                ? $order->target_start_date->startOfWeek()->toDateString()
                : now()->startOfWeek()->toDateString();
            $weekEnd = $order->target_start_date
                ? $order->target_start_date->endOfWeek()->toDateString()
                : now()->endOfWeek()->toDateString();

            $weekKey = $weekStart;

            if (! isset($weeks[$weekKey])) {
                $weeks[$weekKey] = [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                    'orders' => [],
                    'material_needs' => [],
                ];
            }

            $weeks[$weekKey]['orders'][] = [
                'id' => $order->id,
                'po_reference' => $order->po_reference,
                'product_item_id' => $order->product_item_id,
                'qty_required' => (float) $order->qty_required,
                'status' => $order->status,
            ];

            // Aggregate material needs
            if ($order->bom !== null) {
                foreach ($order->bom->components as $comp) {
                    $itemId = $comp->component_item_id;
                    $requiredQty = round(
                        (float) $comp->qty_per_unit * (float) $order->qty_required * (1 + (float) $comp->scrap_factor_pct / 100),
                        4
                    );

                    if (! isset($weeks[$weekKey]['material_needs'][$itemId])) {
                        $weeks[$weekKey]['material_needs'][$itemId] = [
                            'item_id' => $itemId,
                            'item_code' => $comp->componentItem?->item_code ?? '',
                            'item_name' => $comp->componentItem?->name ?? '',
                            'total_required' => 0,
                        ];
                    }

                    $weeks[$weekKey]['material_needs'][$itemId]['total_required'] += $requiredQty;
                }
            }
        }

        // Convert material_needs from associative to indexed array and add stock info
        foreach ($weeks as &$week) {
            $needs = array_values($week['material_needs']);

            foreach ($needs as &$need) {
                $available = (float) StockBalance::where('item_id', $need['item_id'])
                    ->sum('quantity_on_hand');
                $need['available_stock'] = $available;
                $need['shortage'] = max(0, $need['total_required'] - $available);
                $need['sufficient'] = $available >= $need['total_required'];
            }

            $week['material_needs'] = $needs;
        }

        return array_values($weeks);
    }

    /**
     * Find material shortages across all open (draft + released) production orders.
     *
     * @return list<array{item_id: int, item_code: string, item_name: string, total_required: float, available_stock: float, shortage: float}>
     */
    private function findMaterialShortages(): array
    {
        // Aggregate material requirements across all open POs
        $requirements = DB::table('production_orders as po')
            ->join('bom_components as bc', 'po.bom_id', '=', 'bc.bom_id')
            ->join('item_masters as im', 'bc.component_item_id', '=', 'im.id')
            ->whereIn('po.status', ['draft', 'released'])
            ->whereNull('po.deleted_at')
            ->select([
                'bc.component_item_id as item_id',
                'im.item_code',
                'im.name as item_name',
                DB::raw('SUM(bc.qty_per_unit * po.qty_required * (1 + bc.scrap_factor_pct / 100)) as total_required'),
            ])
            ->groupBy('bc.component_item_id', 'im.item_code', 'im.name')
            ->get();

        $shortages = [];

        foreach ($requirements as $req) {
            $availableStock = (float) StockBalance::where('item_id', $req->item_id)
                ->sum('quantity_on_hand');

            $totalRequired = (float) $req->total_required;
            $shortage = max(0, $totalRequired - $availableStock);

            if ($shortage > 0) {
                $shortages[] = [
                    'item_id' => $req->item_id,
                    'item_code' => $req->item_code,
                    'item_name' => $req->item_name,
                    'total_required' => round($totalRequired, 4),
                    'available_stock' => $availableStock,
                    'shortage' => round($shortage, 4),
                ];
            }
        }

        // Sort by shortage descending (most critical first)
        usort($shortages, fn ($a, $b) => $b['shortage'] <=> $a['shortage']);

        return $shortages;
    }

    /**
     * Calculate capacity utilization based on active work orders and work center capacity.
     */
    private function calculateCapacityUtilization(): float
    {
        // Total available capacity (all active work centers, next 7 days)
        $totalCapacity = (float) DB::table('work_centers')
            ->where('is_active', true)
            ->sum('capacity_hours_per_day') * 7;

        if ($totalCapacity <= 0) {
            return 0.0;
        }

        // Calculate hours needed for in-progress + released orders from routings
        $hoursNeeded = (float) DB::table('production_orders as po')
            ->join('routings as r', 'po.bom_id', '=', 'r.bom_id')
            ->whereIn('po.status', ['released', 'in_progress'])
            ->whereNull('po.deleted_at')
            ->selectRaw('SUM(r.setup_time_hours + r.run_time_hours_per_unit * po.qty_required) as total_hours')
            ->value('total_hours') ?? 0;

        return min(100.0, round(($hoursNeeded / $totalCapacity) * 100, 1));
    }
}
