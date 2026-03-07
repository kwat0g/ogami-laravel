// ---------------------------------------------------------------------------
// API envelope types  (matches Laravel ApiResponse trait)
// ---------------------------------------------------------------------------

export interface ApiSuccess<T = unknown> {
  success: true
  message: string
  data: T
}

export interface ApiError {
  success: false
  message: string
  error_code: string
  errors?: Record<string, string[]>
}

export type ApiResponse<T = unknown> = ApiSuccess<T> | ApiError

// ---------------------------------------------------------------------------
// Auth types
// ---------------------------------------------------------------------------

export type AppRole =
  | 'admin'
  | 'super_admin'
  | 'executive'
  | 'vice_president'
  | 'manager'
  | 'officer'
  | 'head'
  | 'staff'

export interface AuthUser {
  id: number
  name: string
  email: string
  roles: AppRole[]
  permissions: string[]
  /** IDs of all departments this user has access to (via pivot table). */
  department_ids: number[]
  /** Primary department ID (is_primary = true in pivot, or first in list). */
  primary_department_id: number | null
  timezone: string
  employee_id?: number | null
}

export interface LoginPayload {
  email: string
  password: string
  device_name?: string
}

export interface LoginResult {
  token?: string
  user?: AuthUser
}

// ---------------------------------------------------------------------------
// Error codes  (matches backend ApiResponse error_code strings)
// ---------------------------------------------------------------------------

export const ErrorCode = {
  VALIDATION_FAILED:            'VALIDATION_FAILED',
  SOD_VIOLATION:                'SOD_VIOLATION',
  UNAUTHENTICATED:              'UNAUTHENTICATED',
  UNAUTHORIZED:                 'UNAUTHORIZED',
  SALARY_BELOW_MINIMUM_WAGE:    'SALARY_BELOW_MINIMUM_WAGE',
  INSUFFICIENT_LEAVE_BALANCE:   'INSUFFICIENT_LEAVE_BALANCE',
  DUPLICATE_PAYROLL_RUN:        'DUPLICATE_PAYROLL_RUN',
  NEGATIVE_NET_PAY:             'NEGATIVE_NET_PAY',
  UNBALANCED_JOURNAL_ENTRY:     'UNBALANCED_JOURNAL_ENTRY',
  LOCKED_PERIOD:                'LOCKED_PERIOD',
  INVALID_STATE_TRANSITION:     'INVALID_STATE_TRANSITION',
  CONTRIBUTION_TABLE_NOT_FOUND: 'CONTRIBUTION_TABLE_NOT_FOUND',
  CREDIT_LIMIT_EXCEEDED:        'CREDIT_LIMIT_EXCEEDED',
  INVALID_CURRENT_PASSWORD:     'INVALID_CURRENT_PASSWORD',
  SAME_PASSWORD:                'SAME_PASSWORD',
} as const

export type ErrorCode = typeof ErrorCode[keyof typeof ErrorCode]
