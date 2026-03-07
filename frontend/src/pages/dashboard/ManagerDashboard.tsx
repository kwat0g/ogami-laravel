import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { useManagerDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { 
  Users, 
  Clock, 
  FileCheck, 
  AlertCircle, 
  Calendar,
  TrendingUp,
  UserCheck,
  UserX,
  Timer,
  BarChart3,
  PieChart,
  Activity,
  ChevronRight,
  Briefcase
} from 'lucide-react'

// Modern stat card with subtle shadow and hover effect
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
    blue: {
      bg: 'bg-blue-50',
      border: 'border-blue-100',
      iconBg: 'bg-blue-500',
      text: 'text-blue-700',
      subText: 'text-blue-600',
    },
    amber: {
      bg: 'bg-amber-50',
      border: 'border-amber-100',
      iconBg: 'bg-amber-500',
      text: 'text-amber-700',
      subText: 'text-amber-600',
    },
    green: {
      bg: 'bg-green-50',
      border: 'border-green-100',
      iconBg: 'bg-green-500',
      text: 'text-green-700',
      subText: 'text-green-600',
    },
    red: {
      bg: 'bg-red-50',
      border: 'border-red-100',
      iconBg: 'bg-red-500',
      text: 'text-red-700',
      subText: 'text-red-600',
    },
    gray: {
      bg: 'bg-gray-50',
      border: 'border-gray-200',
      iconBg: 'bg-gray-500',
      text: 'text-gray-700',
      subText: 'text-gray-600',
    },
    purple: {
      bg: 'bg-purple-50',
      border: 'border-purple-100',
      iconBg: 'bg-purple-500',
      text: 'text-purple-700',
      subText: 'text-purple-600',
    },
    indigo: {
      bg: 'bg-indigo-50',
      border: 'border-indigo-100',
      iconBg: 'bg-indigo-500',
      text: 'text-indigo-700',
      subText: 'text-indigo-600',
    },
  }

  const colors = colorMap[color]

  const content = (
    <div className={`${colors.bg} border ${colors.border} rounded-xl p-5 hover:shadow-md transition-all duration-200`}>
      <div className="flex items-start justify-between">
        <div className={`h-12 w-12 rounded-xl ${colors.iconBg} flex items-center justify-center shadow-sm`}>
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
        <p className={`text-sm font-medium ${colors.text} mt-1`}>{label}</p>
        {sub && <p className={`text-xs mt-1 ${colors.subText}`}>{sub}</p>}
      </div>
    </div>
  )

  if (href) {
    return <Link to={href} className="block">{content}</Link>
  }
  return content
}

// Simple bar chart component
function SimpleBarChart({ data, valueKey, labelKey }: { data: Record<string, unknown>[], valueKey: string, labelKey: string }) {
  const maxValue = Math.max(...data.map(d => d[valueKey] || 0))
  
  return (
    <div className="space-y-3">
      {data.map((item, idx) => (
        <div key={idx} className="flex items-center gap-3">
          <span className="text-sm text-gray-600 w-20 truncate">{item[labelKey]}</span>
          <div className="flex-1 h-6 bg-gray-100 rounded-lg overflow-hidden">
            <div 
              className="h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-lg transition-all duration-500"
              style={{ width: `${maxValue > 0 ? (item[valueKey] / maxValue) * 100 : 0}%` }}
            />
          </div>
          <span className="text-sm font-semibold text-gray-900 w-10 text-right">{item[valueKey]}</span>
        </div>
      ))}
    </div>
  )
}

