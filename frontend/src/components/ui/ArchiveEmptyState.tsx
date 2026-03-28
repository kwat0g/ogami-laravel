import { Archive, CheckCircle } from 'lucide-react'

interface ArchiveEmptyStateProps {
  isArchiveView: boolean
  /** Label for the record type, e.g. "employees", "vendors" */
  recordLabel?: string
}

/**
 * Empty state component for both active and archive views.
 */
export default function ArchiveEmptyState({
  isArchiveView,
  recordLabel = 'records',
}: ArchiveEmptyStateProps) {
  if (isArchiveView) {
    return (
      <div className="py-16 text-center text-neutral-400">
        <Archive className="h-10 w-10 mx-auto mb-3 opacity-40" />
        <p className="text-sm font-medium text-neutral-500">Archive is empty</p>
        <p className="text-xs mt-1">
          Archived {recordLabel} will appear here.
        </p>
      </div>
    )
  }

  return (
    <div className="py-16 text-center text-neutral-400">
      <CheckCircle className="h-10 w-10 mx-auto mb-3 opacity-40" />
      <p className="text-sm font-medium text-neutral-500">No {recordLabel} found</p>
      <p className="text-xs mt-1">
        Start by adding a new record.
      </p>
    </div>
  )
}
