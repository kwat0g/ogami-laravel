import type { ReactNode } from 'react'

interface PageHeaderProps {
  /** Main page title (h1) */
  title: string
  /** Optional subtitle / description text */
  subtitle?: string
  /** Optional slot for action buttons */
  actions?: ReactNode
}

/**
 * Minimalist page header with clean typography
 */
export default function PageHeader({ title, subtitle, actions }: PageHeaderProps) {
  return (
    <div className="flex items-start justify-between gap-4 mb-8">
      <div className="min-w-0">
        <h1 className="text-xl font-semibold text-neutral-900 tracking-tight">
          {title}
        </h1>
        {subtitle && (
          <p className="mt-1 text-sm text-neutral-500">
            {subtitle}
          </p>
        )}
      </div>
      {actions && (
        <div className="flex items-center gap-2 flex-shrink-0">
          {actions}
        </div>
      )}
    </div>
  )
}
