import { Archive } from 'lucide-react'

/**
 * Amber/orange banner displayed at the top of a list page when viewing
 * archived (soft-deleted) records. Provides clear visual differentiation
 * from the active view.
 */
export default function ArchiveViewBanner() {
  return (
    <div className="flex items-center gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
      <Archive className="h-5 w-5 text-amber-600 flex-shrink-0" />
      <div>
        <p className="text-sm font-semibold text-amber-800">
          You are viewing archived records
        </p>
        <p className="text-xs text-amber-600">
          These records have been archived and are no longer active. You can restore them or permanently delete them.
        </p>
      </div>
    </div>
  )
}
