<?php

declare(strict_types=1);

use App\Http\Controllers\Production\BomController;
use App\Http\Controllers\Production\DeliveryScheduleController;
use App\Http\Controllers\Production\ProductionOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {

    // ── Bill of Materials ────────────────────────────────────────────────────
    Route::get('boms',                  [BomController::class, 'index']);
    Route::post('boms',                 [BomController::class, 'store']);
    Route::get('boms/{bom}',            [BomController::class, 'show']);
    Route::put('boms/{bom}',            [BomController::class, 'update']);

    // ── Delivery Schedules ───────────────────────────────────────────────────
    Route::get('delivery-schedules',                              [DeliveryScheduleController::class, 'index']);
    Route::post('delivery-schedules',                             [DeliveryScheduleController::class, 'store']);
    Route::get('delivery-schedules/{deliverySchedule}',           [DeliveryScheduleController::class, 'show']);
    Route::put('delivery-schedules/{deliverySchedule}',           [DeliveryScheduleController::class, 'update']);

    // ── Production Orders ────────────────────────────────────────────────────
    Route::get('orders',                              [ProductionOrderController::class, 'index']);
    Route::post('orders',                             [ProductionOrderController::class, 'store']);
    Route::get('orders/{productionOrder}',            [ProductionOrderController::class, 'show']);

    Route::middleware('throttle:api-action')->group(function (): void {
        Route::patch('orders/{productionOrder}/release',  [ProductionOrderController::class, 'release']);
        Route::patch('orders/{productionOrder}/start',    [ProductionOrderController::class, 'start']);
        Route::patch('orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete']);
        Route::patch('orders/{productionOrder}/cancel',   [ProductionOrderController::class, 'cancel']);
        Route::post('orders/{productionOrder}/output',    [ProductionOrderController::class, 'logOutput']);
    });
});
