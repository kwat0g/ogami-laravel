import type { ReactNode } from 'react'
import { useAuthStore } from '@/stores/authStore'

interface PermissionGuardProps {
  /**
   * The permission string to check (e.g. 'leaves.approve').
   * Use `PERMISSIONS.*` constants from `@/lib/permissions` to avoid typos.
   */
  permission: string

  /** Rendered when the user has the permission. */
  children: ReactNode

  /**
   * Optional fallback rendered when the user lacks the permission.
   * Defaults to `null` (silent — the element is removed from the DOM).
   */
  fallback?: ReactNode
}

/**
 * Renders `children` only when the authenticated user holds `permission`.
 * Renders `fallback` (default: null) otherwise.
 *
 * Usage:
 * ```tsx
 * <PermissionGuard permission="leaves.approve">
 *   <ApproveButton />
 * </PermissionGuard>
 *
 * // With fallback
 * <PermissionGuard permission="payroll.initiate" fallback={<p>Read-only</p>}>
 *   <RunPayrollButton />
 * </PermissionGuard>
 * ```
 */
export default function PermissionGuard({
  permission,
  children,
  fallback = null,
}: PermissionGuardProps) {
  const hasPermission = useAuthStore((s) => s.hasPermission)

  if (!hasPermission(permission)) {
    return <>{fallback}</>
  }

  return <>{children}</>
}
