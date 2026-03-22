<?php

declare(strict_types=1);

use App\Http\Controllers\Payroll\PayPeriodController;
use App\Http\Controllers\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\Payroll\PayrollDetailController;
use App\Http\Controllers\Payroll\PayrollRunController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payroll Routes — /api/v1/payroll/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:payroll'])->group(function () {

    // ── Payroll Runs ──────────────────────────────────────────────────────────
    Route::get('runs', [PayrollRunController::class, 'index'])
        ->name('runs.index');

    // Pre-run validation (returns structured list of PR-001 to PR-008 results)
    Route::get('runs/validate', [PayrollRunController::class, 'validatePreRun'])
        ->name('runs.validate');

    // Draft scope preview — no run ID needed; used by wizard before DB commit
    Route::get('runs/draft-scope-preview', [PayrollRunController::class, 'draftScopePreview'])
        ->name('runs.draft-scope-preview');

    Route::post('runs', [PayrollRunController::class, 'store'])
        ->name('runs.store');

    Route::get('runs/{payrollRun}', [PayrollRunController::class, 'show'])
        ->name('runs.show');

    Route::patch('runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])
        ->middleware('throttle:api-action')
        ->name('runs.lock');

    Route::patch('runs/{payrollRun}/approve', [PayrollRunController::class, 'approve'])
        ->middleware(['sod:payroll,approve', 'throttle:api-action'])  // checks payroll.initiate (SOD-005)
        ->name('runs.approve');

    Route::patch('runs/{payrollRun}/submit', [PayrollRunController::class, 'submit'])
        ->middleware('throttle:api-action')
        ->name('runs.submit');

    Route::patch('runs/{payrollRun}/accounting-approve', [PayrollRunController::class, 'accountingApprove'])
        ->middleware(['sod:payroll,acctg_approve', 'throttle:api-action'])  // checks payroll.initiate (SOD-006)
        ->name('runs.accounting-approve');

    Route::patch('runs/{payrollRun}/post', [PayrollRunController::class, 'post'])
        ->middleware('throttle:api-action')
        ->name('runs.post');

    Route::patch('runs/{payrollRun}/reject', [PayrollRunController::class, 'reject'])
        ->middleware('throttle:api-action')
        ->name('runs.reject');

    Route::get('runs/{payrollRun}/exceptions', [PayrollRunController::class, 'exceptions'])
        ->name('runs.exceptions');

    Route::patch('runs/{payrollRun}/cancel', [PayrollRunController::class, 'cancel'])
        ->middleware('throttle:api-action')
        ->name('runs.cancel');

    Route::delete('runs/{payrollRun}', [PayrollRunController::class, 'destroy'])
        ->middleware('throttle:api-action')
        ->name('runs.destroy');

    // ── Workflow v1.0 — Step 2: Scope ─────────────────────────────────────────
    Route::get('runs/{payrollRun}/scope-preview', [PayrollRunController::class, 'scopePreview'])
        ->name('runs.scope-preview');

    Route::patch('runs/{payrollRun}/scope', [PayrollRunController::class, 'confirmScope'])
        ->name('runs.scope.confirm');

    Route::post('runs/{payrollRun}/scope/exclusions', [PayrollRunController::class, 'addExclusion'])
        ->name('runs.scope.exclusions.add');

    Route::delete('runs/{payrollRun}/scope/exclusions/{employeeId}', [PayrollRunController::class, 'removeExclusion'])
        ->name('runs.scope.exclusions.remove');

    // ── Workflow v1.0 — Step 3: Pre-Run Validation ────────────────────────────
    Route::post('runs/{payrollRun}/pre-run-checks', [PayrollRunController::class, 'preRunValidate'])
        ->name('runs.pre-run-checks');

    Route::post('runs/{payrollRun}/acknowledge', [PayrollRunController::class, 'acknowledgePreRun'])
        ->middleware('throttle:api-action')
        ->name('runs.acknowledge');

    // ── Workflow v1.0 — Step 4: Begin Computation ─────────────────────────────
    Route::post('runs/{payrollRun}/compute', [PayrollRunController::class, 'beginComputation'])
        ->middleware('throttle:api-action')
        ->name('runs.compute');

    // ── Workflow v1.0 — Step 4: Computation ──────────────────────────────────
    Route::get('runs/{payrollRun}/progress', [PayrollRunController::class, 'progress'])
        ->name('runs.progress');

    // ── Workflow v1.0 — Step 5: Review ────────────────────────────────────────
    Route::get('runs/{payrollRun}/breakdown', [PayrollRunController::class, 'breakdown'])
        ->name('runs.breakdown');

    Route::get('runs/{payrollRun}/breakdown/{payrollDetail}', [PayrollRunController::class, 'breakdownDetail'])
        ->name('runs.breakdown.detail');

    Route::patch('runs/{payrollRun}/review/flag/{detailId}', [PayrollRunController::class, 'flagEmployee'])
        ->name('runs.review.flag');

    Route::post('runs/{payrollRun}/submit-for-hr', [PayrollRunController::class, 'submitForHrApproval'])
        ->name('runs.submit-for-hr');

    // ── Workflow v1.0 — Step 6: HR Review ─────────────────────────────────────
    Route::post('runs/{payrollRun}/hr-approve', [PayrollRunController::class, 'hrApprove'])
        ->middleware('sod:payroll,hr_approve')  // checks payroll.initiate (SOD-005)
        ->name('runs.hr-approve');

    // ── Workflow v1.0 — Step 7: Accounting Review ─────────────────────────────
    Route::get('runs/{payrollRun}/gl-preview', [PayrollRunController::class, 'glPreview'])
        ->name('runs.gl-preview');

    Route::post('runs/{payrollRun}/acctg-approve', [PayrollRunController::class, 'acctgApprove'])
        ->middleware('sod:payroll,acctg_approve')  // checks payroll.initiate (SOD-006)
        ->name('runs.acctg-approve');

    // ── Workflow v1.0 — Step 7b: VP Final Approval ────────────────────────────
    Route::post('runs/{payrollRun}/vp-approve', [PayrollRunController::class, 'vpApprove'])
        ->middleware(['sod:payroll,vp_approve', 'throttle:api-action'])  // checks payroll.initiate (SOD-008)
        ->name('runs.vp-approve');

    // ── Workflow v1.0 — Step 8: Disbursement ──────────────────────────────────
    Route::post('runs/{payrollRun}/disburse', [PayrollRunController::class, 'disburse'])
        ->name('runs.disburse');

    Route::post('runs/{payrollRun}/publish', [PayrollRunController::class, 'publish'])
        ->name('runs.publish');

    // Approval history (used in Steps 6, 7, and audit views)
    Route::get('runs/{payrollRun}/approvals', [PayrollRunController::class, 'approvals'])
        ->name('runs.approvals');

    // ── Payroll Details (payslips) ────────────────────────────────────────────
    Route::get('runs/{payrollRun}/details', [PayrollDetailController::class, 'index'])
        ->name('runs.details.index');

    Route::get('runs/{payrollRun}/details/{payrollDetail}', [PayrollDetailController::class, 'show'])
        ->name('runs.details.show');

    Route::get('runs/{payrollRun}/details/{payrollDetail}/payslip', [PayrollDetailController::class, 'payslip'])
        ->name('runs.details.payslip');

    // ── Exports ───────────────────────────────────────────────────────────────
    Route::get('runs/{payrollRun}/export/register', [PayrollRunController::class, 'exportRegister'])
        ->name('runs.export.register');

    Route::get('runs/{payrollRun}/export/disbursement', [PayrollRunController::class, 'exportDisbursement'])
        ->name('runs.export.disbursement');

    // Payroll breakdown export — HR Manager only, only when DISBURSED
    Route::get('runs/{payrollRun}/export/breakdown', [PayrollRunController::class, 'exportBreakdown'])
        ->name('runs.export.breakdown')
        ->middleware('can:exportBreakdown,payrollRun');

    // ── Payroll Adjustments ───────────────────────────────────────────────────
    Route::get('runs/{payrollRun}/adjustments', [PayrollAdjustmentController::class, 'index'])
        ->name('runs.adjustments.index');

    Route::post('runs/{payrollRun}/adjustments', [PayrollAdjustmentController::class, 'store'])
        ->name('runs.adjustments.store');

    Route::delete('adjustments/{payrollAdjustment}', [PayrollAdjustmentController::class, 'destroy'])
        ->name('adjustments.destroy');

    // ── Pay Periods ───────────────────────────────────────────────────────────
    Route::get('periods', [PayPeriodController::class, 'index'])
        ->name('periods.index');

    Route::post('periods', [PayPeriodController::class, 'store'])
        ->middleware('permission:payroll.initiate')
        ->name('periods.store');

    Route::get('periods/{payPeriod}', [PayPeriodController::class, 'show'])
        ->name('periods.show');

    Route::patch('periods/{payPeriod}/close', [PayPeriodController::class, 'close'])
        ->middleware('permission:payroll.initiate')
        ->name('periods.close');
});
