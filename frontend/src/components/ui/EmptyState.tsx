import type { ReactNode } from 'react'
import { InboxIcon } from 'lucide-react'

interface EmptyStateProps {
  /** Optional icon component. Defaults to an Inbox icon. */
  icon?: ReactNode
  /** Short primary message (e.g. "No employees found"). */
  title: string
  /** Optional supporting description. */
  description?: string
  /** Optional action slot (e.g. a "Create" button). */
  action?: ReactNode
}

/**
 * Shown when a list or query returns no results.
 *
 * Usage:
 * ```tsx
 * <EmptyState
 *   title="No leave requests"
 *   description="No requests match the current filters."
 *   action={<Link to="/hr/leave/new">+ New Request</Link>}
 * />
 * ```
 */
export default function EmptyState({ icon, title, description, action }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center py-16 px-4 text-center">
      <div className="text-neutral-300 mb-4">
        {icon ?? <InboxIcon className="h-12 w-12" strokeWidth={1.5} />}
      </div>
      <h3 className="text-base font-semibold text-neutral-700">{title}</h3>
      {description && (
        <p className="mt-1 text-sm text-neutral-500 max-w-sm">{description}</p>
      )}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
