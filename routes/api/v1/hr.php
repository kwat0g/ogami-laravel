<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Http\Controllers\HR\EmployeeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Routes — /api/v1/hr/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Team Management (department-scoped for managers/supervisors) ────────────
    // Must be defined BEFORE apiResource to avoid being matched as {employee} parameter
    Route::get('employees/team', [EmployeeController::class, 'team'])
        ->middleware('permission:employees.view_team')
        ->name('employees.team');

    // ── Employee (dept_scope restricts queries to the user's department) ────────
    Route::middleware(['dept_scope'])->group(function () {
        Route::apiResource('employees', EmployeeController::class);
        // SoD-001 (creator ≠ activator) is enforced at the policy level in EmployeePolicy.
        Route::post('employees/{employee}/transition', [EmployeeController::class, 'transition'])
            ->name('employees.transition');
    });

    // ── Salary grades (read-only for most; HR manager can manage via separate admin routes) ──
    Route::get('salary-grades', fn () => \App\Domains\HR\Models\SalaryGrade::where('is_active', true)
        ->orderBy('level')
        ->get()
        ->map(fn ($g) => [
            'id' => $g->id,
            'code' => $g->code,
            'name' => $g->name,
            'level' => $g->level,
            'employment_type' => $g->employment_type,
            'min_monthly_rate' => $g->min_monthly_rate,
            'max_monthly_rate' => $g->max_monthly_rate,
        ])
    )->name('salary-grades.index');

    // ── Leave types (reference) ───────────────────────────────────────────────
    Route::get('leave-types', fn () => \App\Domains\Leave\Models\LeaveType::where('is_active', true)
        ->orderBy('code')
        ->get([
            'id', 'code', 'name', 'category', 'is_paid', 'max_days_per_year',
            'requires_approval', 'requires_documentation', 'monthly_accrual_days',
            'max_carry_over_days', 'can_be_monetized', 'deducts_absent_on_lwop',
        ])
    )->name('leave-types.index');

    // ── Loan types (reference) ────────────────────────────────────────────────
    Route::get('loan-types', fn () => \App\Domains\Loan\Models\LoanType::where('is_active', true)
        ->orderBy('name')
        ->get()
    )->name('loan-types.index');

    // ── Departments ───────────────────────────────────────────────────────────
    Route::get('departments', function (Request $request) {
        // Reference data — available to any authenticated user
        $query = Department::orderBy('code');
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return $query->paginate((int) $request->query('per_page', '50'));
    })->name('departments.index');

    Route::post('departments', function (Request $request) {
        abort_unless($request->user()->can('employees.manage_structure'), 403);
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:departments,code',
            'name' => 'required|string|max:100',
            'parent_department_id' => 'nullable|exists:departments,id',
            'cost_center_code' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);

        return response()->json(Department::create($validated), 201);
    })->name('departments.store');

    Route::patch('departments/{department}', function (Request $request, Department $department) {
        abort_unless($request->user()->can('employees.manage_structure'), 403);
        $validated = $request->validate([
            'code' => 'sometimes|string|max:20|unique:departments,code,'.$department->id,
            'name' => 'sometimes|string|max:100',
            'parent_department_id' => 'nullable|exists:departments,id',
            'cost_center_code' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);
        $department->update($validated);

        return response()->json($department->fresh());
    })->name('departments.update');

    Route::delete('departments/{department}', function (Request $request, Department $department) {
        abort_unless($request->user()->can('employees.manage_structure'), 403);
        $department->delete();

        return response()->noContent();
    })->name('departments.destroy');

    // ── Positions ─────────────────────────────────────────────────────────────
    Route::get('positions', function (Request $request) {
        // Reference data — available to any authenticated user
        $query = Position::with('department')->orderBy('title');
        if ($request->query('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return $query->paginate((int) $request->query('per_page', '50'));
    })->name('positions.index');

    Route::post('positions', function (Request $request) {
        abort_unless($request->user()->can('employees.manage_structure'), 403);
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:positions,code',
            'title' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'pay_grade' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        return response()->json(Position::create($validated), 201);
    })->name('positions.store');

    Route::patch('positions/{position}', function (Request $request, Position $position) {
        abort_unless($request->user()->can('employees.manage_structure'), 403);
        $validated = $request->validate([
            'code' => 'sometimes|string|max:30|unique:positions,code,'.$position->id,
            'title' => 'sometimes|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'pay_grade' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $position->update($validated);

        return response()->json($position->fresh()->load('department'));
    })->name('positions.update');

    Route::delete('positions/{position}', function (Request $request, Position $position) {
        abort_unless($request->user()->can('employees.manage_structure'), 403);
        $position->delete();

        return response()->noContent();
    })->name('positions.destroy');
});
