<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Role-Based Dashboard Service — returns KPIs tailored to the user's role.
 *
 * Each role sees a different default dashboard view:
 *   - Executive/VP: financial KPIs, cash position, budget utilization
 *   - Production Manager: OEE, yield, production schedule, mold status
 *   - HR Manager: headcount, turnover, pending approvals, attendance
 *   - Accounting Manager: GL health, unreconciled items, tax deadlines
 *   - Warehouse Manager: stock alerts, pending GRs, MRQ queue
 *   - Sales Manager: pipeline, quotations, delivery status
 *   - Employee: own payslips, leave balance, loan balance, attendance
 *
 * Configurable via system_settings keys like 'dashboard.production_roles'.
 */
final class RoleBasedDashboardService implements ServiceContract
{
    /**
     * Get role-appropriate dashboard KPIs for the authenticated user.
     *
     * @return array{role_dashboard: string, kpis: array<string, mixed>}
     */
    public function forUser(User $user): array
    {
        $dashboardType = $this->resolveDashboardType($user);

        $kpis = match ($dashboardType) {
            'executive' => $this->executiveKpis(),
            'production' => $this->productionKpis(),
            'hr' => $this->hrKpis(),
            'accounting' => $this->accountingKpis(),
            'warehouse' => $this->warehouseKpis(),
            'sales' => $this->salesKpis(),
            'employee' => $this->employeeKpis($user),
            default => $this->executiveKpis(),
        };

        return [
            'role_dashboard' => $dashboardType,
            'kpis' => $kpis,
        ];
    }

    /**
     * Resolve which dashboard type to show based on user roles/permissions.
     */
    private function resolveDashboardType(User $user): string
    {
        if ($user->hasRole(['super_admin', 'admin', 'executive', 'vice_president'])) {
            return 'executive';
        }

        // Check module-specific permissions to determine role dashboard
        if ($user->hasPermissionTo('production.order.manage') || $user->hasPermissionTo('production.order.view')) {
            return 'production';
        }

        if ($user->hasPermissionTo('hr.employee.manage') || $user->hasPermissionTo('payroll.initiate')) {
            return 'hr';
        }

        if ($user->hasPermissionTo('accounting.je.manage') || $user->hasPermissionTo('ap.invoice.manage')) {
            return 'accounting';
        }

        if ($user->hasPermissionTo('inventory.stock.manage') || $user->hasPermissionTo('inventory.mrq.manage')) {
            return 'warehouse';
        }

        if ($user->hasPermissionTo('sales.order.manage') || $user->hasPermissionTo('crm.order.manage')) {
            return 'sales';
        }

        return 'employee';
    }

    private function executiveKpis(): array
    {
        $today = now()->toDateString();
        $currentYear = (int) now()->format('Y');

        return [
            'revenue_this_month' => (int) DB::table('customer_invoices')
                ->where('status', 'approved')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', $currentYear)
                ->sum('subtotal') * 100,
            'cash_position' => (float) DB::table('bank_accounts')
                ->whereNull('deleted_at')
                ->sum('current_balance'),
            'open_ar' => (int) DB::table('customer_invoices')
                ->whereIn('status', ['approved', 'partially_paid'])
                ->whereNull('deleted_at')
                ->count(),
            'overdue_ap' => (int) DB::table('vendor_invoices')
                ->whereIn('status', ['approved', 'partially_paid'])
                ->where('due_date', '<', $today)
                ->whereNull('deleted_at')
                ->count(),
            'active_employees' => (int) DB::table('employees')
                ->where('employment_status', 'active')
                ->whereNull('deleted_at')
                ->count(),
            'pending_approvals' => $this->countPendingApprovals(),
        ];
    }

