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
    });
});
