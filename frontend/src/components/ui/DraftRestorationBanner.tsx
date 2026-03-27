/**
 * DraftRestorationBanner - Shows a prompt to restore unsaved form draft
 *
 * Usage:
 *   const { hasDraft, restore, clear } = useAutoSave('pr-create', formData)
 *
 *   {hasDraft && (
 *     <DraftRestorationBanner
 *       onRestore={() => { const data = restore(); if (data) setFormData(data) }}
 *       onDiscard={clear}
 *       timestamp={getDraftTimestamp('pr-create')}
 *     />
 *   )}
 */
import { RotateCcw, X } from 'lucide-react'

interface DraftRestorationBannerProps {
  onRestore: () => void
  onDiscard: () => void
  timestamp?: string | null
}

function formatDraftTime(iso: string | null | undefined): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return ''
    const now = Date.now()
    const diff = now - d.getTime()
    const mins = Math.floor(diff / 60_000)
    if (mins < 1) return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs}h ago`
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

export function DraftRestorationBanner({ onRestore, onDiscard, timestamp }: DraftRestorationBannerProps): JSX.Element {
  const timeLabel = formatDraftTime(timestamp)

  return (
    <div className="flex items-center justify-between px-4 py-3 rounded-lg border border-blue-200 bg-blue-50 mb-4">
      <div className="flex items-center gap-3">
        <RotateCcw className="h-4 w-4 text-blue-600" />
        <div>
          <p className="text-sm font-medium text-blue-800">Unsaved draft found</p>
          {timeLabel && <p className="text-xs text-blue-600">Last saved {timeLabel}</p>}
        </div>
      </div>
      <div className="flex items-center gap-2">
        <button
          onClick={onRestore}
          className="px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
        >
          Restore Draft
        </button>
        <button
          onClick={onDiscard}
          className="p-1.5 text-blue-400 hover:text-blue-600 rounded hover:bg-blue-100 transition-colors"
          title="Discard draft"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}

export default DraftRestorationBanner
