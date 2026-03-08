import { useRef, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { Bell, CheckCheck, ExternalLink } from 'lucide-react'
import {
  useUnreadCount,
  useNotifications,
  useMarkRead,
  useMarkAllRead,
  useNotificationPanel,
  type AppNotification,
} from '@/hooks/useNotifications'

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return 'just now'
  if (m < 60) return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h ago`
  return `${Math.floor(h / 24)}d ago`
}

// ── Notification item row ─────────────────────────────────────────────────────

function NotificationRow({ n, onRead }: { n: AppNotification; onRead: (id: string) => void }) {
  const content = (
    <div
      className={`px-4 py-3 hover:bg-neutral-50 transition-colors cursor-pointer ${!n.read ? 'bg-neutral-50' : ''}`}
      onClick={() => { if (!n.read) onRead(n.id) }}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          {n.title && (
            <p className={`text-sm font-medium text-neutral-900 truncate ${!n.read ? 'font-semibold' : ''}`}>
              {n.title}
            </p>
          )}
          {n.message && (
            <p className="text-xs text-neutral-500 mt-0.5 line-clamp-2">{n.message}</p>
          )}
          <p className="text-[11px] text-neutral-400 mt-1">{timeAgo(n.created_at)}</p>
        </div>
        <div className="flex items-center gap-1.5 flex-shrink-0">
          {!n.read && (
            <span className="w-2 h-2 bg-neutral-900 rounded-full" />
          )}
          {n.action_url && (
            <ExternalLink className="h-3.5 w-3.5 text-neutral-400" />
          )}
        </div>
      </div>
    </div>
  )

  if (n.action_url) {
    return (
      <Link to={n.action_url} className="block">
        {content}
      </Link>
    )
  }

  return content
}

// ── Main component ────────────────────────────────────────────────────────────

export default function NotificationBell() {
  const { open, toggle, close } = useNotificationPanel()
  const { data: countData } = useUnreadCount()
  const { data, isLoading } = useNotifications(1, false)
  const markRead    = useMarkRead()
  const markAllRead = useMarkAllRead()
  const panelRef    = useRef<HTMLDivElement>(null)

  const unread = countData?.count ?? 0

  // Close on click outside
  useEffect(() => {
    if (!open) return
    const handler = (e: MouseEvent) => {
      if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
        close()
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [open, close])

  return (
    <div className="relative" ref={panelRef}>
      {/* Bell button */}
      <button
        onClick={toggle}
        className="relative p-2 rounded border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 transition-colors text-neutral-600 hover:text-neutral-900"
        aria-label="Notifications"
      >
        <Bell className="h-5 w-5" />
        {unread > 0 && (
          <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-0.5 leading-none">
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {/* Dropdown panel */}
      {open && (
        <div className="absolute right-0 top-full mt-2 w-96 bg-white border border-neutral-200 rounded shadow-lg z-50">
          {/* Panel header */}
          <div className="flex items-center justify-between px-4 py-3 border-b border-neutral-200">
            <h3 className="text-sm font-semibold text-neutral-900">
              Notifications {unread > 0 && <span className="text-neutral-600">({unread} new)</span>}
            </h3>
            {unread > 0 && (
              <button
                onClick={() => markAllRead.mutate()}
                disabled={markAllRead.isPending}
                className="flex items-center gap-1 px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium disabled:opacity-50"
              >
                <CheckCheck className="h-3.5 w-3.5" />
                Mark all read
              </button>
            )}
          </div>

          {/* Items */}
          <div className="max-h-[400px] overflow-y-auto divide-y divide-neutral-100">
            {isLoading ? (
              <div className="px-4 py-8 text-center text-sm text-neutral-400">Loading…</div>
            ) : !data?.data.length ? (
              <div className="px-4 py-8 text-center text-sm text-neutral-400">
                <Bell className="h-8 w-8 mx-auto mb-2 opacity-30" />
                No notifications yet
              </div>
            ) : (
              data.data.map((n) => (
                <NotificationRow
                  key={n.id}
                  n={n}
                  onRead={(id) => { markRead.mutate(id); close() }}
                />
              ))
            )}
          </div>

          {/* Footer */}
          {data && data.meta.total > 20 && (
            <div className="border-t border-neutral-200 px-4 py-2.5 text-center">
              <Link
                to="/notifications"
                className="inline-block px-3 py-1.5 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                onClick={close}
              >
                View all {data.meta.total} notifications
              </Link>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
