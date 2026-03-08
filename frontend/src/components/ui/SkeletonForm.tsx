/**
 * SkeletonForm
 *
 * Content-shaped loading placeholder for form pages.
 * Renders N rows of label + input skeleton pairs.
 *
 * Usage:
 *   <SkeletonForm rows={6} cols={2} />           // 2-column grid form
 *   <SkeletonForm rows={8} withHeader withActions />  // full page form skeleton
 */

interface SkeletonFormProps {
  /** Number of field rows to render. Default: 6 */
  rows?: number
  /** Number of columns in the form grid. 1 (stacked) or 2 (two-col grid). Default: 1 */
  cols?: 1 | 2
  /** Show a page header skeleton (title + breadcrumbs). Default: false */
  withHeader?: boolean
  /** Show a bottom action bar skeleton (Save / Cancel). Default: false */
  withActions?: boolean
  /** Show a card wrapper around the form fields. Default: true */
  withCard?: boolean
  className?: string
}

function FieldSkeleton() {
  return (
    <div className="flex flex-col gap-1.5">
      {/* Label */}
      <div className="h-3.5 bg-neutral-200 rounded w-24" />
      {/* Input */}
      <div className="h-10 bg-neutral-200 rounded w-full" />
    </div>
  )
}

export default function SkeletonForm({
  rows = 6,
  cols = 1,
  withHeader = false,
  withActions = false,
  withCard = true,
  className = '',
}: SkeletonFormProps) {
  const fields = Array.from({ length: rows })

  const gridClass = cols === 2
    ? 'grid grid-cols-1 sm:grid-cols-2 gap-5'
    : 'flex flex-col gap-5'

  const body = (
    <div className={['animate-pulse', className].join(' ')} role="status" aria-label="Loading form">
      {withHeader && (
        <div className="mb-6">
          {/* Breadcrumbs */}
          <div className="flex items-center gap-2 mb-3">
            <div className="h-3.5 bg-neutral-200 rounded w-16" />
            <div className="h-3 bg-neutral-200 rounded w-2" />
            <div className="h-3.5 bg-neutral-200 rounded w-24" />
            <div className="h-3 bg-neutral-200 rounded w-2" />
            <div className="h-3.5 bg-neutral-200 rounded w-32" />
          </div>
          {/* Title */}
          <div className="h-7 bg-neutral-200 rounded w-56 mb-1" />
          {/* Subtitle */}
          <div className="h-4 bg-neutral-200 rounded w-72" />
        </div>
      )}

      <div className={withCard ? 'bg-white border border-neutral-200 rounded p-6' : ''}>
        <div className={gridClass}>
          {fields.map((_, i) => (
            <FieldSkeleton key={i} />
          ))}
        </div>
      </div>

      {withActions && (
        <div className="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-neutral-100">
          <div className="h-9 bg-neutral-200 rounded w-20" />
          <div className="h-9 bg-neutral-200 rounded w-28" />
        </div>
      )}

      <span className="sr-only">Loading form…</span>
    </div>
  )

  return body
}
