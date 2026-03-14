<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ChartOfAccountsController;
use App\Http\Controllers\Admin\HolidayCalendarController;
use App\Http\Controllers\Admin\LoanTypeController;
use App\Http\Controllers\Admin\MinimumWageController;
use App\Http\Controllers\Admin\PagibigContributionController;
use App\Http\Controllers\Admin\PhilhealthContributionController;
use App\Http\Controllers\Admin\SalaryGradeController;
use App\Http\Controllers\Admin\SssContributionController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TaxBracketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Admin Routes — /api/v1/admin/*
| User management, system settings, and dashboard stats.
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard Stats
    // GET /api/v1/admin/dashboard/stats
    // Available to any authenticated user (filtered per role by frontend)
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('dashboard/stats', function (Request $request) {
        $user = $request->user();
        $isGlobal = $user->hasAnyRole(['admin', 'executive']);

        // Resolve the departments this user can see
        $deptIds = $isGlobal
            ? null
            : DB::table('user_department_access')
                ->where('user_id', $user->id)
                ->pluck('department_id')
                ->toArray();

        // Friendly scope label returned to the frontend
        if ($isGlobal) {
            $scopeLabel = 'Company Wide';
        } elseif (! empty($deptIds) && count($deptIds) === 1) {
            $scopeLabel = DB::table('departments')->where('id', $deptIds[0])->value('name') ?? 'Your Department';
        } else {
            $scopeLabel = 'Your Departments';
        }

        // Reusable scoped employee base query
        $empBase = fn () => DB::table('employees')
            ->where('is_active', true)
            ->where('employment_status', 'active')
            ->when(! $isGlobal, fn ($q) => $q->whereIn('department_id', $deptIds ?? []));

        // Active headcount (scoped)
        $totalEmployees = $empBase()->count();

        // Headcount by department (top 6, scoped)
        $byDepartment = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.is_active', true)
            ->where('employees.employment_status', 'active')
            ->when(! $isGlobal, fn ($q) => $q->whereIn('employees.department_id', $deptIds ?? []))
            ->select('departments.name as department', DB::raw('count(*) as count'))
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // Headcount trend — hires per month for last 6 months (scoped)
        $hiredTrend = DB::table('employees')
            ->where('date_hired', '>=', now()->subMonths(6)->startOfMonth())
            ->when(! $isGlobal, fn ($q) => $q->whereIn('department_id', $deptIds ?? []))
            ->select(
                DB::raw("to_char(date_hired, 'Mon YYYY') as month"),
                DB::raw("date_trunc('month', date_hired) as month_start"),
                DB::raw('count(*) as count')
            )
            ->groupBy(DB::raw("to_char(date_hired, 'Mon YYYY')"), DB::raw("date_trunc('month', date_hired)"))
            ->orderBy('month_start')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'count' => (int) $r->count]);

        // Scoped employee IDs — used to filter leave / attendance sub-queries
        $empIds = $isGlobal
            ? null
            : DB::table('employees')
                ->where('is_active', true)
                ->where('employment_status', 'active')
                ->whereIn('department_id', $deptIds ?? [])
                ->pluck('id');

        // Leave requests by status (current year, scoped)
        $leaveByStatus = DB::table('leave_requests')
            ->whereYear('created_at', now()->year)
            ->when(! $isGlobal, fn ($q) => $q->whereIn('employee_id', $empIds ?? []))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Pending approvals (scoped for HR-type items)
        $pendingLeaves = DB::table('leave_requests')
            ->where('status', 'pending')
            ->when(! $isGlobal, fn ($q) => $q->whereIn('employee_id', $empIds ?? []))
            ->count();
        $pendingOvertime = DB::table('overtime_requests')
            ->where('status', 'pending')
            ->when(! $isGlobal, fn ($q) => $q->whereIn('employee_id', $empIds ?? []))
            ->count();

        // Finance-domain pending counts — only for users with relevant permissions
        $pendingLoans = ($isGlobal || $user->can('loans.hr_approve') || $user->can('loans.accounting_approve'))
            ? DB::table('loans')->where('status', 'pending')->count()
            : null;
        $pendingJEs = ($isGlobal || $user->can('journal_entries.approve'))
            ? DB::table('journal_entries')->where('status', 'submitted')->count()
            : null;
        $pendingInvoices = ($isGlobal || $user->can('vendor_invoices.approve'))
            ? DB::table('vendor_invoices')->where('status', 'submitted')->count()
            : null;

        // Attendance summary — current month (scoped)
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $monthEnd = now()->endOfMonth()->format('Y-m-d');
        $attendanceSummary = DB::table('attendance_logs')
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->when(! $isGlobal, fn ($q) => $q->whereIn('employee_id', $empIds ?? []))
            ->select(
                DB::raw('count(*) as total_records'),
                DB::raw('sum(case when is_present then 1 else 0 end) as present'),
                DB::raw('sum(case when is_absent then 1 else 0 end) as absent'),
                DB::raw('sum(late_minutes) as total_late_minutes')
            )
            ->first();

        // Payroll cost trend — only for users who can view payroll
        $payrollTrend = null;
        if ($isGlobal || $user->can('payroll.view')) {
            $payrollTrend = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                ->where('chart_of_accounts.code', '5001')
                ->where('journal_entries.status', 'posted')
                ->select(
                    DB::raw("to_char(journal_entries.date, 'Mon YYYY') as month"),
                    DB::raw("date_trunc('month', journal_entries.date) as month_start"),
                    DB::raw('sum(journal_entry_lines.debit) as total')
                )
                ->groupBy(
                    DB::raw("to_char(journal_entries.date, 'Mon YYYY')"),
                    DB::raw("date_trunc('month', journal_entries.date)")
                )
                ->orderBy('month_start')
                ->get()
                ->map(fn ($r) => ['month' => $r->month, 'total' => (float) $r->total]);
        }

        // Active fiscal period (all roles)
        $activePeriod = DB::table('fiscal_periods')
            ->where('status', 'open')
            ->orderBy('date_from')
            ->first(['id', 'name', 'date_from', 'date_to', 'status']);

        return response()->json([
            'scoped' => ! $isGlobal,
            'scope_label' => $scopeLabel,
            'total_employees' => $totalEmployees,
            'by_department' => $byDepartment,
            'hired_trend' => $hiredTrend,
            'leave_by_status' => $leaveByStatus,
            'pending_approvals' => [
                'leaves' => $pendingLeaves,
                'overtime' => $pendingOvertime,
                'loans' => $pendingLoans,
                'journal_entries' => $pendingJEs,
                'invoices' => $pendingInvoices,
                'total' => $pendingLeaves + $pendingOvertime
                    + ($pendingLoans ?? 0)
                    + ($pendingJEs ?? 0)
                    + ($pendingInvoices ?? 0),
            ],
            'attendance_summary' => $attendanceSummary,
            'payroll_trend' => $payrollTrend,
            'active_period' => $activePeriod,
        ]);
    })->name('dashboard.stats');

    // ─────────────────────────────────────────────────────────────────────────
    // User Management
    // ─────────────────────────────────────────────────────────────────────────

    // GET /api/v1/admin/users
    // Optional query params:
    //   ?include_archived=1  include archived users in mixed list
    //   ?archived=1          only archived users
    Route::get('users', function (Request $request) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $query = \App\Models\User::with(['roles', 'employee' => function ($q) {
            $q->select('id', 'user_id', 'employee_code', 'first_name', 'last_name', 'department_id')
                ->with(['department:id,name,code']);
        }])
            ->select('id', 'name', 'email', 'department_id', 'last_login_at', 'created_at', 'locked_until', 'failed_login_attempts', 'deleted_at');

        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('include_archived')) {
            $query->withTrashed();
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role = $request->string('role')->trim()->value()) {
            $query->role($role);
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    })->name('users.index');

    // GET /api/v1/admin/employees/available?department_id=X
    // Returns employees in a given department that do NOT yet have a user account.
    // Used in Step 2 of the user-creation wizard.
    Route::get('employees/available', function (Request $request) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $deptId = $request->integer('department_id');

        $query = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereNull('employees.user_id')
            ->whereNull('employees.deleted_at')
            ->where('employees.employment_status', 'active')
            ->where('employees.is_active', true)
            ->select(
                'employees.id',
                'employees.employee_code',
                'employees.first_name',
                'employees.last_name',
                'employees.department_id',
                'departments.name as department_name',
            )
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');

        if ($deptId > 0) {
            $query->where('employees.department_id', $deptId);
        }

        return response()->json(['data' => $query->get()]);
    })->name('employees.available');

    // GET /api/v1/admin/vendors/available?search=abc&limit=50
    // Returns active accredited vendors with an email and no linked user account.
    Route::get('vendors/available', function (Request $request) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $search = $request->string('search')->trim()->value();
        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $query = DB::table('vendors')
            ->whereNull('vendors.deleted_at')
            ->where('vendors.is_active', true)
            ->where('vendors.accreditation_status', 'accredited')
            ->whereNotNull('vendors.email')
            ->where('vendors.email', '!=', '')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('users')
                    ->whereNull('users.deleted_at')
                    ->whereColumn('users.vendor_id', 'vendors.id');
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('users')
                    ->whereNull('users.deleted_at')
                    ->whereColumn('users.email', 'vendors.email');
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('vendors.name', 'ilike', "%{$search}%")
                        ->orWhere('vendors.email', 'ilike', "%{$search}%")
                        ->orWhere('vendors.tin', 'ilike', "%{$search}%");
                });
            })
            ->select(
                'vendors.id',
                'vendors.name',
                'vendors.email',
                'vendors.contact_person',
                'vendors.accreditation_status',
            )
            ->orderBy('vendors.name')
            ->limit($limit);

        return response()->json(['data' => $query->get()]);
    })->name('vendors.available');

    // GET /api/v1/admin/customers/available?search=abc&limit=50
    // Returns active customers with an email and no linked user account.
    Route::get('customers/available', function (Request $request) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $search = $request->string('search')->trim()->value();
        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $query = DB::table('customers')
            ->whereNull('customers.deleted_at')
            ->where('customers.is_active', true)
            ->whereNotNull('customers.email')
            ->where('customers.email', '!=', '')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('users')
                    ->whereNull('users.deleted_at')
                    ->whereColumn('users.client_id', 'customers.id');
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('users')
                    ->whereNull('users.deleted_at')
                    ->whereColumn('users.email', 'customers.email');
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('customers.name', 'ilike', "%{$search}%")
                        ->orWhere('customers.email', 'ilike', "%{$search}%")
                        ->orWhere('customers.tin', 'ilike', "%{$search}%");
                });
            })
            ->select(
                'customers.id',
                'customers.name',
                'customers.email',
                'customers.contact_person',
            )
            ->orderBy('customers.name')
            ->limit($limit);

        return response()->json(['data' => $query->get()]);
    })->name('customers.available');

    // POST /api/v1/admin/users
    Route::post('users', function (Request $request) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', 'exists:roles,name'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        // Validate employee belongs to no existing user
        if (! empty($data['employee_id'])) {
            $empHasUser = DB::table('employees')
                ->where('id', $data['employee_id'])
                ->whereNotNull('user_id')
                ->exists();
            if ($empHasUser) {
                return response()->json(['message' => 'This employee already has a user account.'], 422);
            }
        }

        $mustChangePassword = in_array($data['role'], ['vendor', 'client'], true);

        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'password_changed_at' => $mustChangePassword ? null : now(),
        ]);

        $user->assignRole($data['role']);

        // Link employee and provision RDAC department access
        if (! empty($data['employee_id'])) {
            $employee = DB::table('employees')
                ->where('id', $data['employee_id'])
                ->first(['id', 'department_id']);

            // Set user_id on the employee record
            DB::table('employees')
                ->where('id', $employee->id)
                ->update(['user_id' => $user->id]);

            // Provision user_department_access for the employee's department
            if ($employee->department_id) {
                DB::table('user_department_access')->insertOrIgnore([
                    'user_id' => $user->id,
                    'department_id' => $employee->department_id,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Keep denormalised column in sync
                $user->update(['department_id' => $employee->department_id]);
            }
        }

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $user->load(['roles', 'employee.department']),
        ], 201);
    })->name('users.store');

    // PATCH /api/v1/admin/users/{user}
    Route::patch('users/{user}', function (Request $request, \App\Models\User $user) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:254', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['password_changed_at'] = $user->hasAnyRole(['vendor', 'client']) ? null : now();
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->fresh('roles'),
        ]);
    })->name('users.update');

    // POST /api/v1/admin/users/{user}/reset-password
    // Generates a random password and resets the user.
    Route::post('users/{user}/reset-password', function (Request $request, \App\Models\User $user) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $password = \Illuminate\Support\Str::password(12);

        $user->update([
            'password' => Hash::make($password),
            'password_changed_at' => null, // Force change on next login
        ]);

        // Revoke sessions
        $user->tokens()->delete();

        return response()->json([
             'message' => 'Password reset successfully.',
             'password' => $password,
        ]);
    })->name('users.resetPassword');

    // POST /api/v1/admin/users/{user}/disable
    Route::post('users/{user}/disable', function (Request $request, \App\Models\User $user) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        // Prevent self-disable so admins cannot lock themselves out of user management.
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot disable your own account.'], 422);
        }

        // Revoke all active tokens immediately so existing sessions are terminated.
        $user->tokens()->delete();

        // Use a long lock window as the disable flag; unlock endpoint re-enables the account.
        $user->update([
            'locked_until' => now()->addYears(10),
            'failed_login_attempts' => 0,
        ]);

        return response()->json(['message' => 'User account disabled.']);
    })->middleware('throttle:api-action')->name('users.disable');

    // DELETE /api/v1/admin/users/{user}
    // Archive user account (soft-delete).
    Route::delete('users/{user}', function (Request $request, \App\Models\User $user) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        // Prevent self-delete
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // Revoke all active tokens immediately so existing sessions are terminated.
        $user->tokens()->delete();

        // Unlink employee record so a replacement account can be provisioned.
        DB::table('employees')->where('user_id', $user->id)->update(['user_id' => null]);

        $user->delete();

        return response()->json(['message' => 'User archived successfully.']);
    })->middleware('throttle:api-action')->name('users.destroy');

    // POST /api/v1/admin/users/{user}/roles
    Route::post('users/{user}/roles', function (Request $request, \App\Models\User $user) {
        abort_unless($request->user()->can('system.assign_roles'), 403, 'Insufficient permissions.');

        $data = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles([$data['role']]);

        return response()->json([
            'message' => 'Role assigned successfully.',
            'data' => $user->fresh('roles'),
        ]);
    })->name('users.assignRole');

    // POST /api/v1/admin/users/{user}/unlock
    Route::post('users/{user}/unlock', function (Request $request, \App\Models\User $user) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $user->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        return response()->json(['message' => 'User account unlocked.']);
    })->middleware('throttle:api-action')->name('users.unlock');

    // GET /api/v1/admin/roles
    Route::get('roles', function (Request $request) {
        abort_unless($request->user()->can('system.manage_users'), 403, 'Insufficient permissions.');

        $roles = Role::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->count(),
            ]);

        return response()->json(['data' => $roles]);
    })->name('roles.index');

    // ─────────────────────────────────────────────────────────────────────────
    // System Settings — all operations require system.edit_settings
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('can:system.edit_settings')->group(function () {
        // GET /api/v1/admin/settings
        Route::get('settings', [SystemSettingController::class, 'index'])
            ->name('settings.index');

        // GET /api/v1/admin/settings/group/{group}
        Route::get('settings/group/{group}', [SystemSettingController::class, 'byGroup'])
            ->name('settings.byGroup');

        // GET /api/v1/admin/settings/key/{key}
        Route::get('settings/key/{key}', [SystemSettingController::class, 'show'])
            ->name('settings.show');

        // PATCH /api/v1/admin/settings/{key}
        Route::patch('settings/{key}', [SystemSettingController::class, 'update'])
            ->name('settings.update');

        // POST /api/v1/admin/settings/bulk-update
        Route::post('settings/bulk-update', [SystemSettingController::class, 'bulkUpdate'])
            ->name('settings.bulkUpdate');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Audit Logs
    // GET /api/v1/admin/audit-logs
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('audit-logs', function (Request $request) {
        abort_unless($request->user()->can('system.view_audit_log'), 403, 'Insufficient permissions.');

        $query = DB::table('audits')
            ->leftJoin('users', 'audits.user_id', '=', 'users.id')
            ->select(
                'audits.id',
                'audits.event',
                'audits.auditable_type',
                'audits.auditable_id',
                'audits.old_values',
                'audits.new_values',
                'audits.ip_address',
                'audits.user_agent',
                'audits.url',
                'audits.tags',
                'audits.created_at',
                DB::raw("COALESCE(users.name, 'System') as user_name"),
                DB::raw("COALESCE(users.email, 'system') as user_email")
            )
            ->orderByDesc('audits.created_at');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('audits.event', 'ilike', "%{$search}%")
                    ->orWhere('audits.auditable_type', 'ilike', "%{$search}%")
                    ->orWhere('users.name', 'ilike', "%{$search}%")
                    ->orWhere('audits.tags', 'ilike', "%{$search}%");
            });
        }

        // Filter by event type
        if ($event = $request->input('event')) {
            $query->where('audits.event', $event);
        }

        // Filter by model type
        if ($model = $request->input('auditable_type')) {
            $query->where('audits.auditable_type', 'ilike', "%{$model}%");
        }

        // Filter by date range
        if ($from = $request->input('date_from')) {
            $query->where('audits.created_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->where('audits.created_at', '<=', $to.' 23:59:59');
        }

        // Filter by user
        if ($userId = $request->input('user_id')) {
            $query->where('audits.user_id', $userId);
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    })->name('audit-logs.index');

    // ─────────────────────────────────────────────────────────────────────────
    // Reference Tables (Tax, Contributions, Rates, etc.)
    // All require system.edit_settings permission
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware('can:system.edit_settings')->group(function () {
        // TRAIN Tax Brackets
        Route::get('tax-brackets/active', [TaxBracketController::class, 'active']);
        Route::apiResource('tax-brackets', TaxBracketController::class);

        // SSS Contribution Table
        Route::get('sss-contributions/active', [SssContributionController::class, 'active']);
        Route::apiResource('sss-contributions', SssContributionController::class)
            ->parameters(['sss-contributions' => 'sssContribution']);

        // PhilHealth Premium Table
        Route::get('philhealth-contributions/active', [PhilhealthContributionController::class, 'active']);
        Route::apiResource('philhealth-contributions', PhilhealthContributionController::class)
            ->parameters(['philhealth-contributions' => 'philhealth']);

        // Pag-IBIG Contribution Table
        Route::get('pagibig-contributions/active', [PagibigContributionController::class, 'active']);
        Route::apiResource('pagibig-contributions', PagibigContributionController::class)
            ->parameters(['pagibig-contributions' => 'pagibig']);

        // Minimum Wage Rates
        Route::get('minimum-wages/current-by-region', [MinimumWageController::class, 'currentByRegion']);
        Route::apiResource('minimum-wages', MinimumWageController::class)
            ->parameters(['minimum-wages' => 'minimumWage']);

        // Holiday Calendar
        Route::get('holidays/by-year/{year}', [HolidayCalendarController::class, 'byYear']);
        Route::post('holidays/bulk', [HolidayCalendarController::class, 'bulkStore']);
        Route::apiResource('holidays', HolidayCalendarController::class)
            ->parameters(['holidays' => 'holiday']);

        // Salary Grades
        Route::get('salary-grades/by-type/{type}', [SalaryGradeController::class, 'byType']);
        Route::apiResource('salary-grades', SalaryGradeController::class);

        // Loan Types
        Route::get('loan-types/by-category/{category}', [LoanTypeController::class, 'byCategory']);
        Route::apiResource('loan-types', LoanTypeController::class);

        // Chart of Accounts
        Route::post('chart-of-accounts/{chartOfAccount}/archive', [ChartOfAccountsController::class, 'archive']);
        Route::apiResource('chart-of-accounts', ChartOfAccountsController::class);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Backup Management
    // Requires: system.manage_backups (admin only)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('backups')->group(function () {
        Route::get('/',          [BackupController::class, 'index'])->name('admin.backups.index');
        Route::get('/status',    [BackupController::class, 'status'])->name('admin.backups.status');
        Route::get('/download',  [BackupController::class, 'download'])->name('admin.backups.download');
        Route::post('/run',      [BackupController::class, 'run'])->name('admin.backups.run');
        Route::post('/restore',  [BackupController::class, 'restore'])->name('admin.backups.restore');
    });
});
