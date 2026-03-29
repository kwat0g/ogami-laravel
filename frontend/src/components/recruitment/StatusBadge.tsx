import { statusColors } from '@/types/recruitment'

interface StatusBadgeProps {
  status: string
  label: string
}

const colorClasses: Record<string, string> = {
  gray: 'bg-neutral-100 text-neutral-700 ring-neutral-300',
  amber: 'bg-amber-100 text-amber-700 ring-amber-300',
  blue: 'bg-blue-100 text-blue-700 ring-blue-300',
  red: 'bg-red-100 text-red-700 ring-red-300',
  green: 'bg-green-100 text-green-700 ring-green-300',
  orange: 'bg-orange-100 text-orange-700 ring-orange-300',
  slate: 'bg-slate-100 text-slate-700 ring-slate-300',
  sky: 'bg-sky-100 text-sky-700 ring-sky-300',
  teal: 'bg-teal-100 text-teal-700 ring-teal-300',
  purple: 'bg-purple-100 text-purple-700 ring-purple-300',
  emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-300',
}

export default function StatusBadge({ status, label }: StatusBadgeProps) {
  const color = statusColors[status] ?? 'gray'
  const cls = colorClasses[color] ?? colorClasses.gray

  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${cls}`}
    >
      {label}
    </span>
  )
}
