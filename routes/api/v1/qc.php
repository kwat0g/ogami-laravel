<?php

declare(strict_types=1);

use App\Http\Controllers\QC\InspectionController;
use App\Http\Controllers\QC\InspectionTemplateController;
use App\Http\Controllers\QC\NcrController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| QC / QA Routes  — prefix: /api/v1/qc/
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Inspection templates
    Route::get('templates',                            [InspectionTemplateController::class, 'index']);
    Route::post('templates',                           [InspectionTemplateController::class, 'store']);
    Route::get('templates/{inspectionTemplate}',       [InspectionTemplateController::class, 'show']);
    Route::put('templates/{inspectionTemplate}',       [InspectionTemplateController::class, 'update']);

    // Inspections
    Route::get('inspections',                          [InspectionController::class, 'index']);
    Route::post('inspections',                         [InspectionController::class, 'store']);
    Route::get('inspections/{inspection}',             [InspectionController::class, 'show']);
    Route::patch('inspections/{inspection}/results',   [InspectionController::class, 'recordResults'])
        ->middleware('throttle:30,1');

    // NCRs
    Route::get('ncrs',                                 [NcrController::class, 'index']);
    Route::post('ncrs',                                [NcrController::class, 'store']);
    Route::get('ncrs/{nonConformanceReport}',          [NcrController::class, 'show']);
    Route::patch('ncrs/{nonConformanceReport}/capa',   [NcrController::class, 'issueCapa'])
        ->middleware('throttle:30,1');
    Route::patch('ncrs/{nonConformanceReport}/close',  [NcrController::class, 'close'])
        ->middleware('throttle:30,1');
    Route::patch('capa/{capaAction}/complete',         [NcrController::class, 'completeCapa'])
        ->middleware('throttle:30,1');
});
