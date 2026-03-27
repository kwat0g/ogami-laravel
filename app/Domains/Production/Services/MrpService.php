<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Production\Models\BillOfMaterials;
use App\Domains\Production\Models\ProductionOrder;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Material Requirements Planning (MRP) Service.
 *
 * Explodes BOMs against demand (open production orders) and compares
 * required materials against current stock to identify shortages.
 *
 * This is a simplified single-level MRP explosion suitable for a
 * manufacturing ERP thesis project.
 */
final class MrpService implements ServiceContract
{
    /**
     * Run MRP explosion for all open/scheduled production orders.
     *
     * Returns material requirements with shortage analysis.
     *
     * @return Collection<int, array{
     *     component_item_id: int,
     *     component_code: string,
     *     component_name: string,
     *     unit_of_measure: string,
     *     total_required: float,
     *     total_reserved: float,
     *     current_stock: float,
     *     shortage: float,
     *     production_orders: array,
     * }>
     */
    public function explode(): Collection
    {
        // 1. Get all open/scheduled production orders
        $orders = ProductionOrder::query()
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->with('bom.components.componentItem')
            ->get();

        if ($orders->isEmpty()) {
            return collect();
        }

        // 2. Explode BOMs: for each order, calculate component requirements
        $requirements = collect();

        foreach ($orders as $order) {
            if ($order->bom === null) {
                continue;
            }

            $orderQty = (float) $order->quantity;

            foreach ($order->bom->components as $comp) {
                $qtyPerUnit = (float) $comp->qty_per_unit;
                $scrapFactor = 1 + ((float) $comp->scrap_factor_pct / 100);
                $grossRequired = $orderQty * $qtyPerUnit * $scrapFactor;

                $key = $comp->component_item_id;
                $existing = $requirements->get($key, [
                    'component_item_id' => $comp->component_item_id,
                    'component_code' => $comp->componentItem?->item_code ?? '—',
                    'component_name' => $comp->componentItem?->name ?? '—',
                    'unit_of_measure' => $comp->unit_of_measure,
                    'total_required' => 0.0,
                    'production_orders' => [],
                ]);

                $existing['total_required'] += $grossRequired;
                $existing['production_orders'][] = [
                    'production_order_id' => $order->id,
                    'product' => $order->bom?->productItem?->name ?? '—',
                    'order_qty' => $orderQty,
                    'component_qty_needed' => round($grossRequired, 4),
                ];

                $requirements->put($key, $existing);
            }
        }

        // 3. Get current stock and reservations
        $itemIds = $requirements->keys()->toArray();

        $stocks = StockBalance::query()
            ->whereIn('item_id', $itemIds)
            ->select('item_id', DB::raw('SUM(CAST(quantity_on_hand AS numeric)) as total_qty'))
            ->groupBy('item_id')
            ->pluck('total_qty', 'item_id');

        $reservations = DB::table('stock_reservations')
            ->whereIn('item_id', $itemIds)
            ->where('status', 'active')
            ->select('item_id', DB::raw('SUM(CAST(quantity_on_hand AS numeric)) as total_reserved'))
            ->groupBy('item_id')
            ->pluck('total_reserved', 'item_master_id');

        // 4. Calculate shortages
        return $requirements->map(function (array $req) use ($stocks, $reservations) {
            $currentStock = (float) ($stocks[$req['component_item_id']] ?? 0);
            $reserved = (float) ($reservations[$req['component_item_id']] ?? 0);
            $available = max(0, $currentStock - $reserved);
            $shortage = max(0, $req['total_required'] - $available);

            return [
                ...$req,
                'total_required' => round($req['total_required'], 4),
                'total_reserved' => round($reserved, 4),
                'current_stock' => round($currentStock, 4),
                'available' => round($available, 4),
                'shortage' => round($shortage, 4),
            ];
        })
            ->sortByDesc('shortage')
            ->values();
    }

    /**
     * Suggest purchase requests for items with shortages.
     *
     * @return Collection<int, array{
     *     item_id: int,
     *     item_code: string,
     *     item_name: string,
     *     shortage_qty: float,
     *     suggested_order_qty: float,
     *     preferred_vendor_id: int|null,
     * }>
     */
    public function suggestPurchases(): Collection
    {
        $explosion = $this->explode();

        return $explosion
            ->filter(fn (array $row) => $row['shortage'] > 0)
            ->map(function (array $row) {
                $item = \App\Domains\Inventory\Models\ItemMaster::find($row['component_item_id']);
                $reorderQty = $item ? (float) $item->reorder_qty : 0;

                // Suggest the greater of shortage or reorder quantity
                $suggestedQty = max($row['shortage'], $reorderQty);

                return [
                    'item_id' => $row['component_item_id'],
                    'item_code' => $row['component_code'],
                    'item_name' => $row['component_name'],
                    'shortage_qty' => $row['shortage'],
                    'suggested_order_qty' => round($suggestedQty, 4),
                    'preferred_vendor_id' => $item?->preferred_vendor_id,
                ];
            })
            ->values();
    }

    /**
     * MRP summary — overview stats.
     *
     * @return array{
     *     total_open_orders: int,
     *     total_components_needed: int,
     *     components_with_shortage: int,
     *     components_fully_stocked: int,
     * }
     */
    public function summary(): array
    {
        $explosion = $this->explode();

        return [
            'total_open_orders' => ProductionOrder::whereIn('status', ['draft', 'scheduled', 'in_progress'])->count(),
            'total_components_needed' => $explosion->count(),
            'components_with_shortage' => $explosion->where('shortage', '>', 0)->count(),
            'components_fully_stocked' => $explosion->where('shortage', '<=', 0)->count(),
        ];
    }
}
