import { cn } from '@/lib/utils'

interface StatusBadgeProps {
  status?: string
  children: React.ReactNode
  className?: string
}

const STATUS_STYLES: Record<string, string> = {
  // General
  draft: 'bg-neutral-100 text-neutral-600 border-neutral-200',
  pending: 'bg-amber-50 text-amber-700 border-amber-200',
  active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  inactive: 'bg-neutral-100 text-neutral-500 border-neutral-200',
  completed: 'bg-blue-50 text-blue-700 border-blue-200',
  approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  rejected: 'bg-red-50 text-red-700 border-red-200',
  cancelled: 'bg-neutral-100 text-neutral-400 border-neutral-200',
  closed: 'bg-neutral-100 text-neutral-500 border-neutral-200',
  
  // Priority
  low: 'bg-blue-50 text-blue-700 border-blue-200',
  minor: 'bg-blue-50 text-blue-700 border-blue-200',
  medium: 'bg-amber-50 text-amber-700 border-amber-200',
  major: 'bg-amber-50 text-amber-700 border-amber-200',
  high: 'bg-red-50 text-red-700 border-red-200',
  critical: 'bg-red-50 text-red-700 border-red-200',
  urgent: 'bg-red-50 text-red-700 border-red-200',
  
  // Process
  open: 'bg-neutral-50 text-neutral-600 border-neutral-300',
  'in progress': 'bg-blue-50 text-blue-700 border-blue-200',
  'in_progress': 'bg-blue-50 text-blue-700 border-blue-200',
  'under review': 'bg-purple-50 text-purple-700 border-purple-200',
  'under_review': 'bg-purple-50 text-purple-700 border-purple-200',
  verified: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  
  // Document
  sent: 'bg-blue-50 text-blue-700 border-blue-200',
  received: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'partially received': 'bg-amber-50 text-amber-700 border-amber-200',
  'partially_received': 'bg-amber-50 text-amber-700 border-amber-200',
  'fully received': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'fully_received': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  
  // QC
  passed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  failed: 'bg-red-50 text-red-700 border-red-200',
  'on hold': 'bg-amber-50 text-amber-700 border-amber-200',
  'on_hold': 'bg-amber-50 text-amber-700 border-amber-200',
  voided: 'bg-neutral-100 text-neutral-400 border-neutral-200',
  
  // CAPA
  corrective: 'bg-blue-50 text-blue-700 border-blue-200',
  preventive: 'bg-purple-50 text-purple-700 border-purple-200',
}

export function StatusBadge({ status, children, className }: StatusBadgeProps) {
  const normalizedStatus = status?.toLowerCase().replace(/-/g, ' ').replace(/_/g, ' ') || 'draft'
  const style = STATUS_STYLES[normalizedStatus] || STATUS_STYLES['draft']
  
  return (
    <span className={cn(
      'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium border capitalize shadow-sm',
      style,
      className
    )}>
      {children}
    </span>
  )
}

export default StatusBadge
