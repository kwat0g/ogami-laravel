import axios from 'axios'
import type { ApiError } from '@/types/api'

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,   // send session cookies on every request
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// ── Client-side write cooldown ─────────────────────────────────────────────
// Prevents button-spam: the same endpoint (method + URL) cannot be fired more
// than once within WRITE_COOLDOWN_MS even when the server returns a 422/403.
// Different resource IDs (e.g. /loans/1/approve vs /loans/2/approve) are
// tracked separately so bulk approval workflows are unaffected.
const WRITE_COOLDOWN_MS = 1500
const lastWriteCallAt = new Map<string, number>()

api.interceptors.request.use(
  (config) => {
    const method = (config.method ?? 'get').toUpperCase()
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      const key = `${method}:${config.url ?? ''}`
      const lastCall = lastWriteCallAt.get(key) ?? 0
      const now = Date.now()
      if (now - lastCall < WRITE_COOLDOWN_MS) {
        // Abort the duplicate call silently — UI button is still disabled via isPending
        const controller = new AbortController()
        controller.abort()
        config.signal = controller.signal
      } else {
        lastWriteCallAt.set(key, now)
      }
    }
    return config
  },
)

// ── Response: normalise error shape ────────────────────────────────────────
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Cooldown-aborted duplicate call — drop silently, no toast
    if (axios.isCancel(error)) {
      return Promise.reject({ __cooldown: true })
    }

    if (!error.response) {
      return Promise.reject(new Error('Network error. Please check your connection.'))
    }

    const status = error.response.status as number
    const data   = error.response.data as ApiError

    // ── 401: Session expired or wiped (e.g. after a DB restore) ─────────
    // Clear auth state and redirect to login for every 401, UNLESS we are
    // already on the login page (which would cause an infinite redirect loop
    // from the pre-login /auth/me probe that always returns 401).
    if (status === 401) {
      import('@/stores/authStore').then(({ useAuthStore }) => {
        useAuthStore.getState().clearAuth()
        if (!window.location.pathname.startsWith('/login')) {
          window.location.replace('/login')
        }
      })
      return Promise.reject({ __handled: true })
    }

    // ── 429: Rate limit hit — show warning toast ──────────────────────────
    if (status === 429) {
      import('sonner').then(({ toast }) => {
        toast.warning('Too many requests — please slow down and try again in a moment.', {
          id: 'rate-limit',
          duration: 5000,
        })
      })
      return Promise.reject({ __handled: true })
    }

    // ── 5xx: Show server error toast ──────────────────────────────────────
    if (status >= 500) {
      import('sonner').then(({ toast }) => {
        toast.error('Server error. Please try again or contact support.', {
          id: `server-error-${status}`,
          duration: 6000,
        })
      })
      return Promise.reject({ __handled: true })
    }

    return Promise.reject(data ?? { success: false, message: 'An unexpected error occurred.', error_code: 'UNKNOWN' })
  },
)

/** Returns true for errors already toasted by the api interceptor (429, 5xx, cooldown). */
export function isHandledApiError(err: unknown): boolean {
  if (err instanceof Error) return false
  const e = err as Record<string, unknown>
  return e?.__handled === true || e?.__cooldown === true
}

export default api
