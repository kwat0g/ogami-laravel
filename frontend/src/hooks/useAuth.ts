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
  const { user, setAuth } = useAuthStore()

  const query = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: async ({ signal }) => {
      const res = await api.get<ApiSuccess<AuthUser>>(
        '/auth/me',
        { signal, __skipAuthRedirect: true } as AxiosRequestConfig,
      )
      return res.data.data
    },
    // Only fetch if we don't already have user data (prevents race after login)
    enabled: !user,
    staleTime: 5 * 60 * 1000, // 5 minutes - data considered fresh
    gcTime: 10 * 60 * 1000,   // 10 minutes - keep in garbage collection cache
    retry: (failureCount, err) => {
      const status = (err as { status?: number; response?: { status?: number } }).status
        ?? (err as { response?: { status?: number } }).response?.status
      // Occasionally /auth/me returns a transient 401 during session bootstrap.
      // Retry once before treating the user as logged out.
      if (status === 401) return failureCount < 1
      return false
    },
    retryDelay: 300,
    // Prevent re-fetching when components mount/unmount
    refetchOnMount: false,
    // Avoid repeated unauthenticated probes from focus events.
    refetchOnWindowFocus: false,
  })

  useEffect(() => {
    if (query.data) {
      setAuth(query.data)
    }
  }, [query.data, setAuth])

  return {
    user: user ?? query.data ?? null,
    // Loading if:
    // - Query is loading and no user in store
    // - OR we have query data but haven't synced it to store yet (prevents 403 flash)
    isLoading: (query.isLoading && !user) || (!!query.data && !user),
    isAuthenticated: !!(user ?? query.data),
  }
}
