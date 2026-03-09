/**
 * useRealtimeEvents — single Reverb subscription per authenticated user.
 *
 * Mount this ONCE in AppLayout (after auth is confirmed). It opens one
 * WebSocket channel subscription (`private-user.{id}`) and uses it to push
 * TanStack Query cache invalidations instead of polling.
 *
 * Channels & events handled:
 *   private-user.{id}
 *     ├── .leave.filed         → invalidate leaves + notifications
 *     ├── .leave.decided       → invalidate leaves + notifications
 *     └── .payroll.status_changed → invalidate payroll-runs + notifications
 *
 *   private-App.Models.User.{id}
 *     └── (any Laravel Notification broadcast) → invalidate notifications +
 *         domain-specific query keys based on notification type
 *
 * On unmount (logout / navigation), channels are left and the Echo connection
 * is reused for the next auth session (disconnectEcho() is called on logout).
 */
import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { getEcho } from '@/lib/echo'
import { useUiStore } from '@/stores/uiStore'

interface PayrollStatusPayload {
  run_id: number
  reference_no: string
  status: string
}

interface NotificationPayload {
  type?: string
  [key: string]: unknown
}

export function useRealtimeEvents(userId: number | null | undefined): void {
  const qc = useQueryClient()
  const setSystemRestore = useUiStore((s) => s.setSystemRestore)

  useEffect(() => {
    if (!userId) return

    const echo = getEcho()
    if (!echo) return // Reverb not configured — skip WS, polling is the fallback

    // ── 0. Public system channel — no auth required ───────────────────────────
    // Fires when an admin initiates a DB restore so all users get a warning overlay.
    const systemCh = echo
      .channel('system')
      .listen('.system.restore.starting', () => {
        setSystemRestore(true)
      })
    const userCh = echo
      .private(`user.${userId}`)
      // Leave events → invalidate the leave list + unread badge
      .listen('.leave.filed', () => {
        void qc.invalidateQueries({ queryKey: ['notifications'] })
        void qc.invalidateQueries({ queryKey: ['leaves'] })
      })
      .listen('.leave.decided', () => {
        void qc.invalidateQueries({ queryKey: ['notifications'] })
        void qc.invalidateQueries({ queryKey: ['leaves'] })
      })
      // Payroll status change → update the specific run + the run list
      .listen('.payroll.status_changed', (e: PayrollStatusPayload) => {
        void qc.invalidateQueries({ queryKey: ['notifications'] })
        void qc.invalidateQueries({ queryKey: ['payroll-runs'] })
        // If the user has this specific run open, refresh it immediately
        void qc.invalidateQueries({ queryKey: ['payroll-run', String(e.run_id)] })
      })

    // ── 2. Laravel Notification broadcast on App.Models.User.{id} ────────────
    // Fired by any Notification class that includes the 'broadcast' channel.
    // We inspect the notification type to invalidate the right domain query keys.
    const notifCh = echo
      .private(`App.Models.User.${userId}`)
      .notification((notif: NotificationPayload) => {
        // Always refresh the notifications badge/list
        void qc.invalidateQueries({ queryKey: ['notifications'] })

        // Invalidate domain-specific queries based on notification type
        const type = notif?.type ?? ''

        if (type.includes('Loan')) {
          void qc.invalidateQueries({ queryKey: ['loans'] })
          void qc.invalidateQueries({ queryKey: ['team-loans'] })
        }

        if (type.includes('Overtime')) {
          void qc.invalidateQueries({ queryKey: ['overtime-requests'] })
          void qc.invalidateQueries({ queryKey: ['team-overtime-requests'] })
        }

        if (type.includes('Leave')) {
          void qc.invalidateQueries({ queryKey: ['leave-requests'] })
          void qc.invalidateQueries({ queryKey: ['team-leave-requests'] })
          void qc.invalidateQueries({ queryKey: ['leave-balances'] })
          void qc.invalidateQueries({ queryKey: ['leave-calendar'] })
        }

        if (type.includes('VendorInvoice')) {
          void qc.invalidateQueries({ queryKey: ['ap-invoices'] })
        }

        if (type.includes('Payroll')) {
          void qc.invalidateQueries({ queryKey: ['payroll-runs'] })
        }
      })

    return () => {
      // Clean up listeners but leave the WS connection alive for re-use
      systemCh.stopListening('.system.restore.starting')
      echo.leave('system')
      userCh
        .stopListening('.leave.filed')
        .stopListening('.leave.decided')
        .stopListening('.payroll.status_changed')
      notifCh.stopListening('notification')
      echo.leave(`user.${userId}`)
      echo.leave(`App.Models.User.${userId}`)
    }
  }, [userId, qc, setSystemRestore])
}
