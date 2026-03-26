<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module Access Middleware — Enforces permission + department access control.
 *
 * This middleware combines permission checks with department-based SoD enforcement.
 * It ensures users can only access modules relevant to their department.
 *
 * Usage in routes:
 *   Route::middleware('module_access:accounting')
 *   Route::middleware('module_access:accounting,journal_entries.view')
 *
 * The module parameter maps to MODULE_DEPARTMENTS in the frontend:
 *   - accounting: ['ACCTG', 'EXEC']
 *   - hr: ['HR']
 *   - payroll: ['HR', 'ACCTG']
 *   - inventory: ['WH', 'PURCH', 'PROD', 'PLANT', 'SALES']
 *   - production: ['PROD', 'PLANT', 'PPC']
 *   - procurement: ['PURCH', 'PROD', 'PLANT']
 *   - qc: ['QC', 'PROD', 'WH']
 *   - maintenance: ['MAINT', 'PROD', 'PLANT']
 *   - mold: ['MOLD', 'PROD']
 *   - delivery: ['WH', 'SALES', 'PROD', 'PLANT']
 *   - crm: ['SALES']
 *   - ar: ['SALES', 'ACCTG']
 *   - ap: ['ACCTG', 'PURCH']
 *
 * Bypass roles (see all departments):
 *   admin, super_admin, executive, vice_president
 */
class ModuleAccessMiddleware
{
    /** @var array<string, list<string>> */
    private const MODULE_DEPARTMENTS = [
        'accounting' => ['ACCTG', 'EXEC'],
        'journal_entries' => ['ACCTG'],
        'chart_of_accounts' => ['ACCTG'],
        'fiscal_periods' => ['ACCTG'],

        // HR module: HR dept manages all employee data; other depts access team-view endpoints
        // via fine-grained permission checks (employees.view_team, attendance.view_team, etc.)
        'hr' => ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
        'employees' => ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
        'attendance' => ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
        'leaves' => ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
        'overtime' => ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],

        'payroll' => ['HR', 'ACCTG'],
        'loans' => ['HR', 'ACCTG'],

        'ap' => ['ACCTG', 'PURCH'],
        'vendors' => ['ACCTG', 'PURCH'],
        'vendor_invoices' => ['ACCTG'],
        'vendor_payments' => ['ACCTG'],

        'ar' => ['SALES', 'ACCTG', 'PURCH'],
        'customers' => ['SALES', 'ACCTG', 'PURCH'],
        'customer_invoices' => ['SALES', 'ACCTG'],

        'tax' => ['ACCTG'],
        'vat_ledger' => ['ACCTG'],
        'bir_filing' => ['ACCTG'],

        'banking' => ['ACCTG'],
        'bank_accounts' => ['ACCTG'],
        'bank_reconciliations' => ['ACCTG'],

        'fixed_assets' => ['ACCTG'],
        'budget' => ['ACCTG', 'EXEC'],

        'procurement' => ['PURCH', 'PROD', 'PLANT', 'ACCTG', 'WH'],
        'purchase_requests' => ['PURCH', 'PROD', 'PLANT', 'ACCTG'],
        'purchase_orders' => ['PURCH'],
        'goods_receipts' => ['PURCH', 'WH'],

        'inventory' => ['WH', 'PURCH', 'PROD', 'PLANT', 'SALES'],
        'items' => ['WH', 'PURCH', 'PROD', 'SALES'],
        'stock' => ['WH', 'PURCH', 'PROD', 'SALES'],
        'requisitions' => ['WH', 'PURCH', 'PROD'],
        'adjustments' => ['WH'],
        'locations' => ['WH'],

        'production' => ['PROD', 'PLANT', 'PPC'],
        'work_orders' => ['PROD', 'PLANT'],
        'boms' => ['PROD', 'PLANT'],
        'delivery_schedules' => ['PROD', 'PLANT', 'PPC'],

