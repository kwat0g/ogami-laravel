import { cn } from '@/lib/utils'

interface InfoRowProps {
  label: string
  value: React.ReactNode
  className?: string
  fullWidth?: boolean
}

export function InfoRow({ label, value, className, fullWidth }: InfoRowProps) {
  return (
    <div className={cn(
      'flex items-start gap-4 py-3 border-b border-neutral-100 last:border-0',
      fullWidth && 'sm:col-span-2',
      className
    )}>
      <dt className="text-sm text-neutral-500 w-40 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-neutral-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

interface InfoListProps {
  children: React.ReactNode
  className?: string
  columns?: 1 | 2
}

export function InfoList({ children, className, columns = 2 }: InfoListProps) {
  return (
    <dl className={cn(
      columns === 2 && 'grid grid-cols-1 sm:grid-cols-2 gap-x-8',
      className
    )}>
      {children}
    </dl>
  )
}

export default InfoRow
