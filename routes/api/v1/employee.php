<?php

declare(strict_types=1);

use App\Http\Controllers\Employee\EmployeeSelfServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Employee Self-Service — Route Group
|--------------------------------------------------------------------------
| Scoped to the authenticated user's own employee record.
| All routes require auth:sanctum.
|
| Prefix  : /api/v1/employee
| Name    : v1.employee.
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Payslip history (own only)
    Route::get('me/payslips', [EmployeeSelfServiceController::class, 'payslips'])
        ->name('me.payslips');

    // Payslip detail with full breakdown (own only)
    Route::get('me/payslips/{payrollDetail}', [EmployeeSelfServiceController::class, 'payslipDetail'])
        ->name('me.payslips.detail');

    // Payslip PDF download (own only)
    Route::get('me/payslips/{payrollDetail}/pdf', [EmployeeSelfServiceController::class, 'downloadPayslip'])
        ->name('me.payslips.pdf');

    // Year-to-date summary
    Route::get('me/ytd', [EmployeeSelfServiceController::class, 'ytdSummary'])
        ->name('me.ytd');

    // Own HR profile (read + limited self-service update)
    Route::get('me/profile', [EmployeeSelfServiceController::class, 'profile'])
        ->name('me.profile');
    Route::patch('me/profile', [EmployeeSelfServiceController::class, 'updateProfile'])
        ->name('me.profile.update');

    // Own leave balances + request history
    Route::get('me/leave', [EmployeeSelfServiceController::class, 'myLeave'])
        ->name('me.leave');

    // Own loan balances + amortisation history
    Route::get('me/loans', [EmployeeSelfServiceController::class, 'myLoans'])
        ->name('me.loans');

    // Own attendance / DTR
    Route::get('me/attendance', [EmployeeSelfServiceController::class, 'myAttendance'])
        ->name('me.attendance');

    Route::get('me/attendance/pdf', [EmployeeSelfServiceController::class, 'downloadDtr'])
        ->name('me.attendance.pdf');
});
