/**
 * Employee / Staff Dashboard
 *
 * Self-service portal showing personal attendance, leave balances, loan status,
 * payroll summary, and recent requests. Every employee sees this dashboard.
 */
import { useAuth } from '@/hooks/useAuth'
import { useStaffDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card } from '@/components/ui/Card'
import {
  KpiCard,
  SectionHeader,
  MiniAreaChart,
  MiniDonutChart,
  WidgetCard,
  DashboardGrid,
  QuickActions,
  ActivityFeed,
  formatPeso,
} from '@/components/dashboard/DashboardWidgets'
import {
  Calendar,
  Wallet,
  UserCircle,
  Clock,
  CheckCircle2,
  AlertCircle,
  PiggyBank,
  Timer,
  FileText,
} from 'lucide-react'

const LEAVE_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']

export default function EmployeeDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading, error } = useStaffDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const currentYear = new Date().getFullYear()
  const analytics = stats?.analytics

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-neutral-900 dark:text-neutral-100">
          Welcome, {user?.name?.split(' ')[0] ?? 'there'}
        </h1>
        <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
          Your personal dashboard - attendance, leave, payroll, and self-service
        </p>
      </div>

      {/* Error state */}
      {error && (
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load dashboard. Please refresh.</span>
          </div>
        </Card>
      )}

      {/* Personal KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard
          label="Leave Balance"
          value={`${stats?.leave.balance_days ?? 0} days`}
          sub={`${stats?.leave.pending_requests ?? 0} pending`}
          icon={Calendar}
          color={(stats?.leave.balance_days ?? 0) < 5 ? 'warning' : 'success'}
          href="/me/leaves"
        />
        <KpiCard
          label="Active Loans"
          value={stats?.loans.active_loans ?? 0}
          sub={`${stats?.loans.pending_approvals ?? 0} pending`}
          icon={PiggyBank}
          color={(stats?.loans.active_loans ?? 0) > 0 ? 'info' : 'default'}
          href="/me/loans"
        />
        <KpiCard
          label="OT Hours"
          value={stats?.attendance.this_month.ot_hours ?? 0}
          sub="This month"
          icon={Timer}
          href="/me/overtime"
        />
        <KpiCard
          label="YTD Net Pay"
          value={formatPeso(stats?.payroll.ytd_net ?? 0)}
          sub={`${currentYear} to date`}
          icon={Wallet}
          color="info"
          href="/self-service/payslips"
        />
      </div>

      {/* Attendance This Month */}
      <WidgetCard title="Attendance This Month" action={{ label: 'View Details', href: '/me/attendance' }}>
        <div className="grid grid-cols-4 gap-3">
          {[
            { label: 'Present', value: stats?.attendance.this_month.present ?? 0, icon: CheckCircle2, color: 'bg-green-50 border-green-100 text-green-600' },
            { label: 'Absent', value: stats?.attendance.this_month.absent ?? 0, icon: AlertCircle, color: 'bg-red-50 border-red-100 text-red-600' },
            { label: 'Late', value: stats?.attendance.this_month.late ?? 0, icon: Clock, color: 'bg-amber-50 border-amber-100 text-amber-600' },
            { label: 'OT Hours', value: stats?.attendance.this_month.ot_hours ?? 0, icon: Timer, color: 'bg-blue-50 border-blue-100 text-blue-600' },
          ].map(({ label, value, icon: Icon, color }) => (
            <Card key={label} className={color}>
              <div className="p-3 text-center">
                <Icon className="h-4 w-4 mx-auto mb-2 opacity-70" />
                <p className="text-xl font-bold">{value}</p>
                <p className="text-[10px] uppercase tracking-wide font-medium mt-0.5">{label}</p>
              </div>
            </Card>
          ))}
        </div>
      </WidgetCard>

      {/* Analytics Charts (if available) */}
      {analytics && (
        <DashboardGrid>
          {/* Attendance Rate Trend */}
          {analytics.attendance_rate && analytics.attendance_rate.length > 0 && (
            <WidgetCard title="My Attendance Rate">
              <MiniAreaChart
                data={analytics.attendance_rate.map((m: { month: string; rate: number }) => ({
                  name: m.month,
                  value: m.rate,
                }))}
                color="#10b981"
                height={180}
                formatValue={(v: number) => `${v}%`}
              />
            </WidgetCard>
          )}

          {/* Leave Utilization */}
          {analytics.leave_utilization && analytics.leave_utilization.length > 0 && (
            <WidgetCard title="Leave Utilization">
              <MiniDonutChart
                data={analytics.leave_utilization.map((l: { name: string; days_used: number }, i: number) => ({
                  name: l.name,
                  value: l.days_used,
                  color: LEAVE_COLORS[i % LEAVE_COLORS.length],
                }))}
                height={180}
                centerLabel="Days Used"
                centerValue={analytics.leave_utilization.reduce((sum: number, l: { days_used: number }) => sum + l.days_used, 0)}
              />
            </WidgetCard>
          )}
        </DashboardGrid>
      )}

      {/* YTD Comparison (if available) */}
      {analytics?.ytd_comparison && (
        <div>
          <SectionHeader title="Year-over-Year Comparison" />
          <div className="grid grid-cols-3 gap-3">
            <Card className="p-4 text-center">
              <p className="text-xl font-bold text-neutral-900">{formatPeso(analytics.ytd_comparison.current_year_gross)}</p>
              <p className="text-xs text-neutral-500 mt-1 uppercase tracking-wide">{currentYear} YTD Gross</p>
            </Card>
            <Card className="p-4 text-center">
              <p className="text-xl font-bold text-neutral-700">{formatPeso(analytics.ytd_comparison.last_year_gross)}</p>
              <p className="text-xs text-neutral-500 mt-1 uppercase tracking-wide">{currentYear - 1} YTD Gross</p>
            </Card>
            <Card className={`p-4 text-center ${analytics.ytd_comparison.change_percent >= 0 ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'}`}>
              <p className={`text-xl font-bold ${analytics.ytd_comparison.change_percent >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                {analytics.ytd_comparison.change_percent > 0 ? '+' : ''}{analytics.ytd_comparison.change_percent.toFixed(1)}%
              </p>
              <p className={`text-xs mt-1 uppercase tracking-wide ${analytics.ytd_comparison.change_percent >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                Change
              </p>
            </Card>
          </div>
        </div>
      )}

      {/* Active Loans */}
      {(stats?.loans.active_loans ?? 0) > 0 && (
        <WidgetCard title="Active Loans" action={{ label: 'View All', href: '/me/loans' }}>
          <div className="flex items-center gap-4 p-4 bg-blue-50 border border-blue-100 rounded-lg">
            <PiggyBank className="h-6 w-6 text-blue-600 shrink-0" />
            <div className="flex-1">
              <p className="text-xs text-blue-700 font-medium uppercase tracking-wide">Total Outstanding Balance</p>
              <p className="text-2xl font-bold text-blue-900 mt-0.5">{formatPeso(stats?.loans.total_outstanding ?? 0)}</p>
            </div>
            <div className="text-right">
              <p className="text-xs text-blue-700 font-medium uppercase tracking-wide">Active Loans</p>
              <p className="text-2xl font-bold text-blue-900 mt-0.5">{stats?.loans.active_loans}</p>
            </div>
          </div>
        </WidgetCard>
      )}

      {/* Recent Requests */}
      <DashboardGrid>
        <WidgetCard title="Recent Leave Requests" action={{ label: 'View All', href: '/me/leaves' }}>
          <ActivityFeed
            items={(stats?.recent_requests.leaves ?? []).slice(0, 5).map((leave) => ({
              id: leave.id,
              label: leave.leave_type?.name ?? 'Leave',
              sub: `${leave.date_from} to ${leave.date_to} (${leave.total_days} day${leave.total_days > 1 ? 's' : ''})`,
              status: leave.status,
            }))}
            emptyMessage="No recent leave requests"
          />
        </WidgetCard>

        <WidgetCard title="Recent Overtime Requests" action={{ label: 'View All', href: '/me/overtime' }}>
          <ActivityFeed
            items={(stats?.recent_requests.overtime ?? []).slice(0, 5).map((ot) => ({
              id: ot.id,
              label: 'Overtime Request',
              sub: `${ot.work_date} - ${ot.requested_hours} hours`,
              status: ot.status,
            }))}
            emptyMessage="No recent overtime requests"
          />
        </WidgetCard>
      </DashboardGrid>

      {/* Quick Access */}
      <div>
        <SectionHeader title="Quick Access" />
        <QuickActions actions={[
          { label: 'My Payslips', href: '/self-service/payslips', icon: FileText },
          { label: 'My Leaves', href: '/me/leaves', icon: Calendar },
          { label: 'My Loans', href: '/me/loans', icon: Wallet },
          { label: 'My Overtime', href: '/me/overtime', icon: Timer },
          { label: 'My Attendance', href: '/me/attendance', icon: Clock },
          { label: 'My Profile', href: '/me/profile', icon: UserCircle },
        ]} />
      </div>
    </div>
  )
}
