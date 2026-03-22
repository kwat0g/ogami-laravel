import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface AppNotification {
  id: string
  type: string | null
  title: string | null
  message: string | null
  action_url: string | null
  read: boolean
  read_at: string | null
  created_at: string
}

interface NotificationMeta {
  current_page: number
  last_page: number
  total: number
  unread_count: number
}

interface NotificationsResponse {
  data: AppNotification[]
  meta: NotificationMeta
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

/** Paginated notification list with optional unread-only filter. */
export function useNotifications(page = 1, unreadOnly = false, enabled = true) {
  return useQuery<NotificationsResponse>({
    queryKey: ['notifications', page, unreadOnly],
    queryFn: async () => {
      const res = await api.get<NotificationsResponse>('/notifications', {
        params: { page, per_page: 20, unread: unreadOnly ? 1 : undefined },
      })
      return res.data
    },
    enabled,
    // Keep data fresh for the full polling cycle — prevents stale-on-mount
    // refetches in the 5min window between polls.
    staleTime: 5 * 60_000,
    // WebSocket (Reverb) pushes invalidations in real-time via useRealtimeEvents.
    // This poll is a fallback for when the WS connection is unavailable.
    refetchInterval: enabled ? 5 * 60_000 : false,
    refetchIntervalInBackground: false,
  })
}

/** Lightweight poll for the notification badge count. */
export function useUnreadCount(enabled = true) {
  return useQuery<{ count: number }>({
    queryKey: ['notifications', 'unread-count'],
    queryFn: async () => {
      const res = await api.get<{ count: number }>('/notifications/unread-count')
      return res.data
    },
    enabled,
    // Keep data fresh for the poll cycle.
    staleTime: 60_000,
    // WS push handles real-time updates; this is a 60s backstop if WS drops.
    refetchInterval: enabled ? 60_000 : false,
    refetchIntervalInBackground: false,
    // Refetch badge immediately when the user returns to the tab.
    refetchOnWindowFocus: enabled,
  })
}

/** Mark a single notification as read. */
export function useMarkRead() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => api.put(`/notifications/${id}/read`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}

/** Mark all notifications as read. */
export function useMarkAllRead() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.put('/notifications/read-all'),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}

/** Delete (dismiss) a single notification. */
export function useDeleteNotification() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => api.delete(`/notifications/${id}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}

/** Toggle open/close state with click-outside handling (returned for convenience). */
export function useNotificationPanel() {
  const [open, setOpen] = useState(false)
  const toggle = () => setOpen((v) => !v)
  const close  = () => setOpen(false)
  return { open, toggle, close }
}
