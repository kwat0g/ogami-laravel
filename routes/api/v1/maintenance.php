<?php

declare(strict_types=1);

use App\Http\Controllers\Maintenance\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    // Equipment
    Route::get('/equipment', [MaintenanceController::class, 'indexEquipment']);
    Route::post('/equipment', [MaintenanceController::class, 'storeEquipment']);
    Route::get('/equipment/{equipment}', [MaintenanceController::class, 'showEquipment']);
    Route::put('/equipment/{equipment}', [MaintenanceController::class, 'updateEquipment']);
    Route::post('/equipment/{equipment}/pm-schedules', [MaintenanceController::class, 'storePmSchedule']);

    // Work Orders
    Route::get('/work-orders', [MaintenanceController::class, 'indexWorkOrders']);
    Route::post('/work-orders', [MaintenanceController::class, 'storeWorkOrder']);
    Route::get('/work-orders/{maintenanceWorkOrder}', [MaintenanceController::class, 'showWorkOrder']);
    Route::middleware('throttle:30,1')->group(function (): void {
        Route::patch('/work-orders/{maintenanceWorkOrder}/start', [MaintenanceController::class, 'startWorkOrder']);
        Route::patch('/work-orders/{maintenanceWorkOrder}/complete', [MaintenanceController::class, 'completeWorkOrder']);
    });

    // Work Order Parts (C1: Maintenance ↔ Inventory spare parts)
    Route::get('/work-orders/{maintenanceWorkOrder}/parts', [MaintenanceController::class, 'indexParts']);
    Route::post('/work-orders/{maintenanceWorkOrder}/parts', [MaintenanceController::class, 'addPart']);
});
