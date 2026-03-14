<?php

declare(strict_types=1);

use App\Http\Controllers\AR\CustomerController;
use App\Http\Controllers\AR\CustomerCreditNoteController;
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

    // Client Portal Account provisioning (admin only)
    Route::post('customers/{customer}/provision-account', [CustomerController::class, 'provisionPortalAccount'])
        ->middleware(['permission:system.manage_users', 'throttle:api-action'])
        ->name('customers.provision-account');

    Route::post('customers/{customer}/reset-account', [CustomerController::class, 'resetPortalAccountPassword'])
        ->middleware(['permission:system.manage_users', 'throttle:api-action'])
        ->name('customers.reset-account');

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

    // ── AR Credit / Debit Notes ───────────────────────────────────────────────
    Route::get('credit-notes', [CustomerCreditNoteController::class, 'index'])
        ->name('customer-credit-notes.index');

    Route::post('credit-notes', [CustomerCreditNoteController::class, 'store'])
        ->middleware('throttle:api-action')
        ->name('customer-credit-notes.store');

    Route::get('credit-notes/{customerCreditNote}', [CustomerCreditNoteController::class, 'show'])
        ->name('customer-credit-notes.show');

    Route::patch('credit-notes/{customerCreditNote}/post', [CustomerCreditNoteController::class, 'post'])
        ->middleware(['sod:customer_credit_notes,post', 'throttle:api-action'])
        ->name('customer-credit-notes.post');

    // ── AR Aging Report ───────────────────────────────────────────────────────
    Route::get('aging-report', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        $asOfDate = \Carbon\Carbon::parse($request->input('as_of_date', now()->toDateString()));

        $invoices = \App\Domains\AR\Models\CustomerInvoice::with('customer:id,company_name')
            ->whereNotIn('status', ['cancelled', 'written_off'])
            ->whereColumn('total_amount', '>', \Illuminate\Support\Facades\DB::raw("COALESCE((SELECT SUM(amount) FROM customer_payments WHERE customer_payments.customer_invoice_id = customer_invoices.id), 0)"))
            ->get(['id', 'customer_id', 'invoice_number', 'due_date', 'total_amount', 'status']);

        $buckets = [];
        foreach ($invoices as $inv) {
            $paid = (float) $inv->payments()->sum('amount');
            $balance = max(0.0, round((float) $inv->total_amount - $paid, 2));
            if ($balance <= 0) continue;

            $daysOverdue = max(0, (int) $inv->due_date->diffInDays($asOfDate, false));
            $bucket = match (true) {
                $inv->due_date->isAfter($asOfDate)  => 'current',
                $daysOverdue <= 30                    => '1_30',
                $daysOverdue <= 60                    => '31_60',
                $daysOverdue <= 90                    => '61_90',
                default                               => 'over_90',
            };

            $custId = $inv->customer_id;
            if (!isset($buckets[$custId])) {
                $buckets[$custId] = [
                    'customer_id'   => $custId,
                    'customer_name' => $inv->customer->company_name ?? "Customer #{$custId}",
                    'current'       => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0,
                    'total'         => 0.0,
                ];
            }
            $buckets[$custId][$bucket] = round($buckets[$custId][$bucket] + $balance, 2);
            $buckets[$custId]['total'] = round($buckets[$custId]['total'] + $balance, 2);
        }

        return response()->json([
            'data' => array_values($buckets),
            'as_of_date' => $asOfDate->toDateString(),
        ]);
    })->middleware('permission:reports.ar_aging')->name('ar.aging-report');

    // ── Customer Statement Export (CSV) ─────────────────────────────────────
    Route::get('customers/{customer}/statement', function (\App\Domains\AR\Models\Customer $customer): \Symfony\Component\HttpFoundation\StreamedResponse {
        $invoices = \Illuminate\Support\Facades\DB::table('customer_invoices')
            ->where('customer_id', $customer->id)
            ->select('invoice_number', 'invoice_date', 'due_date', 'total_amount', 'amount_paid', 'balance_due', 'status')
            ->orderBy('invoice_date')
            ->get();

        return response()->streamDownload(function () use ($customer, $invoices) {
            $out = fopen('php://output', 'w');
            if ($out === false) return;
            fputcsv($out, ['CUSTOMER STATEMENT OF ACCOUNT']);
            fputcsv($out, ['Customer', $customer->company_name ?? "#{$customer->id}"]);
            fputcsv($out, ['Generated', now()->format('Y-m-d H:i')]);
            fputcsv($out, []);
            fputcsv($out, ['Invoice #', 'Date', 'Due Date', 'Total', 'Paid', 'Balance', 'Status']);
            $totalBalance = 0.0;
            foreach ($invoices as $inv) {
                $balance = (float) ($inv->balance_due ?? 0);
                $totalBalance += $balance;
                fputcsv($out, [
                    $inv->invoice_number, $inv->invoice_date, $inv->due_date,
                    number_format((float) ($inv->total_amount ?? 0), 2),
                    number_format((float) ($inv->amount_paid ?? 0), 2),
                    number_format($balance, 2),
                    $inv->status,
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['', '', '', '', 'TOTAL BALANCE:', number_format($totalBalance, 2)]);
            fclose($out);
        }, "customer_statement_{$customer->id}_" . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    })->name('ar.customer-statement');
});
