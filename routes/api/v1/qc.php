<?php

declare(strict_types=1);

use App\Http\Controllers\QC\InspectionController;
use App\Http\Controllers\QC\InspectionTemplateController;
use App\Http\Controllers\QC\NcrController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| QC / QA Routes  — prefix: /api/v1/qc/
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:qc'])->group(function () {

    // Inspection templates
    Route::get('templates', [InspectionTemplateController::class, 'index']);
    Route::post('templates', [InspectionTemplateController::class, 'store']);
    Route::get('templates/{inspectionTemplate}', [InspectionTemplateController::class, 'show']);
    Route::put('templates/{inspectionTemplate}', [InspectionTemplateController::class, 'update']);
    Route::delete('templates/{inspectionTemplate}', [InspectionTemplateController::class, 'destroy'])
        ->middleware('throttle:10,1');

    // Inspections
    Route::get('inspections', [InspectionController::class, 'index']);
    Route::post('inspections', [InspectionController::class, 'store']);
    Route::get('inspections/{inspection}', [InspectionController::class, 'show']);
    Route::delete('inspections/{inspection}', [InspectionController::class, 'destroy'])
        ->middleware('throttle:10,1');
    Route::patch('inspections/{inspection}/results', [InspectionController::class, 'recordResults'])
        ->middleware('throttle:30,1');
    Route::patch('inspections/{inspection}/cancel-results', [InspectionController::class, 'cancelResults'])
        ->middleware('throttle:10,1');

    // NCRs
    Route::get('ncrs', [NcrController::class, 'index']);
    Route::post('ncrs', [NcrController::class, 'store']);
    Route::get('ncrs/{nonConformanceReport}', [NcrController::class, 'show']);
    Route::patch('ncrs/{nonConformanceReport}/capa', [NcrController::class, 'issueCapa'])
        ->middleware('throttle:30,1');
    Route::patch('ncrs/{nonConformanceReport}/close', [NcrController::class, 'close'])
        ->middleware('throttle:30,1');
    Route::get('capa', [NcrController::class, 'capaIndex']);
    Route::patch('capa/{capaAction}/complete', [NcrController::class, 'completeCapa'])
        ->middleware('throttle:30,1');

    // ── QC Defect Rate Analytics ─────────────────────────────────────────────
    Route::get('reports/defect-rate', function (): JsonResponse {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();
            $label = now()->subMonths($i)->format('M Y');

            $total = DB::table('inspections')->whereBetween('created_at', [$start, $end])->count();
            $passed = DB::table('inspections')->whereBetween('created_at', [$start, $end])->where('status', 'passed')->count();
            $failed = DB::table('inspections')->whereBetween('created_at', [$start, $end])->where('status', 'failed')->count();

            $defectRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;

            $months[] = ['month' => $label, 'total' => $total, 'passed' => $passed, 'failed' => $failed, 'defect_rate' => $defectRate];
        }

        // Top defect severities from NCRs (severity is the available column)
        $topDefects = DB::table('non_conformance_reports')
            ->select('severity', DB::raw('count(*) as count'))
            ->whereNotNull('severity')
            ->groupBy('severity')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json(['data' => $months, 'top_defects' => $topDefects]);
    })->name('reports.defect-rate');
});
