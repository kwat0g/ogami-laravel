import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHeadDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Users,
  Clock,
  CalendarOff,
  ChevronRight,
  CheckCircle,
  AlertCircle,
  FileCheck,
  Timer,
  DollarSign,
  Wrench,
  Package,
  Truck,
  ClipboardList,
  UserCheck,
  UserX,
} from 'lucide-react'

// ── Reusable components ───────────────────────────────────────────────────────

function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  color = 'blue',
  href,
}: {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  color?: 'blue' | 'amber' | 'green' | 'red' | 'gray' | 'purple' | 'indigo'
  href?: string
}) {
  const colorMap = {
    blue:   { bg: 'bg-blue-50',   border: 'border-blue-100',   iconBg: 'bg-blue-500',   text: 'text-blue-700',   subText: 'text-blue-600' },
    amber:  { bg: 'bg-amber-50',  border: 'border-amber-100',  iconBg: 'bg-amber-500',  text: 'text-amber-700',  subText: 'text-amber-600' },
    green:  { bg: 'bg-green-50',  border: 'border-green-100',  iconBg: 'bg-green-500',  text: 'text-green-700',  subText: 'text-green-600' },
    red:    { bg: 'bg-red-50',    border: 'border-red-100',    iconBg: 'bg-red-500',    text: 'text-red-700',    subText: 'text-red-600' },
    gray:   { bg: 'bg-gray-50',   border: 'border-gray-200',   iconBg: 'bg-gray-500',   text: 'text-gray-700',   subText: 'text-gray-600' },
    purple: { bg: 'bg-purple-50', border: 'border-purple-100', iconBg: 'bg-purple-500', text: 'text-purple-700', subText: 'text-purple-600' },
    indigo: { bg: 'bg-indigo-50', border: 'border-indigo-100', iconBg: 'bg-indigo-500', text: 'text-indigo-700', subText: 'text-indigo-600' },
  }
  const c = colorMap[color]
  const content = (
    <div className={`${c.bg} border ${c.border} rounded-xl p-5 hover:shadow-md transition-all duration-200`}>
      <div className="flex items-start justify-between">
        <div className={`h-12 w-12 rounded-xl ${c.iconBg} flex items-center justify-center shadow-sm`}>
          <Icon className="h-6 w-6 text-white" />
        </div>
        {href && (
          <div className="h-8 w-8 rounded-lg bg-white/60 flex items-center justify-center">
            <ChevronRight className="h-4 w-4 text-gray-400" />
          </div>
        )}
      </div>
      <div className="mt-4">
        <p className="text-3xl font-bold text-gray-900">{value}</p>
        <p className={`text-sm font-medium ${c.text} mt-1`}>{label}</p>
        {sub && <p className={`text-xs mt-1 ${c.subText}`}>{sub}</p>}
      </div>
    </div>
  )
  if (href) return <Link to={href} className="block">{content}</Link>
  return content
}