        'qc' => ['QC', 'PROD', 'WH'],
        'inspections' => ['QC', 'PROD', 'WH'],
        'ncr' => ['QC'],
        'capa' => ['QC'],

        'maintenance' => ['MAINT', 'PROD', 'PLANT'],
        'equipment' => ['MAINT', 'PROD', 'PLANT'],

        'mold' => ['MOLD', 'PROD'],
        'mold_masters' => ['MOLD', 'PROD'],

        'delivery' => ['WH', 'SALES', 'PROD', 'PLANT'],
        'shipments' => ['WH', 'SALES', 'PROD', 'PLANT'],

        'iso' => ['ISO', 'QC'],
        'crm' => ['SALES'],
        'tickets' => ['SALES'],

        'admin' => ['IT', 'EXEC'],
        'users' => ['IT', 'EXEC'],
        'settings' => ['IT', 'EXEC'],

        // Approvals (VP/Executive level)
        'approvals' => ['EXEC', 'VP'],

        // Reports (HR and Accounting)
        'reports' => ['HR', 'ACCTG', 'EXEC'],
    ];

    private const BYPASS_ROLES = ['admin', 'super_admin', 'executive', 'vice_president'];

    /**
     * Handle an incoming request.
     *
     * @param  string  $module  The module key (e.g., 'accounting', 'hr')
     * @param  string|null  $permission  Optional specific permission to check
     */
    public function handle(Request $request, Closure $next, string $module, ?string $permission = null): Response
    {
        $user = $request->user();

        // Unauthenticated - let auth middleware handle it
        if (! $user) {
            return $next($request);
        }

        // Bypass roles can access any module
        if ($user->hasAnyRole(self::BYPASS_ROLES)) {
            return $next($request);
        }

        // Client portal users can access CRM module (for tickets and orders)
        if ($user->hasRole('client') && $module === 'crm') {
            return $next($request);
        }

        // Vendor portal users can access vendors module
        if ($user->hasRole('vendor') && in_array($module, ['vendors', 'vendor_portal'], true)) {
            return $next($request);
        }

        // Check permission if specified
        if ($permission !== null && ! $user->can($permission)) {
            abort(403, 'Permission denied');
        }

        // Dept heads with the create-dept permission can reach purchase-request endpoints
        // regardless of their home department (e.g. HR Head, Sales Head raising a PR).
        if (in_array($module, ['procurement', 'purchase_requests'], true)
            && $user->hasPermissionTo('procurement.purchase-request.create-dept')) {
            return $next($request);
        }

        // Check department access
        $allowedDepts = self::MODULE_DEPARTMENTS[$module] ?? null;

        if ($allowedDepts === null) {
            // Unknown module - log warning and deny
            Log::warning('ModuleAccessMiddleware: Unknown module', [
                'module' => $module,
                'user_id' => $user->id,
                'url' => $request->fullUrl(),
            ]);
            abort(403, 'Module access denied');
        }

        // Get user's department code
        $userDeptCode = $this->getUserDepartmentCode($user);

        if ($userDeptCode === null) {
            abort(403, 'Department not assigned');
        }

        // Check if user's department is in allowed list
        if (! in_array($userDeptCode, $allowedDepts, true)) {
            // Log violation for audit
            Log::warning('Module access denied - wrong department', [
                'user_id' => $user->id,
                'user_dept' => $userDeptCode,
                'required_depts' => $allowedDepts,
                'module' => $module,
                'url' => $request->fullUrl(),
            ]);

            abort(403, 'Access restricted to specific departments');
        }

        return $next($request);
    }

    /**
     * Get the user's primary department code.
     */
    private function getUserDepartmentCode($user): ?string
    {
        // Try to get from loaded relationship
        $primaryDept = $user->departments
            ->firstWhere('pivot.is_primary', true) ?? $user->departments->first();

        if ($primaryDept) {
            return $primaryDept->code;
        }

        // Fallback: load from database
        $dept = $user->departments()
            ->select(['departments.code', 'user_department_access.is_primary'])
            ->first();

        return $dept?->code;
    }
}
