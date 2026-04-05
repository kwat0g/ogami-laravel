import type { ReactNode } from 'react'
import { useAuthStore } from '@/stores/authStore'
import { useDepartmentGuard, type MODULE_DEPARTMENTS } from '@/hooks/useDepartmentGuard'
import { useSodCheck } from '@/hooks/useSodCheck'

type ModuleKey = keyof typeof MODULE_DEPARTMENTS

interface ActionGuardProps {
  /** 
   * The permission required for this action (e.g., 'employees.create').
   * If not provided, only department/SoD checks are applied.
   */
  permission?: string

  /**
   * Optional role-based override. If provided, users with any of these roles
   * can pass the principal auth check even without the permission string.
   */
  rolesAny?: string[]
  
  /**
   * The module key for department-based SoD enforcement.
   * Required if you want department-level access control.
   * Use module keys from MODULE_DEPARTMENTS.
   */
  module?: ModuleKey
  
  /**
   * For record-level SoD: the ID of the user who initiated the record.
   * If provided and matches current user, action is blocked (unless admin).
   */
  initiatedById?: number | null
  
  /**
   * Additional custom condition to disable the action.
   */
  disabled?: boolean
  
  /** Content to render when all checks pass */
  children: ReactNode
  
  /**
   * Optional fallback when any check fails.
   * Defaults to null (element removed from DOM).
   */
  fallback?: ReactNode
  
  /**
   * If true, renders children but with disabled state and tooltip instead of hiding.
   * Useful for buttons that should remain visible but disabled.
   */
  showDisabled?: boolean
}

export interface GuardResult {
  allowed: boolean
  reason: string | null
  permissionOk: boolean
  roleOk: boolean
  principalOk: boolean
  departmentOk: boolean
  sodOk: boolean
}

/**
 * Comprehensive action guard that checks:
 * 1. Permission (if provided)
 * 2. Department access (if module provided)
 * 3. Record-level SoD (if initiatedById provided)
 * 
 * Usage:
 * ```tsx
 * // Simple permission check
 * <ActionGuard permission="employees.create">
 *   <button>Add Employee</button>
 * </ActionGuard>
 * 
 * // Permission + Department (SoD)
 * <ActionGuard permission="journal_entries.create" module="accounting">
 *   <button>Create Entry</button>
 * </ActionGuard>
 * 
 * // Permission + Department + SoD (for approval actions)
 * <ActionGuard 
 *   permission="payroll.approve" 
 *   module="payroll"
 *   initiatedById={run.initiated_by_id}
 * >
 *   <button>Approve Payroll</button>
 * </ActionGuard>
 * 
 * // Show disabled instead of hiding
 * <ActionGuard 
 *   permission="employees.delete" 
 *   module="hr"
 *   showDisabled
 * >
 *   <button>Delete</button>
 * </ActionGuard>
 * ```
 */
export function ActionGuard({
  permission,
  rolesAny,
  module,
  initiatedById,
  disabled = false,
  children,
  fallback = null,
  showDisabled = false,
}: ActionGuardProps): React.ReactElement | null {
  const hasPermission = useAuthStore((s) => s.hasPermission)
  const hasAnyRole = useAuthStore((s) => s.hasAnyRole)

  // ── Hooks must be called unconditionally (Rules of Hooks) ──────────────────
  // We always call both hooks; the `module` / `initiatedById` props control
  // whether we actually USE the result, not whether we call the hook.
  const deptCheck = useDepartmentGuard(module ?? '_none_')
  const sodCheck = useSodCheck(initiatedById ?? null)

  // Check permission
  const permissionOk = !!permission && hasPermission(permission)
  const roleOk = !!rolesAny?.length && hasAnyRole(rolesAny)
  const principalOk = (!permission && !rolesAny?.length) || permissionOk || roleOk

  // Check department access (only relevant when a module was specified)
  const departmentOk = !module || deptCheck.hasAccess

  // Check SoD (only relevant when initiatedById was specified)
  const sodOk = initiatedById === undefined || !sodCheck.isBlocked

  // Combined result
  const allowed = principalOk && departmentOk && sodOk && !disabled

  // Build reason message
  let reason: string | null = null
  if (disabled) {
    reason = 'Action is disabled'
  } else if (!principalOk) {
    reason = permission
      ? `Permission required: ${permission}`
      : 'Role access required'
  } else if (!departmentOk) {
    reason = deptCheck.reason
  } else if (!sodOk) {
    reason = sodCheck.reason
  }

  // If not allowed and not showing disabled state, return fallback
  if (!allowed && !showDisabled) {
    return <>{fallback}</>
  }

  // If showing disabled, wrap children with disabled state
  if (!allowed && showDisabled) {
    return (
      <span title={reason ?? 'Action not available'} className="inline-block">
        <span className="opacity-50 cursor-not-allowed pointer-events-none">
          {children}
        </span>
      </span>
    )
  }

  // All checks passed
  return <>{children}</>
}

