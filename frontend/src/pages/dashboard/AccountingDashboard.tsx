import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAccountingDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { 
  AlertCircle, 
  DollarSign,
  Calculator,
  Receipt,
  BookOpen,
  Wallet,
  Building,
  TrendingUp,
  TrendingDown,
  Calendar,
  BarChart3,
  PieChart,
  PiggyBank,
  ChevronRight,
  Landmark
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
  value: React.ReactNode
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  color?: 'blue' | 'amber' | 'green' | 'red' | 'gray' | 'purple' | 'indigo' | 'cyan'
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
    cyan: {
      bg: 'bg-cyan-50',
      border: 'border-cyan-100',
      iconBg: 'bg-cyan-500',
      text: 'text-cyan-700',
      subText: 'text-cyan-600',
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
  color?: 'amber' | 'blue' | 'purple' | 'cyan'
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
    purple: {
      bg: 'bg-purple-50',
      border: 'border-purple-200',
      badge: 'bg-purple-500',
      text: 'text-purple-800',
      subtext: 'text-purple-600',
    },
    cyan: {
      bg: 'bg-cyan-50',
      border: 'border-cyan-200',
      badge: 'bg-cyan-500',
      text: 'text-cyan-800',
      subtext: 'text-cyan-600',
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
          <span className="text-sm text-gray-600 w-24 truncate">{item[labelKey]}</span>
          <div className="flex-1 h-6 bg-gray-100 rounded-lg overflow-hidden">
            <div 
              className="h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-lg transition-all duration-500"
              style={{ width: `${maxValue > 0 ? (item[valueKey] / maxValue) * 100 : 0}%` }}
            />
          </div>
          <span className="text-sm font-semibold text-gray-900 w-14 text-right">
            {typeof item[valueKey] === 'number' 
              ? item[valueKey].toLocaleString('en-PH', { maximumFractionDigits: 0 })
              : item[valueKey]
            }
          </span>
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

export default function AccountingDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading } = useAccountingDashboardStats()

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
            Accounting Dashboard
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Welcome back, {user?.name?.split(' ')[0] ?? 'there'}. Here's your financial overview.
          </p>
        </div>
        <div className="text-right">
          <p className="text-xs text-gray-500">{new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Pending Approvals Section - Highlighted */}
      {pendingTotal > 0 && (
        <SectionCard title="Items Requiring Accounting Approval" icon={AlertCircle}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <PendingAlert
              count={stats?.pending_approvals.loans_for_accounting ?? 0}
              label="Loans for Disbursement"
              href="/hr/loans"
              color="amber"
            />
            <PendingAlert
              count={stats?.pending_approvals.journal_entries ?? 0}
              label="Journal Entries"
              href="/accounting/journal-entries"
              color="blue"
            />
            <PendingAlert
              count={stats?.pending_approvals.vendor_invoices ?? 0}
              label="Vendor Invoices"
              href="/accounting/ap/invoices"
              color="purple"
            />
            <PendingAlert
              count={stats?.pending_approvals.payroll_for_review ?? 0}
              label="Payroll for Review"
              href="/payroll/runs"
              color="cyan"
            />
          </div>
        </SectionCard>
      )}

      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Pending Loans"
          value={stats?.pending_approvals.loans_for_accounting ?? 0}
          sub="Ready for disbursement"
          icon={Wallet}
          color="amber"
          href="/hr/loans"
        />
        <StatCard
          label="Pending JEs"
          value={stats?.pending_approvals.journal_entries ?? 0}
          sub="Awaiting posting"
          icon={BookOpen}
          color="blue"
          href="/accounting/journal-entries"
        />
        <StatCard
          label="Pending AP"
          value={stats?.pending_approvals.vendor_invoices ?? 0}
          sub="Vendor invoices"
          icon={Receipt}
          color="purple"
          href="/accounting/ap/invoices"
        />
        <StatCard
          label="Payroll Review"
          value={stats?.pending_approvals.payroll_for_review ?? 0}
          sub="Runs awaiting review"
          icon={Calculator}
          color={stats?.pending_approvals.payroll_for_review ? 'amber' : 'green'}
          href="/payroll/runs"
        />
      </div>

      {/* Financial Analytics Section */}
      {analytics && (
        <SectionCard title="Financial Analytics" icon={BarChart3}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {/* AP Aging */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <TrendingDown className="h-4 w-4 text-red-500" />
                AP Aging Summary
              </h3>
              <div className="space-y-2">
                <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-100">
                  <span className="text-sm text-gray-700 font-medium">Current</span>
                  <span className="text-sm font-bold text-green-700">
                    ₱{(analytics.ap_aging?.current ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100">
                  <span className="text-sm text-gray-700 font-medium">1-30 Days</span>
                  <span className="text-sm font-bold text-amber-700">
                    ₱{(analytics.ap_aging?.['1_30_days'] ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-100">
                  <span className="text-sm text-gray-700 font-medium">31-60 Days</span>
                  <span className="text-sm font-bold text-orange-700">
                    ₱{(analytics.ap_aging?.['31_60_days'] ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-100">
                  <span className="text-sm text-gray-700 font-medium">Over 60 Days</span>
                  <span className="text-sm font-bold text-red-700">
                    ₱{(analytics.ap_aging?.over_60_days ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
              </div>
            </div>

            {/* AR Aging */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-green-500" />
                AR Aging Summary
              </h3>
              <div className="space-y-2">
                <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-100">
                  <span className="text-sm text-gray-700 font-medium">Current</span>
                  <span className="text-sm font-bold text-green-700">
                    ₱{(analytics.ar_aging?.current ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100">
                  <span className="text-sm text-gray-700 font-medium">1-30 Days</span>
                  <span className="text-sm font-bold text-amber-700">
                    ₱{(analytics.ar_aging?.['1_30_days'] ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-100">
                  <span className="text-sm text-gray-700 font-medium">31-60 Days</span>
                  <span className="text-sm font-bold text-orange-700">
                    ₱{(analytics.ar_aging?.['31_60_days'] ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
                <div className="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-100">
                  <span className="text-sm text-gray-700 font-medium">Over 60 Days</span>
                  <span className="text-sm font-bold text-red-700">
                    ₱{(analytics.ar_aging?.over_60_days ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                  </span>
                </div>
              </div>
            </div>

            {/* Cash Position */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <PiggyBank className="h-4 w-4 text-blue-500" />
                Cash Position
              </h3>
              <div className="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-100">
                <div className="flex items-center gap-2 mb-3">
                  <div className="h-10 w-10 rounded-lg bg-blue-500 flex items-center justify-center">
                    <Landmark className="h-5 w-5 text-white" />
                  </div>
                  <span className="text-sm font-semibold text-blue-800">Total Cash & Bank</span>
                </div>
                <p className="text-3xl font-bold text-gray-900">
                  ₱{(analytics.cash_position?.total_balance ?? 0).toLocaleString('en-PH', { maximumFractionDigits: 2 })}
                </p>
                <p className="text-sm text-gray-600 mt-2">
                  Across {analytics.cash_position?.account_count ?? 0} accounts
                </p>
              </div>
            </div>

            {/* Revenue vs Expense */}
            <div className="md:col-span-2">
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-indigo-500" />
                Revenue vs Expenses (6 months)
              </h3>
              <div className="space-y-3">
                {analytics.revenue_vs_expense?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex items-center gap-4">
                    <span className="text-sm text-gray-600 w-14 font-medium">{item.month}</span>
                    <div className="flex-1 flex items-center gap-2">
                      <div className="flex-1 h-8 bg-gray-100 rounded-lg overflow-hidden relative flex">
                        {/* Revenue bar */}
                        <div 
                          className="h-full bg-gradient-to-r from-green-500 to-green-400 rounded-l-lg transition-all duration-500"
                          style={{ 
                            width: `${Math.min(50, (item.revenue / Math.max(...analytics.revenue_vs_expense.map((d: Record<string, unknown>) => Math.max(d.revenue, d.expenses)))) * 50)}%` 
                          }}
                        />
                        <div className="flex-1" />
                        {/* Expense bar */}
                        <div 
                          className="h-full bg-gradient-to-l from-red-500 to-red-400 rounded-r-lg transition-all duration-500"
                          style={{ 
                            width: `${Math.min(50, (item.expenses / Math.max(...analytics.revenue_vs_expense.map((d: Record<string, unknown>) => Math.max(d.revenue, d.expenses)))) * 50)}%` 
                          }}
                        />
                      </div>
                    </div>
                    <div className="flex items-center gap-3 w-36 justify-end">
                      <span className="text-sm font-semibold text-green-600">
                        {item.revenue > 0 ? (item.revenue / 1000).toFixed(0) + 'k' : '0'}
                      </span>
                      <span className="text-sm font-semibold text-red-600">
                        {item.expenses > 0 ? (item.expenses / 1000).toFixed(0) + 'k' : '0'}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
              <div className="flex items-center justify-end gap-4 mt-3 text-sm">
                <span className="flex items-center gap-1 font-medium">
                  <span className="w-3 h-3 bg-green-500 rounded" /> Revenue
                </span>
                <span className="flex items-center gap-1 font-medium">
                  <span className="w-3 h-3 bg-red-500 rounded" /> Expenses
                </span>
              </div>
            </div>

            {/* Top Expense Categories */}
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <PieChart className="h-4 w-4 text-amber-500" />
                Top Expense Categories
              </h3>
              {analytics.top_expense_categories?.length > 0 ? (
                <SimpleBarChart 
                  data={analytics.top_expense_categories} 
                  valueKey="total" 
                  labelKey="name" 
                />
              ) : (
                <p className="text-sm text-gray-400 py-8 text-center bg-gray-50 rounded-lg">No expense data</p>
              )}
            </div>

            {/* Liabilities Trend */}
            <div className="md:col-span-2">
              <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-red-500" />
                Liabilities Trend (6 months)
              </h3>
              <div className="h-28 flex items-end gap-3">
                {analytics.liabilities_trend?.map((item: Record<string, unknown>, idx: number) => (
                  <div key={idx} className="flex-1 flex flex-col items-center gap-2">
                    <div 
                      className="w-full max-w-[40px] bg-gradient-to-t from-red-500 to-red-400 rounded-t-lg transition-all duration-500"
                      style={{ 
                        height: `${Math.max(10, (item.amount / Math.max(...analytics.liabilities_trend.map((d: Record<string, unknown>) => d.amount || 1))) * 80)}px` 
                      }}
                    />
                    <span className="text-[10px] text-gray-500 font-medium">{item.month.split(' ')[0]}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </SectionCard>
      )}

      {/* Financial Summary */}
      <SectionCard title="Financial Summary" icon={DollarSign}>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="p-4 rounded-xl bg-blue-50 border border-blue-100">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-blue-500 flex items-center justify-center">
                <Receipt className="h-4 w-4 text-white" />
              </div>
              <span className="text-xs font-semibold text-blue-700">Pending AP</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">
              {stats?.financial_summary.pending_vendor_invoices ?? 0}
            </p>
            <p className="text-xs text-gray-600 mt-1">Vendor invoices</p>
          </div>
          <div className="p-4 rounded-xl bg-green-50 border border-green-100">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-green-500 flex items-center justify-center">
                <DollarSign className="h-4 w-4 text-white" />
              </div>
              <span className="text-xs font-semibold text-green-700">Pending AR</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">
              {stats?.financial_summary.pending_customer_invoices ?? 0}
            </p>
            <p className="text-xs text-gray-600 mt-1">Customer invoices</p>
          </div>
          <div className="p-4 rounded-xl bg-purple-50 border border-purple-100">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-purple-500 flex items-center justify-center">
                <Building className="h-4 w-4 text-white" />
              </div>
              <span className="text-xs font-semibold text-purple-700">Unreconciled</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">
              {stats?.financial_summary.unreconciled_bank_accounts ?? 0}
            </p>
            <p className="text-xs text-gray-600 mt-1">Bank accounts</p>
          </div>
          <div className="p-4 rounded-xl bg-gray-50 border border-gray-200">
            <div className="flex items-center gap-2 mb-3">
              <div className="h-8 w-8 rounded-lg bg-gray-500 flex items-center justify-center">
                <Calendar className="h-4 w-4 text-white" />
              </div>
              <span className="text-xs font-semibold text-gray-700">Fiscal Period</span>
            </div>
            <p className="text-lg font-bold text-gray-900">
              {stats?.current_fiscal_period?.name ?? 'N/A'}
            </p>
            <p className="text-xs text-gray-600 mt-1">
              {stats?.current_fiscal_period 
                ? `${stats.current_fiscal_period.date_from} to ${stats.current_fiscal_period.date_to}`
                : 'No active period'
              }
            </p>
          </div>
        </div>
      </SectionCard>

      {/* Active Payroll Info */}
      {stats?.active_payroll && (
        <SectionCard title="Active Payroll Run" icon={Calculator} action={{ label: 'View details', href: `/payroll/runs/${stats.active_payroll.ulid}` }}>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div className="p-4 bg-gray-50 rounded-lg">
              <p className="text-xs text-gray-500 mb-1">Run ID</p>
              <p className="text-xl font-bold text-gray-900">#{stats.active_payroll.id}</p>
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
              <p className="text-xl font-bold text-gray-900">{stats.active_payroll.total_employees ?? 0}</p>
            </div>
          </div>
        </SectionCard>
      )}

      {/* Quick Links */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Quick Access</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link 
            to="/accounting/journal-entries"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors">
              <BookOpen className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Journal Entries</span>
          </Link>
          <Link 
            to="/accounting/ap/invoices"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-red-100 text-red-600 flex items-center justify-center group-hover:bg-red-500 group-hover:text-white transition-colors">
              <Receipt className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">AP Invoices</span>
          </Link>
          <Link 
            to="/payroll/runs"
            className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:border-blue-300 hover:shadow-md transition-all duration-200 group"
          >
            <div className="h-10 w-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center group-hover:bg-purple-500 group-hover:text-white transition-colors">
              <Calculator className="h-5 w-5" />
            </div>
            <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">Payroll Runs</span>
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
