<?php

declare(strict_types=1);

use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Http\Controllers\Production\BomController;
use App\Http\Controllers\Production\CombinedDeliveryScheduleController;
use App\Http\Controllers\Production\DeliveryScheduleController;
use App\Http\Controllers\Production\ProductionOrderController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module_access:production'])->group(function (): void {

    // ── Bill of Materials ────────────────────────────────────────────────────
    Route::get('boms', [BomController::class, 'index']);
    Route::post('boms', [BomController::class, 'store']);
    Route::get('boms/{bom}', [BomController::class, 'show']);
    Route::put('boms/{bom}', [BomController::class, 'update']);
    Route::patch('boms/{bom}/activate', [BomController::class, 'activate']);
    Route::delete('boms/{bom}', [BomController::class, 'destroy']);
    Route::post('boms/{bom}/rollup-cost', [BomController::class, 'rollupCost'])->middleware('throttle:api-action');
    Route::get('boms/{bom}/cost-compare', [BomController::class, 'costCompare']);

    // ── Delivery Schedules ───────────────────────────────────────────────────
    Route::get('delivery-schedules', [DeliveryScheduleController::class, 'index']);
    Route::post('delivery-schedules', [DeliveryScheduleController::class, 'store']);
    Route::get('delivery-schedules/{deliverySchedule}', [DeliveryScheduleController::class, 'show']);
    Route::put('delivery-schedules/{deliverySchedule}', [DeliveryScheduleController::class, 'update']);
    Route::post('delivery-schedules/{deliverySchedule}/fulfill', [DeliveryScheduleController::class, 'fulfillFromStock'])
        ->middleware('throttle:api-action');

    // ── Combined Delivery Schedules (Multi-item delivery grouping) ────────────
    Route::get('combined-delivery-schedules', [CombinedDeliveryScheduleController::class, 'index'])
        ->middleware('can:viewAny,'.CombinedDeliverySchedule::class);
    Route::get('combined-delivery-schedules/{schedule:ulid}', [CombinedDeliveryScheduleController::class, 'show'])
        ->middleware('can:view,schedule');
    Route::post('combined-delivery-schedules/{schedule:ulid}/dispatch', [CombinedDeliveryScheduleController::class, 'dispatch'])
        ->middleware('can:update,schedule', 'throttle:api-action');
    Route::post('combined-delivery-schedules/{schedule:ulid}/delivered', [CombinedDeliveryScheduleController::class, 'markDelivered'])
        ->middleware('can:update,schedule', 'throttle:api-action');
    Route::post('combined-delivery-schedules/{schedule:ulid}/notify-missing', [CombinedDeliveryScheduleController::class, 'notifyMissingItems'])
        ->middleware('can:update,schedule', 'throttle:api-action');
    Route::post('combined-delivery-schedules/{schedule:ulid}/acknowledge', [CombinedDeliveryScheduleController::class, 'acknowledgeReceipt'])
        ->middleware('can:respond,schedule', 'throttle:api-action');

    // ── Production Orders ────────────────────────────────────────────────────
    Route::get('orders', [ProductionOrderController::class, 'index']);
    Route::post('orders', [ProductionOrderController::class, 'store']);
    Route::get('orders/smart-defaults', [ProductionOrderController::class, 'smartDefaults']);
    Route::get('orders/{productionOrder}', [ProductionOrderController::class, 'show']);

    Route::middleware('throttle:api-action')->group(function (): void {
        Route::patch('orders/{productionOrder}/release', [ProductionOrderController::class, 'release']);
        Route::patch('orders/{productionOrder}/start', [ProductionOrderController::class, 'start']);
        Route::patch('orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete']);
        Route::patch('orders/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel']);
        Route::patch('orders/{productionOrder}/void', [ProductionOrderController::class, 'void']);
        Route::post('orders/{productionOrder}/output', [ProductionOrderController::class, 'logOutput']);
    });

    // PROD-001: Pre-release stock availability check
    Route::get('orders/{productionOrder}/stock-check', [ProductionOrderController::class, 'stockCheck']);

    // ── Production Cost Auto-Posting to GL (Phase 2) ──────────────────────
    Route::post('orders/{productionOrder}/post-cost', function (\Illuminate\Http\Request $request, \App\Domains\Production\Models\ProductionOrder $productionOrder): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Production\Services\ProductionCostPostingService::class);
        return response()->json(['data' => $service->postCostVariance($productionOrder, $request->user())]);
    })->middleware('throttle:api-action');

    // ── Production Cost Analysis Report ──────────────────────────────────────
    Route::get('reports/cost-analysis', function (Request $request): JsonResponse {
        $query = ProductionOrder::with(['product', 'bom:id,name'])
            ->whereIn('status', ['completed', 'released', 'in_progress']);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $orders = $query->orderByDesc('created_at')->limit(100)->get();

        $rows = $orders->map(function ($order) {
            // Sum material costs from stock ledger entries linked to this production order
            $materialCost = DB::table('stock_ledger')
                ->where('reference_type', 'production_orders')
                ->where('reference_id', $order->id)
                ->where('quantity', '<', 0) // issues (negative = consumed)
                ->join('item_masters', 'stock_ledger.item_id', '=', 'item_masters.id')
                ->sum(DB::raw('abs(stock_ledger.quantity) * coalesce(item_masters.unit_cost, 0)'));

            // Output qty from production output logs
            $outputQty = $order->total_output_qty ?? $order->qty_produced ?? 0;

            $unitCost = $outputQty > 0 ? round((float) $materialCost / $outputQty, 2) : 0;

            return [
                'order_id' => $order->id,
                'ulid' => $order->ulid,
                'po_reference' => $order->po_reference ?? "PO-{$order->id}",
                'product_name' => $order->product?->name ?? '—',
                'bom_name' => $order->bom?->name ?? '—',
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

        return response()->json([
            'data' => $rows->values(),
            'summary' => [
                'total_orders' => $rows->count(),
                'total_material_cost' => round($totalMaterialCost, 2),
                'total_output' => $totalOutput,
                'avg_unit_cost' => $totalOutput > 0 ? round($totalMaterialCost / $totalOutput, 2) : 0,
            ],
        ]);
    })->name('reports.cost-analysis')->middleware('permission:production.orders.view');
});
