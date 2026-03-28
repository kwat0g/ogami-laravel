<?php

declare(strict_types=1);

use App\Http\Controllers\Mold\MoldController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'module_access:mold'])->group(function (): void {
    Route::get('/molds', [MoldController::class, 'index']);
    Route::post('/molds', [MoldController::class, 'store']);
    Route::get('/molds/{moldMaster}', [MoldController::class, 'show']);
    Route::put('/molds/{moldMaster}', [MoldController::class, 'update']);
    Route::post('/molds/{moldMaster}/shots', [MoldController::class, 'logShots'])
        ->middleware('throttle:60,1');
    Route::patch('/molds/{moldMaster}/retire', [MoldController::class, 'retire']);
    Route::get('/molds-archived', [MoldController::class, 'archived']);
    Route::post('/molds/{moldMaster}/restore', [MoldController::class, 'restore'])
        ->middleware('throttle:api-action');
    Route::delete('/molds/{moldMaster}/force', [MoldController::class, 'forceDelete'])
        ->middleware('throttle:api-action');

    // ── Mold Analytics (Phase 2) — Cost Amortization & Lifecycle ──────────
    Route::get('/molds/{moldMaster}/cost-amortization', function (\App\Domains\Mold\Models\MoldMaster $moldMaster): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Mold\Services\MoldAnalyticsService::class);
        return response()->json(['data' => $service->costAmortization($moldMaster)]);
    })->name('molds.cost-amortization');

    Route::get('/analytics/lifecycle', function (): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Mold\Services\MoldAnalyticsService::class);
        return response()->json(['data' => $service->lifecycleDashboard()]);
    })->name('analytics.lifecycle');
});
