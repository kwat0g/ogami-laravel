<?php

declare(strict_types=1);

use App\Http\Controllers\Budget\BudgetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Budget & Cost Centre API Routes  (prefix: /api/v1/budget)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:budget'])->group(function (): void {
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

    // ── Department Budgets (Managed by Accounting) ────────────────────────────
    Route::get('department-budgets', function (\Illuminate\Http\Request $request) {
        $query = \App\Domains\HR\Models\Department::orderBy('code')
            ->select([
                'id',
                'code',
                'name',
                'annual_budget_centavos',
                'fiscal_year_start_month',
                'is_active',
            ]);

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return $query->paginate((int) $request->query('per_page', '50'));
    })
        ->middleware('permission:budget.view')
        ->name('department-budgets.index');

    Route::patch('department-budgets/{department}', function (\Illuminate\Http\Request $request, \App\Domains\HR\Models\Department $department) {
        abort_unless($request->user()->can('budget.manage'), 403, 'Only Accounting Manager can set department budgets.');

        $validated = $request->validate([
            'annual_budget_centavos' => 'required|integer|min:0',
            'fiscal_year_start_month' => 'sometimes|integer|min:1|max:12',
        ]);

        $department->update($validated);

        return response()->json([
            'message' => 'Department budget updated successfully.',
            'department' => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'annual_budget_centavos' => $department->annual_budget_centavos,
                'fiscal_year_start_month' => $department->fiscal_year_start_month,
                'formatted_budget' => '₱'.number_format($department->annual_budget_centavos / 100, 2),
            ],
        ]);
    })
        ->middleware(['permission:budget.manage', 'throttle:api-action'])
        ->name('department-budgets.update');
});
