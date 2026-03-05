import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type { LeaveRequest, Loan, OvertimeRequest } from '@/types/hr'
import type { PayrollRun } from '@/types/payroll'

// ============================================================================
// Dashboard Types
// ============================================================================

export interface ManagerDashboardStats {
  department: {
    id: number
    name: string
    code: string
  }
  headcount: {
    total: number
    active: number
    on_leave: number
  }
  pending_approvals: {
    leaves: number
    overtime: number
    loans: number
    total: number
  }
  recent_requests: {
    leaves: LeaveRequest[]
    overtime: OvertimeRequest[]
    loans: Loan[]
  }
  attendance_today: {
    present: number
    absent: number
    late: number
    on_leave: number
  }
  analytics?: {
    attendance_trend: Array<{ month: string; rate: number; total: number }>
    leave_by_type: Array<{ name: string; total_days: number }>
    overtime_trend: Array<{ month: string; hours: number }>
    tenure_distribution: {
      less_than_1_year: number
      '1_to_3_years': number
      '3_to_5_years': number
      more_than_5_years: number
    }
    comparison: {
      dept_attendance_rate: number
      company_avg_attendance: number
      vs_company_avg: number
    }
  }
}

export interface HrDashboardStats {
  company_wide: {
    total_employees: number
    total_departments: number
    new_hires_this_month: number
  }
  pending_approvals: {
    leaves: number
    overtime: number
    loans: number
    total: number
  }
  by_department: Array<{
    department: string
    count: number
  }>
  attendance_summary: {
    present: number
    absent: number
    late: number
    total: number
  } | null
  active_payroll: PayrollRun | null
  analytics?: {
    headcount_trend: Array<{ month: string; count: number }>
    turnover_by_department: Array<{ name: string; count: number }>
    avg_tenure_by_dept: Array<{ name: string; avg_years: number }>
    hires_vs_terminations: Array<{ month: string; hires: number; terminations: number; net_change: number }>
    leave_utilization: Array<{ name: string; total_days: number }>
    payroll_trend: Array<{ month: string; amount: number; amount_php: number }>
    overall_turnover_rate: number
  }
}

export interface AccountingDashboardStats {
  pending_approvals: {
    loans_for_accounting: number
    journal_entries: number
    vendor_invoices: number
    payroll_for_review: number
    total: number
  }
  financial_summary: {
    pending_vendor_invoices: number
    pending_customer_invoices: number
    unreconciled_bank_accounts: number
  }
  active_payroll: PayrollRun | null
  current_fiscal_period: {
    id: number
    name: string
    date_from: string
    date_to: string
  } | null
  analytics?: {
    ap_aging: {
      current: number
      '1_30_days': number
      '31_60_days': number
      over_60_days: number
    }
    ar_aging: {
      current: number
      '1_30_days': number
      '31_60_days': number
      over_60_days: number
    }
    expenses_by_month: Array<{ month: string; amount: number }>
    top_expense_categories: Array<{ name: string; total: number }>
    cash_position: {
      account_count: number
      total_balance: number
    }
    liabilities_trend: Array<{ month: string; amount: number }>
    revenue_vs_expense: Array<{ month: string; revenue: number; expenses: number; net: number }>
  }
}

export interface AdminDashboardStats {
  system_health: {
    active_users: number
    total_users: number
    locked_accounts: number
    failed_logins_today: number
  }
  recent_activity: {
    logins_today: number
    password_changes: number
    new_users: number
  }
  system_status: {
    last_backup: string | null
    horizon_status: 'running' | 'stopped' | 'unknown'
    queue_size: number
  }
}

export interface StaffDashboardStats {
  attendance: {
    this_month: {
      present: number
      absent: number
      late: number
      ot_hours: number
    }
  }
  leave: {
    balance_days: number
    pending_requests: number
    approved_upcoming: number
  }
  loans: {
    active_loans: number
    total_outstanding: number
    pending_approvals: number
  }
  payroll: {
    last_payslip_date: string | null
    ytd_gross: number
    ytd_net: number
  }
  recent_requests: {
    leaves: LeaveRequest[]
    overtime: OvertimeRequest[]
    loans: Loan[]
  }
  analytics?: {
    attendance_rate: Array<{ month: string; rate: number }>
    leave_utilization: Array<{ name: string; days_used: number }>
    ytd_comparison: {
      current_year_gross: number
      last_year_gross: number
      change_percent: number
    }
  }
}

export interface ExecutiveDashboardStats {
  company_overview: {
    total_employees: number
    total_departments: number
    active_projects: number
  }
  financial_health: {
    current_month_payroll: number
    pending_vendor_invoices: number
    pending_customer_invoices: number
  }
  pending_executive_approvals: {
    leaves: number
    high_value_loans: number
    total: number
  }
  key_metrics: {
    headcount_change: number
    attrition_rate: number
    avg_tenure_years: number
  }
  analytics?: {
    revenue_expense_trend: Array<{ month: string; revenue: number; expenses: number; profit: number; profit_margin: number }>
    department_cost_allocation: Array<{ department: string; cost: number }>
    headcount_by_department: Array<{ name: string; count: number }>
    financial_ratios: {
      gross_profit_margin: number
      current_ratio: number
      debt_to_equity: number
      ytd_revenue: number
      ytd_expenses: number
    }
    payroll_by_department: Array<{ department: string; total_payroll: number; employee_count: number; avg_per_employee: number }>
  }
}

