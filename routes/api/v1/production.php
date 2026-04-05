<?php

declare(strict_types=1);

use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Domains\Production\Models\ProductionOrder;
use App\Http\Controllers\Production\BomController;
use App\Http\Controllers\Production\CombinedDeliveryScheduleController;
use App\Http\Controllers\Production\DeliveryScheduleController;
use App\Http\Controllers\Production\ProductionOrderController;
use App\Http\Controllers\Production\RoutingController;
use App\Http\Controllers\Production\WorkCenterController;
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
    Route::get('boms-archived', [BomController::class, 'archived']);
    Route::post('boms/{bom}/restore', [BomController::class, 'restore'])->middleware('throttle:api-action');
    Route::delete('boms/{bom}/force', [BomController::class, 'forceDelete'])->middleware('throttle:api-action');
    Route::get('boms/{bom}/cost-breakdown', [BomController::class, 'costBreakdown']);
    Route::get('boms/{bom}/cost-history', [BomController::class, 'costHistory']);
    Route::post('boms/{bom}/rollup-cost', [BomController::class, 'rollupCost'])->middleware('throttle:api-action');
    Route::get('boms/{bom}/cost-compare', [BomController::class, 'costCompare']);

    // ── Delivery Schedules ───────────────────────────────────────────────────
    Route::get('delivery-schedules', [DeliveryScheduleController::class, 'index']);
    Route::post('delivery-schedules', [DeliveryScheduleController::class, 'store']);
    Route::get('delivery-schedules/{deliverySchedule}', [DeliveryScheduleController::class, 'show']);
    Route::put('delivery-schedules/{deliverySchedule}', [DeliveryScheduleController::class, 'update']);
    Route::post('delivery-schedules/{deliverySchedule}/fulfill', [DeliveryScheduleController::class, 'fulfillFromStock'])
        ->middleware('throttle:api-action');

    // ── Delivery Schedule Workflow Actions ─────────────────────────────────
    Route::post('delivery-schedules/{deliverySchedule:ulid}/dispatch', [DeliveryScheduleController::class, 'dispatch'])
        ->middleware('throttle:api-action');
    Route::post('delivery-schedules/{deliverySchedule:ulid}/delivered', [DeliveryScheduleController::class, 'markDelivered'])
        ->middleware('throttle:api-action');
    Route::post('delivery-schedules/{deliverySchedule:ulid}/acknowledge', [DeliveryScheduleController::class, 'acknowledgeReceipt'])
        ->middleware('can:respond,deliverySchedule')
        ->middleware('throttle:api-action');
    Route::post('delivery-schedules/{deliverySchedule:ulid}/notify-missing', [DeliveryScheduleController::class, 'notifyMissingItems'])
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
    Route::get('orders-archived', [ProductionOrderController::class, 'archived']);
    Route::post('orders/replenishment', [ProductionOrderController::class, 'createReplenishment'])->middleware('throttle:api-action');
    Route::post('orders', [ProductionOrderController::class, 'store']);
    Route::get('orders/smart-defaults', [ProductionOrderController::class, 'smartDefaults']);
    Route::get('orders/{productionOrder}', [ProductionOrderController::class, 'show']);
    Route::post('orders/{productionOrder}/restore', [ProductionOrderController::class, 'restore'])->middleware('throttle:api-action');
    Route::delete('orders/{productionOrder}/force', [ProductionOrderController::class, 'forceDelete'])->middleware('throttle:api-action');

    Route::put('orders/{productionOrder}', [ProductionOrderController::class, 'update'])->middleware('throttle:api-action');

    Route::middleware('throttle:api-action')->group(function (): void {
        Route::patch('orders/{productionOrder}/release', [ProductionOrderController::class, 'release']);
        Route::patch('orders/{productionOrder}/approve-release', [ProductionOrderController::class, 'approveRelease']);
        Route::patch('orders/{productionOrder}/start', [ProductionOrderController::class, 'start']);
        Route::patch('orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete']);
        Route::patch('orders/{productionOrder}/close', [ProductionOrderController::class, 'close']);
        Route::patch('orders/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel']);
        Route::patch('orders/{productionOrder}/void', [ProductionOrderController::class, 'void']);
        Route::patch('orders/{productionOrder}/hold', [ProductionOrderController::class, 'hold']);
        Route::patch('orders/{productionOrder}/resume', [ProductionOrderController::class, 'resume']);
        Route::post('orders/{productionOrder}/output', [ProductionOrderController::class, 'logOutput']);
    });

    // PROD-001: Pre-release stock availability check
    Route::get('orders/{productionOrder}/stock-check', [ProductionOrderController::class, 'stockCheck']);

    // ── Production Cost Auto-Posting to GL (Phase 2) ──────────────────────
    Route::post('orders/{productionOrder}/post-cost', function (\Illuminate\Http\Request $request, \App\Domains\Production\Models\ProductionOrder $productionOrder): \Illuminate\Http\JsonResponse {
        abort_unless($request->user()?->can('production.orders.update'), 403, 'Unauthorized to post production costs.');
        $service = app(\App\Domains\Production\Services\ProductionCostPostingService::class);
        return response()->json(['data' => $service->postCostVariance($productionOrder, $request->user())]);
    })->middleware('throttle:api-action');

    // ── Production Cost Analysis Report ──────────────────────────────────────
    Route::get('reports/cost-analysis', function (Request $request): JsonResponse {
        $service = app(\App\Domains\Production\Services\ProductionReportService::class);

        return response()->json($service->costAnalysis($request->only(['date_from', 'date_to'])));
    })->name('reports.cost-analysis')->middleware('permission:production.orders.view');

    // ── Work Centers ─────────────────────────────────────────────────────
    Route::get('work-centers', [WorkCenterController::class, 'index']);
    Route::post('work-centers', [WorkCenterController::class, 'store'])->middleware('throttle:api-action');
    Route::get('work-centers/{workCenter}', [WorkCenterController::class, 'show']);
    Route::put('work-centers/{workCenter}', [WorkCenterController::class, 'update'])->middleware('throttle:api-action');
    Route::delete('work-centers/{workCenter}', [WorkCenterController::class, 'destroy'])->middleware('throttle:api-action');

    // ── Routings ──────────────────────────────────────────────────────────
    Route::get('routings', [RoutingController::class, 'index']);
    Route::get('routings/bom/{bomId}', [RoutingController::class, 'forBom']);
    Route::post('routings', [RoutingController::class, 'store'])->middleware('throttle:api-action');
    Route::put('routings/{routing}', [RoutingController::class, 'update'])->middleware('throttle:api-action');
    Route::delete('routings/{routing}', [RoutingController::class, 'destroy'])->middleware('throttle:api-action');
    Route::post('routings/bom/{bomId}/reorder', [RoutingController::class, 'reorder'])->middleware('throttle:api-action');

    // ── MRP (Material Requirements Planning) ─────────────────────────────
    Route::prefix('mrp')->middleware('permission:production.orders.view')->group(function (): void {
        Route::get('/summary', function (): JsonResponse {
            $service = app(\App\Domains\Production\Services\MrpService::class);
            return response()->json(['data' => $service->summary()]);
        });

        Route::get('/explode', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'product_item_id' => ['required', 'integer', 'exists:item_masters,id'],
                'qty' => ['required', 'numeric', 'min:0.0001'],
            ]);
            $service = app(\App\Domains\Production\Services\MrpService::class);
            return response()->json(['data' => $service->explodeRequirements(
                (int) $validated['product_item_id'],
                (float) $validated['qty']
            )]);
        });

        Route::get('/time-phased', function (): JsonResponse {
            $service = app(\App\Domains\Production\Services\MrpService::class);
            return response()->json(['data' => $service->timePhased()]);
        });
    });

    // ── BOM Where-Used (Enhancement) ────────────────────────────────────
    Route::get('bom/where-used/{itemId}', function (int $itemId): JsonResponse {
        $service = app(\App\Domains\Production\Services\CostingService::class);
        return response()->json(['data' => $service->whereUsed($itemId)]);
    });
});
