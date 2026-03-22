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
    // Only fetch if we don't already have user data (prevents race after login)
    enabled: !user,
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
    // Only re-validate when window focuses if we don't have a user
    refetchOnWindowFocus: !user,
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
    // A successful login may have set the user while this /auth/me probe
    // was still in-flight (old session → 401).  Never clear a freshly-set
    // user based on a stale 401 response from a prior session check.
    if (user) return
    const status = (query.error as { status?: number }).status
    if (status !== 401) return
    if (query.failureCount < 2) return
    clearAuth()
  }, [query.isError, query.error, query.failureCount, clearAuth, user])

  return {
    user: user ?? query.data ?? null,
    // Loading if:
    // - Query is loading and no user in store
    // - OR we have query data but haven't synced it to store yet (prevents 403 flash)
    isLoading: (query.isLoading && !user) || (!!query.data && !user),
    isAuthenticated: !!(user ?? query.data),
  }
}
