/**
 * StatusBadge - Minimalist design
 * Renders subtle status indicators with minimal color usage
 */

type Variant = 'default' | 'muted' | 'subtle'

interface StatusBadgeProps {
  label: string
  variant?: Variant
  /** Auto-detect style from label when not explicitly set */
  autoStyle?: boolean
}

// Minimalist status mapping - uses neutrals with minimal accent
const STATUS_STYLES: Record<string, { bg: string; text: string; dot?: string }> = {
  // Active/Positive states
  active: { bg: 'bg-green-50', text: 'text-green-700', dot: 'bg-green-500' },
  completed: { bg: 'bg-green-50', text: 'text-green-600' },
  settled: { bg: 'bg-green-50', text: 'text-green-600' },
  approved: { bg: 'bg-blue-50', text: 'text-blue-700', dot: 'bg-blue-400' },
  verified: { bg: 'bg-blue-50', text: 'text-blue-700' },
  success: { bg: 'bg-green-50', text: 'text-green-700' },
  ready_for_disbursement: { bg: 'bg-blue-50', text: 'text-blue-700', dot: 'bg-blue-400' },
  
  // Pending/Warning states - subtle amber
  pending: { bg: 'bg-amber-50', text: 'text-amber-700', dot: 'bg-amber-500' },
  processing: { bg: 'bg-amber-50', text: 'text-amber-700', dot: 'bg-amber-500' },
  draft: { bg: 'bg-neutral-50', text: 'text-neutral-600' },
  
  // Negative states - subtle red
  rejected: { bg: 'bg-red-50', text: 'text-red-700', dot: 'bg-red-500' },
  cancelled: { bg: 'bg-red-50', text: 'text-red-700', dot: 'bg-red-500' },
  error: { bg: 'bg-red-50', text: 'text-red-700', dot: 'bg-red-500' },
  terminated: { bg: 'bg-red-50', text: 'text-red-700', dot: 'bg-red-500' },
  resigned: { bg: 'bg-red-50', text: 'text-red-700', dot: 'bg-red-500' },
  written_off: { bg: 'bg-red-50', text: 'text-red-700' },
  
  // Info states - subtle blue (minimal use)
  info: { bg: 'bg-slate-50', text: 'text-slate-700' },
  submitted: { bg: 'bg-slate-50', text: 'text-slate-700' },
  
  // Inactive states
  inactive: { bg: 'bg-neutral-50', text: 'text-neutral-500' },
  archived: { bg: 'bg-neutral-50', text: 'text-neutral-500' },
  closed: { bg: 'bg-neutral-50', text: 'text-neutral-500' },
}

function getStatusStyle(label: string): { bg: string; text: string; dot?: string } {
  const normalized = label.toLowerCase().replace(/\s+/g, '_')
  return STATUS_STYLES[normalized] || { bg: 'bg-neutral-100', text: 'text-neutral-700' }
}

export default function StatusBadge({ label, variant = 'default', autoStyle = true }: StatusBadgeProps) {
  const style = autoStyle ? getStatusStyle(label) : { bg: 'bg-neutral-100', text: 'text-neutral-700' }
  
  if (variant === 'muted') {
    return (
      <span className="inline-flex items-center text-xs text-neutral-500">
        {label}
      </span>
    )
  }
  
  if (variant === 'subtle') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs text-neutral-600">
        {style.dot && <span className={`h-1.5 w-1.5 rounded-full ${style.dot}`} />}
        {label}
      </span>
    )
  }

  return (
    <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium ${style.bg} ${style.text}`}>
      {style.dot && <span className={`h-1 w-1 rounded-full ${style.dot}`} />}
      {label}
    </span>
  )
}
