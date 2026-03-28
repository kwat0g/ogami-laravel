<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes — /api/v1/dashboard/*
| Role-specific dashboard data endpoints with analytics.
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // Role-Based Dashboard (auto-selects KPIs based on user role)
    // GET /api/v1/dashboard/my
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('my', function (Request $request) {
        $service = app(\App\Domains\Dashboard\Services\RoleBasedDashboardService::class);

        return response()->json(['data' => $service->forUser($request->user())]);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Manager Dashboard
    // GET /api/v1/dashboard/manager
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('manager', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.mgr.'.$user->id.'.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // Get user's primary department
        $deptId = $request->integer('department_id')
            ?: DB::table('user_department_access')
                ->where('user_id', $user->id)
                ->where('is_primary', true)
                ->value('department_id');

        if (! $deptId) {
            return response()->json([
                'department' => ['id' => 0, 'name' => 'No Department', 'code' => 'N/A'],
                'headcount' => ['total' => 0, 'active' => 0, 'on_leave' => 0],
                'pending_approvals' => ['leaves' => 0, 'overtime' => 0, 'loans' => 0, 'total' => 0],
                'recent_requests' => ['leaves' => [], 'overtime' => [], 'loans' => []],
                'attendance_today' => ['present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0],
                'analytics' => [
                    'attendance_trend' => [],
                    'leave_by_type' => [],
                    'overtime_trend' => [],
                    'tenure_distribution' => [],
                    'comparison' => [
                        'dept_attendance_rate' => 0,
                        'company_avg_attendance' => 0,
                        'vs_company_avg' => 0,
                    ],
                ],
            ]);
        }

        $department = DB::table('departments')->where('id', $deptId)->first();

        // Headcount stats
        $headcount = [
            'total' => DB::table('employees')->where('department_id', $deptId)->count(),
            'active' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('employment_status', 'active')
                ->where('is_active', true)
                ->count(),
            'on_leave' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('employment_status', 'on_leave')
                ->count(),
        ];

        // Get employee IDs in department
        $empIds = DB::table('employees')
            ->where('department_id', $deptId)
            ->pluck('id');

        // Pending approvals
        $pendingLeaves = DB::table('leave_requests')
            ->whereIn('employee_id', $empIds)
            ->where('status', 'PENDING')
            ->count();
        $pendingOvertime = DB::table('overtime_requests')
            ->whereIn('employee_id', $empIds)
            ->where('status', 'PENDING')
            ->count();
        $pendingLoans = DB::table('loans')
            ->whereIn('employee_id', $empIds)
            ->where('status', 'PENDING')
            ->count();

        // Recent requests
        $recentLeaves = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->whereIn('leave_requests.employee_id', $empIds)
            ->orderByDesc('leave_requests.id')
            ->limit(5)
            ->get([
                'leave_requests.id',
                'leave_requests.date_from',
                'leave_requests.date_to',
                'leave_requests.status',
                'leave_requests.total_days',
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as employee_name"),
                'leave_types.name as leave_type_name',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'date_from' => $r->date_from,
                'date_to' => $r->date_to,
                'status' => $r->status,
                'total_days' => $r->total_days,
                'employee' => ['full_name' => $r->employee_name],
                'leave_type' => ['name' => $r->leave_type_name],
            ]);

        $recentOvertime = DB::table('overtime_requests')
            ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
            ->whereIn('overtime_requests.employee_id', $empIds)
            ->orderByDesc('overtime_requests.id')
            ->limit(5)
            ->get([
                'overtime_requests.id',
                'overtime_requests.work_date',
                'overtime_requests.status',
                'overtime_requests.requested_minutes',
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as employee_name"),
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'work_date' => $r->work_date,
                'status' => $r->status,
                'requested_hours' => $r->requested_minutes ? round((float) $r->requested_minutes / 60, 1) : 0,
                'employee' => ['full_name' => $r->employee_name],
            ]);

        // Today's attendance
        $today = now()->format('Y-m-d');
        $attendanceToday = [
            'present' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->where('work_date', $today)
                ->where('is_present', true)
                ->count(),
            'absent' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->where('work_date', $today)
                ->where('is_absent', true)
                ->count(),
            'late' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->where('work_date', $today)
                ->where('late_minutes', '>', 0)
                ->count(),
            'on_leave' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('employment_status', 'on_leave')
                ->count(),
        ];

        // ─── ANALYTICS ───

        // 1. Attendance Rate Trend (Last 6 months)
        $attendanceTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth()->format('Y-m-d');
            $monthEnd = now()->subMonths($i)->endOfMonth()->format('Y-m-d');
            $monthLabel = now()->subMonths($i)->format('M Y');

            $totalRecords = DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$monthStart, $monthEnd])
                ->count();

            $presentRecords = DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$monthStart, $monthEnd])
                ->where('is_present', true)
                ->count();

            $attendanceTrend[] = [
                'month' => $monthLabel,
                'rate' => $totalRecords > 0 ? round(($presentRecords / $totalRecords) * 100, 1) : 0,
                'total' => $totalRecords,
            ];
        }

        // 2. Leave by Type (Current year)
        $leaveByType = DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->whereIn('leave_requests.employee_id', $empIds)
            ->whereYear('leave_requests.created_at', now()->year)
            ->where('leave_requests.status', 'APPROVED')
            ->select('leave_types.name', DB::raw('sum(leave_requests.total_days) as total_days'))
            ->groupBy('leave_types.name')
            ->orderByDesc('total_days')
            ->limit(5)
            ->get();

        // 3. Overtime Hours Trend (Last 6 months)
        $overtimeTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth()->format('Y-m-d');
            $monthEnd = now()->subMonths($i)->endOfMonth()->format('Y-m-d');
            $monthLabel = now()->subMonths($i)->format('M Y');

            $totalMinutes = (int) DB::table('overtime_requests')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$monthStart, $monthEnd])
                ->where('status', 'APPROVED')
                ->sum('requested_minutes');

            $overtimeTrend[] = [
                'month' => $monthLabel,
                'hours' => round((float) $totalMinutes / 60, 1),
            ];
        }

        // 4. Employee Tenure Distribution
        $tenureDistribution = [
            'less_than_1_year' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('is_active', true)
                ->where('date_hired', '>=', now()->subYear())
                ->count(),
            '1_to_3_years' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('is_active', true)
                ->whereBetween('date_hired', [now()->subYears(3), now()->subYear()])
                ->count(),
            '3_to_5_years' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('is_active', true)
                ->whereBetween('date_hired', [now()->subYears(5), now()->subYears(3)])
                ->count(),
            'more_than_5_years' => DB::table('employees')
                ->where('department_id', $deptId)
                ->where('is_active', true)
                ->where('date_hired', '<', now()->subYears(5))
                ->count(),
        ];

        // 5. Department vs Company Average (Comparison)
        $deptAttendanceRate = $attendanceTrend[count($attendanceTrend) - 1]['rate'] ?? 0;
        $companyAvgAttendance = (float) (DB::table('attendance_logs')
            ->whereBetween('work_date', [now()->subMonth()->startOfMonth(), now()])
            ->selectRaw('(sum(case when is_present then 1 else 0 end) * 100.0 / count(*)) as rate')
            ->value('rate') ?? 0);

        return tap(response()->json([
            'department' => [
                'id' => $department?->id ?? $deptId,
                'name' => $department?->name ?? 'Unknown',
                'code' => $department?->code ?? 'N/A',
            ],
            'headcount' => $headcount,
            'pending_approvals' => [
                'leaves' => $pendingLeaves,
                'overtime' => $pendingOvertime,
                'loans' => $pendingLoans,
                'total' => $pendingLeaves + $pendingOvertime + $pendingLoans,
            ],
            'recent_requests' => [
                'leaves' => $recentLeaves,
                'overtime' => $recentOvertime,
                'loans' => [],
            ],
            'attendance_today' => $attendanceToday,
            'analytics' => [
                'attendance_trend' => $attendanceTrend,
                'leave_by_type' => $leaveByType,
                'overtime_trend' => $overtimeTrend,
                'tenure_distribution' => $tenureDistribution,
                'comparison' => [
                    'dept_attendance_rate' => round($deptAttendanceRate, 1),
                    'company_avg_attendance' => round($companyAvgAttendance, 1),
                    'vs_company_avg' => round($deptAttendanceRate - $companyAvgAttendance, 1),
                ],
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.manager')->can('leaves.view_team');

    // ─────────────────────────────────────────────────────────────────────────
    // Supervisor Dashboard
    // GET /api/v1/dashboard/supervisor
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('supervisor', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.sup.'.$user->id.'.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // Get department IDs the supervisor has access to
        $deptIds = DB::table('user_department_access')
            ->where('user_id', $user->id)
            ->pluck('department_id')
            ->toArray();

        if (empty($deptIds)) {
            return response()->json([
                'team' => ['member_count' => 0, 'present_today' => 0, 'on_leave' => 0],
                'pending_approvals' => ['leaves' => 0, 'overtime' => 0, 'loans' => 0, 'total' => 0],
                'team_attendance' => ['this_week' => ['present' => 0, 'absent' => 0, 'late' => 0]],
                'recent_requests' => ['leaves' => [], 'overtime' => [], 'loans' => []],
                'analytics' => [
                    'weekly_attendance_rate' => [],
                    'overtime_by_employee' => [],
                    'leave_calendar' => [],
                ],
            ]);
        }

        // Get employee IDs in supervised departments
        $empIds = DB::table('employees')
            ->whereIn('department_id', $deptIds)
            ->pluck('id');

        // Team stats
        $team = [
            'member_count' => $empIds->count(),
            'present_today' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->where('work_date', now()->format('Y-m-d'))
                ->where('is_present', true)
                ->count(),
            'on_leave' => DB::table('employees')
                ->whereIn('department_id', $deptIds)
                ->where('employment_status', 'on_leave')
                ->count(),
        ];

        // Pending approvals
        $pendingLeaves = DB::table('leave_requests')
            ->whereIn('employee_id', $empIds)
            ->where('status', 'PENDING')
            ->count();
        $pendingOvertime = DB::table('overtime_requests')
            ->whereIn('employee_id', $empIds)
            ->where('status', 'PENDING')
            ->count();
        $pendingLoans = DB::table('loans')
            ->whereIn('employee_id', $empIds)
            ->where('status', 'PENDING')
            ->count();

        // Team attendance this week
        $weekStart = now()->startOfWeek()->format('Y-m-d');
        $weekEnd = now()->endOfWeek()->format('Y-m-d');
        $teamAttendanceThisWeek = [
            'present' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->where('is_present', true)
                ->count(),
            'absent' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->where('is_absent', true)
                ->count(),
            'late' => DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$weekStart, $weekEnd])
                ->where('late_minutes', '>', 0)
                ->count(),
        ];

        // Recent requests
        $recentLeaves = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->whereIn('leave_requests.employee_id', $empIds)
            ->orderByDesc('leave_requests.id')
            ->limit(5)
            ->get([
                'leave_requests.id',
                'leave_requests.date_from',
                'leave_requests.date_to',
                'leave_requests.status',
                'leave_requests.total_days',
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as employee_name"),
                'leave_types.name as leave_type_name',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'date_from' => $r->date_from,
                'date_to' => $r->date_to,
                'status' => $r->status,
                'total_days' => $r->total_days,
                'employee' => ['full_name' => $r->employee_name],
                'leave_type' => ['name' => $r->leave_type_name],
            ]);

        $recentOvertime = DB::table('overtime_requests')
            ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
            ->whereIn('overtime_requests.employee_id', $empIds)
            ->orderByDesc('overtime_requests.id')
            ->limit(5)
            ->get([
                'overtime_requests.id',
                'overtime_requests.work_date',
                'overtime_requests.status',
                'overtime_requests.requested_minutes',
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as employee_name"),
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'work_date' => $r->work_date,
                'status' => $r->status,
                'requested_hours' => $r->requested_minutes ? round((float) $r->requested_minutes / 60, 1) : 0,
                'employee' => ['full_name' => $r->employee_name],
            ]);

        // ─── ANALYTICS ───

        // 1. Weekly Attendance Rate (Last 4 weeks)
        $weeklyAttendanceRate = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStartDate = now()->subWeeks($i)->startOfWeek()->format('Y-m-d');
            $weekEndDate = now()->subWeeks($i)->endOfWeek()->format('Y-m-d');
            $weekLabel = now()->subWeeks($i)->format('M d');

            $total = DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$weekStartDate, $weekEndDate])
                ->count();

            $present = DB::table('attendance_logs')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('work_date', [$weekStartDate, $weekEndDate])
                ->where('is_present', true)
                ->count();

            $weeklyAttendanceRate[] = [
                'week' => $weekLabel,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        }

        // 2. Overtime by Employee (Top 5 this month)
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $overtimeByEmployee = DB::table('overtime_requests')
            ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
            ->whereIn('overtime_requests.employee_id', $empIds)
            ->where('overtime_requests.work_date', '>=', $monthStart)
            ->where('overtime_requests.status', 'APPROVED')
            ->select(
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as employee_name"),
                DB::raw('sum(overtime_requests.requested_minutes) / 60 as total_hours')
            )
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('total_hours')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'employee' => $r->employee_name,
                'hours' => round((float) $r->total_hours, 1),
            ]);

        // 3. Leave Calendar (Upcoming 30 days)
        $leaveCalendar = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->whereIn('leave_requests.employee_id', $empIds)
            ->where('leave_requests.status', 'APPROVED')
            ->where('leave_requests.date_from', '>=', now()->format('Y-m-d'))
            ->where('leave_requests.date_from', '<=', now()->addDays(30)->format('Y-m-d'))
            ->orderBy('leave_requests.date_from')
            ->limit(10)
            ->get([
                'leave_requests.date_from',
                'leave_requests.date_to',
                'leave_requests.total_days',
                DB::raw("concat(employees.first_name, ' ', employees.last_name) as employee_name"),
                'leave_types.name as leave_type',
            ])
            ->map(fn ($r) => [
                'date_from' => $r->date_from,
                'date_to' => $r->date_to,
                'days' => $r->total_days,
                'employee' => $r->employee_name,
                'type' => $r->leave_type,
            ]);

        return tap(response()->json([
            'team' => $team,
            'pending_approvals' => [
                'leaves' => $pendingLeaves,
                'overtime' => $pendingOvertime,
                'loans' => $pendingLoans,
                'total' => $pendingLeaves + $pendingOvertime + $pendingLoans,
            ],
            'team_attendance' => ['this_week' => $teamAttendanceThisWeek],
            'recent_requests' => [
                'leaves' => $recentLeaves,
                'overtime' => $recentOvertime,
                'loans' => [],
            ],
            'analytics' => [
                'weekly_attendance_rate' => $weeklyAttendanceRate,
                'overtime_by_employee' => $overtimeByEmployee,
                'leave_calendar' => $leaveCalendar,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.supervisor')->can('employees.view_team');

    // Alias: /dashboard/head → /dashboard/supervisor (v2 role rename: supervisor → head)
    Route::redirect('head', '/api/v1/dashboard/supervisor', 307)->name('dashboard.head');

    // ─────────────────────────────────────────────────────────────────────────
    // HR Dashboard
    // GET /api/v1/dashboard/hr
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('hr', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.hr.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // Company-wide stats
        $companyWide = [
            'total_employees' => DB::table('employees')->where('is_active', true)->count(),
            'total_departments' => DB::table('departments')->where('is_active', true)->count(),
            'new_hires_this_month' => DB::table('employees')
                ->where('is_active', true)
                ->whereMonth('date_hired', now()->month)
                ->whereYear('date_hired', now()->year)
                ->count(),
        ];

        // Pending approvals
        $pendingLeaves = DB::table('leave_requests')->where('status', 'SUPERVISOR_APPROVED')->count();
        $pendingOvertime = DB::table('overtime_requests')->where('status', 'PENDING')->count();
        $pendingLoans = DB::table('loans')->where('status', 'SUPERVISOR_APPROVED')->count();

        // By department
        $byDepartment = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.is_active', true)
            ->where('employees.employment_status', 'active')
            ->select('departments.name as department', DB::raw('count(*) as count'))
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // Attendance summary (current month)
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');
        $attendanceSummary = DB::table('attendance_logs')
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->select(
                DB::raw('sum(case when is_present then 1 else 0 end) as present'),
                DB::raw('sum(case when is_absent then 1 else 0 end) as absent'),
                DB::raw('sum(case when late_minutes > 0 then 1 else 0 end) as late'),
                DB::raw('count(*) as total')
            )
            ->first();

        // Active payroll run (v1.0 statuses are uppercase)
        $activePayroll = DB::table('payroll_runs')
            ->whereIn('status', ['DRAFT', 'SCOPE_SET', 'PRE_RUN_CHECKED', 'PROCESSING', 'COMPUTED', 'REVIEW', 'SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED'])
            ->orderByDesc('created_at')
            ->first();

        // Transform to match frontend expectations
        if ($activePayroll) {
            $activePayroll->pay_period = ['name' => $activePayroll->pay_period_label];
            // Note: ulid column is already present from DB::table() — do not overwrite it
        }

        // ─── ANALYTICS ───

        // 1. Headcount Trend (Last 12 months)
        $headcountTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $endOfMonth = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $count = DB::table('employees')
                ->where('is_active', true)
                ->where(function ($q) use ($endOfMonth) {
                    $q->whereNull('separation_date')
                        ->orWhere('separation_date', '>', $endOfMonth);
                })
                ->where('date_hired', '<=', $endOfMonth)
                ->count();

            $headcountTrend[] = [
                'month' => $monthLabel,
                'count' => $count,
            ];
        }

        // 2. Turnover Rate by Department (This year)
        $startOfYear = now()->startOfYear();
        $turnoverByDept = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereNotNull('employees.separation_date')
            ->whereYear('employees.separation_date', now()->year)
            ->select('departments.name', DB::raw('count(*) as count'))
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // 3. Average Tenure by Department
        $avgTenureByDept = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.is_active', true)
            ->whereNotNull('employees.date_hired')
            ->select(
                'departments.name',
                DB::raw('round(avg(extract(year from age(now(), employees.date_hired))), 1) as avg_years')
            )
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('avg_years')
            ->limit(5)
            ->get();

        // 4. New Hires vs Terminations (Last 6 months)
        $hiresVsTerminations = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStartDate = now()->subMonths($i)->startOfMonth();
            $monthEndDate = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $hires = DB::table('employees')
                ->whereBetween('date_hired', [$monthStartDate, $monthEndDate])
                ->count();

            $terminations = DB::table('employees')
                ->whereNotNull('separation_date')
                ->whereBetween('separation_date', [$monthStartDate, $monthEndDate])
                ->count();

            $hiresVsTerminations[] = [
                'month' => $monthLabel,
                'hires' => $hires,
                'terminations' => $terminations,
                'net_change' => $hires - $terminations,
            ];
        }

        // 5. Leave Utilization Rate (Current year)
        $leaveUtilization = DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->whereYear('leave_requests.created_at', now()->year)
            ->where('leave_requests.status', 'APPROVED')
            ->select('leave_types.name', DB::raw('sum(leave_requests.total_days) as total_days'))
            ->groupBy('leave_types.name')
            ->orderByDesc('total_days')
            ->limit(5)
            ->get();

        // 6. Payroll Cost Trend (Last 6 months)
        $payrollTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStartDate = now()->subMonths($i)->startOfMonth();
            $monthEndDate = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $totalPayroll = (int) DB::table('payroll_runs')
                ->where('status', 'COMPLETED')
                ->whereBetween('pay_date', [$monthStartDate, $monthEndDate])
                ->sum('net_pay_total_centavos');

            $payrollTrend[] = [
                'month' => $monthLabel,
                'amount' => $totalPayroll,
                'amount_php' => round((float) $totalPayroll / 100, 2),
            ];
        }

        // 7. Overall Turnover Rate
        $totalEmployeesAtStart = DB::table('employees')
            ->where('date_hired', '<', $startOfYear)
            ->where(function ($q) use ($startOfYear) {
                $q->whereNull('separation_date')
                    ->orWhere('separation_date', '>=', $startOfYear);
            })
            ->count();

        $totalSeparations = DB::table('employees')
            ->whereNotNull('separation_date')
            ->whereYear('separation_date', now()->year)
            ->count();

        $overallTurnoverRate = $totalEmployeesAtStart > 0
            ? round(($totalSeparations / $totalEmployeesAtStart) * 100, 1)
            : 0;

        return tap(response()->json([
            'company_wide' => $companyWide,
            'pending_approvals' => [
                'leaves' => $pendingLeaves,
                'overtime' => $pendingOvertime,
                'loans' => $pendingLoans,
                'total' => $pendingLeaves + $pendingOvertime + $pendingLoans,
            ],
            'by_department' => $byDepartment,
            'attendance_summary' => $attendanceSummary ? [
                'present' => $attendanceSummary->present ?? 0,
                'absent' => $attendanceSummary->absent ?? 0,
                'late' => $attendanceSummary->late ?? 0,
                'total' => $attendanceSummary->total ?? 0,
            ] : null,
            'active_payroll' => $activePayroll,
            'analytics' => [
                'headcount_trend' => $headcountTrend,
                'turnover_by_department' => $turnoverByDept,
                'avg_tenure_by_dept' => $avgTenureByDept,
                'hires_vs_terminations' => $hiresVsTerminations,
                'leave_utilization' => $leaveUtilization,
                'payroll_trend' => $payrollTrend,
                'overall_turnover_rate' => $overallTurnoverRate,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.hr')->can('employees.view_full_record');

    // ─────────────────────────────────────────────────────────────────────────
    // Accounting Dashboard
    // GET /api/v1/dashboard/accounting
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('accounting', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.acctg.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // Pending approvals for accounting
        $pendingLoans = DB::table('loans')->where('status', 'APPROVED')->count();
        $pendingJEs = DB::table('journal_entries')->where('status', 'SUBMITTED')->count();
        $pendingInvoices = DB::table('vendor_invoices')->where('status', 'PENDING_APPROVAL')->count();
        $pendingPayroll = DB::table('payroll_runs')->whereIn('status', ['SUBMITTED', 'HR_APPROVED'])->count();

        // Financial summary
        $pendingVendorInvoices = DB::table('vendor_invoices')
            ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED', 'PARTIALLY_PAID'])
            ->count();
        $pendingCustomerInvoices = DB::table('customer_invoices')
            ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
            ->count();
        $unreconciledBanks = DB::table('bank_accounts')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('bank_reconciliations')
                    ->whereColumn('bank_reconciliations.bank_account_id', 'bank_accounts.id')
                    ->whereIn('bank_reconciliations.status', ['draft', 'in_progress']);
            })
            ->count();

        // Active payroll for review (v1.0 statuses are uppercase)
        $activePayroll = DB::table('payroll_runs')
            ->whereIn('status', ['SUBMITTED', 'HR_APPROVED', 'RETURNED', 'REVIEW', 'COMPUTED'])
            ->orderByDesc('updated_at')
            ->first();

        // Transform to match frontend expectations
        if ($activePayroll) {
            $activePayroll->pay_period = ['name' => $activePayroll->pay_period_label];
            // Note: ulid column is already present from DB::table() — do not overwrite it
        }

        // Current fiscal period
        $currentPeriod = DB::table('fiscal_periods')
            ->where('status', 'open')
            ->orderBy('date_from')
            ->first(['id', 'name', 'date_from', 'date_to']);

        // ─── ANALYTICS ───

        // 1. AP Aging Summary
        $today = now()->format('Y-m-d');
        $apAging = [
            'current' => (float) DB::table('vendor_invoices')
                ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED'])
                ->where('due_date', '>=', $today)
                ->sum('net_amount'),
            '1_30_days' => (float) DB::table('vendor_invoices')
                ->whereIn('status', ['APPROVED'])
                ->where('due_date', '<', $today)
                ->where('due_date', '>=', now()->subDays(30)->format('Y-m-d'))
                ->sum('net_amount'),
            '31_60_days' => (float) DB::table('vendor_invoices')
                ->whereIn('status', ['APPROVED'])
                ->where('due_date', '<', now()->subDays(30)->format('Y-m-d'))
                ->where('due_date', '>=', now()->subDays(60)->format('Y-m-d'))
                ->sum('net_amount'),
            'over_60_days' => (float) DB::table('vendor_invoices')
                ->whereIn('status', ['APPROVED'])
                ->where('due_date', '<', now()->subDays(60)->format('Y-m-d'))
                ->sum('net_amount'),
        ];

        // 2. AR Aging Summary
        $arAging = [
            'current' => (float) DB::table('customer_invoices')
                ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
                ->where('due_date', '>=', $today)
                ->sum('total_amount'),
            '1_30_days' => (float) DB::table('customer_invoices')
                ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
                ->where('due_date', '<', $today)
                ->where('due_date', '>=', now()->subDays(30)->format('Y-m-d'))
                ->sum('total_amount'),
            '31_60_days' => (float) DB::table('customer_invoices')
                ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
                ->where('due_date', '<', now()->subDays(30)->format('Y-m-d'))
                ->where('due_date', '>=', now()->subDays(60)->format('Y-m-d'))
                ->sum('total_amount'),
            'over_60_days' => (float) DB::table('customer_invoices')
                ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
                ->where('due_date', '<', now()->subDays(60)->format('Y-m-d'))
                ->sum('total_amount'),
        ];

        // 3. Monthly Expenses Breakdown (Last 6 months - from JE lines)
        $expensesByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStartDate = now()->subMonths($i)->startOfMonth();
            $monthEndDate = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $totalExpenses = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.date', [$monthStartDate, $monthEndDate])
                ->where('chart_of_accounts.account_type', 'expense')
                ->sum('journal_entry_lines.debit');

            $expensesByMonth[] = [
                'month' => $monthLabel,
                'amount' => (float) $totalExpenses,
            ];
        }

        // 4. Top Expense Categories (This month)
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $topExpenses = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.date', [$monthStart, $monthEnd])
            ->where('chart_of_accounts.account_type', 'expense')
            ->select(
                'chart_of_accounts.name',
                DB::raw('sum(journal_entry_lines.debit) as total')
            )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 5. Cash Position Summary - calculate from journal entries
        $cashAccountIds = DB::table('chart_of_accounts')
            ->where('account_type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'ilike', '%cash%')
                    ->orWhere('name', 'ilike', '%bank%');
            })
            ->where('is_active', true)
            ->pluck('id');

        $cashAccountCount = $cashAccountIds->count();

        $totalCashBalance = 0;
        if ($cashAccountCount > 0) {
            $totalCashBalance = (float) DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereIn('journal_entry_lines.account_id', $cashAccountIds)
                ->where('journal_entries.status', 'posted')
                ->selectRaw('SUM(CASE WHEN journal_entry_lines.debit > 0 THEN journal_entry_lines.debit ELSE -journal_entry_lines.credit END) as balance')
                ->value('balance') ?? 0;
        }

        $cashPosition = (object) [
            'account_count' => $cashAccountCount,
            'total_balance' => $totalCashBalance,
        ];

        // 6. Outstanding Liabilities Trend (Last 6 months)
        // Fetch once outside the loop — the result is the same on every iteration
        $liabilityAccountIds = DB::table('chart_of_accounts')
            ->where('account_type', 'liability')
            ->where('is_active', true)
            ->pluck('id');

        $liabilitiesTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $endOfMonth = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $totalLiabilities = 0;
            if ($liabilityAccountIds->count() > 0) {
                $totalLiabilities = (float) DB::table('journal_entry_lines')
                    ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->whereIn('journal_entry_lines.account_id', $liabilityAccountIds)
                    ->where('journal_entries.status', 'posted')
                    ->where('journal_entries.date', '<=', $endOfMonth)
                    ->selectRaw('SUM(CASE WHEN journal_entry_lines.credit > 0 THEN journal_entry_lines.credit ELSE -journal_entry_lines.debit END) as balance')
                    ->value('balance') ?? 0;
            }

            $liabilitiesTrend[] = [
                'month' => $monthLabel,
                'amount' => (float) $totalLiabilities,
            ];
        }

        // 7. Revenue vs Expense Comparison (Last 6 months)
        $revenueVsExpense = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStartDate = now()->subMonths($i)->startOfMonth();
            $monthEndDate = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $revenue = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.date', [$monthStartDate, $monthEndDate])
                ->where('chart_of_accounts.account_type', 'revenue')
                ->sum('journal_entry_lines.credit');

            $expenses = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.date', [$monthStartDate, $monthEndDate])
                ->where('chart_of_accounts.account_type', 'expense')
                ->sum('journal_entry_lines.debit');

            $revenueVsExpense[] = [
                'month' => $monthLabel,
                'revenue' => (float) $revenue,
                'expenses' => (float) $expenses,
                'net' => (float) ($revenue - $expenses),
            ];
        }

        return tap(response()->json([
            'pending_approvals' => [
                'loans_for_accounting' => $pendingLoans,
                'journal_entries' => $pendingJEs,
                'vendor_invoices' => $pendingInvoices,
                'payroll_for_review' => $pendingPayroll,
                'total' => $pendingLoans + $pendingJEs + $pendingInvoices + $pendingPayroll,
            ],
            'financial_summary' => [
                'pending_vendor_invoices' => $pendingVendorInvoices,
                'pending_customer_invoices' => $pendingCustomerInvoices,
                'unreconciled_bank_accounts' => $unreconciledBanks,
            ],
            'active_payroll' => $activePayroll,
            'current_fiscal_period' => $currentPeriod,
            'analytics' => [
                'ap_aging' => $apAging,
                'ar_aging' => $arAging,
                'expenses_by_month' => $expensesByMonth,
                'top_expense_categories' => $topExpenses,
                'cash_position' => [
                    'account_count' => $cashPosition?->account_count ?? 0,
                    'total_balance' => (float) ($cashPosition?->total_balance ?? 0),
                ],
                'liabilities_trend' => $liabilitiesTrend,
                'revenue_vs_expense' => $revenueVsExpense,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.accounting')->can('journal_entries.view');

    // ─────────────────────────────────────────────────────────────────────────
    // Admin Dashboard
    // GET /api/v1/dashboard/admin
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('admin', function (Request $request) {
        $user = $request->user();

        abort_unless($user->can('system.manage_users'), 403, 'Insufficient permissions.');
        $_ttl = min((int) env('DASHBOARD_CACHE_TTL', 120), 30);
        $_cacheKey = 'dash.admin.'.now()->format('Y-m-d-H-i');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        $today = now()->format('Y-m-d');

        // System health
        $systemHealth = [
            'active_users' => count(Redis::keys('user_activity:*')),
            'total_users' => DB::table('users')->count(),
            'locked_accounts' => DB::table('users')
                ->whereNotNull('locked_until')
                ->where('locked_until', '>', now())
                ->count(),
            'failed_logins_today' => DB::table('audits')
                ->where('event', 'failed_login')
                ->whereDate('created_at', $today)
                ->count(),
        ];

        // Recent activity (today)
        $recentActivity = [
            'logins_today' => DB::table('users')
                ->whereDate('last_login_at', $today)
                ->count(),
            'password_changes' => DB::table('users')
                ->whereDate('password_changed_at', $today)
                ->count(),
            'new_users' => DB::table('users')
                ->whereDate('created_at', $today)
                ->count(),
        ];

        // System status
        $systemStatus = [
            'last_backup' => null, // Placeholder - would need backups table
            'horizon_status' => 'running', // Simplified
            'queue_size' => DB::table('jobs')->count(),
        ];

        // ─── ANALYTICS ───

        // 1. User Activity by Role
        $userActivityByRole = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->where('users.last_login_at', '>=', now()->subDays(30))
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get();

        // 2. Failed Login Attempts Trend (Last 7 days - placeholder)
        $loginTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $loginTrend[] = [
                'date' => now()->subDays($i)->format('M d'),
                'successful' => DB::table('users')->whereDate('last_login_at', $date)->count(),
                'failed' => 0, // Placeholder
            ];
        }

        // 3. User Growth (Last 6 months)
        $userGrowth = [];
        for ($i = 5; $i >= 0; $i--) {
            $endOfMonth = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $count = DB::table('users')
                ->where('created_at', '<=', $endOfMonth)
                ->count();

            $userGrowth[] = [
                'month' => $monthLabel,
                'total_users' => $count,
            ];
        }

        // 4. Role Distribution
        $roleDistribution = DB::table('roles')
            ->leftJoin('model_has_roles', function ($join) {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', 'App\Models\User');
            })
            ->select('roles.name', DB::raw('count(model_has_roles.model_id) as user_count'))
            ->groupBy('roles.id', 'roles.name')
            ->orderByDesc('user_count')
            ->get();

        return tap(response()->json([
            'system_health' => $systemHealth,
            'recent_activity' => $recentActivity,
            'system_status' => $systemStatus,
            'analytics' => [
                'user_activity_by_role' => $userActivityByRole,
                'login_trend' => $loginTrend,
                'user_growth' => $userGrowth,
                'role_distribution' => $roleDistribution,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.admin');

    // ─────────────────────────────────────────────────────────────────────────
    // Staff Dashboard (Employee Self-Service)
    // GET /api/v1/dashboard/staff
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('staff', function (Request $request) {
        $user = $request->user();
        $employeeId = $user->employee_id;

        if (! $employeeId) {
            return response()->json([
                'attendance' => ['this_month' => ['present' => 0, 'absent' => 0, 'late' => 0, 'ot_hours' => 0]],
                'leave' => ['balance_days' => 0, 'pending_requests' => 0, 'approved_upcoming' => 0],
                'loans' => ['active_loans' => 0, 'total_outstanding' => 0, 'pending_approvals' => 0],
                'payroll' => ['last_payslip_date' => null, 'ytd_gross' => 0, 'ytd_net' => 0],
                'recent_requests' => ['leaves' => [], 'overtime' => [], 'loans' => []],
                'analytics' => [
                    'attendance_rate' => [],
                    'leave_utilization' => [],
                    'ytd_comparison' => [
                        'current_year_gross' => 0,
                        'last_year_gross' => 0,
                        'change_percent' => 0,
                    ],
                ],
            ]);
        }

        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.staff.'.$user->id.'.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        $currentYear = now()->year;
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');

        // Attendance this month
        $attendanceSummary = DB::table('attendance_logs')
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->select(
                DB::raw('sum(case when is_present then 1 else 0 end) as present'),
                DB::raw('sum(case when is_absent then 1 else 0 end) as absent'),
                DB::raw('sum(case when late_minutes > 0 then 1 else 0 end) as late'),
                DB::raw('round(sum(overtime_minutes) / 60.0, 1) as ot_hours')
            )
            ->first();

        // Leave stats
        $leaveBalance = DB::table('leave_balances')
            ->where('employee_id', $employeeId)
            ->where('year', $currentYear)
            ->sum('balance');
        $pendingLeaves = DB::table('leave_requests')
            ->where('employee_id', $employeeId)
            ->where('status', 'PENDING')
            ->count();
        $approvedUpcoming = DB::table('leave_requests')
            ->where('employee_id', $employeeId)
            ->where('status', 'APPROVED')
            ->where('date_from', '>=', now()->format('Y-m-d'))
            ->count();

        // Loan stats
        $activeLoans = DB::table('loans')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['active', 'ready_for_disbursement'])
            ->count();
        $totalOutstanding = DB::table('loans')
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->sum('outstanding_balance_centavos');
        $pendingLoans = DB::table('loans')
            ->where('employee_id', $employeeId)
            ->where('status', 'PENDING')
            ->count();

        // Payroll stats
        $lastPayslip = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_details.employee_id', $employeeId)
            ->where('payroll_runs.status', 'COMPLETED')
            ->orderByDesc('payroll_runs.pay_date')
            ->first(['payroll_runs.pay_date']);

        $ytdPayroll = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_details.employee_id', $employeeId)
            ->where('payroll_runs.status', 'COMPLETED')
            ->whereYear('payroll_runs.pay_date', $currentYear)
            ->select(
                DB::raw('sum(payroll_details.gross_pay_centavos) as ytd_gross'),
                DB::raw('sum(payroll_details.net_pay_centavos) as ytd_net')
            )
            ->first();

        // Recent requests
        $recentLeaves = DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->where('leave_requests.employee_id', $employeeId)
            ->orderByDesc('leave_requests.id')
            ->limit(5)
            ->get([
                'leave_requests.id',
                'leave_requests.date_from',
                'leave_requests.date_to',
                'leave_requests.status',
                'leave_requests.total_days',
                'leave_types.name as leave_type_name',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'date_from' => $r->date_from,
                'date_to' => $r->date_to,
                'status' => $r->status,
                'total_days' => $r->total_days,
                'leave_type' => ['name' => $r->leave_type_name],
            ]);

        $recentOvertime = DB::table('overtime_requests')
            ->where('employee_id', $employeeId)
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'work_date',
                'status',
                'requested_minutes',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'work_date' => $r->work_date,
                'status' => $r->status,
                'requested_hours' => $r->requested_minutes ? round((float) $r->requested_minutes / 60, 1) : 0,
            ]);

        $recentLoans = DB::table('loans')
            ->join('loan_types', 'loans.loan_type_id', '=', 'loan_types.id')
            ->where('loans.employee_id', $employeeId)
            ->orderByDesc('loans.id')
            ->limit(5)
            ->get([
                'loans.id',
                'loans.ulid',
                'loans.status',
                'loans.principal_centavos',
                'loan_types.name as loan_type_name',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'ulid' => $r->ulid,
                'status' => $r->status,
                'principal_centavos' => $r->principal_centavos,
                'loan_type' => ['name' => $r->loan_type_name],
            ]);

        // ─── ANALYTICS ───

        // 1. Attendance Rate (Last 6 months)
        $attendanceRate = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth()->format('Y-m-d');
            $mEnd = now()->subMonths($i)->endOfMonth()->format('Y-m-d');
            $mLabel = now()->subMonths($i)->format('M Y');

            $total = DB::table('attendance_logs')
                ->where('employee_id', $employeeId)
                ->whereBetween('work_date', [$mStart, $mEnd])
                ->count();

            $present = DB::table('attendance_logs')
                ->where('employee_id', $employeeId)
                ->whereBetween('work_date', [$mStart, $mEnd])
                ->where('is_present', true)
                ->count();

            $attendanceRate[] = [
                'month' => $mLabel,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        }

        // 2. Leave Utilization by Type (Current year)
        $leaveUtilization = DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->where('leave_requests.employee_id', $employeeId)
            ->whereYear('leave_requests.created_at', $currentYear)
            ->where('leave_requests.status', 'APPROVED')
            ->select('leave_types.name', DB::raw('sum(leave_requests.total_days) as days_used'))
            ->groupBy('leave_types.name')
            ->get();

        // 3. YTD Comparison with Last Year
        $lastYearPayroll = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_details.employee_id', $employeeId)
            ->where('payroll_runs.status', 'COMPLETED')
            ->whereYear('payroll_runs.pay_date', $currentYear - 1)
            ->select(
                DB::raw('sum(payroll_details.gross_pay_centavos) as ytd_gross'),
                DB::raw('sum(payroll_details.net_pay_centavos) as ytd_net')
            )
            ->first();

        $ytdComparison = [
            'current_year_gross' => (int) ($ytdPayroll?->ytd_gross ?? 0),
            'last_year_gross' => (int) ($lastYearPayroll?->ytd_gross ?? 0),
            'change_percent' => ($lastYearPayroll?->ytd_gross ?? 0) > 0
                ? round(((($ytdPayroll?->ytd_gross ?? 0) - $lastYearPayroll->ytd_gross) / $lastYearPayroll->ytd_gross) * 100, 1)
                : 0,
        ];

        return tap(response()->json([
            'attendance' => [
                'this_month' => [
                    'present' => $attendanceSummary?->present ?? 0,
                    'absent' => $attendanceSummary?->absent ?? 0,
                    'late' => $attendanceSummary?->late ?? 0,
                    'ot_hours' => (float) ($attendanceSummary?->ot_hours ?? 0),
                ],
            ],
            'leave' => [
                'balance_days' => (int) $leaveBalance,
                'pending_requests' => $pendingLeaves,
                'approved_upcoming' => $approvedUpcoming,
            ],
            'loans' => [
                'active_loans' => $activeLoans,
                'total_outstanding' => (int) $totalOutstanding,
                'pending_approvals' => $pendingLoans,
            ],
            'payroll' => [
                'last_payslip_date' => $lastPayslip?->pay_date,
                'ytd_gross' => (int) ($ytdPayroll?->ytd_gross ?? 0),
                'ytd_net' => (int) ($ytdPayroll?->ytd_net ?? 0),
            ],
            'recent_requests' => [
                'leaves' => $recentLeaves,
                'overtime' => $recentOvertime,
                'loans' => $recentLoans,
            ],
            'analytics' => [
                'attendance_rate' => $attendanceRate,
                'leave_utilization' => $leaveUtilization,
                'ytd_comparison' => $ytdComparison,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.staff');

    // ─────────────────────────────────────────────────────────────────────────
    // Executive Dashboard
    // GET /api/v1/dashboard/executive
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('executive', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.exec.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // Company overview
        $companyOverview = [
            'total_employees' => DB::table('employees')->where('is_active', true)->count(),
            'total_departments' => DB::table('departments')->where('is_active', true)->count(),
            'active_projects' => 0, // Placeholder - would need projects table
        ];

        // Financial health
        $currentMonthPayroll = DB::table('payroll_runs')
            ->where('status', 'COMPLETED')
            ->whereMonth('pay_date', now()->month)
            ->whereYear('pay_date', now()->year)
            ->sum('net_pay_total_centavos');

        $financialHealth = [
            'current_month_payroll' => (int) $currentMonthPayroll,
            'pending_vendor_invoices' => DB::table('vendor_invoices')
                ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED'])
                ->count(),
            'pending_customer_invoices' => DB::table('customer_invoices')
                ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
                ->count(),
        ];

        // Pending executive approvals — leave requests filed by users who are
        // managers/hr_managers/accounting_managers (Spatie role join, no requester_role column).
        $pendingLeaves = DB::table('leave_requests')
            ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->join('model_has_roles', function ($j) {
                $j->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'App\Models\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['manager', 'hr_manager', 'accounting_manager'])
            ->where('leave_requests.status', 'SUBMITTED')
            ->count();
        $pendingHighValueLoans = DB::table('loans')
            ->where('principal_centavos', '>', 10000000) // > 100k PHP
            ->where('status', 'SUPERVISOR_APPROVED')
            ->count();

        // Key metrics
        $lastMonthHeadcount = DB::table('employees')
            ->where('is_active', true)
            ->where('date_hired', '<', now()->startOfMonth())
            ->count();
        $currentHeadcount = $companyOverview['total_employees'];
        $headcountChange = $currentHeadcount - $lastMonthHeadcount;

        // Attrition rate (simplified - employees who left this year)
        $startOfYear = now()->startOfYear();
        $employeesAtStart = DB::table('employees')
            ->where('date_hired', '<', $startOfYear)
            ->where(function ($q) use ($startOfYear) {
                $q->whereNull('separation_date')
                    ->orWhere('separation_date', '>=', $startOfYear);
            })
            ->count();
        $separatedThisYear = DB::table('employees')
            ->whereNotNull('separation_date')
            ->whereYear('separation_date', now()->year)
            ->count();
        $attritionRate = $employeesAtStart > 0
            ? ($separatedThisYear / $employeesAtStart) * 100
            : 0;

        // Average tenure
        $avgTenure = DB::table('employees')
            ->whereNotNull('date_hired')
            ->where('is_active', true)
            ->select(DB::raw('avg(extract(year from age(now(), date_hired))) as avg_years'))
            ->value('avg_years') ?? 0;

        // ─── ANALYTICS ───

        // 1. Revenue vs Expense Trend (Last 12 months)
        $revenueExpenseTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStartDate = now()->subMonths($i)->startOfMonth();
            $monthEndDate = now()->subMonths($i)->endOfMonth();
            $monthLabel = now()->subMonths($i)->format('M Y');

            $revenue = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.date', [$monthStartDate, $monthEndDate])
                ->where('chart_of_accounts.account_type', 'revenue')
                ->sum('journal_entry_lines.credit');

            $expenses = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.date', [$monthStartDate, $monthEndDate])
                ->where('chart_of_accounts.account_type', 'expense')
                ->sum('journal_entry_lines.debit');

            $revenueExpenseTrend[] = [
                'month' => $monthLabel,
                'revenue' => (float) $revenue,
                'expenses' => (float) $expenses,
                'profit' => (float) ($revenue - $expenses),
                'profit_margin' => $revenue > 0 ? round((($revenue - $expenses) / $revenue) * 100, 1) : 0,
            ];
        }

        // 2. Department Cost Allocation (Current month)
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $deptCostAllocation = DB::table('payroll_details')
            ->join('payroll_runs', 'payroll_details.payroll_run_id', '=', 'payroll_runs.id')
            ->join('employees', 'payroll_details.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('payroll_runs.status', 'COMPLETED')
            ->whereBetween('payroll_runs.pay_date', [$monthStart, $monthEnd])
            ->select(
                'departments.name',
                DB::raw('sum(payroll_details.net_pay_centavos) as total_cost')
            )
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($r) => [
                'department' => $r->name,
                'cost' => (int) $r->total_cost,
            ]);

        // 3. Headcount by Department with Trend
        $headcountByDept = DB::table('departments')
            ->where('departments.is_active', true)
            ->leftJoin('employees', function ($join) {
                $join->on('departments.id', '=', 'employees.department_id')
                    ->where('employees.is_active', true);
            })
            ->select('departments.name', DB::raw('count(employees.id) as count'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->get();

        // 4. Key Financial Ratios
        $totalRevenue = (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.status', 'posted')
            ->whereYear('journal_entries.date', now()->year)
            ->where('chart_of_accounts.account_type', 'revenue')
            ->sum('journal_entry_lines.credit');

        $totalExpenses = (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.status', 'posted')
            ->whereYear('journal_entries.date', now()->year)
            ->where('chart_of_accounts.account_type', 'expense')
            ->sum('journal_entry_lines.debit');

        // Calculate total assets from journal entries
        $assetAccountIds = DB::table('chart_of_accounts')
            ->where('account_type', 'asset')
            ->where('is_active', true)
            ->pluck('id');

        $totalAssets = 0;
        if ($assetAccountIds->count() > 0) {
            $totalAssets = (float) DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereIn('journal_entry_lines.account_id', $assetAccountIds)
                ->where('journal_entries.status', 'posted')
                ->selectRaw('SUM(CASE WHEN journal_entry_lines.debit > 0 THEN journal_entry_lines.debit ELSE -journal_entry_lines.credit END) as balance')
                ->value('balance') ?? 0;
        }

        // Calculate total liabilities from journal entries
        $liabilityAccountIds = DB::table('chart_of_accounts')
            ->where('account_type', 'liability')
            ->where('is_active', true)
            ->pluck('id');

        $totalLiabilities = 0;
        if ($liabilityAccountIds->count() > 0) {
            $totalLiabilities = (float) DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereIn('journal_entry_lines.account_id', $liabilityAccountIds)
                ->where('journal_entries.status', 'posted')
                ->selectRaw('SUM(CASE WHEN journal_entry_lines.credit > 0 THEN journal_entry_lines.credit ELSE -journal_entry_lines.debit END) as balance')
                ->value('balance') ?? 0;
        }

        $financialRatios = [
            'gross_profit_margin' => $totalRevenue > 0 ? round((($totalRevenue - $totalExpenses) / $totalRevenue) * 100, 1) : 0,
            'current_ratio' => $totalLiabilities > 0 ? round($totalAssets / $totalLiabilities, 2) : 0,
            'debt_to_equity' => ($totalAssets - $totalLiabilities) > 0 ? round($totalLiabilities / ($totalAssets - $totalLiabilities), 2) : 0,
            'ytd_revenue' => (float) $totalRevenue,
            'ytd_expenses' => (float) $totalExpenses,
        ];

        // 5. Top Performing Departments (by revenue per employee - if revenue can be attributed)
        // For now, we'll show payroll cost per department as a proxy
        $payrollByDept = DB::table('payroll_runs')
            ->where('payroll_runs.status', 'COMPLETED')
            ->whereYear('payroll_runs.pay_date', now()->year)
            ->join('payroll_details', 'payroll_runs.id', '=', 'payroll_details.payroll_run_id')
            ->join('employees', 'payroll_details.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->select(
                'departments.name',
                DB::raw('sum(payroll_details.net_pay_centavos) as total_payroll'),
                DB::raw('count(distinct employees.id) as employee_count')
            )
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_payroll')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'department' => $r->name,
                'total_payroll' => (int) $r->total_payroll,
                'employee_count' => $r->employee_count,
                'avg_per_employee' => $r->employee_count > 0 ? round((float) $r->total_payroll / (int) $r->employee_count) : 0,
            ]);

        return tap(response()->json([
            'company_overview' => $companyOverview,
            'financial_health' => $financialHealth,
            'pending_executive_approvals' => [
                'leaves' => $pendingLeaves,
                'high_value_loans' => $pendingHighValueLoans,
                'total' => $pendingLeaves + $pendingHighValueLoans,
            ],
            'key_metrics' => [
                'headcount_change' => $headcountChange,
                'attrition_rate' => round($attritionRate, 1),
                'avg_tenure_years' => round((float) $avgTenure, 1),
            ],
            'analytics' => [
                'revenue_expense_trend' => $revenueExpenseTrend,
                'department_cost_allocation' => $deptCostAllocation,
                'headcount_by_department' => $headcountByDept,
                'financial_ratios' => $financialRatios,
                'payroll_by_department' => $payrollByDept,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.executive')->can('system.view_audit_log');

    // ─────────────────────────────────────────────────────────────────────────
    // Vice President Dashboard
    // GET /api/v1/dashboard/vp
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('vp', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.vp.'.$user->id.'.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // ── Pending approvals awaiting VP sign-off ────────────────────────────
        $pendingLoans = DB::table('loans')
            ->where('status', 'officer_reviewed')
            ->where('workflow_version', 2)
            ->count();

        $pendingPRs = DB::table('purchase_requests')
            ->where('status', 'reviewed')
            ->count();

        $pendingMRQs = DB::table('material_requisitions')
            ->where('status', 'reviewed')
            ->count();

        $totalPending = $pendingLoans + $pendingPRs + $pendingMRQs;

        // ── Financial summary ─────────────────────────────────────────────────
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');

        $totalPayrollThisMonth = (int) DB::table('payroll_runs')
            ->whereIn('status', ['ACCTG_APPROVED', 'PUBLISHED'])
            ->whereBetween('pay_date', [$monthStart, $monthEnd])
            ->sum('net_pay_total_centavos');

        $pendingVendorInvoices = DB::table('vendor_invoices')
            ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED'])
            ->count();

        $pendingCustomerInvoices = DB::table('customer_invoices')
            ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
            ->count();

        $openProductionOrders = DB::table('production_orders')
            ->whereIn('status', ['draft', 'released', 'in_progress'])
            ->count();

        // ── Recent items forwarded to VP (most recent first) ──────────────────
        $recentLoans = DB::table('loans')
            ->join('employees', 'loans.employee_id', '=', 'employees.id')
            ->where('loans.status', 'officer_reviewed')
            ->where('loans.workflow_version', 2)
            ->select(
                DB::raw("'Loan' as type"),
                'loans.reference_no as reference',
                DB::raw("CONCAT(employees.first_name, ' ', employees.last_name) as requestor"),
                'loans.principal_centavos as amount',
                'loans.updated_at as submitted_at'
            )
            ->orderByDesc('loans.updated_at')
            ->limit(5)
            ->get();

        $recentPRs = DB::table('purchase_requests')
            ->join('users', 'purchase_requests.submitted_by_id', '=', 'users.id')
            ->where('purchase_requests.status', 'reviewed')
            ->select(
                DB::raw("'Purchase Request' as type"),
                'purchase_requests.pr_reference as reference',
                'users.name as requestor',
                DB::raw('NULL as amount'),
                'purchase_requests.updated_at as submitted_at'
            )
            ->orderByDesc('purchase_requests.updated_at')
            ->limit(5)
            ->get();

        $recentMRQs = DB::table('material_requisitions')
            ->join('users', 'material_requisitions.requested_by_id', '=', 'users.id')
            ->where('material_requisitions.status', 'reviewed')
            ->select(
                DB::raw("'MRQ' as type"),
                'material_requisitions.mr_reference as reference',
                'users.name as requestor',
                DB::raw('NULL as amount'),
                'material_requisitions.updated_at as submitted_at'
            )
            ->orderByDesc('material_requisitions.updated_at')
            ->limit(5)
            ->get();

        $recentApprovals = collect($recentLoans)
            ->concat($recentPRs)
            ->concat($recentMRQs)
            ->sortByDesc('submitted_at')
            ->take(10)
            ->values()
            ->all();

        return tap(response()->json([
            'pending_approvals' => [
                'loans' => $pendingLoans,
                'purchase_requests' => $pendingPRs,
                'mrq' => $pendingMRQs,
                'total' => $totalPending,
            ],
            'financial_summary' => [
                'total_payroll_this_month' => $totalPayrollThisMonth,
                'pending_vendor_invoices' => $pendingVendorInvoices,
                'pending_customer_invoices' => $pendingCustomerInvoices,
                'open_production_orders' => $openProductionOrders,
            ],
            'recent_approvals' => $recentApprovals,
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.vp')->can('loans.vp_approve');

    // ─────────────────────────────────────────────────────────────────────────
    // Officer Dashboard
    // GET /api/v1/dashboard/officer
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('officer', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.officer.'.$user->id.'.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        // ── Accounting KPIs ───────────────────────────────────────────────────
        $pendingVendorInvoices = DB::table('vendor_invoices')
            ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED', 'PARTIALLY_PAID'])
            ->count();

        $pendingCustomerInvoices = DB::table('customer_invoices')
            ->whereIn('status', ['APPROVED', 'PARTIALLY_PAID'])
            ->count();

        $journalEntriesToPost = DB::table('journal_entries')
            ->where('status', 'SUBMITTED')
            ->count();

        $bankReconDue = DB::table('bank_accounts')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('bank_reconciliations')
                    ->whereColumn('bank_reconciliations.bank_account_id', 'bank_accounts.id')
                    ->whereIn('bank_reconciliations.status', ['draft', 'in_progress']);
            })
            ->count();

        // ── Procurement ───────────────────────────────────────────────────────
        $pendingPRReview = DB::table('purchase_requests')
            ->where('status', 'checked')
            ->count();

        $pendingPRBudgetCheck = DB::table('purchase_requests')
            ->where('status', 'reviewed')
            ->count();

        $openPOs = DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'partially_received'])
            ->count();

        $pendingGR = DB::table('purchase_orders')
            ->where('status', 'sent')
            ->count();

        // ── Delivery ──────────────────────────────────────────────────────────
        $inboundDraft = DB::table('delivery_receipts')
            ->where('direction', 'inbound')
            ->where('status', 'draft')
            ->count();

        $outboundDraft = DB::table('delivery_receipts')
            ->where('direction', 'outbound')
            ->where('status', 'draft')
            ->count();

        $inTransitShipments = DB::table('shipments')
            ->where('status', 'in_transit')
            ->count();

        // ── Payroll ───────────────────────────────────────────────────────────
        $runsPendingAcctg = DB::table('payroll_runs')
            ->where('status', 'HR_APPROVED')
            ->count();

        $nextPayDate = DB::table('payroll_runs')
            ->where('pay_date', '>=', now()->format('Y-m-d'))
            ->whereIn('status', ['HR_APPROVED', 'ACCTG_APPROVED', 'SUBMITTED'])
            ->orderBy('pay_date')
            ->value('pay_date');

        return tap(response()->json([
            'accounting' => [
                'pending_vendor_invoices' => $pendingVendorInvoices,
                'pending_customer_invoices' => $pendingCustomerInvoices,
                'journal_entries_to_post' => $journalEntriesToPost,
                'bank_recon_due' => $bankReconDue,
            ],
            'procurement' => [
                'pending_pr_review' => $pendingPRReview,
                'pending_pr_budget_check' => $pendingPRBudgetCheck,
                'open_pos' => $openPOs,
                'pending_gr' => $pendingGR,
            ],
            'delivery' => [
                'inbound_draft' => $inboundDraft,
                'outbound_draft' => $outboundDraft,
                'in_transit_shipments' => $inTransitShipments,
            ],
            'payroll' => [
                'runs_pending_acctg_approval' => $runsPendingAcctg,
                'next_pay_date' => $nextPayDate,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.officer')->can('journal_entries.view');

    // Purchasing Officer Dashboard
    // GET /api/v1/dashboard/purchasing-officer
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('purchasing-officer', function (Request $request) {
        $user = $request->user();
        $_ttl = (int) env('DASHBOARD_CACHE_TTL', 120);
        $_cacheKey = 'dash.purchasing_officer.'.$user->id.'.'.now()->format('Y-m-d-H');
        if ($_ttl > 0 && ($cached = Cache::get($_cacheKey)) !== null) {
            return response()->json($cached);
        }

        $draftPRs = DB::table('purchase_requests')
            ->where('status', 'draft')
            ->count();

        $submittedPRs = DB::table('purchase_requests')
            ->whereIn('status', ['submitted', 'checked'])
            ->count();

        $pendingBudgetCheck = DB::table('purchase_requests')
            ->where('status', 'reviewed')
            ->count();

        $openPOs = DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'partially_received'])
            ->count();

        $pendingGR = DB::table('purchase_orders')
            ->where('status', 'sent')
            ->count();

        $vendorsActive = DB::table('vendors')
            ->where('status', 'active')
            ->count();

        $topVendors = DB::table('purchase_orders')
            ->select('vendors.name', DB::raw('COUNT(purchase_orders.id) as po_count'))
            ->join('vendors', 'vendors.id', '=', 'purchase_orders.vendor_id')
            ->whereIn('purchase_orders.status', ['sent', 'partially_received', 'received'])
            ->whereNull('purchase_orders.deleted_at')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('po_count')
            ->limit(5)
            ->get();

        return tap(response()->json([
            'purchase_requests' => [
                'draft' => $draftPRs,
                'submitted' => $submittedPRs,
                'pending_budget_check' => $pendingBudgetCheck,
            ],
            'purchase_orders' => [
                'open' => $openPOs,
                'pending_gr' => $pendingGR,
            ],
            'vendors' => [
                'active' => $vendorsActive,
                'top_5' => $topVendors,
            ],
        ]),
            fn ($resp) => ($_ttl > 0 ? Cache::put($_cacheKey, $resp->getData(true), $_ttl) : null));
    })->name('dashboard.purchasing-officer')->can('procurement.purchase-request.view');

    // ─────────────────────────────────────────────────────────────────────────
    // Executive Analytics Dashboard (service-based, replaces inline queries)
    // GET /api/v1/dashboard/executive-analytics
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('executive-analytics', \App\Http\Controllers\Dashboard\ExecutiveDashboardController::class)
        ->name('dashboard.executive-analytics');

    // ── Supplementary KPIs (Phase 4) ──────────────────────────────────────
    Route::get('kpis/supplementary', function (): \Illuminate\Http\JsonResponse {
        $service = app(\App\Domains\Dashboard\Services\DashboardKpiService::class);
        return response()->json(['data' => $service->supplementaryKpis()]);
    })->name('dashboard.kpis.supplementary');

});
