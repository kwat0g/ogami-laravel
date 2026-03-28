<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes — /api/v1/dashboard/*
| Role-specific dashboard data endpoints with analytics.
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Role-Based Dashboard (auto-selects KPIs based on user role)
    Route::get('my', [DashboardController::class, 'my']);

    // Manager Dashboard (department-scoped)
    Route::get('manager', [DashboardController::class, 'manager'])
        ->name('dashboard.manager')->can('employees.view_team');

    // Supervisor Dashboard
    Route::get('supervisor', [DashboardController::class, 'supervisor'])
        ->name('dashboard.supervisor')->can('employees.view_team');

    // Alias: /dashboard/head → /dashboard/supervisor (v2 role rename)
    Route::redirect('head', '/api/v1/dashboard/supervisor', 307)->name('dashboard.head');

    // HR Dashboard
    Route::get('hr', [DashboardController::class, 'hr'])
        ->name('dashboard.hr')->can('hr.full_access');

    // Accounting Dashboard
    Route::get('accounting', [DashboardController::class, 'accounting'])
        ->name('dashboard.accounting')->can('journal_entries.view');

    // Admin Dashboard
    Route::get('admin', [DashboardController::class, 'admin'])
        ->name('dashboard.admin');

    // Staff Self-Service Dashboard
    Route::get('staff', [DashboardController::class, 'staff'])
        ->name('dashboard.staff');

    // Executive Dashboard
    Route::get('executive', [DashboardController::class, 'executive'])
        ->name('dashboard.executive');

    // VP Dashboard
    Route::get('vp', [DashboardController::class, 'vp'])
        ->name('dashboard.vp');

    // Officer Dashboard
    Route::get('officer', [DashboardController::class, 'officer'])
        ->name('dashboard.officer');

    // Purchasing Officer Dashboard
    Route::get('purchasing-officer', [DashboardController::class, 'purchasingOfficer'])
        ->name('dashboard.purchasing-officer');

    // Executive Analytics (existing invokable controller)
    Route::get('executive-analytics', \App\Http\Controllers\Dashboard\ExecutiveDashboardController::class)
        ->middleware('permission:reports.financial_statements')
        ->name('dashboard.executive-analytics');

    // Supplementary KPIs (Phase 4)
    Route::get('kpis/supplementary', [DashboardController::class, 'supplementaryKpis']);
});
