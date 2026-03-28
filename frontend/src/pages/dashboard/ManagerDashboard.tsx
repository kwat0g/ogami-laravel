/**
 * Manager Dashboard (Department-aware)
 *
 * Shows team KPIs, pending approvals, department analytics with Recharts,
 * attendance summary, and recent requests. Adapts to the manager's
 * primary department.
 */
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { useManagerDashboardStats } from '@/hooks/useDashboard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card } from '@/components/ui/Card'
import {
  KpiCard,
  ApprovalAlert,
  SectionHeader,
  MiniBarChart,
  MiniAreaChart,
  MiniDonutChart,
  WidgetCard,
  DashboardGrid,
  QuickActions,
  ActivityFeed,
} from '@/components/dashboard/DashboardWidgets'
import {
  Users,
  Clock,
  Calendar,
  AlertCircle,
  UserCheck,
  UserX,
  Timer,
  TrendingUp,
  Briefcase,
  FileCheck,
} from 'lucide-react'

const LEAVE_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']

export default function ManagerDashboard() {
  const { user: _user } = useAuth()
  const { primaryDepartmentId } = useAuthStore()
  const deptId = primaryDepartmentId()

  const { data: stats, isLoading, error } = useManagerDashboardStats(deptId)

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingTotal = stats?.pending_approvals.total ?? 0
  const analytics = stats?.analytics
  const deptName = stats?.department?.name ?? 'Department'

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-neutral-900">{deptName} Dashboard</h1>
        <p className="text-sm text-neutral-500 mt-0.5">
          Team performance, pending approvals, and department analytics
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

      {/* Team KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard
          label="Active Staff"
          value={stats?.headcount.active ?? 0}
          sub={`of ${stats?.headcount.total ?? 0} total`}
          icon={Users}
          color="info"
          href="/team/employees"
        />
        <KpiCard
          label="On Leave"
          value={stats?.headcount.on_leave ?? 0}
          sub="Currently away"
          icon={Calendar}
          color={(stats?.headcount.on_leave ?? 0) > 3 ? 'warning' : 'default'}
        />
        <KpiCard
          label="Present Today"
          value={stats?.attendance_today.present ?? 0}
          sub={`${stats?.attendance_today.late ?? 0} late`}
          icon={UserCheck}
          color="success"
        />
        <KpiCard
          label="Pending Approvals"
          value={pendingTotal}
          sub={pendingTotal > 0 ? 'Needs attention' : 'All clear'}
          icon={AlertCircle}
          color={pendingTotal > 0 ? 'warning' : 'success'}
        />
      </div>

      {/* Pending Approval Alerts */}
      {pendingTotal > 0 && (
        <div className="space-y-2">
          <ApprovalAlert count={stats?.pending_approvals.leaves ?? 0} label="Leave requests pending your approval" href="/team/leave" />
          <ApprovalAlert count={stats?.pending_approvals.overtime ?? 0} label="Overtime requests pending review" href="/team/overtime" />
          <ApprovalAlert count={stats?.pending_approvals.loans ?? 0} label="Loan applications awaiting review" href="/team/loans" />
        </div>
      )}

      {/* Today's Attendance Visual */}
      <WidgetCard title="Today's Attendance" action={{ label: 'View All', href: '/team/attendance' }}>
        <div className="grid grid-cols-4 gap-3">
          {[
            { label: 'Present', value: stats?.attendance_today.present ?? 0, icon: UserCheck, color: 'bg-green-50 border-green-100 text-green-600' },
            { label: 'Absent', value: stats?.attendance_today.absent ?? 0, icon: UserX, color: 'bg-red-50 border-red-100 text-red-600' },
            { label: 'Late', value: stats?.attendance_today.late ?? 0, icon: Timer, color: 'bg-amber-50 border-amber-100 text-amber-600' },
            { label: 'On Leave', value: stats?.attendance_today.on_leave ?? 0, icon: Calendar, color: 'bg-blue-50 border-blue-100 text-blue-600' },
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
          {analytics.attendance_trend && analytics.attendance_trend.length > 0 && (
            <WidgetCard title="Attendance Rate Trend">
              <MiniAreaChart
                data={analytics.attendance_trend.map((item: { month: string; rate: number }) => ({
                  name: item.month,
                  value: item.rate,
                }))}
                color="#10b981"
                height={200}
                formatValue={(v: number) => `${v}%`}
              />
            </WidgetCard>
          )}

          {/* Leave by Type */}
          {analytics.leave_by_type && analytics.leave_by_type.length > 0 && (
            <WidgetCard title="Leave by Type (YTD)">
              <MiniDonutChart
                data={analytics.leave_by_type.map((item: { name: string; total_days: number }, i: number) => ({
                  name: item.name,
                  value: item.total_days,
                  color: LEAVE_COLORS[i % LEAVE_COLORS.length],
                }))}
                height={200}
                centerLabel="Total Days"
                centerValue={analytics.leave_by_type.reduce((sum: number, i: { total_days: number }) => sum + i.total_days, 0)}
              />
            </WidgetCard>
          )}

          {/* Overtime Hours Trend */}
          {analytics.overtime_trend && analytics.overtime_trend.length > 0 && (
            <WidgetCard title="Overtime Hours Trend">
              <MiniBarChart
                data={analytics.overtime_trend.map((item: { month: string; hours: number }) => ({
                  name: item.month,
                  value: item.hours,
                }))}
                color="#f59e0b"
                height={200}
                formatValue={(v: number) => `${v}h`}
              />
            </WidgetCard>
          )}

          {/* Tenure Distribution */}
          {analytics.tenure_distribution && (
            <WidgetCard title="Tenure Distribution">
              <MiniDonutChart
                data={[
                  { name: '< 1 year', value: analytics.tenure_distribution.less_than_1_year, color: '#3b82f6' },
                  { name: '1-3 years', value: analytics.tenure_distribution['1_to_3_years'], color: '#10b981' },
                  { name: '3-5 years', value: analytics.tenure_distribution['3_to_5_years'], color: '#f59e0b' },
                  { name: '> 5 years', value: analytics.tenure_distribution.more_than_5_years, color: '#8b5cf6' },
                ].filter(d => d.value > 0)}
                height={200}
                centerLabel="Staff"
                centerValue={stats?.headcount.total ?? 0}
              />
            </WidgetCard>
          )}
        </DashboardGrid>
      )}

      {/* Department vs Company Comparison */}
      {analytics?.comparison && (
        <div>
          <SectionHeader title="Department vs Company Average" />
          <div className="grid grid-cols-3 gap-3">
            <Card className="p-4 text-center bg-blue-50 border-blue-100">
              <p className="text-2xl font-bold text-blue-700">{analytics.comparison.dept_attendance_rate}%</p>
              <p className="text-xs text-blue-600 mt-1 uppercase tracking-wide font-medium">Your Dept</p>
            </Card>
            <Card className="p-4 text-center">
              <p className="text-2xl font-bold text-neutral-700">{analytics.comparison.company_avg_attendance}%</p>
              <p className="text-xs text-neutral-500 mt-1 uppercase tracking-wide font-medium">Company Avg</p>
            </Card>
            <Card className={`p-4 text-center ${analytics.comparison.vs_company_avg >= 0 ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'}`}>
              <p className={`text-2xl font-bold ${analytics.comparison.vs_company_avg >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                {analytics.comparison.vs_company_avg > 0 ? '+' : ''}{analytics.comparison.vs_company_avg}%
              </p>
              <p className={`text-xs mt-1 uppercase tracking-wide font-medium ${analytics.comparison.vs_company_avg >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                Difference
              </p>
            </Card>
          </div>
        </div>
      )}

      {/* Recent Requests */}
      <DashboardGrid>
        <WidgetCard title="Recent Leave Requests" action={{ label: 'View All', href: '/team/leave' }}>
          <ActivityFeed
            items={(stats?.recent_requests.leaves ?? []).slice(0, 5).map((leave) => ({
              id: leave.id,
              label: leave.employee?.full_name ?? 'Unknown',
              sub: `${leave.leave_type?.name ?? 'Leave'} - ${leave.date_from} to ${leave.date_to}`,
              status: leave.status,
            }))}
            emptyMessage="No recent leave requests"
          />
        </WidgetCard>

        <WidgetCard title="Recent Overtime Requests" action={{ label: 'View All', href: '/team/overtime' }}>
          <ActivityFeed
            items={(stats?.recent_requests.overtime ?? []).slice(0, 5).map((ot) => ({
              id: ot.id,
              label: ot.employee?.full_name ?? 'Unknown',
              sub: `Overtime - ${ot.work_date}`,
              status: ot.status,
            }))}
            emptyMessage="No recent overtime requests"
          />
        </WidgetCard>
      </DashboardGrid>

      {/* Quick Navigation */}
      <div>
        <SectionHeader title="Quick Access" />
        <QuickActions actions={[
          { label: 'View Team', href: '/team/employees', icon: Users },
          { label: 'Attendance', href: '/team/attendance', icon: Clock },
          { label: 'Leave Requests', href: '/team/leave', icon: Calendar },
          { label: 'Overtime', href: '/team/overtime', icon: TrendingUp },
          { label: 'Loan Requests', href: '/team/loans', icon: FileCheck },
          { label: 'Team Performance', href: '/team/employees', icon: Briefcase },
        ]} />
      </div>
    </div>
  )
}
