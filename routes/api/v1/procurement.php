<?php

declare(strict_types=1);

use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Routes — /api/v1/procurement
|--------------------------------------------------------------------------
| Approval chain: Staff → Head (note) → Manager (check) → Officer (review) → VP
| SoD: each consecutive stage enforced at DB + service + policy layers
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Purchase Requests ────────────────────────────────────────────────────
    Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])
            ->name('index');

        Route::post('/', [PurchaseRequestController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
            ->name('show');

        Route::patch('/{purchaseRequest}', [PurchaseRequestController::class, 'update'])
            ->middleware('throttle:api-action')
            ->name('update');

        // Workflow transitions
        Route::post('/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])
            ->middleware('throttle:api-action')
            ->name('submit');

        Route::post('/{purchaseRequest}/note', [PurchaseRequestController::class, 'note'])
            ->middleware('throttle:api-action')
            ->name('note');

        Route::post('/{purchaseRequest}/check', [PurchaseRequestController::class, 'check'])
            ->middleware('throttle:api-action')
            ->name('check');

        Route::post('/{purchaseRequest}/review', [PurchaseRequestController::class, 'review'])
            ->middleware('throttle:api-action')
            ->name('review');

        Route::post('/{purchaseRequest}/vp-approve', [PurchaseRequestController::class, 'vpApprove'])
            ->middleware('throttle:api-action')
            ->name('vp-approve');

        Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])
            ->middleware('throttle:api-action')
            ->name('reject');

        Route::post('/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])
            ->middleware('throttle:api-action')
            ->name('cancel');
    });

    // ── Purchase Orders ──────────────────────────────────────────────────────
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])
            ->name('index');

        Route::post('/', [PurchaseOrderController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->name('show');

        Route::patch('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
            ->middleware('throttle:api-action')
            ->name('update');

        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
            ->middleware('throttle:api-action')
            ->name('send');

        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware('throttle:api-action')
            ->name('cancel');
    });

    // ── Goods Receipts ───────────────────────────────────────────────────────
    Route::prefix('goods-receipts')->name('goods-receipts.')->group(function () {
        Route::get('/', [GoodsReceiptController::class, 'index'])
            ->name('index');

        Route::post('/', [GoodsReceiptController::class, 'store'])
            ->middleware('throttle:api-action')
            ->name('store');

        Route::get('/{goodsReceipt}', [GoodsReceiptController::class, 'show'])
            ->name('show');

        Route::post('/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm'])
            ->middleware('throttle:api-action')
            ->name('confirm');
    });
});
