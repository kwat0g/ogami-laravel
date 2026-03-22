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
});
