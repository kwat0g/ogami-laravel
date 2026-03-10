import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHeadDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Users,
  CalendarOff,
  ChevronRight,
  CheckCircle,
  AlertCircle,
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
  href,
}: {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href?: string
}) {
  const content = (
    <div className="bg-white border border-neutral-200 rounded p-4">
      <div className="flex items-start justify-between">
        <Icon className="h-5 w-5 text-neutral-500" />
        {href && (
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        )}
      </div>
      <div className="mt-3">
        <p className="text-2xl font-semibold text-neutral-900">{value}</p>
        <p className="text-sm text-neutral-600 mt-1">{label}</p>
        {sub && <p className="text-xs text-neutral-500 mt-1">{sub}</p>}
      </div>
    </div>
  )
  if (href) return <Link to={href} className="block">{content}</Link>
  return content
}

function SectionCard({
  title,
  children,
  action,
}: {
  title: string
  children: React.ReactNode
  action?: { label: string; href: string }
}) {
  return (
    <div className="bg-white border border-neutral-200 rounded">
      <div className="px-4 py-3 border-b border-neutral-200 flex items-center justify-between">
        <h2 className="text-sm font-medium text-neutral-900">{title}</h2>
        {action && (
          <Link to={action.href} className="text-xs text-neutral-600 hover:text-neutral-900 flex items-center gap-1">
            {action.label}
            <ChevronRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="p-4">{children}</div>
    </div>
  )
}

function PendingAlert({ count, label, href }: { count: number; label: string; href: string }) {
  if (count === 0) return null
  return (
    <Link
      to={href}
      className="flex items-center gap-4 p-4 border border-amber-200 bg-amber-50 rounded"
    >
      <span className="text-lg font-semibold text-amber-700">{count}</span>
      <div className="flex-1">
        <span className="text-sm font-medium text-neutral-800 block">{label}</span>
        <span className="text-xs text-neutral-600">Click to note &amp; forward</span>
      </div>
      <ChevronRight className="h-4 w-4 text-neutral-400" />
    </Link>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function HeadDashboard() {
  useAuth()
  const { data: stats, isLoading, error } = useHeadDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const team    = stats?.team
  const pending = stats?.pending_approvals
  const weekly  = stats?.team_attendance?.this_week

  const recentLeaves   = stats?.recent_requests?.leaves ?? []
  const recentOT       = stats?.recent_requests?.overtime ?? []
  const recentLoans    = stats?.recent_requests?.loans ?? []

  return (
    <div className="space-y-6">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        Department Head
      </h1>

      {/* Error banner */}
      {error && (
        <div className="border border-red-200 bg-red-50 rounded p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-red-500" />
          <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
        </div>
      )}

      {/* Pending approvals summary banner */}
      {(pending?.total ?? 0) > 0 && (
        <div className="border border-amber-200 bg-amber-50 rounded p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-amber-600" />
          <span className="text-sm font-medium text-amber-800">
            <span className="underline">{pending!.total}</span> request(s) need your attention today.
          </span>
        </div>
      )}

      {/* Team overview stats */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Team Overview</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Team Members"    value={team?.member_count ?? 0} icon={Users}      />
          <StatCard label="Present Today"   value={team?.present_today ?? 0} icon={UserCheck}  />
          <StatCard label="On Leave Today"  value={team?.on_leave ?? 0}      icon={CalendarOff} href="/leave/requests" />
          <StatCard label="Total Pending"   value={pending?.total ?? 0}      icon={AlertCircle} />
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
        <SectionCard title="Team Attendance — This Week">
          {weekly ? (
            <div className="space-y-3">
              {[
                { label: 'Present',  value: weekly.present,  icon: UserCheck },
                { label: 'Absent',   value: weekly.absent,   icon: UserX },
                { label: 'Late',     value: weekly.late,     icon: Timer },
              ].map(({ label, value, icon: ItemIcon }) => (
                <div key={label} className="flex items-center gap-3">
                  <ItemIcon className="h-4 w-4 text-neutral-500" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm text-neutral-600">{label}</span>
                      <span className="text-sm font-semibold text-neutral-900">{value}</span>
                    </div>
                    <div className="h-1.5 bg-neutral-100 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-neutral-400 rounded-full"
                        style={{ width: `${(team?.member_count ?? 0) > 0 ? (value / (team?.member_count ?? 1)) * 100 : 0}%` }}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-neutral-400 text-center py-4">No attendance data available.</p>
          )}
        </SectionCard>

        {/* Pending requests summary */}
        <SectionCard title="Pending to Note">
          <div className="space-y-2">
            {[
              { label: 'Leave Requests',    count: pending?.leaves ?? 0,   href: '/leave/requests',     icon: CalendarOff },
              { label: 'Overtime Requests', count: pending?.overtime ?? 0, href: '/attendance/overtime', icon: Timer },
              { label: 'Loan Applications', count: pending?.loans ?? 0,    href: '/loans',               icon: DollarSign },
            ].map(({ label, count, href, icon: RowIcon }) => (
              <Link
                key={label}
                to={href}
                className="flex items-center gap-3 p-2 rounded hover:bg-neutral-50 border border-neutral-100"
              >
                <RowIcon className="h-4 w-4 text-neutral-500" />
                <span className="flex-1 text-sm text-neutral-700">{label}</span>
                <span className={`text-sm font-semibold ${count > 0 ? 'text-amber-600' : 'text-neutral-400'}`}>{count}</span>
                <ChevronRight className="h-4 w-4 text-neutral-400" />
              </Link>
            ))}
          </div>
        </SectionCard>
      </div>

      {/* Recent requests needing action */}
      <SectionCard title="Recent Requests (Requires Notation)">
        {recentLeaves.length === 0 && recentOT.length === 0 && recentLoans.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <CheckCircle className="h-10 w-10 text-neutral-300 mb-3" />
            <p className="text-sm text-neutral-500 font-medium">No pending items.</p>
            <p className="text-xs text-neutral-400 mt-1">New requests from your team will appear here.</p>
          </div>
        ) : (
          <div className="divide-y divide-neutral-100">
            {recentLeaves.slice(0, 3).map((req, i) => (
              <RequestRow
                key={`leave-${i}`}
                type="Leave"
                name={req.employee?.full_name}
                detail={req.leave_type?.name}
                href="/leave/requests"
              />
            ))}
            {recentOT.slice(0, 3).map((req, i) => (
              <RequestRow
                key={`ot-${i}`}
                type="Overtime"
                name={req.employee?.full_name}
                detail={req.reason}
                href="/attendance/overtime"
              />
            ))}
            {recentLoans.slice(0, 2).map((req, i) => (
              <RequestRow
                key={`loan-${i}`}
                type="Loan"
                name={req.employee?.full_name}
                detail={req.loan_type?.name}
                href="/loans"
              />
            ))}
          </div>
        )}
      </SectionCard>

      {/* Operational quick links */}
      <SectionCard title="Operational Modules">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {[
            { label: 'Work Orders',       href: '/maintenance/work-orders', icon: Wrench },
            { label: 'Mold Masters',      href: '/mold/masters',            icon: Package },
            { label: 'Production Orders', href: '/production/orders',       icon: ClipboardList },
            { label: 'Delivery',          href: '/delivery',                icon: Truck },
            { label: 'Inventory MRQ',     href: '/inventory/mrqs',          icon: Package },
            { label: 'ISO Audits',        href: '/iso/audits',              icon: CheckCircle },
            { label: 'OT Requests',       href: '/attendance/overtime',     icon: Timer },
            { label: 'Leave Requests',    href: '/leave/requests',          icon: CalendarOff },
          ].map((link) => (
            <Link
              key={link.href}
              to={link.href}
              className="flex items-center gap-2 p-2 rounded border border-neutral-200 hover:bg-neutral-50"
            >
              <link.icon className="h-4 w-4 text-neutral-500 shrink-0" />
              <span className="text-xs text-neutral-700">{link.label}</span>
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
}: {
  type: string
  name?: string
  detail?: string
  href: string
}) {
  return (
    <div className="flex items-center gap-3 py-2">
      <span className="text-xs font-medium text-neutral-600 bg-neutral-100 px-2 py-0.5 rounded">{type}</span>
      <div className="flex-1 min-w-0">
        <p className="text-sm text-neutral-900 truncate">{name ?? 'Unknown'}</p>
        {detail && <p className="text-xs text-neutral-500 truncate">{detail}</p>}
      </div>
      <Link to={href} className="text-xs text-neutral-600 hover:text-neutral-900 flex items-center gap-0.5 shrink-0">
        Review <ChevronRight className="h-3 w-3" />
      </Link>
    </div>
  )
}
