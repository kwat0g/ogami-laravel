<?php

declare(strict_types=1);

use App\Http\Controllers\Reports\GovernmentReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Government Reports — Route Group
|--------------------------------------------------------------------------
| All routes require authentication (auth:sanctum applied in api.php).
| Authorization is enforced per-method via Policy checks in the controller.
|
| Prefix  : /api/v1/reports
| Name    : v1.reports.
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // ── BIR ─────────────────────────────────────────────────────────────────
    Route::get('bir/1601c', [GovernmentReportsController::class, 'form1601c'])
        ->name('bir.1601c');

    Route::get('bir/2316', [GovernmentReportsController::class, 'form2316'])
        ->name('bir.2316');

    Route::get('bir/alphalist', [GovernmentReportsController::class, 'alphalist'])
        ->name('bir.alphalist');

    // ── SSS ──────────────────────────────────────────────────────────────────
    Route::get('sss/sbr2', [GovernmentReportsController::class, 'sssSbr2'])
        ->name('sss.sbr2');

    // ── PhilHealth ───────────────────────────────────────────────────────────
    Route::get('philhealth/rf1', [GovernmentReportsController::class, 'philhealthRf1'])
        ->name('philhealth.rf1');

    // ── Pag-IBIG ─────────────────────────────────────────────────────────────
    Route::get('pagibig/monthly', [GovernmentReportsController::class, 'pagibigMonthly'])
        ->name('pagibig.monthly');
});
