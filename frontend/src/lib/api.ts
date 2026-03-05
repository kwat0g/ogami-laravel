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
const WRITE_COOLDOWN_MS = 800
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
    if (!error.response) {
      return Promise.reject(new Error('Network error. Please check your connection.'))
    }

    const status = error.response.status as number
    const data   = error.response.data as ApiError

    // ── 401: Clear auth state if no active user session ──────────────────
    if (status === 401) {
      import('@/stores/authStore').then(({ useAuthStore }) => {
        if (useAuthStore.getState().user) return
        useAuthStore.getState().clearAuth()
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
    }

    // ── 5xx: Show server error toast ──────────────────────────────────────
    if (status >= 500) {
      import('sonner').then(({ toast }) => {
        toast.error('Server error. Please try again or contact support.', {
          id: `server-error-${status}`,
          duration: 6000,
        })
      })
    }

    return Promise.reject(data ?? { success: false, message: 'An unexpected error occurred.', error_code: 'UNKNOWN' })
  },
)

export default api
