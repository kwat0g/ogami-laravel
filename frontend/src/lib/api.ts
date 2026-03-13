import axios, { type AxiosRequestConfig } from 'axios'
import type { ApiError } from '@/types/api'
import { bumpAuthEpoch, getAuthEpoch } from '@/lib/authEpoch'

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

const AUTH_RECHECK_WINDOW_MS = 5000
let authRecheckPromise: Promise<boolean> | null = null
let lastAuthRecheckAt = 0

function recheckAuth(): Promise<boolean> {
  const now = Date.now()
  if (authRecheckPromise && now - lastAuthRecheckAt < AUTH_RECHECK_WINDOW_MS) {
    return authRecheckPromise
  }

  lastAuthRecheckAt = now
  authRecheckPromise = api
    .get('/auth/me', { __skipAuthRedirect: true, __authRecheck: true } as AxiosRequestConfig)
    .then(() => true)
    .catch(() => false)
    .finally(() => {
      authRecheckPromise = null
    })

  return authRecheckPromise
}

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
    const cfg = config as typeof config & { __authEpoch?: number }
    cfg.__authEpoch = getAuthEpoch()
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
    //
    // Special case — DB restore: when a restore is in progress, the
    // SystemRestoreOverlay (App.tsx) manages the redirect after showing the
    // "Restore Complete" modal.  Doing a hard window.location.replace here
    // would kill the overlay and the toast before the user can read them.
    // We check uiStore instead of the cache endpoint to avoid an extra
    // round-trip on every 401.
    if (status === 401) {
      const cfg = error.config as {
        __authEpoch?: number
        __skipAuthRedirect?: boolean
        __authRecheck?: boolean
      } | undefined
      const requestEpoch = cfg?.__authEpoch
      if (requestEpoch !== undefined && requestEpoch !== getAuthEpoch()) {
        return Promise.reject({ __handled: true })
      }

      if (cfg?.__skipAuthRedirect) {
        return Promise.reject({
          ...(data ?? { success: false, message: 'Unauthenticated.', error_code: 'UNAUTHENTICATED' }),
          status,
        })
      }

      return recheckAuth().then((sessionStillValid) => {
        if (sessionStillValid) {
          return Promise.reject(data ?? { success: false, message: 'Unauthenticated.', error_code: 'UNAUTHENTICATED' })
        }

        void Promise.all([
          import('@/stores/authStore'),
          import('@/stores/uiStore'),
        ]).then(([{ useAuthStore }, { useUiStore }]) => {
          if (useUiStore.getState().systemRestoreInProgress) {
            // Overlay is handling the redirect — suppress the hard page reload.
            return
          }
          useAuthStore.getState().clearAuth()
          bumpAuthEpoch()
          if (!window.location.pathname.startsWith('/login')) {
            window.location.replace('/login')
          }
        })
        return Promise.reject({ __handled: true })
      })
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
