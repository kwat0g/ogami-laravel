import { useAuthStore } from '@/stores/authStore'

/**
 * Returns true when the authenticated user holds the given permission.
 *
 * Usage:
 *   const canRunPayroll = usePermission('payroll.initiate')
 */
export function usePermission(permission: string): boolean {
  return useAuthStore((s) => s.hasPermission(permission))
}

/**
 * Returns true when the user holds any of the given roles.
 *
 * Usage:
 *   const isAdmin = useRole(['admin'])
 */
export function useRole(roles: string | string[]): boolean {
  const check = Array.isArray(roles) ? roles : [roles]
  return useAuthStore((s) => s.hasAnyRole(check))
}

// ---------------------------------------------------------------------------
// Department RDAC hooks
// ---------------------------------------------------------------------------

/**
 * Returns true if the current user has access to the given department.
 * admin / executive bypass all department restrictions.
 */
export function useDepartmentAccess(departmentId: number): boolean {
  return useAuthStore((s) => s.hasDepartmentAccess(departmentId))
}

/** Returns the user’s primary department ID (is_primary pivot or first in list). */
export function usePrimaryDepartmentId(): number | null {
  return useAuthStore((s) => s.primaryDepartmentId())
}

// ---------------------------------------------------------------------------
// Role convenience hooks
// ---------------------------------------------------------------------------

/** True when user holds the `manager` role. */
export const useIsManager      = (): boolean => useAuthStore((s) => s.isManager())
/** True when user holds the `head` role. */
export const useIsHead         = (): boolean => useAuthStore((s) => s.isHead())
/** True when user holds the `officer` role. */
export const useIsOfficer      = (): boolean => useAuthStore((s) => s.isOfficer())
/** True when user holds the `vice_president` role. */
export const useIsVicePresident = (): boolean => useAuthStore((s) => s.isVicePresident())
