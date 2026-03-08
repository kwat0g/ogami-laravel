import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { useManagerDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { StatusBadge } from '@/components/ui/StatusBadge'
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

// Simple stat card using Card component
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
    <Card className="h-full">
      <div className="p-5">
        <div className="flex items-start justify-between">
          <Icon className="h-5 w-5 text-neutral-500" />
          {href && (
            <ChevronRight className="h-4 w-4 text-neutral-400" />
          )}
        </div>
        <div className="mt-4">
          <p className="text-2xl font-semibold text-neutral-900">{value}</p>
          <p className="text-sm text-neutral-600 mt-1">{label}</p>
          {sub && <p className="text-xs text-neutral-500 mt-1">{sub}</p>}
        </div>
      </div>
    </Card>
  )

  if (href) {
    return <Link to={href} className="block">{content}</Link>
  }
  return content
}

// Simple bar chart component - neutral styling
function SimpleBarChart({ data, valueKey, labelKey }: { data: Record<string, unknown>[], valueKey: string, labelKey: string }) {
  const maxValue = Math.max(...data.map(d => d[valueKey] || 0))
  
  return (
    <div className="space-y-2">
      {data.map((item, idx) => (
        <div key={idx} className="flex items-center gap-3">
          <span className="text-sm text-neutral-600 w-20 truncate">{item[labelKey]}</span>
          <div className="flex-1 h-4 bg-neutral-100 rounded overflow-hidden">
            <div 
              className="h-full bg-neutral-400 rounded transition-all duration-500"
              style={{ width: `${maxValue > 0 ? (item[valueKey] / maxValue) * 100 : 0}%` }}
            />
          </div>
          <span className="text-sm font-medium text-neutral-900 w-10 text-right">{item[valueKey]}</span>
        </div>
      ))}
    </div>
  )
}

// Alert card for pending approvals using Card component
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

// Section card using Card component
function SectionCard({ 
  title, 
  children,
  action
}: { 
  title: string
  children: React.ReactNode
  action?: { label: string; href: string }
}) {
  return (
    <Card>
      <CardHeader action={action && (
        <Link to={action.href} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 flex items-center gap-1">
          {action.label}
          <ChevronRight className="h-3 w-3" />
        </Link>
      )}>
        {title}
      </CardHeader>
      <CardBody>{children}</CardBody>
    </Card>
  )
}