export interface HeadDashboardStats {
  team: {
    member_count: number
    present_today: number
    on_leave: number
  }
  pending_approvals: {
    leaves: number
    overtime: number
    loans: number
    total: number
  }
  team_attendance: {
    this_week: {
      present: number
      absent: number
      late: number
    }
  }
  recent_requests: {
    leaves: LeaveRequest[]
    overtime: OvertimeRequest[]
    loans: Loan[]
  }
  analytics?: {
    weekly_attendance_rate: Array<{ week: string; rate: number }>
    overtime_by_employee: Array<{ employee: string; hours: number }>
    leave_calendar: Array<{ date_from: string; date_to: string; days: number; employee: string; type: string }>
  }
}

// ============================================================================
// Dashboard Hooks
// ============================================================================

export function useManagerDashboardStats(departmentId: number | null) {
  return useQuery({
    queryKey: ['manager-dashboard', departmentId],
    queryFn: async () => {
      const res = await api.get<ManagerDashboardStats>('/dashboard/manager', {
        params: departmentId ? { department_id: departmentId } : {},
      })
      return res.data
    },
    enabled: departmentId !== null,
    staleTime: 60_000,
  })
}

export function useHrDashboardStats() {
  return useQuery({
    queryKey: ['hr-dashboard'],
    queryFn: async () => {
      const res = await api.get<HrDashboardStats>('/dashboard/hr')
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useAccountingDashboardStats() {
  return useQuery({
    queryKey: ['accounting-dashboard'],
    queryFn: async () => {
      const res = await api.get<AccountingDashboardStats>('/dashboard/accounting')
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useAdminDashboardStats() {
  return useQuery({
    queryKey: ['admin-dashboard'],
    queryFn: async () => {
      const res = await api.get<AdminDashboardStats>('/dashboard/admin')
      return res.data
    },
    staleTime: 30_000,
    refetchInterval: 60_000,
    refetchIntervalInBackground: false,
  })
}

export function useStaffDashboardStats() {
  return useQuery({
    queryKey: ['staff-dashboard'],
    queryFn: async () => {
      const res = await api.get<StaffDashboardStats>('/dashboard/staff')
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useExecutiveDashboardStats() {
  return useQuery({
    queryKey: ['executive-dashboard'],
    queryFn: async () => {
      const res = await api.get<ExecutiveDashboardStats>('/dashboard/executive')
      return res.data
    },
    staleTime: 120_000,
  })
}

export function useHeadDashboardStats() {
  return useQuery({
    queryKey: ['head-dashboard'],
    queryFn: async () => {
      const res = await api.get<HeadDashboardStats>('/dashboard/head')
      return res.data
    },
    staleTime: 60_000,
  })
}

/** Alias for useHeadDashboardStats — used by the supervisor (head) role dashboard. */
export function useSupervisorDashboardStats() {
  return useHeadDashboardStats()
}

// ============================================================================
// Vice President Dashboard
// ============================================================================

export interface VicePresidentDashboardStats {
  pending_approvals: {
    loans: number
    purchase_requests: number
    mrq: number
    total: number
  }
  financial_summary: {
    total_payroll_this_month: number
    pending_vendor_invoices: number
    pending_customer_invoices: number
    open_production_orders: number
  }
  recent_approvals: Array<{
    type: string
    reference: string
    requestor: string
    amount?: number
    submitted_at: string
  }>
}

export function useVicePresidentDashboardStats() {
  return useQuery({
    queryKey: ['vp-dashboard'],
    queryFn: async () => {
      const res = await api.get<VicePresidentDashboardStats>('/dashboard/vp')
      return res.data
    },
    staleTime: 60_000,
  })
}

// ============================================================================
// Officer Dashboard
// ============================================================================

export interface OfficerDashboardStats {
  accounting: {
    pending_vendor_invoices: number
    pending_customer_invoices: number
    journal_entries_to_post: number
    bank_recon_due: number
  }
  procurement: {
    pending_pr_review: number
    open_pos: number
    pending_gr: number
  }
  delivery: {
    inbound_draft: number
    outbound_draft: number
    in_transit_shipments: number
  }
  payroll: {
    runs_pending_acctg_approval: number
    next_pay_date: string | null
  }
}

export function useOfficerDashboardStats() {
  return useQuery({
    queryKey: ['officer-dashboard'],
    queryFn: async () => {
      const res = await api.get<OfficerDashboardStats>('/dashboard/officer')
      return res.data
    },
    staleTime: 60_000,
  })
}