    private function productionKpis(): array
    {
        return [
            'active_orders' => (int) DB::table('production_orders')
                ->whereIn('status', ['draft', 'scheduled', 'released', 'in_progress'])
                ->whereNull('deleted_at')
                ->count(),
            'completed_this_month' => (int) DB::table('production_orders')
                ->where('status', 'completed')
                ->whereMonth('updated_at', now()->month)
                ->whereNull('deleted_at')
                ->count(),
            'pending_mrqs' => (int) DB::table('material_requisitions')
                ->whereNotIn('status', ['fulfilled', 'cancelled', 'rejected'])
                ->count(),
            'molds_near_limit' => (int) DB::table('mold_masters')
                ->where('status', 'active')
                ->whereNotNull('max_shots')
                ->whereRaw('current_shots >= max_shots * 0.9')
                ->count(),
            'yield_rate_this_month' => $this->calculateYieldRate(),
            'pending_inspections' => (int) DB::table('inspections')
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
        ];
    }

    private function hrKpis(): array
    {
        $currentYear = (int) now()->format('Y');

        return [
            'total_employees' => (int) DB::table('employees')
                ->where('employment_status', 'active')
                ->whereNull('deleted_at')
                ->count(),
            'new_hires_this_month' => (int) DB::table('employees')
                ->where('employment_status', 'active')
                ->whereMonth('hire_date', now()->month)
                ->whereYear('hire_date', $currentYear)
                ->whereNull('deleted_at')
                ->count(),
            'separations_this_year' => (int) DB::table('employees')
                ->whereIn('employment_status', ['resigned', 'terminated'])
                ->whereYear('updated_at', $currentYear)
                ->whereNull('deleted_at')
                ->count(),
            'pending_leave_requests' => (int) DB::table('leave_requests')
                ->whereNotIn('status', ['approved', 'rejected', 'cancelled'])
                ->where('status', '!=', 'draft')
                ->count(),
            'pending_loan_requests' => (int) DB::table('loans')
                ->whereNotIn('status', ['active', 'fully_paid', 'cancelled', 'written_off'])
                ->where('status', '!=', 'pending')
                ->count(),
            'absent_today' => (int) DB::table('attendance_logs')
                ->where('work_date', now()->toDateString())
                ->where('is_absent', true)
                ->count(),
        ];
    }

    private function accountingKpis(): array
    {
        $today = now()->toDateString();

        return [
            'unposted_jes' => (int) DB::table('journal_entries')
                ->whereIn('status', ['draft', 'submitted'])
                ->whereNull('deleted_at')
                ->count(),
            'open_fiscal_periods' => (int) DB::table('fiscal_periods')
                ->where('status', 'open')
                ->count(),
            'unreconciled_transactions' => (int) DB::table('bank_transactions')
                ->where('status', 'pending')
                ->count(),
            'overdue_ap_total' => (float) DB::table('vendor_invoices')
                ->whereIn('status', ['approved', 'partially_paid'])
                ->where('due_date', '<', $today)
                ->whereNull('deleted_at')
                ->sum('balance_due'),
            'overdue_ar_total' => (float) DB::table('customer_invoices')
                ->whereIn('status', ['approved', 'partially_paid'])
                ->where('due_date', '<', $today)
                ->whereNull('deleted_at')
                ->sum('balance_due'),
            'pending_tax_filings' => (int) DB::table('bir_filings')
                ->where('status', 'pending')
                ->count(),
        ];
    }

    private function warehouseKpis(): array
    {
        return [
            'low_stock_items' => (int) DB::table('item_masters')
                ->join('stock_balances', 'item_masters.id', '=', 'stock_balances.item_id')
                ->where('item_masters.is_active', true)
                ->whereRaw('CAST(stock_balances.quantity_on_hand AS numeric) <= CAST(item_masters.reorder_point AS numeric)')
                ->whereRaw('CAST(item_masters.reorder_point AS numeric) > 0')
                ->whereNull('item_masters.deleted_at')
                ->count(),
            'pending_grs' => (int) DB::table('goods_receipts')
                ->where('status', 'draft')
                ->count(),
            'pending_mrqs' => (int) DB::table('material_requisitions')
                ->whereIn('status', ['approved', 'submitted', 'noted', 'checked', 'reviewed'])
                ->count(),
            'total_stock_value' => (float) DB::table('stock_balances')
                ->join('item_masters', 'stock_balances.item_id', '=', 'item_masters.id')
                ->selectRaw('COALESCE(SUM(CAST(stock_balances.quantity_on_hand AS numeric) * COALESCE(item_masters.standard_price_centavos, 0) / 100.0), 0) as total')
                ->value('total'),
            'pending_physical_counts' => (int) DB::table('physical_counts')
                ->whereIn('status', ['draft', 'in_progress', 'pending_approval'])
                ->count(),
        ];
    }

