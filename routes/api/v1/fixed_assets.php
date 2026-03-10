<?php

declare(strict_types=1);

use App\Http\Controllers\FixedAssets\FixedAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fixed Assets Routes — /api/v1/fixed-assets/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function (): void {
    // ── Asset Categories ─────────────────────────────────────────────────
    Route::get('categories', [FixedAssetController::class, 'indexCategories'])
        ->name('categories.index');
    Route::post('categories', [FixedAssetController::class, 'storeCategory'])
        ->middleware('throttle:api-action')
        ->name('categories.store');

    // ── Asset Register ───────────────────────────────────────────────────
    Route::get('', [FixedAssetController::class, 'index'])
        ->name('index');
    Route::post('', [FixedAssetController::class, 'store'])
        ->middleware('throttle:api-action')
        ->name('store');
    Route::get('{fixedAsset}', [FixedAssetController::class, 'show'])
        ->name('show');
    Route::put('{fixedAsset}', [FixedAssetController::class, 'update'])
        ->middleware('throttle:api-action')
        ->name('update');

    // ── Depreciation (batch by fiscal period) ───────────────────────────
    Route::post('depreciate', [FixedAssetController::class, 'depreciatePeriod'])
        ->middleware('throttle:api-action')
        ->name('depreciate');

    // ── Disposal ─────────────────────────────────────────────────────────
    Route::post('{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])
        ->middleware('throttle:api-action')
        ->name('dispose');
});
