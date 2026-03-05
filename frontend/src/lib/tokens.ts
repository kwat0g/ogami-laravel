/**
 * Typed helpers for the Sanctum token stored in localStorage.
 *
 * All reads / writes go through this module so the key lives in one place.
 */

// ── Status → badge variant map ───────────────────────────────────────────────

/**
 * Maps canonical status strings to badge variants used by `StatusBadge`.
 * Import this instead of hard-coding variant strings per-page.
 */
export const STATUS_VARIANT = {
  ACTIVE: 'success', APPROVED: 'success', POSTED: 'success', PAID: 'success',
  SUBMITTED: 'warning', PARTIALLY_PAID: 'warning',
  OVERDUE: 'danger', FAILED: 'danger', REJECTED: 'danger',
  COMPUTING: 'info', PENDING: 'info', DRAFT: 'info',
  INACTIVE: 'neutral', CANCELLED: 'neutral',
} as const

export type StatusVariantKey = keyof typeof STATUS_VARIANT

// ── Label maps ───────────────────────────────────────────────────────────────

/** Human-readable labels for TRAIN Law civil status codes stored in uppercase. */
export const CIVIL_STATUS_LABELS: Record<string, string> = {
  SINGLE:            'Single',
  MARRIED:           'Married',
  WIDOWED:           'Widowed',
  LEGALLY_SEPARATED: 'Legally Separated',
  HEAD_OF_FAMILY:    'Head of Family',
}

/** Human-readable labels for employment types. */
export const EMPLOYMENT_TYPE_LABELS: Record<string, string> = {
  REGULAR:       'Regular',
  PROBATIONARY:  'Probationary',
  CONTRACTUAL:   'Contractual',
  PART_TIME:     'Part-Time',
}

// ── Sanctum auth token ───────────────────────────────────────────────────────

const KEY = 'auth_token' as const

export const tokens = {
  /**
   * Return the stored Sanctum token, or `null` if absent.
   */
  get(): string | null {
    return localStorage.getItem(KEY)
  },

  /**
   * Persist the Sanctum token. Call this after a successful login,
   * **in addition to** updating the Zustand store.
   */
  set(token: string): void {
    localStorage.setItem(KEY, token)
  },

  /**
   * Remove the token from storage. Call this on logout or session expiry.
   */
  clear(): void {
    localStorage.removeItem(KEY)
  },

  /**
   * Returns `true` if a token is currently present (does not validate the
   * token — use the `/api/v1/auth/me` endpoint for that).
   */
  exists(): boolean {
    return !!localStorage.getItem(KEY)
  },
} as const
