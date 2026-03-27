/**
 * ActivityTimeline - Audit trail / activity history component
 *
 * Shows a chronological list of events that happened to an entity.
 * Used on detail pages to display who did what and when.
 *
 * Usage:
 *   <ActivityTimeline
 *     activities={[
 *       { id: 1, action: 'Created', actor: 'John Doe', timestamp: '2026-01-15T10:00:00Z', detail: 'Purchase Request created' },
 *       { id: 2, action: 'Submitted', actor: 'John Doe', timestamp: '2026-01-15T10:30:00Z' },
 *       { id: 3, action: 'Approved', actor: 'Jane Smith', timestamp: '2026-01-16T09:00:00Z', detail: 'Looks good' },
 *     ]}
 *   />
 */
import {
  CheckCircle2,
  XCircle,
  Clock,
  ArrowRight,
  Edit,
  Plus,
  Trash2,
  Eye,
  Send,
  RotateCcw,
} from 'lucide-react'

export interface ActivityEvent {
  id: string | number
  /** The action that was performed */
  action: string
  /** Who performed the action */
  actor?: string | null
  /** When it happened (ISO 8601) */
  timestamp: string
  /** Optional detail/comment */
  detail?: string | null
  /** Optional badge color override */
  type?: 'success' | 'danger' | 'warning' | 'info' | 'neutral'
}

interface ActivityTimelineProps {
  activities: ActivityEvent[]
  /** Max items to show before "Show more" */
  maxVisible?: number
  emptyMessage?: string
}

function getActionIcon(action: string) {
  const normalized = action.toLowerCase()
  if (normalized.includes('created') || normalized.includes('new')) return Plus
  if (normalized.includes('approved') || normalized.includes('confirmed') || normalized.includes('completed') || normalized.includes('passed')) return CheckCircle2
  if (normalized.includes('rejected') || normalized.includes('cancelled') || normalized.includes('failed')) return XCircle
  if (normalized.includes('submitted') || normalized.includes('sent') || normalized.includes('forwarded')) return Send
  if (normalized.includes('updated') || normalized.includes('edited') || normalized.includes('modified')) return Edit
  if (normalized.includes('returned') || normalized.includes('reverted') || normalized.includes('recalled')) return RotateCcw
  if (normalized.includes('deleted') || normalized.includes('removed')) return Trash2
  if (normalized.includes('viewed') || normalized.includes('opened')) return Eye
  if (normalized.includes('moved') || normalized.includes('transitioned') || normalized.includes('converted')) return ArrowRight
  return Clock
}

function getActionColor(action: string, type?: string): string {
  if (type === 'success') return 'bg-green-100 text-green-600'
  if (type === 'danger') return 'bg-red-100 text-red-600'
  if (type === 'warning') return 'bg-amber-100 text-amber-600'
  if (type === 'info') return 'bg-blue-100 text-blue-600'
  if (type === 'neutral') return 'bg-neutral-100 text-neutral-500'

  const normalized = action.toLowerCase()
  if (normalized.includes('approved') || normalized.includes('confirmed') || normalized.includes('completed') || normalized.includes('passed')) return 'bg-green-100 text-green-600'
  if (normalized.includes('rejected') || normalized.includes('cancelled') || normalized.includes('failed')) return 'bg-red-100 text-red-600'
  if (normalized.includes('returned') || normalized.includes('reverted')) return 'bg-amber-100 text-amber-600'
  if (normalized.includes('submitted') || normalized.includes('sent') || normalized.includes('created')) return 'bg-blue-100 text-blue-600'
  return 'bg-neutral-100 text-neutral-500'
}

function formatRelativeTime(iso: string): string {
  const now = Date.now()
  const then = new Date(iso).getTime()
  if (isNaN(then)) return iso

  const diff = now - then
  const mins = Math.floor(diff / 60_000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d ago`

  return new Date(iso).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function ActivityTimeline({ activities, maxVisible = 10, emptyMessage = 'No activity yet' }: ActivityTimelineProps): JSX.Element {
  const sorted = [...activities].sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
  const visible = sorted.slice(0, maxVisible)
  const hasMore = sorted.length > maxVisible

  if (activities.length === 0) {
    return (
      <div className="flex items-center justify-center py-8 text-neutral-400">
        <Clock className="h-5 w-5 mr-2" />
        <span className="text-sm">{emptyMessage}</span>
      </div>
    )
  }

  return (
    <div className="space-y-0">
      {visible.map((event, idx) => {
        const Icon = getActionIcon(event.action)
        const colorClass = getActionColor(event.action, event.type)
        const isLast = idx === visible.length - 1

        return (
          <div key={event.id} className="flex items-start gap-3">
            {/* Icon + connector */}
            <div className="flex flex-col items-center">
              <div className={`w-7 h-7 rounded-full flex items-center justify-center shrink-0 ${colorClass}`}>
                <Icon className="w-3.5 h-3.5" />
              </div>
              {!isLast && <div className="w-0.5 h-6 bg-neutral-200" />}
            </div>

            {/* Content */}
            <div className={`pb-3 flex-1 min-w-0 ${isLast ? 'pb-0' : ''}`}>
              <div className="flex items-baseline justify-between gap-2">
                <p className="text-sm font-medium text-neutral-800">{event.action}</p>
                <span className="text-[10px] text-neutral-400 whitespace-nowrap shrink-0">
                  {formatRelativeTime(event.timestamp)}
                </span>
              </div>
              {event.actor && (
                <p className="text-xs text-neutral-500">by {event.actor}</p>
              )}
              {event.detail && (
                <p className="text-xs text-neutral-500 mt-0.5 italic">"{event.detail}"</p>
              )}
            </div>
          </div>
        )
      })}

      {hasMore && (
        <p className="text-xs text-neutral-400 text-center pt-2">
          + {sorted.length - maxVisible} more event{sorted.length - maxVisible > 1 ? 's' : ''}
        </p>
      )}
    </div>
  )
}

export default ActivityTimeline
