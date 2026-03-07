// ---------------------------------------------------------------------------
// HR Domain Types
// ---------------------------------------------------------------------------

export type EmploymentType =
  | 'regular'
  | 'contractual'
  | 'project_based'
  | 'casual'
  | 'probationary'

export type EmploymentStatus =
  | 'draft'
  | 'active'
  | 'on_leave'
  | 'suspended'
  | 'resigned'
  | 'terminated'

export type OnboardingStatus =
  | 'draft'
  | 'documents_pending'
  | 'active'
  | 'offboarding'
  | 'offboarded'

export type PayBasis = 'monthly' | 'daily'

// ── Shared pagination wrapper ────────────────────────────────────────────────

export interface PaginatedMeta {
  current_page: number
  last_page:    number
  per_page:     number
  total:        number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginatedMeta
}

// ── Shared employee summary (embedded in sub-domain resources) ───────────────

export interface EmployeeSummary {
  id:            number
  ulid:          string
  employee_code: string
  full_name:     string
}

// ── Reference: Department ────────────────────────────────────────────────────

export interface Department {
  id:                    number
  code:                  string
  name:                  string
  parent_department_id:  number | null
  cost_center_code:      string | null
  is_active:             boolean
  created_at:            string
  updated_at:            string
}

// ── Reference: Position ──────────────────────────────────────────────────────

export interface Position {
  id:            number
  code:          string
  title:         string
  department_id: number | null
  department:    { id: number; code: string; name: string } | null
  pay_grade:     string | null
  description:   string | null
  is_active:     boolean
  created_at:    string
  updated_at:    string
}

// ── Reference: Shift Schedule ────────────────────────────────────────────────

export interface ShiftSchedule {
  id:                    number
  name:                  string
  description:           string | null
  start_time:            string   // HH:MM
  end_time:              string   // HH:MM
  break_minutes:         number
  is_night_shift:        boolean
  work_days:             string   // "1,2,3,4,5"
  is_flexible:           boolean
  grace_period_minutes:  number
  is_active:             boolean
  created_at:            string
  updated_at:            string
}

export interface EmployeeShiftAssignment {
  id:                number
  employee_id:       number
  shift_schedule_id: number
  effective_from:    string
  effective_to:      string | null
  notes:             string | null
  created_by:        number
  shift_schedule?:   ShiftSchedule
}

// ── Attendance ───────────────────────────────────────────────────────────────

export interface AttendanceLog {
  id:                   number
  employee_id:          number
  employee:             EmployeeSummary | null
  work_date:            string
  time_in:              string | null
  time_out:             string | null
  source:               'biometric' | 'manual' | 'system'
  worked_minutes:       number
  worked_hours:         number
  late_minutes:         number
  undertime_minutes:    number
  overtime_minutes:     number
  overtime_hours:       number
  nights_diff_minutes:  number
  is_present:           boolean
  is_absent:            boolean
  is_rest_day:          boolean
  is_holiday:           boolean
  holiday_type:         string | null
  remarks:              string | null
  import_batch_id:      string | null
  created_at:           string
  updated_at:           string
}

export interface AttendanceFilters {
  employee_id?:     number
  search?:          string
  date_from?:       string
  date_to?:         string
  import_batch_id?: string
  per_page?:        number
  page?:            number
}

// ── Overtime ─────────────────────────────────────────────────────────────────

export type OvertimeStatus = 'pending' | 'supervisor_approved' | 'pending_executive' | 'approved' | 'rejected' | 'cancelled'

export interface OvertimeRequest {
  id:                number
  employee_id:       number
  employee:          EmployeeSummary | null
  work_date:         string
  ot_start_time:     string
  ot_end_time:       string
  requested_minutes: number
  requested_hours:   number
  approved_minutes:  number | null
  approved_hours:    number | null
  reason:            string
  status:            OvertimeStatus
  /** User who submitted this OT request — required for SOD-003. */
  created_by_id:     number | null
  approved_by:       number | null
  approver_remarks:  string | null
  reviewed_at:       string | null
  /** Multi-step workflow fields */
  requester_role:         'staff' | 'head' | 'manager' | 'officer' | 'vice_president' | null
  supervisor_id:          number | null
  supervisor_remarks:     string | null
  supervisor_approved_at: string | null
  executive_id:           number | null
  executive_remarks:      string | null
  executive_approved_at:  string | null
  created_at:        string
  updated_at:        string
}

export interface OvertimeFilters {
  employee_id?:   number
  status?:        OvertimeStatus
  date_from?:     string
  date_to?:       string
  department_id?: number
  search?:        string
  per_page?:      number
  page?:          number
}

// ── Leave ────────────────────────────────────────────────────────────────────

export type LeaveStatus =
  | 'draft'
  | 'submitted'
  | 'head_approved'
  | 'manager_checked'
  | 'ga_processed'
  | 'approved'
  | 'rejected'
  | 'cancelled'