// Simple table for recent requests using Card component
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
    <Card>
      <CardHeader action={href && (
        <Link to={href} className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 flex items-center gap-1">
          View all
          <ChevronRight className="h-3 w-3" />
        </Link>
      )}>
        {title}
      </CardHeader>
      <div className="divide-y divide-neutral-100">
        {children || (
          <p className="px-4 py-6 text-sm text-neutral-400 text-center">{emptyMessage}</p>
        )}
      </div>
    </Card>
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
  const normalizedStatus = status?.toLowerCase() || 'draft'
  
  return (
    <div className="px-4 py-3 flex items-center justify-between hover:bg-neutral-50">
      <div>
        <p className="text-sm text-neutral-900">{employee || 'Unknown'}</p>
        <p className="text-xs text-neutral-500">{type} • {date}</p>
      </div>
      <StatusBadge status={normalizedStatus}>
        {status?.replace(/_/g, ' ') || 'Unknown'}
      </StatusBadge>
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
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        Department Dashboard
      </h1>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Active Staff"
          value={stats?.headcount.active ?? 0}
          sub={`of ${stats?.headcount.total ?? 0} total`}
          icon={Users}
          href="/team/employees"
        />
        <StatCard
          label="On Leave"
          value={stats?.headcount.on_leave ?? 0}
          sub="Currently away"
          icon={Calendar}
        />
        <StatCard
          label="Present Today"
          value={stats?.attendance_today.present ?? 0}
          sub={`${stats?.attendance_today.late ?? 0} late`}
          icon={UserCheck}
        />
        <StatCard
          label="Pending Approvals"
          value={pendingTotal}
          sub={pendingTotal > 0 ? 'needs attention' : 'all clear'}
          icon={AlertCircle}
        />
      </div>

      {/* Pending Approvals Section */}
      {pendingTotal > 0 && (
        <SectionCard title="Items Requiring Your Approval">
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
        <SectionCard title="Department Analytics">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {/* Attendance Rate Trend */}
            <div>
              <h3 className="text-sm font-medium text-neutral-700 mb-3 flex items-center gap-2">
                <Activity className="h-4 w-4 text-neutral-500" />
                Attendance Rate Trend
              </h3>
              <div className="space-y-2">
                {analytics.attendance_trend?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center justify-between p-2 hover:bg-neutral-50 rounded">
                    <span className="text-sm text-neutral-600">{item.month}</span>
                    <div className="flex items-center gap-3">
                      <div className="w-20 h-2 bg-neutral-100 rounded-full overflow-hidden">
                        <div 
                          className="h-full bg-neutral-400 rounded-full"
                          style={{ width: `${item.rate}%` }}
                        />
                      </div>
                      <span className="text-sm font-medium w-10 text-right">{item.rate}%</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Leave by Type */}
            <div>
              <h3 className="text-sm font-medium text-neutral-700 mb-3 flex items-center gap-2">
                <PieChart className="h-4 w-4 text-neutral-500" />
                Leave by Type (YTD)
              </h3>
              {analytics.leave_by_type?.length > 0 ? (
                <SimpleBarChart 
                  data={analytics.leave_by_type} 
                  valueKey="total_days" 
                  labelKey="name" 
                />
              ) : (
                <p className="text-sm text-neutral-400 py-6 text-center bg-neutral-50 rounded">No leave data</p>
              )}
            </div>

            {/* Overtime Trend */}
            <div>
              <h3 className="text-sm font-medium text-neutral-700 mb-3 flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-neutral-500" />
                Overtime Hours Trend
              </h3>
              <div className="space-y-2">
                {analytics.overtime_trend?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center justify-between p-2 hover:bg-neutral-50 rounded">
                    <span className="text-sm text-neutral-600">{item.month}</span>
                    <span className="text-sm font-medium text-neutral-900">{item.hours} hrs</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Tenure Distribution */}
            <div>
              <h3 className="text-sm font-medium text-neutral-700 mb-3 flex items-center gap-2">
                <Briefcase className="h-4 w-4 text-neutral-500" />
                Tenure Distribution
              </h3>
              <div className="space-y-2">
                <div className="flex items-center justify-between p-2 hover:bg-neutral-50 rounded">
                  <span className="text-sm text-neutral-600">&lt; 1 year</span>
                  <span className="text-sm font-medium bg-neutral-100 px-2 py-0.5 rounded">{analytics.tenure_distribution?.less_than_1_year ?? 0}</span>
                </div>
                <div className="flex items-center justify-between p-2 hover:bg-neutral-50 rounded">
                  <span className="text-sm text-neutral-600">1-3 years</span>
                  <span className="text-sm font-medium bg-neutral-100 px-2 py-0.5 rounded">{analytics.tenure_distribution?.['1_to_3_years'] ?? 0}</span>
                </div>
                <div className="flex items-center justify-between p-2 hover:bg-neutral-50 rounded">
                  <span className="text-sm text-neutral-600">3-5 years</span>
                  <span className="text-sm font-medium bg-neutral-100 px-2 py-0.5 rounded">{analytics.tenure_distribution?.['3_to_5_years'] ?? 0}</span>
                </div>
                <div className="flex items-center justify-between p-2 hover:bg-neutral-50 rounded">
                  <span className="text-sm text-neutral-600">&gt; 5 years</span>
                  <span className="text-sm font-medium bg-neutral-100 px-2 py-0.5 rounded">{analytics.tenure_distribution?.more_than_5_years ?? 0}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Department vs Company Comparison */}
          {analytics.comparison && (
            <div className="mt-6 pt-4 border-t border-neutral-200">
              <h3 className="text-sm font-medium text-neutral-700 mb-3 flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-neutral-500" />
                Department vs Company Average
              </h3>
              <div className="grid grid-cols-3 gap-4">
                <Card>
                  <div className="text-center p-3">
                    <p className="text-xl font-semibold text-neutral-900">{analytics.comparison.dept_attendance_rate}%</p>
                    <p className="text-xs text-neutral-600 mt-1">Your Dept</p>
                  </div>
                </Card>
                <Card>
                  <div className="text-center p-3">
                    <p className="text-xl font-semibold text-neutral-900">{analytics.comparison.company_avg_attendance}%</p>
                    <p className="text-xs text-neutral-600 mt-1">Company Avg</p>
                  </div>
                </Card>
                <Card className={analytics.comparison.vs_company_avg >= 0 ? '' : 'border-red-200'}>
                  <div className={`text-center p-3 ${analytics.comparison.vs_company_avg >= 0 ? '' : 'bg-red-50'}`}>
                    <p className={`text-xl font-semibold ${analytics.comparison.vs_company_avg >= 0 ? 'text-neutral-900' : 'text-red-600'}`}>
                      {analytics.comparison.vs_company_avg > 0 ? '+' : ''}{analytics.comparison.vs_company_avg}%
                    </p>
                    <p className={`text-xs mt-1 ${analytics.comparison.vs_company_avg >= 0 ? 'text-neutral-600' : 'text-red-700'}`}>Difference</p>
                  </div>
                </Card>
              </div>
            </div>
          )}
        </SectionCard>
      )}

      {/* Today's Attendance Summary */}
      <SectionCard title="Today's Attendance" action={{ label: 'View all', href: '/team/attendance' }}>
        <div className="grid grid-cols-4 gap-4">
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <UserCheck className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance_today.present ?? 0}</p>
              <p className="text-xs text-neutral-600">Present</p>
            </div>
          </Card>
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <UserX className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance_today.absent ?? 0}</p>
              <p className="text-xs text-neutral-600">Absent</p>
            </div>
          </Card>
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <Timer className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance_today.late ?? 0}</p>
              <p className="text-xs text-neutral-600">Late</p>
            </div>
          </Card>
          <Card className="bg-neutral-50">
            <div className="text-center p-3">
              <Calendar className="h-4 w-4 text-neutral-500 mx-auto mb-2" />
              <p className="text-xl font-semibold text-neutral-900">{stats?.attendance_today.on_leave ?? 0}</p>
              <p className="text-xs text-neutral-600">On Leave</p>
            </div>
          </Card>
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
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/team/employees"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Users className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">View Team</span>
          </Link>
          <Link 
            to="/team/attendance"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Clock className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">Attendance</span>
          </Link>
          <Link 
            to="/team/leave"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <Calendar className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">Leave Requests</span>
          </Link>
          <Link 
            to="/team/loans"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle"
          >
            <FileCheck className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">Loan Requests</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
