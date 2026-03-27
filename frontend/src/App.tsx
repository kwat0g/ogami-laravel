import { useEffect, useRef, useState } from 'react'
import AppRouter from './router'
import { AlertTriangle, CheckCircle2, RefreshCw } from 'lucide-react'
import { useUiStore } from '@/stores/uiStore'

/**
 * Lives ABOVE the router so no auth redirect / AppLayout unmount can remove it.
 *
 * Data sources (both used simultaneously):
 *   1. Reverb WebSocket — instant signal via uiStore.systemRestoreInProgress
 *   2. Polling /api/v1/system/restore-status every 2 s — fallback when Reverb is
 *      down (dev) and to detect the two-phase cache value:
 *        { in_progress: true,  completed: false } → restore is running
 *        { in_progress: true,  completed: true  } → restore done, 15 s window
 *        { in_progress: false, completed: false } → nothing in progress
 *
 * Behaviour:
 *   restoring phase → show "System Maintenance" blocking overlay for ALL users
 *   done    phase   → show "Restore Complete" overlay + redirect to /login after 3 s
 *
 * The 15-second 'done' window in the cache (set by BackupController after the
 * restore) guarantees every 2-second poll catches the flag at least once, even
 * for fast restores that finish in under one poll cycle.
 */
function SystemRestoreOverlay() {
  const wsFlag = useUiStore((s) => s.systemRestoreInProgress)
  const [visible, setVisible]   = useState(false)
  const [done, setDone]         = useState(false)
  const seenRestoring           = useRef(false)
  const redirectScheduled       = useRef(false)
  const visibleSince            = useRef<number | null>(null)

  // Instant overlay when Reverb broadcasts SystemRestoreStarting
  useEffect(() => {
    if (wsFlag) {
      seenRestoring.current = true
      if (!visibleSince.current) visibleSince.current = Date.now()
      setDone(false)
      setVisible(true)
    }
  }, [wsFlag])

  // Polling fallback — 2 s interval, works without Reverb
  useEffect(() => {
    // Schedule the "done" redirect exactly once; guards re-entry.
    const scheduleRedirect = () => {
      if (redirectScheduled.current) return
      redirectScheduled.current = true
      setDone(true)
      setVisible(true)
      setTimeout(() => {
        // Unblock the 401 interceptor BEFORE navigating so any in-flight
        // auth requests that were suppressed can proceed normally afterwards.
        useUiStore.getState().setSystemRestore(false)
        if (!window.location.pathname.startsWith('/login')) {
          window.location.replace('/login')
        } else {
          // Already on /login — just dismiss the overlay
          setVisible(false)
        }
      }, 3_000)
    }

    const check = async () => {
      try {
        const res = await fetch('/api/v1/system/restore-status')
        if (!res.ok) return
        const { in_progress, completed } = (await res.json()) as {
          in_progress: boolean
          completed: boolean
        }

        if (in_progress && !completed) {
          // Actively restoring — show maintenance overlay.
          // Also set the uiStore flag so the 401 interceptor knows to hold off
          // on doing a hard page-reload; the overlay will handle the redirect.
          seenRestoring.current = true
          if (!visibleSince.current) visibleSince.current = Date.now()
          useUiStore.getState().setSystemRestore(true)
          setDone(false)
          setVisible(true)
        } else if (in_progress && completed) {
          // Restore done; 15 s window still open — transition to success overlay
          seenRestoring.current = true
          useUiStore.getState().setSystemRestore(true)
          scheduleRedirect()
        } else if (!in_progress && seenRestoring.current) {
          // Fallback: TTL expired before the 'done' phase was caught.
          // Dismiss the overlay and redirect if not already on login.
          useUiStore.getState().setSystemRestore(false)
          if (!window.location.pathname.startsWith('/login')) {
            window.location.replace('/login')
          } else {
            // Already on /login (race: redirect happened before done phase) — just dismiss.
            setVisible(false)
          }
        }
      } catch { /* network hiccup — retry on next tick */ }

      // Safety net: if the overlay has been showing for >3 min and we still
      // can't get a clean status (server outage / stale state), force-dismiss.
      if (visibleSince.current && Date.now() - visibleSince.current > 3 * 60_000) {
        useUiStore.getState().setSystemRestore(false)
        visibleSince.current = null
        setVisible(false)
      }
    }

    void check()
    const id = setInterval(() => { void check() }, 2_000)
    return () => clearInterval(id)
  }, []) // dependencies intentionally empty — all logic uses refs

  if (!visible) return null

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm">
      <div className="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center space-y-5">
        {done ? (
          <>
            <div className="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto">
              <CheckCircle2 className="h-8 w-8 text-green-600" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-neutral-900">Restore Complete</h2>
              <p className="text-sm text-neutral-500 mt-2 leading-relaxed">
                The database has been restored successfully. All sessions have been
                invalidated.
              </p>
              <p className="text-sm font-medium text-green-700 mt-2">
                Redirecting to login in a moment…
              </p>
            </div>
            <div className="flex items-center justify-center gap-2 text-sm text-neutral-600 bg-neutral-50 rounded-lg p-3 border border-neutral-200">
              <RefreshCw className="h-4 w-4 animate-spin text-neutral-400" />
              <span>Please wait…</span>
            </div>
          </>
        ) : (
          <>
            <div className="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mx-auto">
              <AlertTriangle className="h-8 w-8 text-amber-600" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-neutral-900">System Maintenance</h2>
              <p className="text-sm text-neutral-500 mt-2 leading-relaxed">
                An administrator is restoring the database. All sessions will be
                invalidated once the restore is complete.
              </p>
              <p className="text-sm font-medium text-amber-700 mt-2">
                You will be redirected to the login page automatically.
              </p>
            </div>
            <div className="flex items-center justify-center gap-2 text-sm text-neutral-600 bg-neutral-50 rounded-lg p-3 border border-neutral-200">
              <RefreshCw className="h-4 w-4 animate-spin text-neutral-400" />
              <span>Please wait, do not close this tab…</span>
            </div>
          </>
        )}
      </div>
    </div>
  )
}

/** Global keyboard shortcut: Ctrl+Shift+D toggles dark mode. */
function useDarkModeShortcut() {
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault()
        useUiStore.getState().toggleColorMode()
      }
    }
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [])
}

export default function App() {
  useDarkModeShortcut()

  return (
    <>
      <SystemRestoreOverlay />
      <AppRouter />
    </>
  )
}
