<?php

declare(strict_types=1);

use App\Http\Controllers\Inventory\ItemMasterController;
use App\Http\Controllers\Inventory\MaterialRequisitionController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseLocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory / Warehouse Routes  — prefix: /api/v1/inventory/
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // ── Item Master ───────────────────────────────────────────────────────
    Route::get('items/low-stock', [ItemMasterController::class, 'lowStock']);
    Route::get('items/categories', [ItemMasterController::class, 'categories']);
    Route::post('items/categories', [ItemMasterController::class, 'storeCategory']);
    Route::get('items', [ItemMasterController::class, 'index']);
    Route::post('items', [ItemMasterController::class, 'store']);
    Route::get('items/{item}', [ItemMasterController::class, 'show']);
    Route::put('items/{item}', [ItemMasterController::class, 'update']);
    Route::patch('items/{item}/toggle-active', [ItemMasterController::class, 'toggleActive']);

    // ── Warehouse Locations ───────────────────────────────────────────────
    Route::get('locations', [WarehouseLocationController::class, 'index']);
    Route::post('locations', [WarehouseLocationController::class, 'store']);
    Route::put('locations/{warehouseLocation}', [WarehouseLocationController::class, 'update']);

    // ── Stock ─────────────────────────────────────────────────────────────
    Route::get('stock-balances', [StockController::class, 'balances']);
    Route::get('stock-ledger', [StockController::class, 'ledger']);
    Route::post('adjustments', [StockController::class, 'adjust'])->middleware('throttle:api-action');

    // ── Material Requisitions ─────────────────────────────────────────────
    Route::get('requisitions', [MaterialRequisitionController::class, 'index']);
    Route::post('requisitions', [MaterialRequisitionController::class, 'store']);
    Route::get('requisitions/{materialRequisition}', [MaterialRequisitionController::class, 'show']);
    Route::patch('requisitions/{materialRequisition}/submit', [MaterialRequisitionController::class, 'submit'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/note', [MaterialRequisitionController::class, 'note'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/check', [MaterialRequisitionController::class, 'check'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/review', [MaterialRequisitionController::class, 'review'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/vp-approve', [MaterialRequisitionController::class, 'vpApprove'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/reject', [MaterialRequisitionController::class, 'reject'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/cancel', [MaterialRequisitionController::class, 'cancel'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/fulfill', [MaterialRequisitionController::class, 'fulfill'])->middleware('throttle:api-action');
});
