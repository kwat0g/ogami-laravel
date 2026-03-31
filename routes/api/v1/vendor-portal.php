<?php

declare(strict_types=1);

use App\Http\Controllers\VendorPortal\VendorPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor Portal Routes — /api/v1/vendor-portal
|--------------------------------------------------------------------------
| All routes require auth:sanctum + vendor_scope middleware.
| Data is automatically scoped to the authenticated user's vendor_id.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'vendor_scope'])->group(function () {

    // ── Orders ───────────────────────────────────────────────────────────────
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [VendorPortalController::class, 'orders'])
            ->name('index');

        Route::get('/{purchaseOrder}', [VendorPortalController::class, 'orderDetail'])
            ->name('show');

        Route::post('/{purchaseOrder}/acknowledge', [VendorPortalController::class, 'acknowledge'])
            ->middleware('throttle:api-action')
            ->name('acknowledge');

        Route::post('/{purchaseOrder}/propose-changes', [VendorPortalController::class, 'proposeChanges'])
            ->middleware('throttle:api-action')
            ->name('propose-changes');

        Route::post('/{purchaseOrder}/in-transit', [VendorPortalController::class, 'markInTransit'])
            ->middleware('throttle:api-action')
            ->name('in-transit');

        Route::post('/{purchaseOrder}/deliver', [VendorPortalController::class, 'markDelivered'])
            ->middleware('throttle:api-action')
            ->name('deliver');
    });

    // ── Catalog Items ────────────────────────────────────────────────────────
    Route::prefix('items')->name('items.')->group(function () {
        Route::get('/', [VendorPortalController::class, 'items'])
            ->name('index');

        Route::post('/', [VendorPortalController::class, 'storeItem'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::patch('/{item}', [VendorPortalController::class, 'updateItem'])
            ->middleware('throttle:api-action')
            ->name('update');

        Route::post('/import', [VendorPortalController::class, 'importItems'])
            ->middleware('throttle:api-action')
            ->name('import');
    });

    // ── Goods Receipts ──────────────────────────────────────────────────────
    Route::prefix('goods-receipts')->name('goods-receipts.')->group(function () {
        Route::get('/', [VendorPortalController::class, 'goodsReceipts'])
            ->name('index');
        Route::get('/{goodsReceipt}', [VendorPortalController::class, 'goodsReceiptDetail'])
            ->name('show');
    });

    // ── Invoices ────────────────────────────────────────────────────────────
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [VendorPortalController::class, 'invoices'])
            ->name('index');
        Route::get('/{vendorInvoice}', [VendorPortalController::class, 'invoiceDetail'])
            ->name('show');

        Route::post('/', [VendorPortalController::class, 'storeInvoice'])
            ->middleware('throttle:api-action')
            ->name('store');
    });
});
