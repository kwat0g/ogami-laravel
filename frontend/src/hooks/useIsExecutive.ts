import { useAuthStore } from '@/stores/authStore'

/**
 * Returns `true` when the currently authenticated user has the `executive`
 * role.  Executives have read-only access across all modules — no
 * create/update/approve operations are permitted.
 *
 * Usage:
 * ```tsx
 * const isExecutive = useIsExecutive()
 * if (isExecutive) return <ReadOnlyView />
 * ```
 */
export function useIsExecutive(): boolean {
  return useAuthStore((s) => s.hasRole('executive'))
}
