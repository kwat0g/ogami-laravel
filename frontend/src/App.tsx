import { useEffect, useRef, useState } from 'react'
import { RouterProvider } from 'react-router-dom'
import { router } from './router'
import { AlertTriangle, RefreshCw } from 'lucide-react'
import { useUiStore } from '@/stores/uiStore'

/**
 * Lives ABOVE the router so no auth redirect / AppLayout unmount can remove it.
 * Polls /api/v1/system/restore-status every 5 s using native fetch (no auth
 * cookies, no Axios interceptors) and also reacts to the Reverb WebSocket
 * flag in uiStore for an instant response when Reverb is running.
 *
 * Behaviour:
 *   in_progress = true  → show blocking overlay for ALL users
 *   in_progress flips false after being true → redirect to /login (unless
 *     already there) so users re-authenticate against the restored DB
 */
function SystemRestoreOverlay() {
  const wsFlag = useUiStore((s) => s.systemRestoreInProgress)
  const [visible, setVisible] = useState(false)
  const seenActive = useRef(false)

  // Instant overlay when Reverb pushes the event
  useEffect(() => {
    if (wsFlag) {
      seenActive.current = true
      setVisible(true)
    }
  }, [wsFlag])

  // Polling fallback — works even when Reverb is not running
  useEffect(() => {
    const check = async () => {
      try {
        const res = await fetch('/api/v1/system/restore-status')
        if (!res.ok) return
        const { in_progress } = (await res.json()) as { in_progress: boolean }
        if (in_progress) {
          seenActive.current = true
          setVisible(true)
        } else if (seenActive.current) {
          // Restore just completed
          seenActive.current = false
          setVisible(false)
          // Redirect to login so everyone re-authenticates with restored data.
          // If already on /login (e.g. redirected earlier by a 401), just
          // hide the overlay — no double-redirect needed.
          if (!window.location.pathname.startsWith('/login')) {
            window.location.replace('/login')
          }
        }
      } catch { /* network hiccup — retry on next tick */ }
    }
    void check()
    const id = setInterval(() => { void check() }, 5_000)
    return () => clearInterval(id)
  }, [])

  if (!visible) return null

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm">
      <div className="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center space-y-5">
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
      </div>
    </div>
  )
}

export default function App() {
  return (
    <>
      <SystemRestoreOverlay />
      <RouterProvider router={router} />
    </>
  )
}
