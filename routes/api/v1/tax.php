<?php

declare(strict_types=1);

use App\Http\Controllers\Tax\VatLedgerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tax Module Routes — /api/v1/tax/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ── VAT Ledger (VAT-004) ──────────────────────────────────────────────────
    Route::get('vat-ledger', [VatLedgerController::class, 'index'])
        ->name('vat-ledger.index');

    Route::get('vat-ledger/{vatLedger}', [VatLedgerController::class, 'show'])
        ->name('vat-ledger.show');

    // VAT-004: close period + carry-forward negative net_vat
    Route::patch('vat-ledger/{vatLedger}/close', [VatLedgerController::class, 'closePeriod'])
        ->name('vat-ledger.close');
});
