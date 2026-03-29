/**
 * ReadOnlyField
 *
 * Displays a labelled read-only value in detail/view panels.
 * Used across HR, Payroll and Finance read views where data cannot be edited.
 *
 * Usage:
 *   <ReadOnlyField label="Employee Code" value="EMP-2026-0001" />
 *   <ReadOnlyField label="Monthly Rate"  value={<CurrencyAmount centavos={1500000} />} />
 */

interface ReadOnlyFieldProps {
  label: string
  value: React.ReactNode
  /** Stack label above value (default) or inline side-by-side. */
  layout?: 'stacked' | 'inline'
  className?: string
}

export default function ReadOnlyField({
  label,
  value,
  layout = 'stacked',
  className = '',
}: ReadOnlyFieldProps) {
  if (layout === 'inline') {
    return (
      <div className={['flex items-center justify-between gap-4 py-1', className].join(' ')}>
        <span className="text-sm text-neutral-500 shrink-0">{label}</span>
        <span className="text-sm font-medium text-neutral-900 text-right">{value ?? '—'}</span>
      </div>
    )
  }

  return (
    <div className={['space-y-0.5', className].join(' ')}>
      <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">{label}</p>
      <p className="text-sm text-neutral-900">{value ?? <span className="text-neutral-400">—</span>}</p>
    </div>
  )
}
