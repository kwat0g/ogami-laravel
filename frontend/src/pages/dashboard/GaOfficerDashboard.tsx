import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHeadDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Users,
  CalendarClock,
  CalendarCheck,
  ClipboardList,
  UserCheck,
  ChevronRight,
  Clock,
  FileText,
  AlertCircle,
} from 'lucide-react'

// ── Helpers ───────────────────────────────────────────────────────────────────

function getGreeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'morning'
  if (h < 17) return 'afternoon'
  return 'evening'
}

// ── Sub-components ────────────────────────────────────────────────────────────

function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
  colorClass,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  colorClass: string
}) {
  return (
    <Link
      to={href}
      className="flex items-start gap-4 p-5 bg-white rounded-xl border border-gray-200 hover:shadow-md hover:border-gray-300 transition-all duration-200"
    >
      <div className={`h-12 w-12 rounded-xl flex items-center justify-center shadow-sm ${colorClass}`}>
        <Icon className="h-6 w-6 text-white" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-3xl font-bold text-gray-900">{value}</p>
        <p className="text-sm font-medium text-gray-700 mt-0.5">{label}</p>
        {sub && <p className="text-xs text-gray-500 mt-0.5">{sub}</p>}
      </div>
      <ChevronRight className="h-5 w-5 text-gray-300 mt-1 shrink-0" />
    </Link>
  )
}

function QuickLink({
  href,
  label,
  icon: Icon,
  colorClass,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
  colorClass: string
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-4 bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all duration-200 group"
    >
      <div className={`h-10 w-10 rounded-lg flex items-center justify-center transition-colors ${colorClass}`}>
        <Icon className="h-5 w-5 text-white" />
      </div>
      <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">{label}</span>
    </Link>
  )
}

function PendingBadge({ count, label, href }: { count: number; label: string; href: string }) {
  if (count === 0) return null
  return (
    <Link
      to={href}
      className="flex items-center gap-4 p-4 rounded-xl border border-amber-200 bg-amber-50 hover:shadow-md transition-all duration-200"
    >
      <div className="h-12 w-12 rounded-xl bg-amber-500 flex items-center justify-center shadow-sm">
        <span className="text-lg font-bold text-white">{count}</span>
      </div>
      <div className="flex-1">
        <span className="text-sm font-semibold text-amber-800 block">{label}</span>
        <span className="text-xs text-amber-600">Click to review</span>
      </div>
      <ChevronRight className="h-5 w-5 text-amber-600" />
    </Link>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function GaOfficerDashboard(): React.ReactElement {
  const { user } = useAuth()
  const { data: stats, isLoading } = useHeadDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const team     = stats?.team
  const pending  = stats?.pending_approvals
  const thisWeek = stats?.team_attendance?.this_week

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Good {getGreeting()}, {user?.name?.split(' ')[0] ?? 'GA Officer'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            General Affairs — HR Administrative Operations
          </p>
        </div>
      </div>

      {/* Pending Approvals Banner */}
      {pending && pending.total > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider">Pending Actions</h2>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <PendingBadge count={pending.leaves}   label="Leave requests awaiting review"    href="/leave/requests" />
            <PendingBadge count={pending.overtime} label="Overtime requests awaiting review" href="/attendance/overtime" />
          </div>
        </div>
      )}

      {/* Team Attendance Stats */}
      <div>
        <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Team Overview</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label="Team Members"
            value={team?.member_count ?? '—'}
            sub="All active employees"
            icon={Users}
            href="/team/employees"
            colorClass="bg-indigo-500"
          />
          <StatCard
            label="Present Today"
            value={team?.present_today ?? '—'}
            sub="Clocked in today"
            icon={UserCheck}
            href="/team/attendance"
            colorClass="bg-green-500"
          />
          <StatCard
            label="On Leave"
            value={team?.on_leave ?? '—'}
            sub="Currently approved leaves"
            icon={CalendarCheck}
            href="/team/leave"
            colorClass="bg-amber-500"
          />
          <StatCard
            label="Absent / Late"
            value={thisWeek ? thisWeek.absent + thisWeek.late : '—'}
            sub="This week"
            icon={AlertCircle}
            href="/team/attendance"
            colorClass="bg-red-500"
          />
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <QuickLink href="/attendance"          label="Attendance"        icon={Clock}          colorClass="bg-blue-500"   />
          <QuickLink href="/attendance/overtime" label="Overtime"          icon={CalendarClock}  colorClass="bg-orange-500" />
          <QuickLink href="/leave"               label="Leave Requests"    icon={CalendarCheck}  colorClass="bg-teal-500"   />
          <QuickLink href="/hr/employees"        label="Employees"         icon={Users}          colorClass="bg-indigo-500" />
          <QuickLink href="/team/leave"          label="Team Leave"        icon={FileText}       colorClass="bg-purple-500" />
          <QuickLink href="/team/attendance"     label="Team Attendance"   icon={ClipboardList}  colorClass="bg-cyan-500"   />
        </div>
      </div>
    </div>
  )
}
