import type { ReactNode } from 'react'
import { useDepartmentGuard, type MODULE_DEPARTMENTS, isRoleAtLeast } from '@/hooks/useDepartmentGuard'
import { useAuthStore } from '@/stores/authStore'

type ModuleKey = keyof typeof MODULE_DEPARTMENTS

interface DepartmentGuardProps {
  /**
   * The module key to check department access for.
   * Use keys from MODULE_DEPARTMENTS.
   */
  module: ModuleKey
  
  /** Content to render when user has department access */
  children: ReactNode
  
  /**
   * Optional fallback when user lacks department access.
   * Defaults to null (element removed from DOM).
   */
  fallback?: ReactNode
  
  /**
   * If true, renders children but with disabled styling.
   * Useful for buttons that should remain visible.
   */
  showDisabled?: boolean
  
  /**
   * Minimum role level required for access (reversed hierarchy).
   * 
   * Reversed hierarchy: Officer (highest) → Manager → Head → Staff (lowest)
   * - 'officer': Only officers and above (officer, vp, executive, admin, super_admin)
   * - 'manager': Managers and above
   * - 'head': Heads and above (includes managers, officers, etc.)
   * - 'staff': All department members (lowest threshold)
   * 
   * When minRole is specified, the component checks both department AND role level.
   * This supports the reversed hierarchy where higher roles have broader but fewer items.
   * 
   * @example
   * // Only show to heads and above (team leads, managers, officers)
   * <DepartmentGuard module="production" minRole="head">
   *   <ApproveButton />
   * </DepartmentGuard>
   */
  minRole?: 'officer' | 'manager' | 'head' | 'staff'
}

/**
 * Renders children only when the user's department has access to the specified module.
 * This enforces SoD by preventing cross-department module access.
 * 
 * Supports reversed hierarchy (Officer → Manager → Head → Staff) via minRole prop.
 * Executives, VPs, admins, and super_admins bypass department checks.
 * 
 * Usage:
 * ```tsx
 * // Basic usage - any department member can see
 * <DepartmentGuard module="accounting">
 *   <button>Create Journal Entry</button>
 * </DepartmentGuard>
 * 
 * // Restrict to heads and above (reversed hierarchy)
 * <DepartmentGuard module="production" minRole="head">
 *   <ApproveButton />
 * </DepartmentGuard>
 * 
 * // Show disabled state instead of hiding
 * <DepartmentGuard module="hr" showDisabled>
 *   <button>Add Employee</button>
 * </DepartmentGuard>
 * 
 * // With custom fallback
 * <DepartmentGuard module="production" fallback={<p>Production access only</p>}>
 *   <WorkOrderList />
 * </DepartmentGuard>
 * ```
 */
export function DepartmentGuard({
  module,
  children,
  fallback = null,
  showDisabled = false,
  minRole,
}: DepartmentGuardProps): React.ReactElement | null {
  const { hasAccess, reason } = useDepartmentGuard(module)
  const user = useAuthStore((s) => s.user)
  
  // Check department access first
  if (!hasAccess) {
    if (showDisabled) {
      return (
        <span title={reason ?? 'Department access denied'} className="inline-block">
          <span className="opacity-50 cursor-not-allowed pointer-events-none">
            {children}
          </span>
        </span>
      )
    }
    return <>{fallback}</>
  }
  
  // Check role hierarchy if minRole is specified
  if (minRole && user) {
    const userRoles = user.roles || []
    const hasMinRole = isRoleAtLeast(userRoles, minRole)
    
    if (!hasMinRole) {
      const roleMessage = `Requires ${minRole} role or higher`
      if (showDisabled) {
        return (
          <span title={roleMessage} className="inline-block">
            <span className="opacity-50 cursor-not-allowed pointer-events-none">
              {children}
            </span>
          </span>
        )
      }
      return <>{fallback}</>
    }
  }
  
  return <>{children}</>
}

export default DepartmentGuard
