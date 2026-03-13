<?php

declare(strict_types=1);

use App\Http\Controllers\Loan\LoanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Loan Domain Routes  (prefix: v1/loans, name: v1.loans.)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('', [LoanController::class, 'index'])->name('index');
    Route::get('team', [LoanController::class, 'team'])
        ->middleware('permission:loans.view_department')
        ->name('team');
    Route::post('', [LoanController::class, 'store'])->name('store');
    Route::get('{loan}', [LoanController::class, 'show'])->name('show');
    Route::get('{loan}/employee-history', [LoanController::class, 'employeeHistory'])->name('employeeHistory');
    Route::patch('{loan}/approve', [LoanController::class, 'approve'])
        ->middleware('throttle:api-action')
        ->name('approve');
    Route::patch('{loan}/accounting-approve', [LoanController::class, 'accountingApprove'])
        ->middleware('throttle:api-action')
        ->name('accountingApprove');
    Route::patch('{loan}/disburse', [LoanController::class, 'disburse'])
        ->middleware('throttle:api-action')
        ->name('disburse');
    Route::patch('{loan}/reject', [LoanController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('reject');
    Route::delete('{loan}', [LoanController::class, 'cancel'])->name('cancel');

    // Workflow v2 actions
    Route::patch('{loan}/head-note', [LoanController::class, 'headNote'])
        ->middleware('throttle:api-action')
        ->name('headNote');
    Route::patch('{loan}/manager-check', [LoanController::class, 'managerCheck'])
        ->middleware('throttle:api-action')
        ->name('managerCheck');
    Route::patch('{loan}/officer-review', [LoanController::class, 'officerReview'])
        ->middleware('throttle:api-action')
        ->name('officerReview');
    Route::patch('{loan}/vp-approve', [LoanController::class, 'vpApprove'])
        ->middleware('throttle:api-action')
        ->name('vpApprove');

    // Amortization schedule sub-resource (read-only)
    Route::get('{loan}/schedule', [LoanController::class, 'schedule'])->name('schedule');

    // Payment recording
    Route::post('{loan}/payments', [LoanController::class, 'recordPayment'])->name('payments.store');

    // ── Loan SOA Export (CSV) ────────────────────────────────────────────────
    Route::get('{loan}/soa-export', function (\App\Domains\Loan\Models\Loan $loan): \Symfony\Component\HttpFoundation\StreamedResponse {        abort_unless(auth()->user()?->hasPermissionTo('loans.view_own'), 403, 'Unauthorized');
        $loan->load(['employee', 'loanType', 'amortizationSchedule']);
        $schedule = $loan->amortizationSchedule->sortBy('installment_no');
        $employeeName = ($loan->employee->first_name ?? '') . ' ' . ($loan->employee->last_name ?? '');

        return response()->streamDownload(function () use ($loan, $schedule, $employeeName) {
            $out = fopen('php://output', 'w');
            if ($out === false) return;
            // Header info
            fputcsv($out, ['LOAN STATEMENT OF ACCOUNT']);
            fputcsv($out, ['Employee', $employeeName]);
            fputcsv($out, ['Loan Type', $loan->loanType?->name ?? '—']);
            fputcsv($out, ['Principal', number_format((float) $loan->principal_amount, 2)]);
            fputcsv($out, ['Status', $loan->status]);
            fputcsv($out, []);
            fputcsv($out, ['#', 'Due Date', 'Amortization', 'Principal', 'Interest', 'Paid Amount', 'Balance']);
            foreach ($schedule as $s) {
                fputcsv($out, [
                    $s->installment_no,
                    $s->due_date,
                    number_format((float) ($s->amortization_amount ?? 0), 2),
                    number_format((float) ($s->principal_component ?? 0), 2),
                    number_format((float) ($s->interest_component ?? 0), 2),
                    number_format((float) ($s->paid_amount ?? 0), 2),
                    number_format((float) ($s->running_balance ?? 0), 2),
                ]);
            }
            fclose($out);
        }, "loan_soa_{$loan->id}_" . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    })->name('soa-export');
});