    private function salesKpis(): array
    {
        $currentYear = (int) now()->format('Y');

        return [
            'open_quotations' => (int) DB::table('quotations')
                ->whereIn('status', ['draft', 'sent'])
                ->whereNull('deleted_at')
                ->count(),
            'pending_orders' => (int) DB::table('sales_orders')
                ->whereIn('status', ['draft', 'confirmed'])
                ->whereNull('deleted_at')
                ->count(),
            'client_orders_pending' => (int) DB::table('client_orders')
                ->whereIn('status', ['pending', 'negotiating', 'client_responded'])
                ->count(),
            'revenue_this_month' => (int) DB::table('client_orders')
                ->where('status', 'approved')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', $currentYear)
                ->sum('total_amount_centavos'),
            'overdue_deliveries' => (int) DB::table('delivery_schedules')
                ->whereIn('status', ['open', 'in_production'])
                ->where('target_delivery_date', '<', now()->toDateString())
                ->count(),
            'open_tickets' => (int) DB::table('tickets')
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
        ];
    }

    private function employeeKpis(User $user): array
    {
        $employee = DB::table('employees')
            ->where('user_id', $user->id)
            ->first();

        if ($employee === null) {
            return ['message' => 'No employee record linked to this user.'];
        }

        $currentYear = (int) now()->format('Y');

        return [
            'leave_balance' => DB::table('leave_balances')
                ->where('employee_id', $employee->id)
                ->where('year', $currentYear)
                ->select('leave_type_id', 'balance', 'used')
                ->get()
                ->toArray(),
            'active_loans' => (int) DB::table('loans')
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->count(),
            'attendance_this_month' => [
                'present' => (int) DB::table('attendance_logs')
                    ->where('employee_id', $employee->id)
                    ->whereMonth('work_date', now()->month)
                    ->where('is_present', true)
                    ->count(),
                'absent' => (int) DB::table('attendance_logs')
                    ->where('employee_id', $employee->id)
                    ->whereMonth('work_date', now()->month)
                    ->where('is_absent', true)
                    ->count(),
            ],
            'pending_requests' => [
                'leave' => (int) DB::table('leave_requests')
                    ->where('employee_id', $employee->id)
                    ->whereNotIn('status', ['approved', 'rejected', 'cancelled'])
                    ->count(),
                'overtime' => (int) DB::table('overtime_requests')
                    ->where('employee_id', $employee->id)
                    ->where('status', 'pending')
                    ->count(),
            ],
        ];
    }

    private function countPendingApprovals(): array
    {
        return [
            'leave' => (int) DB::table('leave_requests')
                ->whereNotIn('status', ['approved', 'rejected', 'cancelled', 'draft'])
                ->count(),
            'loan' => (int) DB::table('loans')
                ->whereNotIn('status', ['active', 'fully_paid', 'cancelled', 'written_off', 'pending'])
                ->count(),
            'pr' => (int) DB::table('purchase_requests')
                ->whereIn('status', ['pending_review', 'reviewed', 'budget_verified'])
                ->count(),
            'payroll' => (int) DB::table('payroll_runs')
                ->whereIn('status', ['REVIEW', 'SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED'])
                ->count(),
        ];
    }

    private function calculateYieldRate(): float
    {
        $result = DB::table('production_output_logs')
            ->whereMonth('log_date', now()->month)
            ->selectRaw('COALESCE(SUM(CAST(qty_produced AS numeric)), 0) as produced')
            ->selectRaw('COALESCE(SUM(CAST(qty_rejected AS numeric)), 0) as rejected')
            ->first();

        $produced = (float) ($result->produced ?? 0);
        $rejected = (float) ($result->rejected ?? 0);
        $total = $produced + $rejected;

        return $total > 0 ? round(($produced / $total) * 100, 2) : 100.0;
    }
}
