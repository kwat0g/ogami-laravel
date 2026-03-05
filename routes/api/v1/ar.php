<?php

declare(strict_types=1);

use App\Http\Controllers\AR\CustomerController;
use App\Http\Controllers\AR\CustomerInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AR Module Routes — /api/v1/ar/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Customers ─────────────────────────────────────────────────────────────
    Route::get('customers', [CustomerController::class, 'index'])
        ->name('customers.index');

    Route::post('customers', [CustomerController::class, 'store'])
        ->name('customers.store');

    Route::get('customers/{customer}', [CustomerController::class, 'show'])
        ->name('customers.show');

    Route::put('customers/{customer}', [CustomerController::class, 'update'])
        ->name('customers.update');

    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('customers.destroy');

    // ── Customer Invoices ─────────────────────────────────────────────────────
    // Static sub-routes BEFORE parameter routes (Laravel matches top-to-bottom)
    Route::get('invoices/due-soon', [CustomerInvoiceController::class, 'dueSoon'])
        ->name('ar-invoices.due-soon');

    Route::get('invoices', [CustomerInvoiceController::class, 'index'])
        ->name('ar-invoices.index');

    Route::post('invoices', [CustomerInvoiceController::class, 'store'])
        ->name('ar-invoices.store');

    Route::get('invoices/{customerInvoice}', [CustomerInvoiceController::class, 'show'])
        ->name('ar-invoices.show');

    // AR-003: approve draft → generate INV number + auto-post JE
    Route::patch('invoices/{customerInvoice}/approve', [CustomerInvoiceController::class, 'approve'])
        ->middleware(['sod:customer_invoices,approve', 'throttle:api-action'])
        ->name('ar-invoices.approve');

    Route::patch('invoices/{customerInvoice}/cancel', [CustomerInvoiceController::class, 'cancel'])
        ->middleware('throttle:api-action')
        ->name('ar-invoices.cancel');

    // AR-005: payment receipt (excess → advance payment automatically)
    Route::post('invoices/{customerInvoice}/payments', [CustomerInvoiceController::class, 'receivePayment'])
        ->name('ar-invoices.receive-payment');

    // AR-006: bad debt write-off (Accounting Manager only)
    Route::patch('invoices/{customerInvoice}/write-off', [CustomerInvoiceController::class, 'writeOff'])
        ->middleware('throttle:api-action')
        ->name('ar-invoices.write-off');
});
