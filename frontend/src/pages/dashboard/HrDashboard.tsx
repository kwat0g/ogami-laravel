import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHrDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { 
  Users, 
  Clock, 
  Building,
  AlertCircle,
  UserCheck,
  UserX,
  Timer,
  TrendingUp,
  Briefcase,
  BarChart3,
  PieChart,
  Activity,
  DollarSign,
  RotateCcw,
  Calendar,
  ChevronRight,
  Users2,
  Wallet
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

// Alert card for pending approvals
function PendingAlert({ 
  count, 
  label, 
  href,
  color = 'amber'
}: { 
  count: number
  label: string
  href: string
  color?: 'amber' | 'blue' | 'green' | 'purple'
}) {
  if (count === 0) return null
  
  const colorMap = {
    amber: {
      bg: 'bg-amber-50',
      border: 'border-amber-200',
      badge: 'bg-amber-500',
      text: 'text-amber-800',
      subtext: 'text-amber-600',
    },
    blue: {
      bg: 'bg-blue-50',
      border: 'border-blue-200',
      badge: 'bg-blue-500',
      text: 'text-blue-800',
      subtext: 'text-blue-600',
    },
    green: {
      bg: 'bg-green-50',
      border: 'border-green-200',
      badge: 'bg-green-500',
      text: 'text-green-800',
      subtext: 'text-green-600',
    },
    purple: {
      bg: 'bg-purple-50',
      border: 'border-purple-200',
      badge: 'bg-purple-500',
      text: 'text-purple-800',
      subtext: 'text-purple-600',
    },
  }
  
  const colors = colorMap[color]
  
  return (
    <Link 
      to={href}
      className={`flex items-center gap-4 p-4 rounded-xl border ${colors.border} ${colors.bg} hover:shadow-md transition-all duration-200`}
    >
      <div className={`h-12 w-12 rounded-xl ${colors.badge} flex items-center justify-center shadow-sm`}>
        <span className="text-lg font-bold text-white">{count}</span>
      </div>
      <div className="flex-1">
        <span className={`text-sm font-semibold ${colors.text} block`}>{label}</span>
        <span className={`text-xs ${colors.subtext}`}>Click to review</span>
      </div>
      <ChevronRight className={`h-5 w-5 ${colors.subtext}`} />
    </Link>
  )
}

// Simple bar chart component
function SimpleBarChart({ data, valueKey, labelKey }: { data: Record<string, unknown>[], valueKey: string, labelKey: string }) {
  const maxValue = Math.max(...data.map(d => d[valueKey] || 0))
  
  return (
    <div className="space-y-3">
      {data.map((item, idx) => (
        <div key={idx} className="flex items-center gap-3">
          <span className="text-sm text-gray-600 w-28 truncate">{item[labelKey]}</span>
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

// Mini chart for headcount trend
function MiniLineChart({ data }: { data: Record<string, unknown>[] }) {
  const maxValue = Math.max(...data.map(d => d.count || 0))
  const minValue = Math.min(...data.map(d => d.count || 0))
  const range = maxValue - minValue || 1
  
  return (
    <div className="h-40 flex items-end gap-2">
      {data.map((item, idx) => {
        const height = maxValue > 0 ? ((item.count - minValue) / range) * 80 + 20 : 20
        return (
          <div key={idx} className="flex-1 flex flex-col items-center gap-2">
            <div className="relative w-full flex justify-center">
              <div 
                className="w-full max-w-[24px] bg-gradient-to-t from-blue-500 to-blue-400 rounded-t-lg transition-all duration-500"
                style={{ height: `${height}px` }}
              />
              <div className="absolute -top-6 opacity-0 hover:opacity-100 transition-opacity">
                <span className="text-xs font-medium text-gray-700 bg-white px-2 py-1 rounded shadow-sm border">
                  {item.count}
                </span>
              </div>
            </div>
            <span className="text-[10px] text-gray-500 font-medium">{item.month.split(' ')[0]}</span>
          </div>
        )
      })}
    </div>
  )
}

export default function HrDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading } = useHrDashboardStats()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const pendingTotal = stats?.pending_approvals.total ?? 0
  const analytics = stats?.analytics

  return (
    <div className="space-y-6">
      {/* Header with Welcome */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            HR Dashboard
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Welcome back, {user?.name?.split(' ')[0] ?? 'there'}. Here's your company overview.
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-gray-500">{new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Pending Approvals Section */}
      {pendingTotal > 0 && (
        <SectionCard title="Pending Approvals" icon={AlertCircle}>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <PendingAlert
              count={stats?.pending_approvals.leaves ?? 0}
              label="Leave Requests"
              href="/hr/leave"
              color="amber"
            />
            <PendingAlert
              count={stats?.pending_approvals.overtime ?? 0}
              label="Overtime Requests"
              href="/hr/overtime"
              color="blue"
            />
            <PendingAlert
              count={stats?.pending_approvals.loans ?? 0}
              label="Loan Applications"
              href="/hr/loans"
              color="green"
            />
          </div>
        </SectionCard>
      )}

      {/* Company Overview Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Total Employees"
          value={stats?.company_wide.total_employees ?? 0}
          sub="Across all departments"
          icon={Users}
          color="blue"
          href="/hr/employees/all"
        />
        <StatCard
          label="Departments"
          value={stats?.company_wide.total_departments ?? 0}
          sub="Active departments"
          icon={Building}
          color="gray"
          href="/hr/departments"
        />
        <StatCard
          label="New Hires"
          value={stats?.company_wide.new_hires_this_month ?? 0}
          sub="This month"
          icon={TrendingUp}
          color="green"
        />
        <StatCard
          label="Turnover Rate"
          value={`${analytics?.overall_turnover_rate ?? 0}%`}
          sub="YTD"
          icon={RotateCcw}
          color="amber"
        />
      </div>

      {/* HR Analytics Section */}
      {analytics && (
        <SectionCard title="HR Analytics" icon={BarChart3}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {/* Headcount Trend */}
            <div className="lg:col-span-1">
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Users2 className="h-4 w-4 text-blue-500" />
                Headcount Trend (12 months)
              </h3>
              <MiniLineChart data={analytics.headcount_trend || []} />
            </div>

            {/* Turnover by Department */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Activity className="h-4 w-4 text-red-500" />
                Turnover by Department (YTD)
              </h3>
              {analytics.turnover_by_department?.length > 0 ? (
                <SimpleBarChart 
                  data={analytics.turnover_by_department} 
                  valueKey="count" 
                  labelKey="name" 
                />
              ) : (
                <p className="text-sm text-gray-400 py-8 text-center bg-gray-50 rounded-lg">No turnover data</p>
              )}
            </div>

            {/* Average Tenure */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Calendar className="h-4 w-4 text-green-500" />
                Avg Tenure by Dept (Years)
              </h3>
              {analytics.avg_tenure_by_dept?.length > 0 ? (
                <div className="space-y-3">
                  {analytics.avg_tenure_by_dept.map((item: Record<string, unknown>, idx: number) => (
                    <div key={idx} className="flex items-center justify-between">
                      <span className="text-sm text-gray-600 truncate flex-1">{item.name}</span>
                      <div className="flex items-center gap-3">
                        <div className="w-20 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                          <div 
                            className="h-full bg-gradient-to-r from-green-500 to-green-400 rounded-full"
                            style={{ width: `${Math.min(100, (item.avg_years / 10) * 100)}%` }}
                          />
                        </div>
                        <span className="text-sm font-semibold w-8 text-right">{item.avg_years}</span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-400 py-8 text-center bg-gray-50 rounded-lg">No data</p>
              )}
            </div>

            {/* Hires vs Terminations */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-indigo-500" />
                Hires vs Terminations (6 months)
              </h3>
              <div className="space-y-2">
                {analytics.hires_vs_terminations?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                    <span className="text-sm text-gray-600 w-14">{item.month}</span>
                    <div className="flex items-center gap-3 flex-1 justify-end">
                      <span className="text-sm font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded">+{item.hires}</span>
                      <span className="text-sm font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded">-{item.terminations}</span>
                      <span className={`text-sm font-semibold w-14 text-right ${item.net_change >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {item.net_change > 0 ? '+' : ''}{item.net_change}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Leave Utilization */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Clock className="h-4 w-4 text-amber-500" />
                Leave Utilization (YTD)
              </h3>
              {analytics.leave_utilization?.length > 0 ? (
                <SimpleBarChart 
                  data={analytics.leave_utilization} 
                  valueKey="total_days" 
                  labelKey="name" 
                />
              ) : (
                <p className="text-sm text-gray-400 py-8 text-center bg-gray-50 rounded-lg">No leave data</p>
              )}
            </div>

            {/* Payroll Trend */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <DollarSign className="h-4 w-4 text-purple-500" />
                Payroll Trend (6 months)
              </h3>
              <div className="space-y-2">
                {analytics.payroll_trend?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                    <span className="text-sm text-gray-600">{item.month}</span>
                    <span className="text-sm font-semibold text-gray-900">
                      ₱{item.amount_php.toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </SectionCard>
      )}

      {/* Two Column Layout: Attendance & Department Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Attendance Summary */}
        {stats?.attendance_summary && (
          <SectionCard title="Attendance Summary (This Month)" icon={UserCheck} action={{ label: 'View all', href: '/hr/attendance' }}>
            <div className="grid grid-cols-4 gap-4">
              <div className="text-center p-4 rounded-xl bg-green-50 border border-green-100">
                <div className="h-10 w-10 rounded-lg bg-green-500 flex items-center justify-center mx-auto mb-2">
                  <UserCheck className="h-5 w-5 text-white" />
                </div>
                <p className="text-2xl font-bold text-gray-900">{stats.attendance_summary.present}</p>
                <p className="text-xs text-gray-600 font-medium">Present</p>
              </div>
              <div className="text-center p-4 rounded-xl bg-red-50 border border-red-100">
                <div className="h-10 w-10 rounded-lg bg-red-500 flex items-center justify-center mx-auto mb-2">
                  <UserX className="h-5 w-5 text-white" />
                </div>
                <p className="text-2xl font-bold text-gray-900">{stats.attendance_summary.absent}</p>
                <p className="text-xs text-gray-600 font-medium">Absent</p>
              </div>
              <div className="text-center p-4 rounded-xl bg-amber-50 border border-amber-100">
                <div className="h-10 w-10 rounded-lg bg-amber-500 flex items-center justify-center mx-auto mb-2">
                  <Timer className="h-5 w-5 text-white" />
                </div>
                <p className="text-2xl font-bold text-gray-900">{stats.attendance_summary.late}</p>
                <p className="text-xs text-gray-600 font-medium">Late</p>
              </div>
              <div className="text-center p-4 rounded-xl bg-blue-50 border border-blue-100">
                <div className="h-10 w-10 rounded-lg bg-blue-500 flex items-center justify-center mx-auto mb-2">
                  <Users className="h-5 w-5 text-white" />
                </div>
                <p className="text-2xl font-bold text-gray-900">{stats.attendance_summary.total}</p>
                <p className="text-xs text-gray-600 font-medium">Total</p>
              </div>
            </div>
          </SectionCard>
        )}

        {/* Headcount by Department */}
        <SectionCard title="Headcount by Department" icon={PieChart} action={{ label: 'All departments', href: '/hr/departments' }}>
          {(stats?.by_department?.length ?? 0) > 0 ? (
            <div className="space-y-3 max-h-64 overflow-y-auto">
              {stats?.by_department.map((dept) => {
                const maxCount = Math.max(...(stats?.by_department?.map(d => d.count) || [1]))
                const percentage = (dept.count / (stats?.company_wide.total_employees || 1)) * 100
                return (
                  <div key={dept.department} className="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <span className="text-sm font-medium text-gray-700 w-32 truncate">{dept.department}</span>
                    <div className="flex-1 flex items-center gap-3">
                      <div className="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden">
                        <div 
                          className="h-full bg-gradient-to-r from-blue-500 to-indigo-400 rounded-full transition-all duration-500"
                          style={{ width: `${(dept.count / maxCount) * 100}%` }}
                        />
                      </div>
                      <span className="text-sm font-bold text-gray-900 w-10 text-right">{dept.count}</span>
                      <span className="text-xs text-gray-500 w-12 text-right">{percentage.toFixed(1)}%</span>
                    </div>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="text-sm text-gray-400 py-8 text-center bg-gray-50 rounded-lg">No department data available</p>
          )}
        </SectionCard>
      </div>

      {/* Active Payroll Info */}
      {stats?.active_payroll && (
        <SectionCard title="Active Payroll Run" icon={Briefcase} action={{ label: 'View details', href: `/payroll/runs/${stats.active_payroll.ulid}` }}>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div className="p-4 bg-gray-50 rounded-lg">
              <p className="text-xs text-gray-500 mb-1">Run ID</p>
              <p className="text-lg font-bold text-gray-900">#{stats.active_payroll.id}</p>
            </div>
            <div className="p-4 bg-gray-50 rounded-lg">
              <p className="text-xs text-gray-500 mb-1">Status</p>
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 capitalize">
                {stats.active_payroll.status}
              </span>
            </div>
            <div className="p-4 bg-gray-50 rounded-lg">
              <p className="text-xs text-gray-500 mb-1">Pay Period</p>
              <p className="text-sm font-bold text-gray-900">{stats.active_payroll.pay_period?.name ?? 'N/A'}</p>
            </div>
            <div className="p-4 bg-gray-50 rounded-lg">
              <p className="text-xs text-gray-500 mb-1">Employees</p>
              <p className="text-lg font-bold text-gray-900">{stats.active_payroll.total_employees ?? 0}</p>
            </div>
          </div>
        </SectionCard>
      )}

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/hr/employees"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors">
              <Users className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">All Employees</span>
          </Link>
          <Link 
            to="/hr/leave"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
              <Clock className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Leave Requests</span>
          </Link>
          <Link 
            to="/hr/overtime"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
              <Timer className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Overtime</span>
          </Link>
          <Link 
            to="/hr/loans"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
              <Wallet className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Loans</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
