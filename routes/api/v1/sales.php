<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\PricingController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\SalesOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales Module Routes — /api/v1/sales/
|--------------------------------------------------------------------------
| Quotations, Sales Orders, and Pricing Engine.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:sales'])->group(function () {

    // ── Pricing ──────────────────────────────────────────────────────────
    Route::get('pricing/resolve', [PricingController::class, 'getPrice']);

    // ── Quotations ───────────────────────────────────────────────────────
    Route::prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->name('index');
        Route::post('/', [QuotationController::class, 'store'])->name('store');
        Route::get('/{quotation:ulid}', [QuotationController::class, 'show'])->name('show');
        Route::patch('/{quotation:ulid}/send', [QuotationController::class, 'send'])->name('send')
            ->middleware('throttle:api-action');
        Route::patch('/{quotation:ulid}/accept', [QuotationController::class, 'accept'])->name('accept')
            ->middleware('throttle:api-action');
        Route::patch('/{quotation:ulid}/reject', [QuotationController::class, 'reject'])->name('reject')
            ->middleware('throttle:api-action');
        Route::post('/{quotation:ulid}/convert-to-order', [QuotationController::class, 'convertToOrder'])->name('convert')
            ->middleware('throttle:api-action');
    });

    // ── Sales Orders ─────────────────────────────────────────────────────
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [SalesOrderController::class, 'index'])->name('index');
        Route::post('/', [SalesOrderController::class, 'store'])->name('store');
        Route::get('/{salesOrder:ulid}', [SalesOrderController::class, 'show'])->name('show');
        Route::patch('/{salesOrder:ulid}/confirm', [SalesOrderController::class, 'confirm'])->name('confirm')
            ->middleware('throttle:api-action');
        Route::patch('/{salesOrder:ulid}/cancel', [SalesOrderController::class, 'cancel'])->name('cancel')
            ->middleware('throttle:api-action');
    });
});
