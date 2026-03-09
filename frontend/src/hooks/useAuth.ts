import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
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
      const res = await api.get<ApiSuccess<AuthUser>>('/auth/me')
      return res.data.data
    },
    enabled: true,
    staleTime: 5 * 60 * 1000, // 5 minutes - data considered fresh
    gcTime: 10 * 60 * 1000,   // 10 minutes - keep in garbage collection cache
    retry: false,
    // Prevent re-fetching when components mount/unmount
    refetchOnMount: false,
    // Re-validate session when the user returns to the tab (catches post-restore logouts)
    refetchOnWindowFocus: true,
    // Background poll every 90 seconds as a fallback for users not connected to
    // Reverb — ensures session wipes (e.g. after DB restore) are detected quickly
    // even for idle users. The api.ts 401 interceptor then redirects to /login.
    refetchInterval: 90_000,
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

  return {
    user: user ?? query.data ?? null,
    // Stay loading until the Zustand store is populated, not just until the
    // query resolves. query.isSuccess && !user means the fetch finished but
    // the setAuth() useEffect hasn't run yet (runs after render). Without
    // this guard, RequirePermission renders with an empty store and redirects
    // to /403 for one cycle before the store is set.
    isLoading: query.isLoading || (query.isSuccess && !user),
    isAuthenticated: !!(user ?? query.data),
  }
}
