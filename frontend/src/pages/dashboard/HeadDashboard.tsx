/**
 * Department Head Dashboard
 *
 * Team oversight with attendance tracking, pending notations (leave/OT/loans),
 * recent requests table, and operational module quick links filtered by permission.
 * Department heads supervise daily operations and forward requests to managers.
 */
import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { useHeadDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card } from '@/components/ui/Card'
import {
  KpiCard,
  ApprovalAlert,
  SectionHeader,
  MiniBarChart,
  MiniAreaChart,
  WidgetCard,
  DashboardGrid,
  QuickActions,
  ActivityFeed,
} from '@/components/dashboard/DashboardWidgets'
import {
  Users,
  CalendarOff,
  AlertCircle,
  Timer,
  DollarSign,
  Wrench,
  Package,
  Truck,
  ClipboardList,
  UserCheck,
  UserX,
  CheckCircle,
  ChevronRight,
  Calendar,
} from 'lucide-react'

export default function HeadDashboard() {
  useAuth()
  const { hasPermission } = useAuthStore()
  const { data: stats, isLoading, error } = useHeadDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const team = stats?.team
  const pending = stats?.pending_approvals
  const weekly = stats?.team_attendance?.this_week
  const analytics = stats?.analytics

  const recentLeaves = stats?.recent_requests?.leaves ?? []
  const recentOT = stats?.recent_requests?.overtime ?? []
  const recentLoans = stats?.recent_requests?.loans ?? []
  const hasAnyRecent = recentLeaves.length > 0 || recentOT.length > 0 || recentLoans.length > 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Department Head Dashboard</h1>
        <p className="text-sm text-neutral-500 mt-0.5">
          Team oversight, pending notations, and operational module access
        </p>
      </div>

      {/* Error */}
      {error && (
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
          </div>
        </Card>
      )}

      {/* Pending summary banner */}
      {(pending?.total ?? 0) > 0 && (
        <Card className="border-amber-200 bg-amber-50 hover:shadow-sm transition-shadow">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-amber-600" />
            <span className="text-sm font-medium text-amber-800">
              <span className="font-bold underline">{pending!.total}</span> request(s) need your attention today.
            </span>
          </div>
        </Card>
      )}

      {/* Team KPIs */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <KpiCard
          label="Team Members"
          value={team?.member_count ?? 0}
          icon={Users}
          color="info"
        />
        <KpiCard
          label="Present Today"
          value={team?.present_today ?? 0}
          icon={UserCheck}
          color="success"
        />
        <KpiCard
          label="On Leave Today"
          value={team?.on_leave ?? 0}
          icon={CalendarOff}
          color={(team?.on_leave ?? 0) > 2 ? 'warning' : 'default'}
          href="/leave/requests"
        />
        <KpiCard
          label="Total Pending"
          value={pending?.total ?? 0}
          icon={AlertCircle}
          color={(pending?.total ?? 0) > 0 ? 'warning' : 'success'}
          sub={(pending?.total ?? 0) > 0 ? 'Needs notation' : 'All clear'}
        />
      </div>

      {/* Approval Alerts */}
      {(pending?.total ?? 0) > 0 && (
        <div className="space-y-2">
          <ApprovalAlert count={pending?.leaves ?? 0} label="Leave requests pending your notation" href="/leave/requests" />
          <ApprovalAlert count={pending?.overtime ?? 0} label="Overtime requests pending your notation" href="/attendance/overtime" />
          <ApprovalAlert count={pending?.loans ?? 0} label="Loan applications pending your notation" href="/loans" />
        </div>
      )}

      {/* Attendance + Pending side by side */}
      <DashboardGrid>
        {/* Weekly Attendance */}
        <WidgetCard title="Team Attendance - This Week">
          {weekly ? (
            <div className="space-y-4">
              {[
                { label: 'Present', value: weekly.present, icon: UserCheck, color: 'bg-green-500' },
                { label: 'Absent', value: weekly.absent, icon: UserX, color: 'bg-red-500' },
                { label: 'Late', value: weekly.late, icon: Timer, color: 'bg-amber-500' },
              ].map(({ label, value, icon: Icon, color }) => (
                <div key={label} className="flex items-center gap-3">
                  <Icon className="h-4 w-4 text-neutral-500 shrink-0" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm text-neutral-600">{label}</span>
                      <span className="text-sm font-bold text-neutral-900">{value}</span>
                    </div>
                    <div className="h-2 bg-neutral-100 rounded-full overflow-hidden">
                      <div
                        className={`h-full rounded-full ${color}`}
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
        </WidgetCard>

        {/* Pending to Note */}
        <WidgetCard title="Pending to Note">
          <div className="space-y-2">
            {[
              { label: 'Leave Requests', count: pending?.leaves ?? 0, href: '/leave/requests', icon: CalendarOff },
              { label: 'Overtime Requests', count: pending?.overtime ?? 0, href: '/attendance/overtime', icon: Timer },
              { label: 'Loan Applications', count: pending?.loans ?? 0, href: '/loans', icon: DollarSign },
            ].map(({ label, count, href, icon: RowIcon }) => (
              <Link
                key={label}
                to={href}
                className="flex items-center gap-3 p-3 rounded-lg border border-neutral-200 hover:bg-neutral-50 transition-colors"
              >
                <div className="p-1.5 rounded bg-neutral-100">
                  <RowIcon className="h-4 w-4 text-neutral-600" />
                </div>
                <span className="flex-1 text-sm text-neutral-700 font-medium">{label}</span>
                <span className={`text-sm font-bold ${count > 0 ? 'text-amber-600' : 'text-neutral-400'}`}>{count}</span>
                <ChevronRight className="h-4 w-4 text-neutral-400" />
              </Link>
            ))}
          </div>
        </WidgetCard>
      </DashboardGrid>

      {/* Analytics Charts (if available) */}
      {analytics && (
        <DashboardGrid>
          {analytics.weekly_attendance_rate && analytics.weekly_attendance_rate.length > 0 && (
            <WidgetCard title="Weekly Attendance Rate">
              <MiniAreaChart
                data={analytics.weekly_attendance_rate.map((w: { week: string; rate: number }) => ({
                  name: w.week,
                  value: w.rate,
                }))}
                color="#10b981"
                height={180}
                formatValue={(v: number) => `${v}%`}
              />
            </WidgetCard>
          )}

          {analytics.overtime_by_employee && analytics.overtime_by_employee.length > 0 && (
            <WidgetCard title="Overtime by Employee">
              <MiniBarChart
                data={analytics.overtime_by_employee.slice(0, 8).map((e: { employee: string; hours: number }) => ({
                  name: e.employee.split(' ')[0],
                  value: e.hours,
                }))}
                color="#f59e0b"
                height={180}
                formatValue={(v: number) => `${v}h`}
              />
            </WidgetCard>
          )}
        </DashboardGrid>
      )}

      {/* Recent Requests */}
      <WidgetCard title="Recent Requests (Requires Notation)">
        {!hasAnyRecent ? (
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <CheckCircle className="h-10 w-10 text-neutral-300 mb-3" />
            <p className="text-sm text-neutral-500 font-medium">No pending items.</p>
            <p className="text-xs text-neutral-400 mt-1">New requests from your team will appear here.</p>
          </div>
        ) : (
          <ActivityFeed
            items={[
              ...recentLeaves.slice(0, 3).map((req, i) => ({
                id: `leave-${i}`,
                label: req.employee?.full_name ?? 'Unknown',
                sub: `Leave - ${req.leave_type?.name ?? 'N/A'}`,
                status: 'pending' as const,
                href: '/leave/requests',
              })),
              ...recentOT.slice(0, 3).map((req, i) => ({
                id: `ot-${i}`,
                label: req.employee?.full_name ?? 'Unknown',
                sub: `Overtime - ${req.reason ?? 'N/A'}`,
                status: 'pending' as const,
                href: '/attendance/overtime',
              })),
              ...recentLoans.slice(0, 2).map((req, i) => ({
                id: `loan-${i}`,
                label: req.employee?.full_name ?? 'Unknown',
                sub: `Loan - ${req.loan_type?.name ?? 'N/A'}`,
                status: 'pending' as const,
                href: '/loans',
              })),
            ]}
          />
        )}
      </WidgetCard>

      {/* Operational Modules (permission-gated) */}
      <div>
        <SectionHeader title="Operational Modules" />
        <QuickActions
          actions={[
            { label: 'Work Orders', href: '/maintenance/work-orders', icon: Wrench },
            { label: 'Mold Masters', href: '/mold/masters', icon: Package },
            { label: 'Production Orders', href: '/production/orders', icon: ClipboardList },
            { label: 'Delivery', href: '/delivery', icon: Truck },
            { label: 'Inventory MRQ', href: '/inventory/requisitions', icon: Package },
            { label: 'ISO Audits', href: '/iso/audits', icon: CheckCircle },
            { label: 'OT Requests', href: '/attendance/overtime', icon: Timer },
            { label: 'Leave Requests', href: '/leave/requests', icon: Calendar },
          ].filter((link) => {
            const permMap: Record<string, string> = {
              '/maintenance/work-orders': 'maintenance.view',
              '/mold/masters': 'mold.view',
              '/production/orders': 'production.orders.view',
              '/delivery': 'delivery.view',
              '/inventory/requisitions': 'inventory.mrq.view',
              '/iso/audits': 'iso.view',
              '/attendance/overtime': 'overtime.view',
              '/leave/requests': 'leaves.view_team',
            }
            return hasPermission(permMap[link.href] ?? '')
          })}
        />
      </div>
    </div>
  )
}
