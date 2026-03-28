<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Production report queries extracted from route closures.
 *
 * Resolves Tech Debt Item 1: raw DB:: queries should live in services,
 * not in route file closures (violates ARCH-001 spirit).
 */
final class ProductionReportService implements ServiceContract
{
    /**
     * Cost analysis report for production orders.
     *
     * @param array{date_from?: string, date_to?: string} $filters
     * @return array{data: Collection, summary: array{total_orders: int, total_material_cost: float, total_output: float, avg_unit_cost: float}}
     */
    public function costAnalysis(array $filters = []): array
    {
        $query = ProductionOrder::with(['product', 'bom:id,name'])
            ->whereIn('status', ['completed', 'released', 'in_progress']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orders = $query->orderByDesc('created_at')->limit(100)->get();

        $rows = $orders->map(function (ProductionOrder $order): array {
            $materialCost = DB::table('stock_ledger')
                ->where('reference_type', 'production_orders')
                ->where('reference_id', $order->id)
                ->where('quantity', '<', 0)
                ->join('item_masters', 'stock_ledger.item_id', '=', 'item_masters.id')
                ->sum(DB::raw('abs(stock_ledger.quantity) * coalesce(item_masters.unit_cost, 0)'));

            $outputQty = $order->total_output_qty ?? $order->qty_produced ?? 0;
            $unitCost = $outputQty > 0 ? round((float) $materialCost / $outputQty, 2) : 0;

            return [
                'order_id' => $order->id,
                'ulid' => $order->ulid,
                'po_reference' => $order->po_reference ?? "PO-{$order->id}",
                'product_name' => $order->product?->name ?? "\u{2014}",
                'bom_name' => $order->bom?->name ?? "\u{2014}",
                'status' => $order->status,
                'qty_required' => $order->qty_required,
                'qty_produced' => $outputQty,
                'material_cost' => round((float) $materialCost, 2),
                'unit_cost' => $unitCost,
                'created_at' => $order->created_at?->toDateString(),
            ];
        });

        $totalMaterialCost = $rows->sum('material_cost');
        $totalOutput = $rows->sum('qty_produced');

        return [
            'data' => $rows->values(),
            'summary' => [
                'total_orders' => $rows->count(),
                'total_material_cost' => round($totalMaterialCost, 2),
                'total_output' => $totalOutput,
                'avg_unit_cost' => $totalOutput > 0 ? round($totalMaterialCost / $totalOutput, 2) : 0,
            ],
        ];
    }
}
