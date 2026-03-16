// ---------------------------------------------------------------------------
// Payroll Domain Types
// ---------------------------------------------------------------------------

/** Legacy statuses kept for backward compat + all v1.0 workflow statuses */
export type PayrollRunStatus =
  // v1.0 workflow statuses (canonical)
  | 'DRAFT'
  | 'SCOPE_SET'
  | 'PRE_RUN_CHECKED'
  | 'PROCESSING'
  | 'COMPUTED'
  | 'REVIEW'
  | 'SUBMITTED'
  | 'HR_APPROVED'
  | 'ACCTG_APPROVED'
  | 'DISBURSED'
  | 'PUBLISHED'
  | 'FAILED'
  | 'RETURNED'
  | 'REJECTED'
  // Legacy statuses
  | 'draft'
  | 'locked'
  | 'processing'
  | 'completed'
  | 'submitted'
  | 'approved'
  | 'posted'
  | 'failed'
  | 'cancelled'

export type PayrollRunType =
  | 'regular'
  | 'thirteenth_month'
  | 'adjustment'
  | 'year_end_reconciliation'
  | 'final_pay'

// ── Workflow v1.0 helpers ─────────────────────────────────────────────────

/** Map a raw status string to a human-readable label */
export const PAYROLL_STATUS_LABELS: Record<string, string> = {
  DRAFT: 'Draft',
  SCOPE_SET: 'Scope Set',
  PRE_RUN_CHECKED: 'Pre-Run Checked',
  PROCESSING: 'Processing',
  COMPUTED: 'Computed',
  REVIEW: 'Under Review',
  SUBMITTED: 'Submitted for HR Review',
  HR_APPROVED: 'HR Approved',
  ACCTG_APPROVED: 'Accounting Approved',
  DISBURSED: 'Disbursed',
  PUBLISHED: 'Published',
  FAILED: 'Failed',
  RETURNED: 'Returned',
  REJECTED: 'Rejected',
  // legacy
  draft: 'Draft',
  locked: 'Locked',
  processing: 'Processing',
  completed: 'Completed',
  submitted: 'Submitted',
  approved: 'Approved',
  posted: 'Posted',
  failed: 'Failed',
  cancelled: 'Cancelled',
}

/** Returns the wizard step number (1–8) for a given status; null if not applicable */
export function statusToWizardStep(status: PayrollRunStatus): number | null {
  const map: Partial<Record<string, number>> = {
    DRAFT: 1,
    SCOPE_SET: 2,
    PRE_RUN_CHECKED: 3,
    PROCESSING: 4,
    COMPUTED: 4,
    REVIEW: 5,
    SUBMITTED: 6,
    HR_APPROVED: 7,
    RETURNED: 6,
    ACCTG_APPROVED: 8,
    DISBURSED: 8,
    PUBLISHED: 8,
  }
  return map[status] ?? null
}

/** Human label for run types */
export const RUN_TYPE_LABELS: Record<PayrollRunType, string> = {
  regular: 'Regular Payroll',
  thirteenth_month: '13th Month',
  adjustment: 'Adjustment',
  year_end_reconciliation: 'Year-End Reconciliation',
  final_pay: 'Final Pay',
}

// ── Scope / Exclusion types ───────────────────────────────────────────────

export interface PayrollRunExclusion {
  id: number
  payroll_run_id: number
  employee_id: number
  reason: string
  excluded_by_id: number
  excluded_at: string
  employee?: { id: number; first_name: string; last_name: string; employee_code: string }
}

export interface ScopeFilters {
  departments?: number[]
  positions?: number[]
  employment_types?: string[]
  include_unpaid_leave?: boolean
  include_probation_end?: boolean
  exclude_no_attendance?: boolean
  exclusions?: Array<{ employee_id: number; reason: string }>
}

export interface ScopePreview {
  total_eligible: number
  manually_excluded: number
  net_in_scope: number
  by_department: Array<{
    department_id: number
    department_name: string
    eligible: number
    excluded: number
    in_scope: number
  }>
  missing_bank_employees: Array<{
    id: number
    full_name: string
    employee_code: string
    department_name: string
  }>
}

