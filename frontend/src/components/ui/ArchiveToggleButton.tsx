import { Archive, ArrowLeft } from 'lucide-react'

interface ArchiveToggleButtonProps {
  isArchiveView: boolean
  onToggle: () => void
  archivedCount?: number
}

/**
 * A toggle button for switching between active and archived record views.
 * Placed in the page header next to primary action buttons.
 */
export default function ArchiveToggleButton({
  isArchiveView,
  onToggle,
  archivedCount,
}: ArchiveToggleButtonProps) {
  return (
    <button
      onClick={onToggle}
      className={`
        inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded border transition-colors
        ${
          isArchiveView
            ? 'bg-amber-50 border-amber-300 text-amber-800 hover:bg-amber-100'
            : 'bg-white border-neutral-300 text-neutral-700 hover:bg-neutral-50'
        }
      `}
    >
      {isArchiveView ? (
        <>
          <ArrowLeft className="h-4 w-4" />
          Back to Active
        </>
      ) : (
        <>
          <Archive className="h-4 w-4" />
          View Archive
          {archivedCount !== undefined && archivedCount > 0 && (
            <span className="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-semibold rounded-full bg-neutral-200 text-neutral-600">
              {archivedCount}
            </span>
          )}
        </>
      )}
    </button>
  )
}
