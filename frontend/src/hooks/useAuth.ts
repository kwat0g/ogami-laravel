import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/authStore'
import api from '@/lib/api'
import type { ApiSuccess, AuthUser } from '@/types/api'

/**
 * Fetches the authenticated user + their full permission set via session cookie.
 * Fires /auth/me once on mount; the 5-minute staleTime prevents redundant calls.
 */
export function useAuth() {
  const { user, setAuth, clearAuth } = useAuthStore()

  const query = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      const res = await api.get<ApiSuccess<AuthUser>>(
        '/auth/me',
        { __skipAuthRedirect: true } as AxiosRequestConfig,
      )
      return res.data.data
    },
    enabled: true,
    staleTime: 5 * 60 * 1000, // 5 minutes - data considered fresh
    gcTime: 10 * 60 * 1000,   // 10 minutes - keep in garbage collection cache
    retry: (failureCount, err) => {
      const status = (err as { status?: number }).status
      if (status === 401) return failureCount < 1
      return false
    },
    retryDelay: 500,
    // Prevent re-fetching when components mount/unmount
    refetchOnMount: false,
    // Re-validate session when the user returns to the tab (catches post-restore logouts)
    refetchOnWindowFocus: true,
    // Background poll every 15 seconds as a fallback when Reverb is not running
    // and the user is idle (no page-level queries firing).
    // The api.ts 401 interceptor then does window.location.replace('/login').
    refetchInterval: 15_000,
  })

  useEffect(() => {
    if (query.data) {
      setAuth(query.data)
    }
  }, [query.data, setAuth])

  // Only clear auth when the server says we're not authenticated AND there is
  // no user in the store yet.  This prevents a stale error state (e.g. the
  // pre-login 401 fetch) from wiping a freshly-set user after login.
  useEffect(() => {
    if (query.isError && !user) {
      clearAuth()
    }
  }, [query.isError, user, clearAuth])

  useEffect(() => {
    if (!query.isError) return
    const status = (query.error as { status?: number }).status
    if (status !== 401) return
    if (query.failureCount < 2) return
    clearAuth()
  }, [query.isError, query.error, query.failureCount, clearAuth])

  return {
    user: user ?? query.data ?? null,
    // Stay loading until the Zustand store is populated, not just until the
    // query resolves. query.isSuccess && !user means the fetch finished but
    // the setAuth() useEffect hasn't run yet (runs after render). Without
    // this guard, RequirePermission renders with an empty store and redirects
    // to /403 for one cycle before the store is set.
    //
    // Guard: only treat as loading when this is the absolute first-ever fetch
    // (neither dataUpdatedAt nor errorUpdatedAt have been set yet). TQ v5
    // resets status → 'pending' on any refetch where data === undefined, so
    // plain query.isLoading flickers to true on every window-focus refetch
    // and refetchInterval tick while on the login page — unmounting <Outlet />
    // and wiping the form. Checking both timestamps ensures background
    // refetches after any prior response never show the skeleton.
    isLoading:
      (query.isLoading && query.dataUpdatedAt === 0 && query.errorUpdatedAt === 0)
      || (query.isSuccess && !user),
    isAuthenticated: !!(user ?? query.data),
  }
}
