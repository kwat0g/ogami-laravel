import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface AuditEntry {
  id: number
  event: string
  old_values: Record<string, unknown>
  new_values: Record<string, unknown>
  user_id: number | null
  user_name: string | null
  created_at: string
}

function formatDate(dateStr: string): string {
  try {
    return new Date(dateStr).toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}

const EVENT_LABELS: Record<string, string> = {
  created: 'Created',
  updated: 'Updated',
  deleted: 'Deleted',
  restored: 'Restored',
}

interface Props {
  auditableType: string
  auditableId: number
  title?: string
}

/**
 * Status Timeline — shows the audit history of status transitions for any model.
 * Uses owen-it/laravel-auditing data via a generic API endpoint.
 */
export default function StatusTimeline({ auditableType, auditableId, title = 'Activity Timeline' }: Props) {
  const { data: audits, isLoading } = useQuery({
    queryKey: ['audit-trail', auditableType, auditableId],
    queryFn: async () => {
      const res = await api.get<{ data: AuditEntry[] }>(
        `/audit-trail/${auditableType}/${auditableId}`,
      )
      return res.data.data
    },
    staleTime: 60_000,
  })

  if (isLoading) return <SkeletonLoader rows={3} />
  if (!audits || audits.length === 0) return <div className="text-xs text-neutral-400 py-2">No activity recorded.</div>

  return (
    <div className="space-y-2">
      <h4 className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{title}</h4>
      <div className="space-y-1">
        {audits.map((audit) => {
          const statusChanged = audit.new_values?.status !== undefined
          const oldStatus = audit.old_values?.status as string | undefined
          const newStatus = audit.new_values?.status as string | undefined
          const changedFields = Object.keys(audit.new_values).filter((k) => k !== 'updated_at')

          return (
            <div key={audit.id} className="flex items-start gap-2 text-xs py-1 border-b border-neutral-100 dark:border-neutral-800 last:border-0">
              <div className="text-neutral-400 whitespace-nowrap w-32 flex-shrink-0">
                {formatDate(audit.created_at)}
              </div>
              <div className="flex-1">
                {statusChanged ? (
                  <span>
                    Status changed from{' '}
                    <span className="font-medium text-neutral-600 dark:text-neutral-300">
                      {(oldStatus ?? 'new').replace(/_/g, ' ')}
                    </span>
                    {' to '}
                    <span className="font-semibold text-neutral-900 dark:text-white">
                      {(newStatus ?? '').replace(/_/g, ' ')}
                    </span>
                  </span>
                ) : (
                  <span className="text-neutral-500">
                    {EVENT_LABELS[audit.event] ?? audit.event}
                    {changedFields.length > 0 && (
                      <span className="text-neutral-400"> ({changedFields.join(', ')})</span>
                    )}
                  </span>
                )}
              </div>
              <div className="text-neutral-400 whitespace-nowrap flex-shrink-0">
                {audit.user_name ?? 'System'}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
