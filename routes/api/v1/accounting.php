<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\BankAccountController;
use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\FiscalPeriodController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\Reports\BalanceSheetController;
use App\Http\Controllers\Accounting\Reports\CashFlowController;
use App\Http\Controllers\Accounting\Reports\GeneralLedgerController;
use App\Http\Controllers\Accounting\Reports\IncomeStatementController;
use App\Http\Controllers\Accounting\Reports\TrialBalanceController;
use App\Http\Controllers\AP\VendorController;
use App\Http\Controllers\AP\VendorInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance / Accounting Routes — /api/v1/finance/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Chart of Accounts (COA-001 to COA-006) ───────────────────────────────
    Route::get('accounts', [ChartOfAccountController::class, 'index'])
        ->name('accounts.index');

    Route::post('accounts', [ChartOfAccountController::class, 'store'])
        ->name('accounts.store');

    Route::get('accounts/{account}', [ChartOfAccountController::class, 'show'])
        ->name('accounts.show');

    Route::put('accounts/{account}', [ChartOfAccountController::class, 'update'])
        ->name('accounts.update');

    Route::delete('accounts/{account}', [ChartOfAccountController::class, 'destroy'])
        ->name('accounts.destroy');

    // ── Fiscal Periods ───────────────────────────────────────────────────────
    Route::get('fiscal-periods', [FiscalPeriodController::class, 'index'])
        ->name('fiscal-periods.index');

    Route::post('fiscal-periods', [FiscalPeriodController::class, 'store'])
        ->name('fiscal-periods.store');

    Route::get('fiscal-periods/{fiscalPeriod}', [FiscalPeriodController::class, 'show'])
        ->name('fiscal-periods.show');

    Route::patch('fiscal-periods/{fiscalPeriod}/open', [FiscalPeriodController::class, 'open'])
        ->middleware('throttle:api-action')
        ->name('fiscal-periods.open');

    Route::patch('fiscal-periods/{fiscalPeriod}/close', [FiscalPeriodController::class, 'close'])
        ->middleware('throttle:api-action')
        ->name('fiscal-periods.close');

    // ── Journal Entries (JE-001 to JE-010) ──────────────────────────────────
    Route::get('journal-entries', [JournalEntryController::class, 'index'])
        ->name('journal-entries.index');

    Route::post('journal-entries', [JournalEntryController::class, 'store'])
        ->name('journal-entries.store');

    Route::get('journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])
        ->name('journal-entries.show');

    Route::patch('journal-entries/{journalEntry}/submit', [JournalEntryController::class, 'submit'])
        ->middleware('throttle:api-action')
        ->name('journal-entries.submit');

    Route::patch('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->middleware(['sod:journal_entries,post', 'throttle:api-action'])
        ->name('journal-entries.post');

    Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
        ->middleware('throttle:api-action')
        ->name('journal-entries.reverse');

    Route::delete('journal-entries/{journalEntry}', [JournalEntryController::class, 'cancel'])
        ->name('journal-entries.cancel');

    // ── Vendors (AP-002, AP-004, AP-011) ─────────────────────────────────────
    Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    Route::patch('vendors/{vendor}/accredit', [VendorController::class, 'accredit'])
        ->middleware('throttle:api-action')
        ->name('vendors.accredit');
    Route::patch('vendors/{vendor}/suspend', [VendorController::class, 'suspend'])
        ->middleware('throttle:api-action')
        ->name('vendors.suspend');

    // ── AP Invoices (AP-001 to AP-011) ───────────────────────────────────────
    // AP operational dashboard: totals by status, overdue summary, aging buckets
    Route::get('ap/dashboard', [VendorInvoiceController::class, 'dashboard'])
        ->name('ap-invoices.dashboard');

    Route::get('ap/invoices/due-soon', [VendorInvoiceController::class, 'dueSoon'])
        ->name('ap-invoices.due-soon');

    Route::get('ap/invoices', [VendorInvoiceController::class, 'index'])
        ->name('ap-invoices.index');

    Route::post('ap/invoices', [VendorInvoiceController::class, 'store'])
        ->name('ap-invoices.store');

    Route::get('ap/invoices/{apInvoice}', [VendorInvoiceController::class, 'show'])
        ->name('ap-invoices.show');

    // BIR Form 2307 (Certificate of Creditable Tax Withheld at Source)
    Route::get('ap/invoices/{apInvoice}/form-2307', [VendorInvoiceController::class, 'form2307'])
        ->name('ap-invoices.form-2307');

    Route::patch('ap/invoices/{apInvoice}/submit', [VendorInvoiceController::class, 'submit'])
        ->middleware('throttle:api-action')
        ->name('ap-invoices.submit');

    Route::patch('ap/invoices/{apInvoice}/head-note', [VendorInvoiceController::class, 'headNote'])
        ->middleware(['sod:vendor_invoices,approve', 'throttle:api-action'])
        ->name('ap-invoices.head-note');

    Route::patch('ap/invoices/{apInvoice}/manager-check', [VendorInvoiceController::class, 'managerCheck'])
        ->middleware(['sod:vendor_invoices,approve', 'throttle:api-action'])
        ->name('ap-invoices.manager-check');

    Route::patch('ap/invoices/{apInvoice}/officer-review', [VendorInvoiceController::class, 'officerReview'])
        ->middleware(['sod:vendor_invoices,approve', 'throttle:api-action'])
        ->name('ap-invoices.officer-review');

    Route::patch('ap/invoices/{apInvoice}/approve', [VendorInvoiceController::class, 'approve'])
        ->middleware(['sod:vendor_invoices,approve', 'throttle:api-action'])
        ->name('ap-invoices.approve');

    Route::patch('ap/invoices/{apInvoice}/reject', [VendorInvoiceController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('ap-invoices.reject');

    Route::post('ap/invoices/{apInvoice}/payments', [VendorInvoiceController::class, 'recordPayment'])
        ->name('ap-invoices.record-payment');

    // ── Financial Reports (GL-001 to GL-005) ─────────────────────────────────
    Route::get('reports/gl', GeneralLedgerController::class)
        ->name('reports.gl');

    Route::get('reports/trial-balance', TrialBalanceController::class)
        ->name('reports.trial-balance');

    Route::get('reports/balance-sheet', BalanceSheetController::class)
        ->name('reports.balance-sheet');

    Route::get('reports/income-statement', IncomeStatementController::class)
        ->name('reports.income-statement');

    Route::get('reports/cash-flow', CashFlowController::class)
        ->name('reports.cash-flow');

    // ── Bank Accounts (GL-006) ───────────────────────────────────────────────
    Route::get('bank-accounts', [BankAccountController::class, 'index'])
        ->name('bank-accounts.index');

    Route::post('bank-accounts', [BankAccountController::class, 'store'])
        ->name('bank-accounts.store');

    Route::get('bank-accounts/{bankAccount}', [BankAccountController::class, 'show'])
        ->name('bank-accounts.show');

    Route::put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])
        ->name('bank-accounts.update');

    Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])
        ->name('bank-accounts.destroy');

    // ── Bank Reconciliation (GL-006) ─────────────────────────────────────────
    Route::get('bank-reconciliations', [BankReconciliationController::class, 'index'])
        ->name('bank-reconciliations.index');

    Route::post('bank-reconciliations', [BankReconciliationController::class, 'store'])
        ->name('bank-reconciliations.store');

    Route::get('bank-reconciliations/{reconciliation}', [BankReconciliationController::class, 'show'])
        ->name('bank-reconciliations.show');

    Route::post('bank-reconciliations/{reconciliation}/import-statement', [BankReconciliationController::class, 'importStatement'])
        ->name('bank-reconciliations.import-statement');

    Route::patch('bank-reconciliations/{reconciliation}/match', [BankReconciliationController::class, 'matchTransaction'])
        ->name('bank-reconciliations.match');

    Route::patch('bank-reconciliations/{reconciliation}/transactions/{bankTransaction}/unmatch', [BankReconciliationController::class, 'unmatchTransaction'])
        ->name('bank-reconciliations.unmatch');

    Route::patch('bank-reconciliations/{reconciliation}/certify', [BankReconciliationController::class, 'certify'])
        ->middleware(['sod:bank_reconciliations,certify', 'throttle:api-action'])
        ->name('bank-reconciliations.certify');
});