export interface LeaveTypeSummary {
  id:   number
  code: string
  name: string
}

export interface LeaveRequest {
  id:               number
  employee_id:      number
  employee:         EmployeeSummary | null
  leave_type_id:    number
  leave_type:       LeaveTypeSummary | null
  submitted_by:     number  // user who filed — SoD-002 initiator
  date_from:        string
  date_to:          string
  total_days:       number
  is_half_day:      boolean
  half_day_period:  'AM' | 'PM' | null
  reason:           string
  status:           LeaveStatus
  // Step 2 — Department Head
  head_id:              number | null
  head_remarks:         string | null
  head_approved_at:     string | null
  // Step 3 — Plant Manager
  manager_checked_by:      number | null
  manager_check_remarks:   string | null
  manager_checked_at:      string | null
  // Step 4 — GA Officer
  ga_processed_by:     number | null
  ga_remarks:          string | null
  ga_processed_at:     string | null
  action_taken:        'approved_with_pay' | 'approved_without_pay' | 'disapproved' | null
  beginning_balance:   number | null
  applied_days:        number | null
  ending_balance:      number | null
  // Step 5 — Vice President
  vp_id:               number | null
  vp_remarks:          string | null
  vp_noted_at:         string | null
  created_at:          string
  updated_at:          string
}

export interface LeaveBalance {
  id:             number
  employee_id:    number
  employee:       EmployeeSummary | null
  leave_type_id:  number
  leave_type:     LeaveTypeSummary | null
  year:           number
  opening_balance: number
  accrued:        number
  adjusted:       number
  used:           number
  monetized:      number
  balance:        number
}

export interface LeaveFilters {
  employee_id?:   number
  status?:        LeaveStatus
  year?:          number
  department_id?: number
  search?:        string
  per_page?:      number
  page?:          number
}

export interface CreateLeaveRequestPayload {
  employee_id:     number
  leave_type_id:   number
  date_from:       string
  date_to:         string
  is_half_day?:    boolean
  half_day_period?: 'AM' | 'PM'
  reason:          string
}

// ── Loan ─────────────────────────────────────────────────────────────────────

export type LoanStatus = 
  | 'pending'
  // v1 chain statuses
  | 'supervisor_approved'
  | 'approved'
  | 'ready_for_disbursement'
  // v2 chain statuses
  | 'head_noted'
  | 'manager_checked'
  | 'officer_reviewed'
  | 'vp_approved'
  | 'disbursing'
  // terminal statuses (shared)
  | 'active'
  | 'fully_paid'
  | 'written_off'
  | 'rejected'
  | 'cancelled'

export interface LoanTypeSummary {
  id:                    number
  code:                  string
  name:                  string
  interest_rate_annual:  number
}

export interface Loan {
  id:                             number
  ulid:                           string
  reference_no:                   string | null
  employee_id:                    number
  employee:                       EmployeeSummary | null
  loan_type_id:                   number
  loan_type:                      LoanTypeSummary | null
  requested_by:                   number
  principal_centavos:             number
  principal_php:                  number
  term_months:                    number
  interest_rate_annual:           number
  monthly_amortization_centavos:  number
  monthly_amortization_php:       number
  total_payable_centavos:         number
  total_payable_php:              number
  outstanding_balance_centavos:   number
  outstanding_balance_php:        number
  loan_date:                      string
  deduction_cutoff:               '1st' | '2nd'
  first_deduction_date:           string | null
  status:                         LoanStatus
  workflow_version:               1 | 2
  // v1 approval chain
  approved_by:                    number | null
  approver_name:                  string | null
  approver_remarks:               string | null
  approved_at:                    string | null
  supervisor_approved_by:         number | null
  supervisor_remarks:             string | null
  supervisor_approved_at:         string | null
  accounting_approved_by:         number | null
  accounting_approver_name:       string | null
  accounting_remarks:             string | null
  accounting_approved_at:         string | null
  // v2 approval chain
  head_noted_by:                  number | null
  head_noted_at:                  string | null
  head_remarks:                   string | null
  manager_checked_by:             number | null
  manager_checked_at:             string | null
  manager_remarks:                string | null
  officer_reviewed_by:            number | null
  officer_reviewed_at:            string | null
  officer_remarks:                string | null
  vp_approved_by:                 number | null
  vp_approved_at:                 string | null
  vp_remarks:                     string | null
  // disbursal
  disbursed_at:                   string | null
  disbursed_by:                   number | null
  journal_entry_id:               number | null
  purpose:                        string | null
  created_at:                     string
  updated_at:                     string
}

export interface LoanScheduleEntry {
  installment_no:    number
  due_date:          string
  principal:         number
  interest:          number
  amortization:      number
  balance:           number
  is_paid:           boolean
  paid_at:           string | null
}

export interface LoanFilters {
  employee_id?:   number
  status?:        LoanStatus
  status_in?:     string    // comma-separated e.g. 'approved,ready_for_disbursement,active'
  loan_type_id?:  number
  per_page?:      number
  page?:          number
}

