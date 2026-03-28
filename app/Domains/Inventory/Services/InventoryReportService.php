<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventory report queries extracted from route closures.
 *
 * Resolves Tech Debt Item 1: raw DB:: queries should live in services,
 * not in route file closures (violates ARCH-001 spirit).
 */
final class InventoryReportService implements ServiceContract
{
    /**
     * Inventory valuation report using average PO cost.
     *
     * @return array{data: Collection, by_category: Collection, grand_total: float}
     */
    public function valuationReport(): array
    {
        $poCosts = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNotNull('purchase_order_items.item_master_id')
            ->whereIn('purchase_orders.status', ['sent', 'partially_received', 'fully_received', 'closed'])
            ->select(
                'purchase_order_items.item_master_id',
                DB::raw('avg(purchase_order_items.agreed_unit_cost) as unit_cost'),
            )
            ->groupBy('purchase_order_items.item_master_id');

        $rows = DB::table('stock_balances')
            ->join('item_masters', 'stock_balances.item_id', '=', 'item_masters.id')
            ->leftJoin('item_categories', 'item_masters.category_id', '=', 'item_categories.id')
            ->leftJoin('warehouse_locations', 'stock_balances.location_id', '=', 'warehouse_locations.id')
            ->leftJoinSub($poCosts, 'po_costs', function ($join): void {
                $join->on('item_masters.id', '=', 'po_costs.item_master_id');
            })
            ->where('stock_balances.quantity_on_hand', '>', 0)
            ->select(
                'item_masters.id as item_id',
                'item_masters.item_code',
                'item_masters.name as item_name',
                DB::raw("coalesce(item_categories.name, 'Uncategorized') as category"),
                'warehouse_locations.name as location',
                'item_masters.unit_of_measure as uom',
                'stock_balances.quantity_on_hand as quantity',
                'po_costs.unit_cost',
                DB::raw('round(stock_balances.quantity_on_hand * coalesce(po_costs.unit_cost, 0), 2) as total_value'),
            )
            ->orderBy('item_categories.name')
            ->orderBy('item_masters.name')
            ->get();

        $byCategory = $rows->groupBy('category')->map(fn ($items, $cat) => [
            'category' => $cat,
            'item_count' => $items->count(),
            'total_qty' => $items->sum('quantity'),
            'total_value' => round($items->sum('total_value'), 2),
        ])->values();

        $grandTotal = round($rows->sum('total_value'), 2);

        return [
            'data' => $rows,
            'by_category' => $byCategory,
            'grand_total' => $grandTotal,
        ];
    }
}
