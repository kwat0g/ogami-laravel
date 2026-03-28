<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\BankAccountController;
use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\FiscalPeriodController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\RecurringJournalTemplateController;
use App\Http\Controllers\Accounting\Reports\BalanceSheetController;
use App\Http\Controllers\Accounting\Reports\CashFlowController;
use App\Http\Controllers\Accounting\Reports\GeneralLedgerController;
use App\Http\Controllers\Accounting\Reports\IncomeStatementController;
use App\Http\Controllers\Accounting\Reports\TrialBalanceController;
use App\Http\Controllers\AP\VendorController;
use App\Http\Controllers\AP\VendorCreditNoteController;
use App\Http\Controllers\AP\VendorInvoiceController;
use App\Http\Controllers\AP\VendorItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance / Accounting Routes — /api/v1/finance/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:accounting'])->group(function () {

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

    Route::get('accounts-archived', [ChartOfAccountController::class, 'archived'])
        ->name('accounts.archived');

    Route::post('accounts/{account}/restore', [ChartOfAccountController::class, 'restore'])
        ->middleware('throttle:api-action')
        ->name('accounts.restore');

    Route::delete('accounts/{account}/force', [ChartOfAccountController::class, 'forceDelete'])
        ->middleware('throttle:api-action')
        ->name('accounts.force-delete');

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

    // ── Journal Entry Templates ──────────────────────────────────────────────
    Route::get('journal-entry-templates', [JournalEntryController::class, 'templates'])
        ->name('journal-entry-templates.index');

    Route::post('journal-entry-templates', [JournalEntryController::class, 'storeTemplate'])
        ->name('journal-entry-templates.store');

    Route::get('journal-entry-templates/{templateId}/apply', [JournalEntryController::class, 'applyTemplate'])
        ->name('journal-entry-templates.apply');

    Route::delete('journal-entry-templates/{templateId}', [JournalEntryController::class, 'deleteTemplate'])
        ->name('journal-entry-templates.destroy');

    // ── Recurring Journal Entry Templates (GL-REC-001/002) ────────────────────
    Route::get('recurring-templates', [RecurringJournalTemplateController::class, 'index'])
        ->name('recurring-templates.index');
    Route::post('recurring-templates', [RecurringJournalTemplateController::class, 'store'])
        ->middleware('throttle:api-action')
        ->name('recurring-templates.store');
    Route::get('recurring-templates/{recurringJournalTemplate}', [RecurringJournalTemplateController::class, 'show'])
        ->name('recurring-templates.show');
    Route::put('recurring-templates/{recurringJournalTemplate}', [RecurringJournalTemplateController::class, 'update'])
        ->middleware('throttle:api-action')
        ->name('recurring-templates.update');
    Route::patch('recurring-templates/{recurringJournalTemplate}/toggle', [RecurringJournalTemplateController::class, 'toggle'])
        ->middleware('throttle:api-action')
        ->name('recurring-templates.toggle');
    Route::delete('recurring-templates/{recurringJournalTemplate}', [RecurringJournalTemplateController::class, 'destroy'])
        ->name('recurring-templates.destroy');

}); // end module_access:accounting

// ── Vendors & Vendor Items — accessible by ACCTG and PURCH departments ────────
// module_access:vendors maps to ['ACCTG', 'PURCH'] in ModuleAccessMiddleware
Route::middleware(['auth:sanctum', 'module_access:vendors'])->group(function () {
    // ── Vendors (AP-002, AP-004, AP-011) ─────────────────────────────────────
    Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::get('vendors/{vendor}/items', [VendorController::class, 'items'])->name('vendors.items');
    Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    Route::get('vendors-archived', [VendorController::class, 'archived'])->name('vendors.archived');
    Route::post('vendors/{vendor}/restore', [VendorController::class, 'restore'])
        ->middleware('throttle:api-action')
        ->name('vendors.restore');
    Route::delete('vendors/{vendor}/force', [VendorController::class, 'forceDelete'])
        ->middleware('throttle:api-action')
        ->name('vendors.force-delete');
    Route::patch('vendors/{vendor}/accredit', [VendorController::class, 'accredit'])
        ->middleware('throttle:api-action')
        ->name('vendors.accredit');
    Route::patch('vendors/{vendor}/suspend', [VendorController::class, 'suspend'])
        ->middleware('throttle:api-action')
        ->name('vendors.suspend');
    Route::post('vendors/{vendor}/provision-account', [VendorController::class, 'provisionPortalAccount'])
        ->middleware(['permission:system.manage_users', 'throttle:api-action'])
        ->name('vendors.provision-account');
    Route::post('vendors/{vendor}/reset-account', [VendorController::class, 'resetPortalAccountPassword'])
        ->middleware(['permission:system.manage_users', 'throttle:api-action'])
        ->name('vendors.reset-account');
    Route::get('vendors/{vendor}/scorecard', [VendorController::class, 'scorecard'])
        ->name('vendors.scorecard');

    // ── Vendor Items ─────────────────────────────────────────────────────────
    Route::get('vendors/{vendor}/items', [VendorItemController::class, 'index'])->name('vendors.items.index');
    Route::post('vendors/{vendor}/items', [VendorItemController::class, 'store'])->name('vendors.items.store');
    Route::put('vendors/{vendor}/items/{vendorItem}', [VendorItemController::class, 'update'])->name('vendors.items.update');
    Route::delete('vendors/{vendor}/items/{vendorItem}', [VendorItemController::class, 'destroy'])->name('vendors.items.destroy');
    Route::post('vendors/{vendor}/items/import', [VendorItemController::class, 'import'])->name('vendors.items.import');
});

// Re-open accounting group for AP Invoices and remaining accounting routes
Route::middleware(['auth:sanctum', 'module_access:accounting'])->group(function () {

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

    Route::post('ap/invoices/from-po', [VendorInvoiceController::class, 'createFromPo'])
        ->middleware('throttle:api-action')
        ->name('ap-invoices.from-po');

    // Batch AP invoice operations (must be above parameterised routes)
    Route::post('ap/invoices/batch-approve', [VendorInvoiceController::class, 'batchApprove'])
        ->middleware('throttle:api-action')
        ->name('ap.invoices.batch-approve');
    Route::post('ap/invoices/batch-reject', [VendorInvoiceController::class, 'batchReject'])
        ->middleware('throttle:api-action')
        ->name('ap.invoices.batch-reject');

    Route::get('ap/invoices/{apInvoice}', [VendorInvoiceController::class, 'show'])
        ->name('ap-invoices.show');

    Route::get('ap/invoices/{apInvoice}/pdf', [VendorInvoiceController::class, 'pdf'])
        ->name('ap-invoices.pdf');

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

    // ── Vendor Credit Notes ──────────────────────────────────────────────────
    Route::get('ap/credit-notes', [VendorCreditNoteController::class, 'index'])
        ->name('vendor-credit-notes.index');

    Route::post('ap/credit-notes', [VendorCreditNoteController::class, 'store'])
        ->name('vendor-credit-notes.store');

    Route::get('ap/credit-notes/{creditNote}', [VendorCreditNoteController::class, 'show'])
        ->name('vendor-credit-notes.show');

    Route::patch('ap/credit-notes/{creditNote}/post', [VendorCreditNoteController::class, 'post'])
        ->name('vendor-credit-notes.post');

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
