import { useAuthStore } from '@/stores/authStore'

export interface SodCheckResult {
  /** True if the current user is the initiator and should be blocked from approving. */
  isBlocked: boolean
  /** Human-readable explanation shown in the tooltip / UI. Null when not blocked. */
  reason: string | null
}

/**
 * Record-level Segregation of Duties check.
 *
 * Returns whether the currently authenticated user is blocked from performing
 * an approval action because they also initiated the record being approved.
 *
 * Bypass rules (not blocked):
 *   - admin role
 *   - `initiatedById` is null (record has no initiator — e.g. migrated data)
 *
 * NOTE: `manager` is NO LONGER bypassed. Department-specific manager sub-roles
 * (hr_manager, finance_manager, ops_manager) have their own SoD constraints per
 * the v1.0 Role & Permission Matrix. Bypassing all `manager` roles would silently
 * disable SOD-005, SOD-006, and SOD-007 for payroll approvals.
 *
 * Usage:
 * ```tsx
 * const { isBlocked, reason } = useSodCheck(run.initiated_by_id)
 * <button disabled={isBlocked} title={reason ?? undefined}>Approve</button>
 * ```
 */
export function useSodCheck(initiatedById: number | null): SodCheckResult {
  const user = useAuthStore((s) => s.user)

  // Unauthenticated or no initiator — no SoD constraint
  if (!user || initiatedById === null || initiatedById === undefined) {
    return { isBlocked: false, reason: null }
  }

  // Role-level bypass: admin and super_admin skip SoD (super_admin is testing superuser)
  if (user.roles.some((r) => ['admin', 'super_admin'].includes(r))) {
    return { isBlocked: false, reason: null }
  }

  if (user.id === initiatedById) {
    return {
      isBlocked: true,
      reason:
        'Segregation of Duties violation: you initiated this record and cannot approve it. ' +
        'A different user must perform the approval.',
    }
  }

  return { isBlocked: false, reason: null }
}
