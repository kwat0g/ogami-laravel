import { Link } from 'react-router-dom'
import { useExecutiveDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { 
  Users, 
  Building,
  TrendingUp,
  TrendingDown,
  DollarSign,
  Briefcase,
  Calendar,
  Activity,
  ChevronRight,
} from 'lucide-react'

// Simple stat card - no decorative styling
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
    <div className="bg-white border border-neutral-200 rounded p-5">
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
  )

  if (href) {
    return <Link to={href} className="block">{content}</Link>
  }
  return content
}

// Alert card for pending approvals - minimal
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
      className="flex items-center gap-4 p-4 border border-amber-200 bg-amber-50 rounded"
    >
      <span className="text-lg font-semibold text-amber-700">{count}</span>
      <div className="flex-1">
        <span className="text-sm font-medium text-neutral-800 block">{label}</span>
        <span className="text-xs text-neutral-600">Click to review</span>
      </div>
      <ChevronRight className="h-4 w-4 text-neutral-400" />
    </Link>
  )
}

// Section card with header - minimal styling
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
      <div className="p-4">
        {children}
      </div>
    </div>
  )
}

export default function ExecutiveDashboard() {
  const { data: stats, isLoading } = useExecutiveDashboardStats()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const pendingTotal = stats?.pending_executive_approvals.total ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        Executive Overview
      </h1>

      {/* Pending Executive Approvals */}
      {pendingTotal > 0 && (
        <SectionCard title="Items Requiring Executive Approval">
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
      <SectionCard title="Company Overview">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard
            label="Total Employees"
            value={stats?.company_overview.total_employees ?? 0}
            sub="Across all departments"
            icon={Users}
            href="/hr/employees"
          />
          <StatCard
            label="Departments"
            value={stats?.company_overview.total_departments ?? 0}
            sub="Active departments"
            icon={Building}
            href="/hr/departments"
          />
          <StatCard
            label="Active Projects"
            value={stats?.company_overview.active_projects ?? 0}
            sub="Ongoing initiatives"
            icon={Briefcase}
          />
          <StatCard
            label="Avg Tenure"
            value={`${stats?.key_metrics.avg_tenure_years?.toFixed(1) ?? 0} yrs`}
            sub="Company average"
            icon={Calendar}
          />
        </div>
      </SectionCard>

      {/* Financial Health */}
      <SectionCard title="Financial Overview">
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <div className="p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-2 mb-2">
              <DollarSign className="h-4 w-4 text-neutral-500" />
              <span className="text-sm font-medium text-neutral-700">Current Payroll</span>
            </div>
            <p className="text-xl font-semibold text-neutral-900">
              ₱{((stats?.financial_health.current_month_payroll ?? 0) / 100).toLocaleString('en-PH', { 
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              })}
            </p>
            <p className="text-xs text-neutral-500 mt-1">This month&apos;s payroll total</p>
          </div>
          <div className="p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-2 mb-2">
              <TrendingDown className="h-4 w-4 text-neutral-500" />
              <span className="text-sm font-medium text-neutral-700">Outstanding AP</span>
            </div>
            <p className="text-xl font-semibold text-neutral-900">
              {stats?.financial_health.pending_vendor_invoices ?? 0}
            </p>
            <p className="text-xs text-neutral-500 mt-1">Pending vendor invoices</p>
          </div>
          <div className="p-4 bg-neutral-50 border border-neutral-200 rounded">
            <div className="flex items-center gap-2 mb-2">
              <TrendingUp className="h-4 w-4 text-neutral-500" />
              <span className="text-sm font-medium text-neutral-700">Outstanding AR</span>
            </div>
            <p className="text-xl font-semibold text-neutral-900">
              {stats?.financial_health.pending_customer_invoices ?? 0}
            </p>
            <p className="text-xs text-neutral-500 mt-1">Pending customer invoices</p>
          </div>
        </div>
      </SectionCard>

      {/* Key Metrics */}
      <SectionCard title="Key HR Metrics">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="flex items-center gap-4 p-4 bg-neutral-50 border border-neutral-200 rounded">
            <Users className="h-5 w-5 text-neutral-500" />
            <div>
              <p className="text-sm text-neutral-600">Headcount Change</p>
              <p className="text-lg font-semibold text-neutral-900">
                {(stats?.key_metrics.headcount_change ?? 0) >= 0 ? '+' : ''}
                {stats?.key_metrics.headcount_change ?? 0}
              </p>
              <p className="text-xs text-neutral-500">vs last month</p>
            </div>
          </div>
          <div className="flex items-center gap-4 p-4 bg-neutral-50 border border-neutral-200 rounded">
            <Activity className="h-5 w-5 text-neutral-500" />
            <div>
              <p className="text-sm text-neutral-600">Attrition Rate</p>
              <p className="text-lg font-semibold text-neutral-900">
                {(stats?.key_metrics.attrition_rate ?? 0).toFixed(1)}%
              </p>
              <p className="text-xs text-neutral-500">Year to date</p>
            </div>
          </div>
          <div className="flex items-center gap-4 p-4 bg-neutral-50 border border-neutral-200 rounded">
            <Calendar className="h-5 w-5 text-neutral-500" />
            <div>
              <p className="text-sm text-neutral-600">Average Tenure</p>
              <p className="text-lg font-semibold text-neutral-900">
                {(stats?.key_metrics.avg_tenure_years ?? 0).toFixed(1)} years
              </p>
              <p className="text-xs text-neutral-500">Company average</p>
            </div>
          </div>
        </div>
      </SectionCard>

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/hr/employees"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <Users className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">All Employees</span>
          </Link>
          <Link 
            to="/payroll/runs"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <DollarSign className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">Payroll</span>
          </Link>
          <Link 
            to="/accounting/ap/invoices"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <TrendingDown className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">AP Invoices</span>
          </Link>
          <Link 
            to="/ar/invoices"
            className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
          >
            <TrendingUp className="h-4 w-4 text-neutral-500" />
            <span className="text-sm font-medium text-neutral-700">AR Invoices</span>
          </Link>
        </div>
      </div>
    </div>
  )
}
