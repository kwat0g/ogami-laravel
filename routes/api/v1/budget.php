<?php

declare(strict_types=1);

use App\Http\Controllers\Budget\BudgetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Budget & Cost Centre API Routes  (prefix: /api/v1/budget)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function (): void {
    // ── Cost Centres ─────────────────────────────────────────────────────────
    Route::get('cost-centers', [BudgetController::class, 'indexCostCenters'])
        ->middleware('permission:budget.view')
        ->name('cost-centers.index');

    Route::post('cost-centers', [BudgetController::class, 'storeCostCenter'])
        ->middleware(['permission:budget.manage', 'throttle:api-action'])
        ->name('cost-centers.store');

    Route::patch('cost-centers/{costCenter}', [BudgetController::class, 'updateCostCenter'])
        ->middleware(['permission:budget.manage', 'throttle:api-action'])
        ->name('cost-centers.update');

    // ── Annual Budget Lines ───────────────────────────────────────────────────
    Route::get('lines', [BudgetController::class, 'indexBudgets'])
        ->middleware('permission:budget.view')
        ->name('lines.index');

    Route::post('lines', [BudgetController::class, 'setBudgetLine'])
        ->middleware(['permission:budget.manage', 'throttle:api-action'])
        ->name('lines.set');

    // ── Utilisation Report ────────────────────────────────────────────────────
    Route::get('utilisation/{costCenter}', [BudgetController::class, 'utilisation'])
        ->middleware('permission:budget.view')
        ->name('utilisation');

    // ── Approval Workflow ────────────────────────────────────────────────────
    Route::patch('lines/{annualBudget}/submit', [BudgetController::class, 'submitBudget'])
        ->middleware(['permission:budget.manage', 'throttle:api-action'])
        ->name('lines.submit');

    Route::patch('lines/{annualBudget}/approve', [BudgetController::class, 'approveBudget'])
        ->middleware(['permission:budget.approve', 'throttle:api-action'])
        ->name('lines.approve');

    Route::patch('lines/{annualBudget}/reject', [BudgetController::class, 'rejectBudget'])
        ->middleware(['permission:budget.approve', 'throttle:api-action'])
        ->name('lines.reject');
});