function SectionCard({
  title,
  icon: Icon,
  children,
  action,
}: {
  title: string
  icon?: React.ComponentType<{ className?: string }>
  children: React.ReactNode
  action?: { label: string; href: string }
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div className="flex items-center gap-2">
          {Icon && <Icon className="h-5 w-5 text-gray-500" />}
          <h2 className="text-sm font-semibold text-gray-800">{title}</h2>
        </div>
        {action && (
          <Link to={action.href} className="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
            {action.label}
            <ChevronRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="p-6">{children}</div>
    </div>
  )
}

function PendingAlert({ count, label, href }: { count: number; label: string; href: string }) {
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
        <span className="text-xs text-amber-600">Click to note &amp; forward</span>
      </div>
      <ChevronRight className="h-5 w-5 text-amber-600" />
    </Link>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function HeadDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading, error } = useHeadDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const team    = stats?.team
  const pending = stats?.pending_approvals
  const weekly  = stats?.team_attendance?.this_week

  const recentLeaves   = stats?.recent_requests?.leaves ?? []
  const recentOT       = stats?.recent_requests?.overtime ?? []
  const recentLoans    = stats?.recent_requests?.loans ?? []

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Good {getGreeting()}, {user?.name?.split(' ')[0] ?? 'Department Head'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Department Head — Team Oversight &amp; Operational Approvals
          </p>
        </div>
        <div className="text-right text-sm text-gray-500">
          <p className="font-medium">{new Date().toLocaleDateString('en-PH', { weekday: 'long' })}</p>
          <p>{new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Error banner */}
      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-red-500" />
          <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
        </div>
      )}

      {/* Pending approvals summary banner */}
      {(pending?.total ?? 0) > 0 && (
        <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-amber-600" />
          <span className="text-sm font-semibold text-amber-800">
            <span className="underline">{pending!.total}</span> request(s) need your attention today.
          </span>
        </div>
      )}

      {/* Team overview stats */}
      <div>
        <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Team Overview</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Team Members"    value={team?.member_count ?? 0} icon={Users}      color="blue" />
          <StatCard label="Present Today"   value={team?.present_today ?? 0} icon={UserCheck}  color="green" />
          <StatCard label="On Leave Today"  value={team?.on_leave ?? 0}      icon={CalendarOff} color="amber" href="/leave/requests" />
          <StatCard label="Total Pending"   value={pending?.total ?? 0}      icon={AlertCircle} color={(pending?.total ?? 0) > 0 ? 'red' : 'gray'} />
        </div>
      </div>

      {/* Pending alerts to note */}
      <div className="space-y-3">
        <PendingAlert count={pending?.leaves ?? 0}   label="Leave requests pending your notation" href="/leave/requests" />
        <PendingAlert count={pending?.overtime ?? 0} label="Overtime requests pending your notation" href="/attendance/overtime" />
        <PendingAlert count={pending?.loans ?? 0}    label="Loan applications pending your notation" href="/loans" />
      </div>

      {/* This week's attendance + pending requests */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Weekly attendance card */}
        <SectionCard title="Team Attendance — This Week" icon={Clock}>
          {weekly ? (
            <div className="space-y-4">
              {[
                { label: 'Present',  value: weekly.present,  color: 'bg-green-500',  textColor: 'text-green-700',  icon: UserCheck },
                { label: 'Absent',   value: weekly.absent,   color: 'bg-red-400',    textColor: 'text-red-700',    icon: UserX },
                { label: 'Late',     value: weekly.late,     color: 'bg-amber-400',  textColor: 'text-amber-700',  icon: Timer },
              ].map(({ label, value, color, textColor, icon: ItemIcon }) => (
                <div key={label} className="flex items-center gap-3">
                  <div className={`h-8 w-8 rounded-lg ${color} flex items-center justify-center shrink-0`}>
                    <ItemIcon className="h-4 w-4 text-white" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <span className={`text-sm font-medium ${textColor}`}>{label}</span>
                      <span className="text-sm font-bold text-gray-900">{value}</span>
                    </div>
                    <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                      <div
                        className={`h-full ${color} rounded-full transition-all duration-500`}
                        style={{ width: `${(team?.member_count ?? 0) > 0 ? (value / (team?.member_count ?? 1)) * 100 : 0}%` }}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-400 text-center py-4">No attendance data available.</p>
          )}
        </SectionCard>

        {/* Pending requests summary */}
        <SectionCard title="Pending to Note" icon={FileCheck}>
          <div className="space-y-3">
            {[
              { label: 'Leave Requests',    count: pending?.leaves ?? 0,   href: '/leave/requests',     colClass: 'bg-blue-500',   icon: CalendarOff },
              { label: 'Overtime Requests', count: pending?.overtime ?? 0, href: '/attendance/overtime', colClass: 'bg-purple-500', icon: Timer },
              { label: 'Loan Applications', count: pending?.loans ?? 0,    href: '/loans',               colClass: 'bg-amber-500',  icon: DollarSign },
            ].map(({ label, count, href, colClass, icon: RowIcon }) => (
              <Link
                key={label}
                to={href}
                className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 border border-gray-100 transition-colors duration-150"
              >
                <div className={`h-9 w-9 rounded-lg ${colClass} flex items-center justify-center shrink-0`}>
                  <RowIcon className="h-4 w-4 text-white" />
                </div>
                <span className="flex-1 text-sm font-medium text-gray-700">{label}</span>
                <span className={`text-sm font-bold ${count > 0 ? 'text-amber-600' : 'text-gray-400'}`}>{count}</span>
                <ChevronRight className="h-4 w-4 text-gray-400" />
              </Link>
            ))}
          </div>
        </SectionCard>
      </div>

      {/* Recent requests needing action */}
      <SectionCard title="Recent Requests (Requires Notation)" icon={CheckCircle}>
        {recentLeaves.length === 0 && recentOT.length === 0 && recentLoans.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <CheckCircle className="h-12 w-12 text-green-300 mb-3" />
            <p className="text-sm text-gray-500 font-medium">No pending items.</p>
            <p className="text-xs text-gray-400 mt-1">New requests from your team will appear here.</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {recentLeaves.slice(0, 3).map((req, i) => (
              <RequestRow
                key={`leave-${i}`}
                type="Leave"
                name={req.employee?.full_name}
                detail={req.leave_type?.name}
                href="/leave/requests"
                color="blue"
              />
            ))}
            {recentOT.slice(0, 3).map((req, i) => (
              <RequestRow
                key={`ot-${i}`}
                type="Overtime"
                name={req.employee?.full_name}
                detail={req.reason}
                href="/attendance/overtime"
                color="purple"
              />
            ))}
            {recentLoans.slice(0, 2).map((req, i) => (
              <RequestRow
                key={`loan-${i}`}
                type="Loan"
                name={req.employee?.full_name}
                detail={req.loan_type?.name}
                href="/loans"
                color="amber"
              />
            ))}
          </div>
        )}
      </SectionCard>

      {/* Operational quick links */}
      <SectionCard title="Operational Modules" icon={ClipboardList}>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {[
            { label: 'Work Orders',       href: '/maintenance/work-orders', icon: Wrench,       color: 'text-orange-600' },
            { label: 'Mold Masters',      href: '/mold/masters',            icon: Package,      color: 'text-indigo-600' },
            { label: 'Production Orders', href: '/production/orders',       icon: ClipboardList, color: 'text-blue-600' },
            { label: 'Delivery',          href: '/delivery',                icon: Truck,        color: 'text-green-600' },
            { label: 'Inventory MRQ',     href: '/inventory/mrqs',          icon: Package,      color: 'text-purple-600' },
            { label: 'ISO Audits',        href: '/iso/audits',              icon: CheckCircle,  color: 'text-teal-600' },
            { label: 'OT Requests',       href: '/attendance/overtime',     icon: Timer,        color: 'text-amber-600' },
            { label: 'Leave Requests',    href: '/leave/requests',          icon: CalendarOff,  color: 'text-red-600' },
          ].map((link) => (
            <Link
              key={link.href}
              to={link.href}
              className="flex items-center gap-2 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 hover:shadow-sm transition-all duration-150"
            >
              <link.icon className={`h-4 w-4 ${link.color} shrink-0`} />
              <span className="text-xs font-medium text-gray-700">{link.label}</span>
            </Link>
          ))}
        </div>
      </SectionCard>
    </div>
  )
}

// ── Sub-components ────────────────────────────────────────────────────────────

function RequestRow({
  type,
  name,
  detail,
  href,
  color,
}: {
  type: string
  name?: string
  detail?: string
  href: string
  color: 'blue' | 'purple' | 'amber'
}) {
  const colorBg = { blue: 'bg-blue-50 text-blue-600', purple: 'bg-purple-50 text-purple-600', amber: 'bg-amber-50 text-amber-600' }[color]
  return (
    <div className="flex items-center gap-3 py-3">
      <span className={`text-xs font-semibold px-2 py-1 rounded-full ${colorBg} shrink-0`}>{type}</span>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">{name ?? 'Unknown'}</p>
        {detail && <p className="text-xs text-gray-500 truncate">{detail}</p>}
      </div>
      <Link to={href} className="text-xs text-blue-600 hover:text-blue-700 flex items-center gap-0.5 shrink-0">
        Review <ChevronRight className="h-3 w-3" />
      </Link>
    </div>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function getGreeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'morning'
  if (h < 18) return 'afternoon'
  return 'evening'
}