// Alert card for pending approvals
function PendingAlert({ 
  count, 
  label, 
  href 
}: { 
  count: number
  label: string
  href: string
}) {
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

// Section card with header
function SectionCard({ 
  title, 
  icon: Icon, 
  children,
  action
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
      <div className="p-6">
        {children}
      </div>
    </div>
  )
}

// Simple table for recent requests
function RecentRequestsTable({ 
  title, 
  emptyMessage,
  href,
  children 
}: { 
  title: string
  emptyMessage: string
  href?: string
  children: React.ReactNode
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h3 className="text-sm font-semibold text-gray-800">{title}</h3>
        {href && (
          <Link to={href} className="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
            View all
            <ChevronRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="divide-y divide-gray-100">
        {children || (
          <p className="px-6 py-8 text-sm text-gray-400 text-center">{emptyMessage}</p>
        )}
      </div>
    </div>
  )
}

function RequestRow({ 
  employee, 
  type, 
  date, 
  status 
}: { 
  employee: string | null
  type: string
  date: string
  status: string
}) {
  const statusColors: Record<string, { bg: string; text: string; border: string }> = {
    pending: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
    submitted: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
    head_approved: { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
    manager_checked: { bg: 'bg-indigo-50', text: 'text-indigo-700', border: 'border-indigo-200' },
    ga_processed: { bg: 'bg-purple-50', text: 'text-purple-700', border: 'border-purple-200' },
    approved: { bg: 'bg-green-50', text: 'text-green-700', border: 'border-green-200' },
    rejected: { bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200' },
    cancelled: { bg: 'bg-gray-50', text: 'text-gray-700', border: 'border-gray-200' },
  }
  
  const colors = statusColors[status] || statusColors.pending
  
  return (
    <div className="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
      <div>
        <p className="text-sm font-semibold text-gray-900">{employee || 'Unknown'}</p>
        <p className="text-xs text-gray-500">{type} • {date}</p>
      </div>
      <span className={`text-xs font-medium px-2.5 py-1 rounded-full border ${colors.bg} ${colors.text} ${colors.border} capitalize`}>
        {status.replace('_', ' ')}
      </span>
    </div>
  )
}

export default function ManagerDashboard() {
  const { user: _user } = useAuth()
  const { primaryDepartmentId } = useAuthStore()
  const deptId = primaryDepartmentId()
  
  const { data: stats, isLoading } = useManagerDashboardStats(deptId)

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const pendingTotal = stats?.pending_approvals.total ?? 0
  const analytics = stats?.analytics

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Department Dashboard
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            {stats?.department.name ?? 'Your Department'} • {stats?.headcount.total ?? 0} total staff
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-gray-500">{new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Active Staff"
          value={stats?.headcount.active ?? 0}
          sub={`of ${stats?.headcount.total ?? 0} total`}
          icon={Users}
          color="blue"
          href="/team/employees"
        />
        <StatCard
          label="On Leave"
          value={stats?.headcount.on_leave ?? 0}
          sub="Currently away"
          icon={Calendar}
          color="gray"
        />
        <StatCard
          label="Present Today"
          value={stats?.attendance_today.present ?? 0}
          sub={`${stats?.attendance_today.late ?? 0} late`}
          icon={UserCheck}
          color="green"
        />
        <StatCard
          label="Pending Approvals"
          value={pendingTotal}
          sub={pendingTotal > 0 ? 'needs attention' : 'all clear'}
          icon={AlertCircle}
          color={pendingTotal > 0 ? 'amber' : 'green'}
        />
      </div>

      {/* Pending Approvals Section */}
      {pendingTotal > 0 && (
        <SectionCard title="Items Requiring Your Approval" icon={Clock}>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <PendingAlert
              count={stats?.pending_approvals.leaves ?? 0}
              label="Leave Requests"
              href="/team/leave"
            />
            <PendingAlert
              count={stats?.pending_approvals.overtime ?? 0}
              label="Overtime Requests"
              href="/team/overtime"
            />
            <PendingAlert
              count={stats?.pending_approvals.loans ?? 0}
              label="Loan Applications"
              href="/team/loans"
            />
          </div>
        </SectionCard>
      )}

      {/* Analytics Section */}
      {analytics && (
        <SectionCard title="Department Analytics" icon={BarChart3}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Attendance Rate Trend */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Activity className="h-4 w-4 text-blue-500" />
                Attendance Rate Trend
              </h3>
              <div className="space-y-3">
                {analytics.attendance_trend?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                    <span className="text-sm text-gray-600">{item.month}</span>
                    <div className="flex items-center gap-3">
                      <div className="w-20 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                        <div 
                          className={`h-full rounded-full ${item.rate >= 95 ? 'bg-green-500' : item.rate >= 90 ? 'bg-amber-500' : 'bg-red-500'}`}
                          style={{ width: `${item.rate}%` }}
                        />
                      </div>
                      <span className="text-sm font-semibold w-10 text-right">{item.rate}%</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Leave by Type */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <PieChart className="h-4 w-4 text-amber-500" />
                Leave by Type (YTD)
              </h3>
              {analytics.leave_by_type?.length > 0 ? (
                <SimpleBarChart 
                  data={analytics.leave_by_type} 
                  valueKey="total_days" 
                  labelKey="name" 
                />
              ) : (
                <p className="text-sm text-gray-400 py-8 text-center bg-gray-50 rounded-lg">No leave data</p>
              )}
            </div>

            {/* Overtime Trend */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-purple-500" />
                Overtime Hours Trend
              </h3>
              <div className="space-y-3">
                {analytics.overtime_trend?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                    <span className="text-sm text-gray-600">{item.month}</span>
                    <span className="text-sm font-semibold text-gray-900">{item.hours} hrs</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Tenure Distribution */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Briefcase className="h-4 w-4 text-indigo-500" />
                Tenure Distribution
              </h3>
              <div className="space-y-3">
                <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                  <span className="text-sm text-gray-600">&lt; 1 year</span>
                  <span className="text-sm font-semibold bg-blue-50 px-2 py-0.5 rounded">{analytics.tenure_distribution?.less_than_1_year ?? 0}</span>
                </div>
                <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                  <span className="text-sm text-gray-600">1-3 years</span>
                  <span className="text-sm font-semibold bg-blue-50 px-2 py-0.5 rounded">{analytics.tenure_distribution?.['1_to_3_years'] ?? 0}</span>
                </div>
                <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                  <span className="text-sm text-gray-600">3-5 years</span>
                  <span className="text-sm font-semibold bg-blue-50 px-2 py-0.5 rounded">{analytics.tenure_distribution?.['3_to_5_years'] ?? 0}</span>
                </div>
                <div className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                  <span className="text-sm text-gray-600">&gt; 5 years</span>
                  <span className="text-sm font-semibold bg-blue-50 px-2 py-0.5 rounded">{analytics.tenure_distribution?.more_than_5_years ?? 0}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Department vs Company Comparison */}
          {analytics.comparison && (
            <div className="mt-8 pt-6 border-t border-gray-100">
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-gray-500" />
                Department vs Company Average
              </h3>
              <div className="grid grid-cols-3 gap-4">
                <div className="text-center p-4 bg-blue-50 rounded-xl border border-blue-100">
                  <p className="text-2xl font-bold text-blue-600">{analytics.comparison.dept_attendance_rate}%</p>
                  <p className="text-xs text-blue-700 font-medium mt-1">Your Dept</p>
                </div>
                <div className="text-center p-4 bg-gray-50 rounded-xl border border-gray-200">
                  <p className="text-2xl font-bold text-gray-700">{analytics.comparison.company_avg_attendance}%</p>
                  <p className="text-xs text-gray-600 font-medium mt-1">Company Avg</p>
                </div>
                <div className={`text-center p-4 rounded-xl border ${analytics.comparison.vs_company_avg >= 0 ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'}`}>
                  <p className={`text-2xl font-bold ${analytics.comparison.vs_company_avg >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                    {analytics.comparison.vs_company_avg > 0 ? '+' : ''}{analytics.comparison.vs_company_avg}%
                  </p>
                  <p className={`text-xs font-medium mt-1 ${analytics.comparison.vs_company_avg >= 0 ? 'text-green-700' : 'text-red-700'}`}>Difference</p>
                </div>
              </div>
            </div>
          )}
        </SectionCard>
      )}

      {/* Today's Attendance Summary */}
      <SectionCard title="Today's Attendance" icon={UserCheck} action={{ label: 'View all', href: '/team/attendance' }}>
        <div className="grid grid-cols-4 gap-4">
          <div className="text-center p-4 rounded-xl bg-green-50 border border-green-100">
            <div className="h-10 w-10 rounded-lg bg-green-500 flex items-center justify-center mx-auto mb-2">
              <UserCheck className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance_today.present ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">Present</p>
          </div>
          <div className="text-center p-4 rounded-xl bg-red-50 border border-red-100">
            <div className="h-10 w-10 rounded-lg bg-red-500 flex items-center justify-center mx-auto mb-2">
              <UserX className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance_today.absent ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">Absent</p>
          </div>
          <div className="text-center p-4 rounded-xl bg-amber-50 border border-amber-100">
            <div className="h-10 w-10 rounded-lg bg-amber-500 flex items-center justify-center mx-auto mb-2">
              <Timer className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance_today.late ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">Late</p>
          </div>
          <div className="text-center p-4 rounded-xl bg-blue-50 border border-blue-100">
            <div className="h-10 w-10 rounded-lg bg-blue-500 flex items-center justify-center mx-auto mb-2">
              <Calendar className="h-5 w-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-gray-900">{stats?.attendance_today.on_leave ?? 0}</p>
            <p className="text-xs text-gray-600 font-medium">On Leave</p>
          </div>
        </div>
      </SectionCard>

      {/* Recent Requests */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <RecentRequestsTable title="Recent Leave Requests" emptyMessage="No recent leave requests" href="/team/leave">
          {stats?.recent_requests.leaves?.slice(0, 5).map((leave) => (
            <RequestRow
              key={leave.id}
              employee={leave.employee?.full_name ?? null}
              type={leave.leave_type?.name ?? 'Leave'}
              date={`${leave.date_from} to ${leave.date_to}`}
              status={leave.status}
            />
          ))}
        </RecentRequestsTable>

        <RecentRequestsTable title="Recent Overtime Requests" emptyMessage="No recent overtime requests" href="/team/overtime">
          {stats?.recent_requests.overtime?.slice(0, 5).map((ot) => (
            <RequestRow
              key={ot.id}
              employee={ot.employee?.full_name ?? null}
              type="Overtime"
              date={ot.work_date}
              status={ot.status}
            />
          ))}
        </RecentRequestsTable>
      </div>

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/team/employees"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors">
              <Users className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">View Team</span>
          </Link>
          <Link 
            to="/team/attendance"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
              <Clock className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Attendance</span>
          </Link>
          <Link 
            to="/team/leave"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
              <Calendar className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Leave Requests</span>
          </Link>
          <Link 
            to="/team/loans"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
              <FileCheck className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Loan Requests</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
