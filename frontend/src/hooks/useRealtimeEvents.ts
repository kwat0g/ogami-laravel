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

  // ── Public system channel — subscribed for ALL authenticated users ────────
  // Must be outside the userId guard so the restore-completed redirect fires
  // even if userId resolves slightly after mount.
  useEffect(() => {
    const echo = getEcho()
    if (!echo) return

    const systemCh = echo
      .channel('system')
      .listen('.system.restore.starting', () => {
        setSystemRestore(true)
      })
      .listen('.system.restore.completed', () => {
        // Signal completion — SystemRestoreOverlay in App.tsx handles the redirect.
        setSystemRestore(false)
      })

    return () => {
      systemCh
        .stopListening('.system.restore.starting')
        .stopListening('.system.restore.completed')
      echo.leave('system')
    }
  }, [qc, setSystemRestore])

  // ── 2. Per-user private channels ─────────────────────────────────────────
  useEffect(() => {
    if (!userId) return

    const echo = getEcho()
    if (!echo) return

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
        void qc.invalidateQueries({ queryKey: ['payroll-run', String(e.run_id)] })
      })

    // Laravel Notification broadcast on App.Models.User.{id}
    const notifCh = echo
      .private(`App.Models.User.${userId}`)
      .notification((notif: NotificationPayload) => {
        void qc.invalidateQueries({ queryKey: ['notifications'] })

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
      userCh
        .stopListening('.leave.filed')
        .stopListening('.leave.decided')
        .stopListening('.payroll.status_changed')
      notifCh.stopListening('notification')
      echo.leave(`user.${userId}`)
      echo.leave(`App.Models.User.${userId}`)
    }
  }, [userId, qc])
}

