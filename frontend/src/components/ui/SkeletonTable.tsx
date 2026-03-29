/**
 * SkeletonTable
 *
 * Content-shaped loading placeholder for data tables.
 * Renders a table header row followed by N skeleton body rows × M columns.
 *
 * Usage:
 *   <SkeletonTable rows={8} cols={5} />
 */

interface SkeletonTableProps {
  /** Number of skeleton body rows to render. Default: 8 */
  rows?: number
  /** Number of columns to render. Default: 5 */
  cols?: number
  /** Show a page header skeleton above the table. Default: false */
  withHeader?: boolean
  className?: string
}

const COL_WIDTHS = ['w-full', 'w-4/5', 'w-3/4', 'w-full', 'w-2/3', 'w-5/6']

export default function SkeletonTable({
  rows = 8,
  cols = 5,
  withHeader = false,
  className = '',
}: SkeletonTableProps) {
  return (
    <div
      className={['animate-pulse', className].join(' ')}
      role="status"
      aria-label="Loading table"
    >
      {/* Optional page header skeleton */}
      {withHeader && (
        <div className="flex items-center justify-between mb-4">
          <div className="h-7 bg-neutral-200 rounded w-48" />
          <div className="h-9 bg-neutral-200 rounded w-32" />
        </div>
      )}

      {/* Table search / filter bar */}
      <div className="flex items-center gap-3 mb-3">
        <div className="h-9 bg-neutral-200 rounded w-64" />
        <div className="h-9 bg-neutral-200 rounded w-24" />
      </div>

      {/* Table */}
      <div className="border border-neutral-200 rounded-xl overflow-hidden">
        {/* Header row */}
        <div className="bg-neutral-50 border-b border-neutral-200 flex gap-4 px-4 py-3">
          {Array.from({ length: cols }).map((_, i) => (
            <div
              key={i}
              className="h-4 bg-neutral-300 rounded flex-1"
              style={{ maxWidth: i === 0 ? '120px' : undefined }}
            />
          ))}
        </div>

        {/* Body rows */}
        {Array.from({ length: rows }).map((_, rowIdx) => (
          <div
            key={rowIdx}
            className={[
              'flex gap-4 px-4 py-3 border-b border-neutral-100 last:border-0',
              rowIdx % 2 === 1 ? 'bg-neutral-50/30' : 'bg-white',
            ].join(' ')}
          >
            {Array.from({ length: cols }).map((_, colIdx) => (
              <div
                key={colIdx}
                className={[
                  'h-4 bg-neutral-200 rounded flex-1',
                  COL_WIDTHS[(rowIdx + colIdx) % COL_WIDTHS.length],
                ].join(' ')}
              />
            ))}
          </div>
        ))}
      </div>

      {/* Pagination skeleton */}
      <div className="flex items-center justify-between mt-3">
        <div className="h-4 bg-neutral-200 rounded w-40" />
        <div className="flex gap-1">
          {[1, 2, 3, 4].map((n) => (
            <div key={n} className="h-8 w-8 bg-neutral-200 rounded" />
          ))}
        </div>
      </div>

      <span className="sr-only">Loading…</span>
    </div>
  )
}