// ── Pre-Run Check types ───────────────────────────────────────────────────

export interface PreRunCheckResult {
  code: string // e.g. PR-001
  label: string
  severity: 'block' | 'warn'
  status: 'pass' | 'block' | 'warn'
  message: string
  details?: {
    employees?: Array<{ full_name: string; employee_code: string }>
    by_department?: Array<{ dept_id: number; dept_name: string; count: number }>
  } | null
}

export interface PreRunValidationResult {
  has_blockers: boolean
  total_passed: number
  checks: PreRunCheckResult[]
}

// ── Approval history ──────────────────────────────────────────────────────

export type ApprovalStage = 'HR_REVIEW' | 'ACCOUNTING'
export type ApprovalAction = 'APPROVED' | 'RETURNED' | 'REJECTED'

export interface PayrollRunApproval {
  id: number
  payroll_run_id: number
  stage: ApprovalStage
  action: ApprovalAction
  actor_id: number
  actor?: { id: number; name: string }
  comments: string | null
  checkboxes_checked: string[] | null
  acted_at: string
  created_at: string
}

// ── GL Preview (Step 7) ───────────────────────────────────────────────────

export interface GlEntry {
  account: string
  description: string
  amount: number
}

export interface GlPreview {
  total_net_pay: number
  total_gross: number
  total_deductions: number
  total_sss_ee: number
  total_sss_er: number
  total_philhealth_ee: number
  total_philhealth_er: number
  total_pagibig_ee: number
  total_pagibig_er: number
  total_withholding_tax: number
  total_loan_deductions: number
  total_other_deductions: number
  total_cash_outflow: number
  debits: GlEntry[]
  credits: GlEntry[]
}

// ── Computation progress ──────────────────────────────────────────────────

export interface ComputationProgress {
  status: PayrollRunStatus
  started_at: string | null
  finished_at: string | null
  total_employees?: number
  employees_processed?: number
  percent_complete?: number
  current_department?: string
  error?: string
}

export interface PayrollRun {
  id: number
  ulid: string
  reference_no: string
  pay_period_id: number | null
  pay_period_label: string
  cutoff_start: string // ISO date
  cutoff_end: string // ISO date
  pay_date: string // ISO date
  status: PayrollRunStatus
  run_type: PayrollRunType
  total_employees: number
  gross_pay_total: number // centavos (legacy key)
  gross_pay_total_centavos: number
  total_deductions: number // centavos (legacy key)
  total_deductions_centavos: number
  net_pay_total: number // centavos (legacy key)
  net_pay_total_centavos: number
  notes: string | null
  created_by: number
  /** SoD subject — the user who initiated/locked this run. */
  initiated_by_id: number | null
  approved_by: number | null
  approved_at: string | null
  locked_at: string | null
  // v1.0 workflow fields
  scope_departments: number[] | null
  scope_positions: number[] | null
  scope_employment_types: string[] | null
  scope_include_unpaid_leave: boolean
  scope_include_probation_end: boolean
  scope_exclude_no_attendance: boolean
  scope_confirmed_at: string | null
  pre_run_checks_json: PreRunCheckResult[] | null
  pre_run_acknowledged_at: string | null
  pre_run_acknowledged_by_id: number | null
  computation_started_at: string | null
  computation_completed_at: string | null
  progress_json: ComputationProgress | null
  hr_approved_by_id: number | null
  hr_approved_at: string | null
  acctg_approved_by_id: number | null
  acctg_approved_at: string | null
  posted_at: string | null
  published_at: string | null
  publish_scheduled_at: string | null
  exclusions?: PayrollRunExclusion[]
  created_at: string
  updated_at: string
}

export interface LoanDeductionDetail {
  loan_id: number
  amount_centavos: number
  amortisation_centavos?: number
  deducted_centavos?: number
  deferred?: boolean
}

export interface PayrollDetailEmployee {
  id: number
  employee_code: string
  first_name: string
  last_name: string
}

export interface PayrollDetail {
  id: number
  payroll_run_id: number
  employee_id: number
  employee?: PayrollDetailEmployee

