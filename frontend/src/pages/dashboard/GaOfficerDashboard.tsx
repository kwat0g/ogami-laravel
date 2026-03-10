import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHeadDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
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

// ── Sub-components ────────────────────────────────────────────────────────────

function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
}) {
  return (
    <Link to={href}>
      <Card className="h-full hover:border-neutral-300 transition-colors">
        <div className="p-5 flex items-start gap-4">
          <Icon className="h-5 w-5 text-neutral-500 mt-0.5" />
          <div className="flex-1 min-w-0">
            <p className="text-2xl font-semibold text-neutral-900">{value}</p>
            <p className="text-sm text-neutral-600 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-500 mt-0.5">{sub}</p>}
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-300 mt-1 shrink-0" />
        </div>
      </Card>
    </Link>
  )
}

function QuickLink({
  href,
  label,
  icon: Icon,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle transition-colors"
    >
      <Icon className="h-4 w-4 text-neutral-500" />
      <span className="text-sm font-medium text-neutral-700">{label}</span>
    </Link>
  )
}

function PendingBadge({ count, label, href }: { count: number; label: string; href: string }) {
  if (count === 0) return null
  return (
    <Link to={href}>
      <Card className="border-amber-200 bg-amber-50 hover:border-amber-300 transition-colors">
        <div className="p-4 flex items-center gap-4">
          <span className="text-lg font-semibold text-amber-700">{count}</span>
          <div className="flex-1">
            <span className="text-sm font-medium text-neutral-800 block">{label}</span>
            <span className="text-xs text-neutral-600">Click to review</span>
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        </div>
      </Card>
    </Link>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function GaOfficerDashboard(): React.ReactElement {
  useAuth()
  const { data: stats, isLoading } = useHeadDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const team     = stats?.team
  const pending  = stats?.pending_approvals
  const thisWeek = stats?.team_attendance?.this_week

  return (
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        General Affairs
      </h1>

      {/* Pending Approvals Banner */}
      {pending && pending.total > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-medium text-neutral-700">Pending Actions</h2>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <PendingBadge count={pending.leaves}   label="Leave requests awaiting review"    href="/leave/requests" />
            <PendingBadge count={pending.overtime} label="Overtime requests awaiting review" href="/attendance/overtime" />
          </div>
        </div>
      )}

      {/* Team Attendance Stats */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Team Overview</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label="Team Members"
            value={team?.member_count ?? '—'}
            sub="All active employees"
            icon={Users}
            href="/team/employees"
          />
          <StatCard
            label="Present Today"
            value={team?.present_today ?? '—'}
            sub="Clocked in today"
            icon={UserCheck}
            href="/team/attendance"
          />
          <StatCard
            label="On Leave"
            value={team?.on_leave ?? '—'}
            sub="Currently approved leaves"
            icon={CalendarCheck}
            href="/team/leave"
          />
          <StatCard
            label="Absent / Late"
            value={thisWeek ? thisWeek.absent + thisWeek.late : '—'}
            sub="This week"
            icon={AlertCircle}
            href="/team/attendance"
          />
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <QuickLink href="/attendance"          label="Attendance"        icon={Clock}          />
          <QuickLink href="/attendance/overtime" label="Overtime"          icon={CalendarClock}  />
          <QuickLink href="/leave"               label="Leave Requests"    icon={CalendarCheck}  />
          <QuickLink href="/hr/employees"        label="Employees"         icon={Users}          />
          <QuickLink href="/team/leave"          label="Team Leave"        icon={FileText}       />
          <QuickLink href="/team/attendance"     label="Team Attendance"   icon={ClipboardList}  />
        </div>
      </div>
    </div>
  )
}
