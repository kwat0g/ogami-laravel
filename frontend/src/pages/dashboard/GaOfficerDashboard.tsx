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
  ArrowUpRight,
} from 'lucide-react'

function KpiCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
  alert,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  alert?: boolean
}) {
  return (
    <Link to={href}>
      <Card className={`h-full hover:shadow-md transition-all ${alert ? 'border-amber-200 bg-amber-50/30' : ''}`}>
        <div className="p-5">
          <div className="flex items-start justify-between">
            <div className={`p-2 rounded-lg ${alert ? 'bg-amber-100' : 'bg-neutral-100'}`}>
              <Icon className={`h-4 w-4 ${alert ? 'text-amber-600' : 'text-neutral-600'}`} />
            </div>
            <ArrowUpRight className="h-4 w-4 text-neutral-400" />
          </div>
          <div className="mt-3">
            <p className={`text-2xl font-bold tracking-tight ${alert ? 'text-amber-700' : 'text-neutral-900'}`}>{value}</p>
            <p className="text-sm text-neutral-500 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
          </div>
        </div>
      </Card>
    </Link>
  )
}

function ActionItem({ count, label, href }: { count: number; label: string; href: string }) {
  if (count === 0) return null
  return (
    <Link to={href}>
      <Card className="border-amber-200 bg-amber-50/50 hover:shadow-sm transition-all">
        <div className="p-4 flex items-center gap-4">
          <div className="text-xl font-bold text-amber-600">{count}</div>
          <div className="flex-1">
            <p className="text-sm font-medium text-neutral-800">{label}</p>
            <p className="text-xs text-neutral-500">Click to review</p>
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        </div>
      </Card>
    </Link>
  )
}

function ModuleLink({
  href,
  label,
  icon: Icon,
  desc,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
  desc?: string
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-lg hover:bg-neutral-50 hover:border-neutral-300 transition-all"
    >
      <div className="p-1.5 rounded bg-neutral-100">
        <Icon className="h-4 w-4 text-neutral-600" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-neutral-700">{label}</p>
        {desc && <p className="text-xs text-neutral-400 truncate">{desc}</p>}
      </div>
      <ChevronRight className="h-4 w-4 text-neutral-300 shrink-0" />
    </Link>
  )
}

export default function GaOfficerDashboard(): React.ReactElement {
  useAuth()
  const { data: stats, isLoading } = useHeadDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const team     = stats?.team
  const pending  = stats?.pending_approvals
  const thisWeek = stats?.team_attendance?.this_week

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-neutral-900">General Affairs Dashboard</h1>
        <p className="text-sm text-neutral-500 mt-0.5">Team management, leave processing, and attendance oversight</p>
      </div>

      {/* Pending Actions */}
      {pending && pending.total > 0 && (
        <div>
          <h2 className="text-sm font-semibold text-neutral-800 uppercase tracking-wide mb-3">Requires Your Action</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <ActionItem count={pending.leaves} label="Leave Requests Pending Approval" href="/executive/leave-approvals" />
            <ActionItem count={pending.overtime} label="Overtime Requests Pending Review" href="/executive/overtime-approvals" />
            <ActionItem count={pending.loans} label="Loan Applications Awaiting Review" href="/team/loans" />
          </div>
        </div>
      )}

      {/* Team KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard
          label="Team Members"
          value={team?.member_count ?? '—'}
          sub="All active employees"
          icon={Users}
          href="/team/employees"
        />
        <KpiCard
          label="Present Today"
          value={team?.present_today ?? '—'}
          sub="Clocked in"
          icon={UserCheck}
          href="/team/attendance"
        />
        <KpiCard
          label="On Leave"
          value={team?.on_leave ?? '—'}
          sub="Currently approved"
          icon={CalendarCheck}
          href="/team/leave"
        />
        <KpiCard
          label="Absent / Late"
          value={thisWeek ? thisWeek.absent + thisWeek.late : '—'}
          sub="This week"
          icon={AlertCircle}
          href="/team/attendance"
          alert={(thisWeek?.absent ?? 0) + (thisWeek?.late ?? 0) > 3}
        />
      </div>

      {/* Modules */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Users className="h-4 w-4 text-neutral-500" />
              People Management
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/team/employees" label="My Team" icon={Users} desc="View and manage team members" />
              <ModuleLink href="/team/attendance" label="Team Attendance" icon={Clock} desc="Daily attendance monitoring" />
              <ModuleLink href="/team/leave" label="Team Leave" icon={CalendarCheck} desc="Leave requests and balances" />
              <ModuleLink href="/team/overtime" label="Team Overtime" icon={CalendarClock} desc="OT requests and approval" />
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <FileText className="h-4 w-4 text-neutral-500" />
              GA Processing
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/executive/leave-approvals" label="GA Leave Processing" icon={CalendarCheck} desc="Process and approve leave requests" />
              <ModuleLink href="/executive/overtime-approvals" label="Overtime Approvals" icon={CalendarClock} desc="Review overtime submissions" />
              <ModuleLink href="/hr/employees/all" label="All Employees" icon={Users} desc="Company-wide employee directory" />
              <ModuleLink href="/team/loans" label="Loan Requests" icon={ClipboardList} desc="Review team loan applications" />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
