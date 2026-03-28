import { ChevronLeft, ChevronRight } from 'lucide-react'

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page?: number
  total?: number
}

interface PaginationProps {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  /** Show total count label (default: true) */
  showTotal?: boolean
}

/**
 * Shared pagination control for all list pages.
 * Renders previous/next buttons with page info.
 */
export default function Pagination({ meta, onPageChange, showTotal = true }: PaginationProps) {
  const { current_page, last_page, total } = meta
  if (last_page <= 1) return null

  return (
    <div className="flex items-center justify-between pt-4 text-sm text-neutral-600 dark:text-neutral-400">
      <div>
        {showTotal && total != null && (
          <span>{total.toLocaleString()} total</span>
        )}
      </div>
      <div className="flex items-center gap-2">
        <button
          disabled={current_page <= 1}
          onClick={() => onPageChange(current_page - 1)}
          aria-label="Previous page"
          className="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-neutral-300 dark:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronLeft className="w-4 h-4" /> Prev
        </button>
        <span className="px-2 tabular-nums">
          {current_page} / {last_page}
        </span>
        <button
          disabled={current_page >= last_page}
          onClick={() => onPageChange(current_page + 1)}
          aria-label="Next page"
          className="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-neutral-300 dark:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          Next <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  )
}
