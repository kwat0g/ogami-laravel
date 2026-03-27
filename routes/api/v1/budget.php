<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Http\Controllers\Budget\BudgetController;
use Illuminate\Http\Request;
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
    Route::get('department-budgets', function (Request $request) {
        $query = Department::orderBy('code')
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

    Route::patch('department-budgets/{department}', function (Request $request, Department $department) {
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

    // ── Budget Variance Analysis ────────────────────────────────────────────
    Route::get('variance', function (Request $request) {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'cost_center_id' => ['sometimes', 'integer', 'exists:cost_centers,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'account_id' => ['sometimes', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $service = app(\App\Domains\Budget\Services\BudgetVarianceService::class);
        $detail = $service->varianceReport($request->only(['fiscal_year', 'cost_center_id', 'department_id', 'account_id']));

        return response()->json(['data' => $detail]);
    })
        ->middleware('permission:budget.view')
        ->name('variance.detail');

    Route::get('variance/by-cost-center', function (Request $request) {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
        ]);

        $service = app(\App\Domains\Budget\Services\BudgetVarianceService::class);
        $summary = $service->varianceByCostCenter(
            $request->integer('fiscal_year'),
            $request->filled('department_id') ? $request->integer('department_id') : null,
        );

        return response()->json(['data' => $summary]);
    })
        ->middleware('permission:budget.view')
        ->name('variance.by-cost-center');

    Route::get('variance/forecast', function (Request $request) {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2099'],
        ]);

        $service = app(\App\Domains\Budget\Services\BudgetVarianceService::class);
        $forecast = $service->yearEndForecast($request->integer('fiscal_year'));

        return response()->json(['data' => $forecast]);
    })
        ->middleware('permission:budget.view')
        ->name('variance.forecast');
});
