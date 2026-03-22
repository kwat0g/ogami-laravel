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

Route::middleware(['auth:sanctum', 'module_access:hr'])->group(function () {

    // ── Team Management (department-scoped for managers/supervisors) ────────────
    // Must be defined BEFORE apiResource to avoid being matched as {employee} parameter
    Route::get('employees/team', [EmployeeController::class, 'team'])
        ->middleware('permission:employees.view_team')
        ->name('employees.team');

    // ── Employee (dept_scope restricts queries to the user's department) ────────
    Route::middleware(['dept_scope'])->group(function () {
        Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
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

    // ── HR Reports ───────────────────────────────────────────────────────────

    Route::prefix('reports')->middleware('permission:hr.full_access')->group(function () {

        // Headcount by department
        Route::get('headcount', function (): \Illuminate\Http\JsonResponse {
            $rows = \Illuminate\Support\Facades\DB::table('employees')
                ->join('departments', 'employees.department_id', '=', 'departments.id')
                ->select(
                    'departments.id as department_id',
                    'departments.code as department_code',
                    'departments.name as department_name',
                    \Illuminate\Support\Facades\DB::raw("count(*) as total"),
                    \Illuminate\Support\Facades\DB::raw("sum(case when employees.employment_status = 'active' and employees.is_active then 1 else 0 end) as active"),
                    \Illuminate\Support\Facades\DB::raw("sum(case when employees.employment_status = 'on_leave' then 1 else 0 end) as on_leave"),
                    \Illuminate\Support\Facades\DB::raw("sum(case when employees.employment_status in ('resigned','terminated') then 1 else 0 end) as separated"),
                )
                ->groupBy('departments.id', 'departments.code', 'departments.name')
                ->orderByDesc('total')
                ->get();

            return response()->json(['data' => $rows]);
        })->name('reports.headcount');

        // Turnover (last 12 months)
        Route::get('turnover', function (): \Illuminate\Http\JsonResponse {
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end   = now()->subMonths($i)->endOfMonth();
                $label = now()->subMonths($i)->format('M Y');

                $hires = \Illuminate\Support\Facades\DB::table('employees')
                    ->whereBetween('date_hired', [$start, $end])->count();
                $terms = \Illuminate\Support\Facades\DB::table('employees')
                    ->whereNotNull('separation_date')
                    ->whereBetween('separation_date', [$start, $end])->count();

                $months[] = ['month' => $label, 'hires' => $hires, 'terminations' => $terms, 'net' => $hires - $terms];
            }

            // Overall turnover rate
            $startOfYear = now()->startOfYear();
            $headcountAtStart = \Illuminate\Support\Facades\DB::table('employees')
                ->where('date_hired', '<', $startOfYear)
                ->where(fn ($q) => $q->whereNull('separation_date')->orWhere('separation_date', '>=', $startOfYear))
                ->count();
            $totalSeps = \Illuminate\Support\Facades\DB::table('employees')
                ->whereNotNull('separation_date')
                ->whereYear('separation_date', now()->year)->count();
            $turnoverRate = $headcountAtStart > 0 ? round(($totalSeps / $headcountAtStart) * 100, 1) : 0;

            return response()->json(['data' => $months, 'turnover_rate_ytd' => $turnoverRate]);
        })->name('reports.turnover');

        // Upcoming birthdays
        Route::get('birthdays', function (Request $request): \Illuminate\Http\JsonResponse {
            $days = $request->integer('days', 30);
            $today = now();

            $employees = \Illuminate\Support\Facades\DB::table('employees')
                ->join('departments', 'employees.department_id', '=', 'departments.id')
                ->where('employees.is_active', true)
                ->whereNotNull('employees.birth_date')
                ->select(
                    'employees.id', 'employees.employee_code',
                    \Illuminate\Support\Facades\DB::raw("concat(employees.first_name, ' ', employees.last_name) as full_name"),
                    'employees.birth_date', 'departments.name as department',
                )
                ->get()
                ->map(function ($e) use ($today) {
                    $bd = \Carbon\Carbon::parse($e->birth_date);
                    $next = $bd->copy()->year($today->year);
                    if ($next->lt($today)) $next->addYear();
                    $e->days_until = (int) $today->diffInDays($next, false);
                    $e->age = $today->year - $bd->year - ($next->year > $today->year ? 0 : ($today->lt($bd->copy()->year($today->year)) ? 1 : 0));
                    return $e;
                })
                ->filter(fn ($e) => $e->days_until >= 0 && $e->days_until <= $days)
                ->sortBy('days_until')
                ->values();

            return response()->json(['data' => $employees]);
        })->name('reports.birthdays');
    });
});

