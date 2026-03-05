import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useExecutiveDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { 
  Users, 
  AlertCircle, 
  Building,
  TrendingUp,
  TrendingDown,
  DollarSign,
  Briefcase,
  Calendar,
  Activity,
  ChevronRight,
  BarChart3
} from 'lucide-react'

// Modern stat card with subtle shadow and hover effect
function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  color = 'blue',
  trend,
  href,
}: {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  color?: 'blue' | 'amber' | 'green' | 'red' | 'gray' | 'purple' | 'indigo'
  trend?: 'up' | 'down' | 'neutral'
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
        <div className="flex flex-col items-end gap-1">
          {href && (
            <div className="h-8 w-8 rounded-lg bg-white/60 flex items-center justify-center">
              <ChevronRight className="h-4 w-4 text-gray-400" />
            </div>
          )}
          {trend && (
            <div className={`flex items-center gap-0.5 text-xs font-medium px-2 py-1 rounded-full ${
              trend === 'up' ? 'bg-green-100 text-green-700' : trend === 'down' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'
            }`}>
              {trend === 'up' && <TrendingUp className="h-3 w-3" />}
              {trend === 'down' && <TrendingDown className="h-3 w-3" />}
              {trend === 'neutral' && <Activity className="h-3 w-3" />}
            </div>
          )}
        </div>
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

export default function ExecutiveDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading } = useExecutiveDashboardStats()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const pendingTotal = stats?.pending_executive_approvals.total ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Executive Overview
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Welcome back, {user?.name?.split(' ')[0] ?? 'there'}. Company-wide performance and key metrics.
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-gray-500">{new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Pending Executive Approvals */}
      {pendingTotal > 0 && (
        <SectionCard title="Items Requiring Executive Approval" icon={AlertCircle}>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <PendingAlert
              count={stats?.pending_executive_approvals.leaves ?? 0}
              label="Leave Requests (Manager-filed)"
              href="/executive/leave-approvals"
            />
            <PendingAlert
              count={stats?.pending_executive_approvals.high_value_loans ?? 0}
              label="High-Value Loan Applications"
              href="/hr/loans"
            />
          </div>
        </SectionCard>
      )}

      {/* Company Overview */}
      <SectionCard title="Company Overview" icon={Building}>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard
            label="Total Employees"
            value={stats?.company_overview.total_employees ?? 0}
            sub="Across all departments"
            icon={Users}
            color="blue"
            trend={stats?.key_metrics.headcount_change && stats.key_metrics.headcount_change > 0 ? 'up' : 'neutral'}
            href="/hr/employees"
          />
          <StatCard
            label="Departments"
            value={stats?.company_overview.total_departments ?? 0}
            sub="Active departments"
            icon={Building}
            color="gray"
            href="/hr/departments"
          />
          <StatCard
            label="Active Projects"
            value={stats?.company_overview.active_projects ?? 0}
            sub="Ongoing initiatives"
            icon={Briefcase}
            color="green"
          />
          <StatCard
            label="Avg Tenure"
            value={`${stats?.key_metrics.avg_tenure_years?.toFixed(1) ?? 0} yrs`}
            sub="Company average"
            icon={Calendar}
            color="indigo"
          />
        </div>
      </SectionCard>

      {/* Financial Health */}
      <SectionCard title="Financial Overview" icon={DollarSign}>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <div className="p-5 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-10 w-10 rounded-lg bg-blue-500 flex items-center justify-center">
                <DollarSign className="h-5 w-5 text-white" />
              </div>
              <span className="text-sm font-semibold text-blue-800">Current Payroll</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">
              ₱{((stats?.financial_health.current_month_payroll ?? 0) / 100).toLocaleString('en-PH', { 
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              })}
            </p>
            <p className="text-xs text-gray-600 mt-1">This month&apos;s payroll total</p>
          </div>
          <div className="p-5 rounded-xl bg-gradient-to-br from-red-50 to-red-100 border border-red-200">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-10 w-10 rounded-lg bg-red-500 flex items-center justify-center">
                <TrendingDown className="h-5 w-5 text-white" />
              </div>
              <span className="text-sm font-semibold text-red-800">Outstanding AP</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">
              {stats?.financial_health.pending_vendor_invoices ?? 0}
            </p>
            <p className="text-xs text-gray-600 mt-1">Pending vendor invoices</p>
          </div>
          <div className="p-5 rounded-xl bg-gradient-to-br from-green-50 to-green-100 border border-green-200">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-10 w-10 rounded-lg bg-green-500 flex items-center justify-center">
                <TrendingUp className="h-5 w-5 text-white" />
              </div>
              <span className="text-sm font-semibold text-green-800">Outstanding AR</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">
              {stats?.financial_health.pending_customer_invoices ?? 0}
            </p>
            <p className="text-xs text-gray-600 mt-1">Pending customer invoices</p>
          </div>
        </div>
      </SectionCard>

      {/* Key Metrics */}
      <SectionCard title="Key HR Metrics" icon={BarChart3}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="flex items-center gap-4 p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="h-12 w-12 rounded-xl bg-blue-500 flex items-center justify-center shadow-sm">
              <Users className="h-6 w-6 text-white" />
            </div>
            <div>
              <p className="text-sm text-gray-600">Headcount Change</p>
              <p className={`text-xl font-bold ${
                (stats?.key_metrics.headcount_change ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'
              }`}>
                {(stats?.key_metrics.headcount_change ?? 0) >= 0 ? '+' : ''}
                {stats?.key_metrics.headcount_change ?? 0}
              </p>
              <p className="text-xs text-gray-500">vs last month</p>
            </div>
          </div>
          <div className="flex items-center gap-4 p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="h-12 w-12 rounded-xl bg-amber-500 flex items-center justify-center shadow-sm">
              <Activity className="h-6 w-6 text-white" />
            </div>
            <div>
              <p className="text-sm text-gray-600">Attrition Rate</p>
              <p className="text-xl font-bold text-gray-900">
                {(stats?.key_metrics.attrition_rate ?? 0).toFixed(1)}%
              </p>
              <p className="text-xs text-gray-500">Year to date</p>
            </div>
          </div>
          <div className="flex items-center gap-4 p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="h-12 w-12 rounded-xl bg-purple-500 flex items-center justify-center shadow-sm">
              <Calendar className="h-6 w-6 text-white" />
            </div>
            <div>
              <p className="text-sm text-gray-600">Average Tenure</p>
              <p className="text-xl font-bold text-gray-900">
                {(stats?.key_metrics.avg_tenure_years ?? 0).toFixed(1)} years
              </p>
              <p className="text-xs text-gray-500">Company average</p>
            </div>
          </div>
        </div>
      </SectionCard>

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
            to="/payroll/runs"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
              <DollarSign className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Payroll</span>
          </Link>
          <Link 
            to="/accounting/ap/invoices"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-red-100 text-red-600 flex items-center justify-center group-hover:bg-red-500 group-hover:text-white transition-colors">
              <TrendingDown className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">AP Invoices</span>
          </Link>
          <Link 
            to="/ar/invoices"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-green-100 text-green-600 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
              <TrendingUp className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">AR Invoices</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
