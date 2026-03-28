/**
 * Reusable Dashboard Header — consistent professional header across all role dashboards.
 */
import { useAuth } from '@/hooks/useAuth'

interface Props {
  roleLabel: string
  subtitle?: string
}

export default function DashboardHeader({ roleLabel, subtitle }: Props) {
  const { user } = useAuth()
  const now = new Date()
  const greeting = now.getHours() < 12 ? 'Good morning' : now.getHours() < 17 ? 'Good afternoon' : 'Good evening'
  const dateStr = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

  return (
    <div className="flex items-start justify-between">
      <div>
        <h1 className="text-2xl font-bold text-neutral-900 dark:text-white">
          {greeting}, {user?.name?.split(' ')[0] ?? 'User'}
        </h1>
        <p className="text-sm text-neutral-500 mt-0.5">
          {dateStr} &middot; {subtitle ?? `${roleLabel} Dashboard`}
        </p>
      </div>
      <div className="text-right hidden md:flex flex-col items-end gap-1.5">
        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1.5 rounded-full">
          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
          System Online
        </span>
        <span className="text-[10px] text-neutral-400 font-medium uppercase tracking-wider">
          {roleLabel}
        </span>
      </div>
    </div>
  )
}