interface ActionButtonProps {
  /** Button label */
  label: string
  
  /** Click handler */
  onClick: () => void
  
  /** Permission required */
  permission?: string
  
  /** Module for department check */
  module?: ModuleKey
  
  /** Record initiator ID for SoD check */
  initiatedById?: number | null
  
  /** Loading state */
  isLoading?: boolean
  
  /** Additional disabled condition */
  disabled?: boolean
  
  /** Visual variant */
  variant?: 'primary' | 'success' | 'danger' | 'warning' | 'ghost'
  
  /** Additional CSS classes */
  className?: string
}

const variantClasses: Record<NonNullable<ActionButtonProps['variant']>, string> = {
  primary:
    'bg-neutral-900 text-white hover:bg-neutral-800 disabled:bg-neutral-300 focus:ring-neutral-400',
  success:
    'bg-green-600 text-white hover:bg-green-700 disabled:bg-green-300 focus:ring-green-500',
  danger:
    'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300 focus:ring-red-500',
  warning:
    'bg-amber-600 text-white hover:bg-amber-700 disabled:bg-amber-300 focus:ring-amber-400',
  ghost:
    'bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 disabled:opacity-50 focus:ring-neutral-400',
}

/**
 * Pre-built action button with integrated permission, department, and SoD checks.
 * 
 * Usage:
 * ```tsx
 * <ActionButton
 *   label="Create Journal Entry"
 *   permission="journal_entries.create"
 *   module="accounting"
 *   onClick={() => navigate('/accounting/journal-entries/new')}
 * />
 * 
 * <ActionButton
 *   label="Approve"
 *   permission="payroll.approve"
 *   module="payroll"
 *   initiatedById={run.initiated_by_id}
 *   variant="success"
 *   onClick={() => approveMutation.mutate()}
 *   isLoading={approveMutation.isPending}
 * />
 * ```
 */
export function ActionButton({
  label,
  onClick,
  permission,
  module,
  initiatedById,
  isLoading = false,
  disabled = false,
  variant = 'primary',
  className = '',
}: ActionButtonProps): React.ReactElement {
  const hasPermission = useAuthStore((s) => s.hasPermission)

  // ── Hooks must be called unconditionally (Rules of Hooks) ──────────────────
  const deptCheck = useDepartmentGuard(module ?? '_none_')
  const sodCheck = useSodCheck(initiatedById ?? null)

  // Check permission
  const permissionOk = !permission || hasPermission(permission)

  // Check department access (only relevant when a module was specified)
  const departmentOk = !module || deptCheck.hasAccess

  // Check SoD (only relevant when initiatedById was specified)
  const sodOk = initiatedById === undefined || !sodCheck.isBlocked
  
  // Determine disabled state and reason
  const isDisabled = !permissionOk || !departmentOk || !sodOk || disabled || isLoading
  
  let title: string | undefined
  if (isLoading) {
    title = 'Processing…'
  } else if (!permissionOk) {
    title = `Permission required: ${permission}`
  } else if (!departmentOk) {
    title = deptCheck.reason ?? 'Department access denied'
  } else if (!sodOk) {
    title = sodCheck.reason ?? 'SoD violation'
  } else if (disabled) {
    title = 'Action disabled'
  }
  
  // Determine button content
  let content: React.ReactNode = label
  if (isLoading) {
    content = (
      <span className="flex items-center gap-2">
        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden>
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z" />
        </svg>
        {label}
      </span>
    )
  } else if (!permissionOk || !departmentOk) {
    content = (
      <span className="flex items-center gap-1.5">
        <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
          <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
        </svg>
        {label}
      </span>
    )
  } else if (!sodOk) {
    content = (
      <span className="flex items-center gap-1.5">
        <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
          <path fillRule="evenodd" d="M10 1a9 9 0 100 18A9 9 0 0010 1zm0 2a7 7 0 110 14A7 7 0 0110 3zm0 3a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1zm0 7a1 1 0 100 2 1 1 0 000-2z" clipRule="evenodd" />
        </svg>
        {label} (SoD)
      </span>
    )
  }
  
  return (
    <div className="relative inline-block" title={title}>
      <button
        type="button"
        onClick={isDisabled ? undefined : onClick}
        disabled={isDisabled}
        aria-disabled={isDisabled}
        className={[
          'px-4 py-2 text-sm font-medium rounded transition-colors',
          'focus:outline-none focus:ring-1',
          'disabled:cursor-not-allowed',
          variantClasses[variant],
          isDisabled && !isLoading ? 'opacity-60' : '',
          className,
        ]
          .filter(Boolean)
          .join(' ')}
      >
        {content}
      </button>
    </div>
  )
}

export default ActionGuard