export interface CreateLoanPayload {
  employee_id:       number
  loan_type_id:      number
  principal_centavos: number
  term_months:       number
  deduction_cutoff:  '1st' | '2nd'
  purpose?:          string
}

// ── Employee (unchanged) ─────────────────────────────────────────────────────

export interface SalaryGradeSummary {
  id: number
  code: string
  name: string
}

export interface EmployeeListItem {
  id: number
  ulid: string
  employee_code: string
  full_name: string
  first_name: string
  last_name: string
  department_id: number | null
  employment_type: EmploymentType
  employment_status: EmploymentStatus
  salary_grade_code: string | null
  basic_monthly_rate: number   // centavos
  date_hired: string           // ISO date
  is_active: boolean
  user_roles: string[]         // Spatie roles (e.g., ['manager', 'head', 'staff'])
  has_sss_no: boolean
  has_tin: boolean
  has_philhealth_no: boolean
  has_pagibig_no: boolean
  /** Included by EmployeeListResource */
  department?: { id: number; name: string } | null
  position?:   { id: number; title: string } | null
}

export interface Employee {
  id: number
  ulid: string
  employee_code: string
  full_name: string
  first_name: string
  last_name: string
  middle_name: string | null
  suffix: string | null
  date_of_birth: string | null
  gender: 'male' | 'female' | 'other'
  civil_status: 'SINGLE' | 'MARRIED' | 'WIDOWED' | 'LEGALLY_SEPARATED' | 'HEAD_OF_FAMILY' | null
  citizenship: string | null
  present_address: string | null
  permanent_address: string | null
  personal_email: string | null
  personal_phone: string | null
  department_id: number | null
  position_id: number | null
  salary_grade: SalaryGradeSummary | null
  reports_to: number | null
  employment_type: EmploymentType
  employment_status: EmploymentStatus
  onboarding_status: OnboardingStatus
  pay_basis: PayBasis
  basic_monthly_rate: number       // centavos
  basic_monthly_rate_php: number   // display
  daily_rate: number
  hourly_rate: number
  date_hired: string
  regularization_date: string | null
  separation_date: string | null
  is_active: boolean
  bank_name: string | null
  bank_account_no: string | null
  notes: string | null
  has_sss_no: boolean
  has_tin: boolean
  has_philhealth_no: boolean
  has_pagibig_no: boolean
  bir_status: string | null
  created_at: string
  updated_at: string
  /** Eager-loaded relations (EmployeeResource includes these). */
  department?: { id: number; name: string } | null
  position?:   { id: number; title: string } | null
  supervisor?: { id: number; ulid: string; full_name: string; employee_code: string } | null
  /** Active shift assignment with schedule details — present when loaded via detail endpoint. */
  current_shift?: {
    id:                number
    shift_schedule_id: number
    shift_name:        string | null
    start_time:        string | null
    end_time:          string | null
    effective_from:    string
  } | null
  /** User who created this record — required for SOD-001 (activate must differ from creator). */
  created_by_id?: number | null
}

export interface SalaryGrade {
  id: number
  code: string
  name: string
  level: number
  employment_type: EmploymentType
  min_monthly_rate: number   // centavos
  max_monthly_rate: number   // centavos
}

export interface EmployeeFilters {
  search?:            string
  department_id?:     number
  employment_status?: EmploymentStatus
  employment_type?:   EmploymentType
  is_active?:         boolean
  per_page?:          number
  page?:              number
}

export interface CreateEmployeePayload {
  first_name:       string
  last_name:        string
  middle_name?:     string
  suffix?:          string
  date_of_birth?:   string
  gender?:          'male' | 'female' | 'other'
  civil_status?:    'SINGLE' | 'MARRIED' | 'WIDOWED' | 'LEGALLY_SEPARATED' | 'HEAD_OF_FAMILY' | null
  citizenship?:     string
  present_address?: string
  permanent_address?: string
  personal_email?:  string
  personal_phone?:  string
  department_id?:   number
  position_id?:     number
  salary_grade_id?: number
  reports_to?:      number
  employment_type:  EmploymentType
  pay_basis:        PayBasis
  basic_monthly_rate: number
  date_hired:       string
  sss_no?:          string
  tin?:             string
  philhealth_no?:   string
  pagibig_no?:      string
  bank_name?:       string
  bank_account_no?: string
  notes?:           string
}

export interface LeaveType {
  id: number
  code: string
  name: string
  category: string
  is_paid: boolean
  max_days_per_year: number
  can_be_monetized: boolean
  is_active: boolean
}

export interface LoanType {
  id: number
  code: string
  name: string
  category: 'government' | 'company'
  interest_rate_annual: number
  max_term_months: number
  max_amount_centavos: number
  min_amount_centavos: number
  subject_to_min_wage_protection: boolean
}