  // Snapshot
  basic_monthly_rate_centavos: number
  daily_rate_centavos: number
  hourly_rate_centavos: number
  working_days_in_period: number
  pay_basis: string

  // Attendance
  days_worked: number
  days_absent: number
  days_late_minutes: number
  undertime_minutes: number
  overtime_regular_minutes: number
  overtime_rest_day_minutes: number
  overtime_holiday_minutes: number
  night_diff_minutes: number
  regular_holiday_days: number
  special_holiday_days: number
  leave_days_paid: number
  leave_days_unpaid: number

  // Earnings
  basic_pay_centavos: number
  overtime_pay_centavos: number
  holiday_pay_centavos: number
  night_diff_pay_centavos: number
  gross_pay_centavos: number

  // Gov deductions
  sss_ee_centavos: number
  sss_er_centavos: number
  philhealth_ee_centavos: number
  philhealth_er_centavos: number
  pagibig_ee_centavos: number
  pagibig_er_centavos: number
  withholding_tax_centavos: number

  // Loans & others
  loan_deductions_centavos: number
  loan_deduction_detail: LoanDeductionDetail[] | null
  other_deductions_centavos: number
  total_deductions_centavos: number
  net_pay_centavos: number

  // Flags
  is_below_min_wage: boolean
  has_deferred_deductions: boolean
  // v1.0 workflow flags
  ln007_applied: boolean
  ln007_truncated_amt: number | null // centavos
  ln007_carried_fwd: number | null // centavos
  edge_cases_applied: string[] | null
  employee_flag: 'none' | 'flagged' | 'resolved'
  review_note: string | null

  // YTD
  ytd_taxable_income_centavos: number
  ytd_tax_withheld_centavos: number

  status: string
  notes: string | null
  created_at: string
  updated_at: string
}

export interface PayrollAdjustment {
  id: number
  payroll_run_id: number
  employee_id: number
  type: 'earning' | 'deduction'
  nature: 'taxable' | 'non_taxable'
  description: string
  amount_centavos: number
  created_at: string
}

// ---------------------------------------------------------------------------
// Request payloads
// ---------------------------------------------------------------------------

// ── Pay Period ────────────────────────────────────────────────────────────

export type PayPeriodStatus = 'open' | 'closed' | 'locked'
export type PayPeriodFrequency = 'semi_monthly' | 'monthly' | 'weekly'

export interface PayPeriod {
  id: number
  label: string
  cutoff_start: string // ISO date
  cutoff_end: string // ISO date
  pay_date: string // ISO date
  status: PayPeriodStatus
  frequency: PayPeriodFrequency
  created_at: string
  updated_at: string
}

export interface CreatePayrollRunPayload {
  run_type?: PayrollRunType
  pay_period_id?: number
  cutoff_start: string
  cutoff_end: string
  pay_date: string
  notes?: string
}

export interface CreateAdjustmentPayload {
  employee_id: number
  type: 'earning' | 'deduction'
  nature: 'taxable' | 'non_taxable'
  description: string
  amount_centavos: number
}

export interface HrApprovePayload {
  action: 'APPROVED' | 'RETURNED'
  comments?: string
  return_comments?: string // required when action === 'RETURNED'
  checkboxes_checked?: string[] // required when action === 'APPROVED', min 3
}

export interface AcctgApprovePayload {
  action: 'APPROVED' | 'REJECTED' | 'RETURNED'
  rejection_reason?: string // required when action === 'REJECTED'
  return_comments?: string // required when action === 'RETURNED'
  comments?: string // optional comments for approval
  checkboxes_checked?: string[] // required when action === 'APPROVED', min 3
}

export interface PublishPayload {
  publish_at?: string | null // ISO datetime, null = immediate
  notify_email?: boolean
  notify_in_app?: boolean
}

// ---------------------------------------------------------------------------
// Filter types
// ---------------------------------------------------------------------------

export interface PayrollRunFilters {
  status?: PayrollRunStatus
  year?: number
  page?: number
  per_page?: number
}

// ── Pre-run check types (legacy — kept for backward compat; prefer PreRunCheckResult) ──
export interface PreRunCheck {
  code: string
  label: string
  status: 'pass' | 'block' | 'warn'
  message?: string
}
