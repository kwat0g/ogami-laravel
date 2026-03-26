<?php

declare(strict_types=1);

use App\Http\Controllers\Tax\BirFilingController;
use App\Http\Controllers\Tax\VatLedgerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tax Module Routes — /api/v1/tax/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:tax'])->group(function () {

    // ── VAT Ledger (VAT-004) ─────────────────────────────────────────────────
    Route::get('vat-ledger', [VatLedgerController::class, 'index'])
        ->name('vat-ledger.index');

    Route::get('vat-ledger/{vatLedger}', [VatLedgerController::class, 'show'])
        ->name('vat-ledger.show');

    // VAT-004: close period + carry-forward negative net_vat
    Route::patch('vat-ledger/{vatLedger}/close', [VatLedgerController::class, 'closePeriod'])
        ->name('vat-ledger.close');

    // ── BIR Filing Tracker ───────────────────────────────────────────────────
    Route::get('bir-filings', [BirFilingController::class, 'index'])
        ->name('bir-filings.index');

    Route::post('bir-filings', [BirFilingController::class, 'schedule'])
        ->name('bir-filings.schedule');

    Route::get('bir-filings/overdue', [BirFilingController::class, 'overdue'])
        ->name('bir-filings.overdue');

    Route::get('bir-filings/calendar', [BirFilingController::class, 'calendar'])
        ->name('bir-filings.calendar');

    Route::patch('bir-filings/{birFiling}/file', [BirFilingController::class, 'markFiled'])
        ->name('bir-filings.file');

    Route::patch('bir-filings/{birFiling}/amend', [BirFilingController::class, 'markAmended'])
        ->name('bir-filings.amend');
});
