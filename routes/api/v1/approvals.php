<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Approvals Routes — /api/v1/approvals/*
| Consolidated pending approvals dashboard for VP/Executive users.
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:approvals'])->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // Pending Approvals Dashboard
    // GET /api/v1/approvals/pending
    // Returns aggregated pending approvals across all modules.
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('pending', function (Request $request) {
        $user = $request->user();

        // Get user's departments for scoping
        $deptIds = DB::table('user_department_access')
            ->where('user_id', $user->id)
            ->pluck('department_id')
            ->toArray();

        $isGlobal = $user->hasAnyRole(['admin', 'executive', 'vice_president']);

        // Helper to get employee IDs in scope
        $getEmpIds = function () use ($deptIds, $isGlobal) {
            if ($isGlobal) {
                return null;
            }

            return DB::table('employees')
                ->whereIn('department_id', $deptIds)
                ->pluck('id');
        };

        $empIds = $getEmpIds();

        // ─── HR APPROVALS ───
        $hrApprovals = [];

        // Leave requests pending VP approval
        if ($user->can('leaves.vp_approve')) {
            $hrApprovals['leaves'] = DB::table('leave_requests')
                ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
                ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
                ->when(! $isGlobal, fn ($q) => $q->whereIn('leave_requests.employee_id', $empIds ?? []))
                ->where('leave_requests.status', 'approved')
                ->whereNull('leave_requests.vp_approved_at')
                ->select(
                    'leave_requests.id',
                    'leave_requests.ulid',
                    DB::raw("concat(employees.first_name, ' ', employees.last_name) as requestor"),
                    'leave_types.name as type',
                    'leave_requests.date_from',
                    'leave_requests.date_to',
                    'leave_requests.total_days',
                    'leave_requests.reason',
                    'leave_requests.created_at'
                )
                ->orderBy('leave_requests.created_at')
                ->limit(20)
                ->get();
        }

        // Overtime requests pending approval
        if ($user->can('overtime.approve')) {
            $hrApprovals['overtime'] = DB::table('overtime_requests')
                ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
                ->when(! $isGlobal, fn ($q) => $q->whereIn('overtime_requests.employee_id', $empIds ?? []))
                ->where('overtime_requests.status', 'pending')
                ->select(
                    'overtime_requests.id',
                    DB::raw("concat(employees.first_name, ' ', employees.last_name) as requestor"),
                    'overtime_requests.work_date',
                    'overtime_requests.requested_minutes',
                    'overtime_requests.reason',
                    'overtime_requests.created_at'
                )
                ->orderBy('overtime_requests.created_at')
                ->limit(20)
                ->get();
        }

        // Loans pending approval
        if ($user->can('loans.vp_approve')) {
            $hrApprovals['loans'] = DB::table('loans')
                ->join('employees', 'loans.employee_id', '=', 'employees.id')
                ->join('loan_types', 'loans.loan_type_id', '=', 'loan_types.id')
                ->when(! $isGlobal, fn ($q) => $q->whereIn('loans.employee_id', $empIds ?? []))
                ->whereIn('loans.status', ['pending', 'supervisor_approved', 'hr_approved'])
                ->select(
                    'loans.id',
                    DB::raw("concat(employees.first_name, ' ', employees.last_name) as requestor"),
                    'loan_types.name as loan_type',
                    'loans.principal_amount',
                    'loans.status',
                    'loans.created_at'
                )
                ->orderBy('loans.created_at')
                ->limit(20)
                ->get();
        }

        // ─── PROCUREMENT APPROVALS ───
        $procurementApprovals = [];

        if ($user->can('purchase_requests.vp_approve')) {
            $procurementApprovals['purchase_requests'] = DB::table('purchase_requests')
                ->join('departments', 'purchase_requests.department_id', '=', 'departments.id')
                ->when(! $isGlobal, fn ($q) => $q->whereIn('purchase_requests.department_id', $deptIds ?? []))
                ->where('purchase_requests.status', 'reviewed')
                ->select(
                    'purchase_requests.id',
                    'purchase_requests.ulid',
                    'purchase_requests.pr_reference',
                    'departments.name as department',
                    'purchase_requests.total_estimated_cost',
                    'purchase_requests.justification',
                    'purchase_requests.created_at'
                )
                ->orderBy('purchase_requests.created_at')
                ->limit(20)
                ->get();
        }

        // ─── ACCOUNTING APPROVALS ───
        $accountingApprovals = [];

        if ($user->can('journal_entries.approve')) {
            $accountingApprovals['journal_entries'] = DB::table('journal_entries')
                ->where('status', 'submitted')
                ->select('id', 'ulid', 'reference', 'date', 'description', 'total_amount', 'created_at')
                ->orderBy('created_at')
                ->limit(20)
                ->get();
        }

        if ($user->can('vendor_invoices.approve')) {
            $accountingApprovals['vendor_invoices'] = DB::table('vendor_invoices')
                ->join('vendors', 'vendor_invoices.vendor_id', '=', 'vendors.id')
                ->where('vendor_invoices.status', 'submitted')
                ->select(
                    'vendor_invoices.id',
                    'vendor_invoices.ulid',
                    'vendor_invoices.invoice_number',
                    'vendors.name as vendor',
                    'vendor_invoices.net_amount',
                    'vendor_invoices.due_date',
                    'vendor_invoices.created_at'
                )
                ->orderBy('vendor_invoices.created_at')
                ->limit(20)
                ->get();
        }

        // ─── PAYROLL APPROVALS ───
        $payrollApprovals = [];

        if ($user->can('payroll.vp_approve')) {
            $payrollApprovals['payroll_runs'] = DB::table('payroll_runs')
                ->whereIn('status', ['submitted', 'hr_approved'])
                ->select('id', 'ulid', 'reference_no', 'pay_period_label', 'total_employees', 'net_pay_total_centavos', 'status', 'created_at')
                ->orderBy('created_at')
                ->limit(10)
                ->get();
        }

        // ─── SUMMARY COUNTS ───
        $summary = [
            'hr' => [
                'leaves' => count($hrApprovals['leaves'] ?? []),
                'overtime' => count($hrApprovals['overtime'] ?? []),
                'loans' => count($hrApprovals['loans'] ?? []),
                'total' => array_sum([
                    count($hrApprovals['leaves'] ?? []),
                    count($hrApprovals['overtime'] ?? []),
                    count($hrApprovals['loans'] ?? []),
                ]),
            ],
            'procurement' => [
                'purchase_requests' => count($procurementApprovals['purchase_requests'] ?? []),
                'total' => count($procurementApprovals['purchase_requests'] ?? []),
            ],
            'accounting' => [
                'journal_entries' => count($accountingApprovals['journal_entries'] ?? []),
                'vendor_invoices' => count($accountingApprovals['vendor_invoices'] ?? []),
                'total' => array_sum([
                    count($accountingApprovals['journal_entries'] ?? []),
                    count($accountingApprovals['vendor_invoices'] ?? []),
                ]),
            ],
            'payroll' => [
                'payroll_runs' => count($payrollApprovals['payroll_runs'] ?? []),
                'total' => count($payrollApprovals['payroll_runs'] ?? []),
            ],
        ];

        $summary['total_pending'] = array_sum([
            $summary['hr']['total'],
            $summary['procurement']['total'],
            $summary['accounting']['total'],
            $summary['payroll']['total'],
        ]);

        return response()->json([
            'summary' => $summary,
            'approvals' => [
                'hr' => $hrApprovals,
                'procurement' => $procurementApprovals,
                'accounting' => $accountingApprovals,
                'payroll' => $payrollApprovals,
            ],
            'meta' => [
                'scope' => $isGlobal ? 'global' : 'department',
                'department_ids' => $deptIds ?: null,
            ],
        ]);
    })->name('approvals.pending')->can('system.view_audit_log');

    // ─────────────────────────────────────────────────────────────────────────
    // Approval Statistics
    // GET /api/v1/approvals/stats
    // Returns high-level approval metrics for the current user.
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('stats', function (Request $request) {
        $user = $request->user();

        // Similar scope logic as pending
        $deptIds = DB::table('user_department_access')
            ->where('user_id', $user->id)
            ->pluck('department_id')
            ->toArray();

        $isGlobal = $user->hasAnyRole(['admin', 'executive', 'vice_president']);

        $empIds = $isGlobal ? null : DB::table('employees')
            ->whereIn('department_id', $deptIds)
            ->pluck('id');

        $today = now()->format('Y-m-d');
        $weekStart = now()->startOfWeek()->format('Y-m-d');

        return response()->json([
            'today' => [
                'approved_by_you' => DB::table('audits')
                    ->where('user_id', $user->id)
                    ->where('event', 'approved')
                    ->whereDate('created_at', $today)
                    ->count(),
                'rejected_by_you' => DB::table('audits')
                    ->where('user_id', $user->id)
                    ->where('event', 'rejected')
                    ->whereDate('created_at', $today)
                    ->count(),
            ],
            'this_week' => [
                'approved_by_you' => DB::table('audits')
                    ->where('user_id', $user->id)
                    ->where('event', 'approved')
                    ->whereBetween('created_at', [$weekStart, $today.' 23:59:59'])
                    ->count(),
            ],
            'avg_approval_time_hours' => null, // Would need additional tracking
        ]);
    })->name('approvals.stats');
});
