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

let authRecheckPromise: Promise<boolean> | null = null
let authRecheckEpoch: number | null = null

/**
 * C9 FIX: Singleton recheck promise.
 *
 * Multiple concurrent 401 responses must share the SAME recheck promise.
 * Previously, the epoch + window guard had a narrow race where two requests
 * could both start a recheck. Now we always return the in-flight promise
 * if one exists, regardless of epoch/timing.
 */
function recheckAuth(epoch: number): Promise<boolean> {
  // Share only within the same auth epoch. A stale recheck from a prior
  // session must never leak into the next login/logout cycle.
  if (authRecheckPromise && authRecheckEpoch === epoch) {
    return authRecheckPromise
  }

  authRecheckEpoch = epoch
  authRecheckPromise = api
    .get('/auth/me', { __skipAuthRedirect: true, __authRecheck: true } as AxiosRequestConfig)
    .then(() => true)
    .catch((err: unknown) => {
      // Only a definitive 401 means the session is truly gone.
      // Transient errors (network hiccup, cancel, 5xx) should not
      // force logout, otherwise users get random "Session expired" loops.
      if (axios.isCancel(err)) return true
      const status = (err as { status?: number; response?: { status?: number } })?.status
        ?? (err as { response?: { status?: number } })?.response?.status
      return status === 401 ? false : true
    })
    .finally(() => {
      // Clear only for the same epoch so newer sessions keep their own recheck.
      setTimeout(() => {
        if (authRecheckEpoch === epoch) {
          authRecheckPromise = null
          authRecheckEpoch = null
        }
      }, 1000)
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
    // REC-18: Cooldown-aborted duplicate call — show user feedback instead of silent drop
    if (axios.isCancel(error)) {
      // Log at debug level for troubleshooting
      console.debug('[api] Duplicate write request blocked by cooldown')
      return Promise.reject({ __cooldown: true, message: 'Request already in progress. Please wait.' })
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

      const epochAtStart = requestEpoch ?? getAuthEpoch()

      return recheckAuth(epochAtStart).then((sessionStillValid) => {
        // Ignore stale 401 handlers that resolve after a login/logout transition.
        if (epochAtStart !== getAuthEpoch()) {
          return Promise.reject({ __handled: true })
        }

        if (sessionStillValid) {
          return Promise.reject(data ?? { success: false, message: 'Unauthenticated.', error_code: 'UNAUTHENTICATED' })
        }

        void Promise.all([
          import('@/stores/authStore'),
          import('@/stores/uiStore'),
          import('sonner'),
        ]).then(([{ useAuthStore }, { useUiStore }, { toast }]) => {
          if (epochAtStart !== getAuthEpoch()) {
            return
          }
          if (useUiStore.getState().systemRestoreInProgress) {
            // Overlay is handling the redirect — suppress the hard page reload.
            return
          }
          useAuthStore.getState().clearAuth()
          bumpAuthEpoch()
          // C9 FIX: Show visible "Session expired" toast before redirect
          toast.error('Session expired. Please log in again.', { id: 'session-expired', duration: 4000 })
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

// ── GET request retry on network errors ────────────────────────────────────
// Automatically retries failed GET requests up to 2 times with exponential
// backoff when the failure is a network error (no response received).
// Does NOT retry 4xx/5xx responses (those are real server errors).
const MAX_RETRIES = 2
const RETRY_BASE_MS = 1000

api.interceptors.response.use(undefined, async (error) => {
  const config = error.config
  if (
    config &&
    (config.method ?? 'get').toUpperCase() === 'GET' &&
    !error.response && // No response = network error
    !config.__retryCount
  ) {
    config.__retryCount = 0
  }

  if (
    config &&
    (config.method ?? 'get').toUpperCase() === 'GET' &&
    !error.response &&
    (config.__retryCount ?? 0) < MAX_RETRIES
  ) {
    config.__retryCount = (config.__retryCount ?? 0) + 1
    const delay = RETRY_BASE_MS * Math.pow(2, config.__retryCount - 1)
    await new Promise(resolve => setTimeout(resolve, delay))
    return api.request(config)
  }

  return Promise.reject(error)
})

/** Returns true for errors already toasted by the api interceptor (429, 5xx, cooldown). */
export function isHandledApiError(err: unknown): boolean {
  if (err instanceof Error) return false
  const e = err as Record<string, unknown>
  return e?.__handled === true || e?.__cooldown === true
}

/**
 * Download a file from the API using authenticated request.
 * Use this instead of window.open() or <a href> for protected endpoints.
 *
 * @param url - API path (with or without /api/v1 prefix)
 * @param filename - Optional filename for the downloaded file
 * @param mimeType - MIME type (default: application/pdf)
 */
export async function downloadFile(
  url: string,
  filename?: string,
  mimeType: string = 'application/pdf'
): Promise<void> {
  const path = url.replace(/^\/api\/v1/, '')

  const response = await api.get(path, {
    responseType: 'blob',
    headers: { Accept: mimeType },
  })

  const blob = new Blob([response.data], { type: mimeType })
  const blobUrl = window.URL.createObjectURL(blob)

  // Try to get filename from Content-Disposition header
  let downloadFilename = filename || 'download'
  const disposition = response.headers['content-disposition']
  if (disposition) {
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    if (match?.[1]) {
      downloadFilename = match[1].replace(/['"]/g, '')
    }
  }

  const link = document.createElement('a')
  link.href = blobUrl
  link.download = downloadFilename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(blobUrl)
}

export default api
