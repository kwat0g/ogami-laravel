<?php

declare(strict_types=1);

use App\Http\Controllers\Inventory\ItemMasterController;
use App\Http\Controllers\Inventory\MaterialRequisitionController;
use App\Http\Controllers\Inventory\PhysicalCountController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseLocationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory / Warehouse Routes  — prefix: /api/v1/inventory/
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'module_access:inventory'])->group(function () {

    // ── Item Master ───────────────────────────────────────────────────────
    Route::get('items/low-stock', [ItemMasterController::class, 'lowStock']);
    Route::get('items/categories', [ItemMasterController::class, 'categories']);
    Route::post('items/categories', [ItemMasterController::class, 'storeCategory']);
    Route::delete('items/categories/{category}', [ItemMasterController::class, 'destroyCategory']);
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
    Route::post('transfers', [StockController::class, 'transfer'])->middleware('throttle:api-action');

    // ── Physical Counts ──────────────────────────────────────────────────
    Route::get('physical-counts', [PhysicalCountController::class, 'index']);
    Route::post('physical-counts', [PhysicalCountController::class, 'store']);
    Route::get('physical-counts/{physicalCount}', [PhysicalCountController::class, 'show']);
    Route::patch('physical-counts/{physicalCount}/start', [PhysicalCountController::class, 'startCounting'])->middleware('throttle:api-action');
    Route::post('physical-counts/{physicalCount}/counts', [PhysicalCountController::class, 'recordCounts'])->middleware('throttle:api-action');
    Route::patch('physical-counts/{physicalCount}/submit', [PhysicalCountController::class, 'submitForApproval'])->middleware('throttle:api-action');
    Route::patch('physical-counts/{physicalCount}/approve', [PhysicalCountController::class, 'approve'])->middleware('throttle:api-action');

    // ── Material Requisitions ─────────────────────────────────────────────
    Route::get('requisitions', [MaterialRequisitionController::class, 'index']);
    Route::post('requisitions', [MaterialRequisitionController::class, 'store']);
    Route::get('requisitions/{materialRequisition}', [MaterialRequisitionController::class, 'show']);
    Route::patch('requisitions/{materialRequisition}/submit', [MaterialRequisitionController::class, 'submit'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/note', [MaterialRequisitionController::class, 'note'])
        ->middleware(['sod:inventory_mrq,note', 'throttle:api-action']);
    Route::patch('requisitions/{materialRequisition}/check', [MaterialRequisitionController::class, 'check'])
        ->middleware(['sod:inventory_mrq,check', 'throttle:api-action']);
    Route::patch('requisitions/{materialRequisition}/review', [MaterialRequisitionController::class, 'review'])
        ->middleware(['sod:inventory_mrq,review', 'throttle:api-action']);
    Route::patch('requisitions/{materialRequisition}/vp-approve', [MaterialRequisitionController::class, 'vpApprove'])
        ->middleware(['sod:inventory_mrq,vp_approve', 'throttle:api-action']);
    Route::patch('requisitions/{materialRequisition}/reject', [MaterialRequisitionController::class, 'reject'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/cancel', [MaterialRequisitionController::class, 'cancel'])->middleware('throttle:api-action');
    Route::patch('requisitions/{materialRequisition}/fulfill', [MaterialRequisitionController::class, 'fulfill'])->middleware('throttle:api-action');

    // ── Inventory Valuation Report ───────────────────────────────────────────
    Route::get('reports/valuation', function (): JsonResponse {
        $service = app(\App\Domains\Inventory\Services\InventoryReportService::class);

        return response()->json($service->valuationReport());
    })->name('reports.valuation');

    // ── Inventory Analytics ─────────────────────────────────────────────────
    Route::get('analytics/abc', function (\Illuminate\Http\Request $request) {
        $service = app(\App\Domains\Inventory\Services\InventoryAnalyticsService::class);
        $year = $request->filled('year') ? $request->integer('year') : null;
        return response()->json(['data' => $service->abcAnalysis($year)]);
    })->name('analytics.abc');

    Route::get('analytics/turnover', function (\Illuminate\Http\Request $request) {
        $service = app(\App\Domains\Inventory\Services\InventoryAnalyticsService::class);
        $year = $request->filled('year') ? $request->integer('year') : null;
        return response()->json(['data' => $service->turnoverAnalysis($year)]);
    })->name('analytics.turnover');

    Route::get('analytics/dead-stock', function (\Illuminate\Http\Request $request) {
        $service = app(\App\Domains\Inventory\Services\InventoryAnalyticsService::class);
        $days = $request->integer('days', 90);
        return response()->json(['data' => $service->deadStock($days)]);
    })->name('analytics.dead-stock');

    // ── Low Stock Reorder ───────────────────────────────────────────────────
    Route::get('low-stock', function () {
        $service = app(\App\Domains\Inventory\Services\LowStockReorderService::class);
        return response()->json(['data' => $service->detectLowStock()->toArray()]);
    })->name('low-stock');

    Route::post('low-stock/create-reorder', function () {
        $service = app(\App\Domains\Inventory\Services\LowStockReorderService::class);
        $prs = $service->createReorderRequests(auth()->id());
        return response()->json([
            'message' => count($prs) > 0
                ? count($prs) . ' auto-reorder Purchase Request(s) created as draft.'
                : 'No items below reorder point. No PRs created.',
            'data' => collect($prs)->map(fn ($pr) => [
                'id' => $pr->id,
                'ulid' => $pr->ulid,
                'pr_reference' => $pr->pr_reference,
                'total_estimated_cost' => $pr->total_estimated_cost,
            ]),
        ]);
    })->name('low-stock.create-reorder');
});
