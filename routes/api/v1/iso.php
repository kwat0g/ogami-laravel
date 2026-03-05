<?php

declare(strict_types=1);

use App\Http\Controllers\ISO\ISOController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    // Controlled Documents
    Route::get('/documents', [ISOController::class, 'indexDocuments']);
    Route::post('/documents', [ISOController::class, 'storeDocument']);
    Route::get('/documents/{controlledDocument}', [ISOController::class, 'showDocument']);
    Route::put('/documents/{controlledDocument}', [ISOController::class, 'updateDocument']);

    // Internal Audits
    Route::get('/audits', [ISOController::class, 'indexAudits']);
    Route::post('/audits', [ISOController::class, 'storeAudit']);
    Route::get('/audits/{internalAudit}', [ISOController::class, 'showAudit']);
    Route::middleware('throttle:30,1')->group(function (): void {
        Route::patch('/audits/{internalAudit}/start', [ISOController::class, 'startAudit']);
        Route::patch('/audits/{internalAudit}/complete', [ISOController::class, 'completeAudit']);
    });
    Route::post('/audits/{internalAudit}/findings', [ISOController::class, 'storeFinding']);
});
