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
        $service = app(\App\Domains\Production\Services\ProductionReportService::class);

        return response()->json($service->costAnalysis($request->only(['date_from', 'date_to'])));
    })->name('reports.cost-analysis')->middleware('permission:production.orders.view');

    // ── Capacity Planning (Enhancement) ──────────────────────────────────
    Route::get('capacity', function (Request $request): JsonResponse {
        $service = app(\App\Domains\Production\Services\CapacityPlanningService::class);
        return response()->json(['data' => $service->utilizationReport($request->input('from'), $request->input('to'))]);
    });
    Route::get('capacity/check/{productionOrder}', function (ProductionOrder $productionOrder): JsonResponse {
        $service = app(\App\Domains\Production\Services\CapacityPlanningService::class);
        return response()->json(['data' => $service->checkFeasibility($productionOrder)]);
    });

    // ── Time-Phased MRP (Enhancement) ───────────────────────────────────
    Route::get('mrp/time-phased', function (): JsonResponse {
        $service = app(\App\Domains\Production\Services\MrpService::class);
        return response()->json(['data' => $service->timePhasedExplode()]);
    });

    // ── BOM Where-Used (Enhancement) ────────────────────────────────────
    Route::get('bom/where-used/{itemId}', function (int $itemId): JsonResponse {
        $service = app(\App\Domains\Production\Services\CostingService::class);
        return response()->json(['data' => $service->whereUsed($itemId)]);
    });
});
