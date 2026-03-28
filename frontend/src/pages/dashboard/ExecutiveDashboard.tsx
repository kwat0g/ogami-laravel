/**
 * Executive Dashboard (Chairman / President)
 *
 * High-level company overview with financial health, workforce metrics,
 * and operational KPIs. Uses Recharts for data visualization.
 */
import { useExecutiveDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card } from '@/components/ui/Card'
import {
  KpiCard,
  ApprovalAlert,
  SectionHeader,
  MiniBarChart,
  MiniDonutChart,
  WidgetCard,
  DashboardGrid,
  QuickActions,
  formatPeso,
} from '@/components/dashboard/DashboardWidgets'
import SystemHealthOverview from '@/components/dashboard/SystemHealthOverview'
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend,
} from 'recharts'
import {
  Users,
  Building,
  TrendingUp,
  TrendingDown,
  DollarSign,
  Calendar,
  Activity,
  Briefcase,
  FileText,
  AlertCircle,
  BarChart3,
} from 'lucide-react'

const DEPT_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16']

export default function ExecutiveDashboard() {
  const { data: stats, isLoading, error } = useExecutiveDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  if (error) {
    return (
      <div className="p-6">
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load executive dashboard. Please refresh.</span>
          </div>
        </Card>
      </div>
    )
  }

  const pendingTotal = stats?.pending_executive_approvals.total ?? 0
  const analytics = stats?.analytics
  const financialRatios = analytics?.financial_ratios

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-neutral-900">Executive Overview</h1>
        <p className="text-sm text-neutral-500 mt-0.5">
          Company-wide performance, financial health, and workforce metrics
        </p>
      </div>

      {/* System Health Overview — 12-module command center */}
      <SystemHealthOverview />

      {/* Pending Executive Approvals */}
      {pendingTotal > 0 && (
        <div className="space-y-2">
          <ApprovalAlert
            count={stats?.pending_executive_approvals.leaves ?? 0}
            label="Leave Requests (Manager-filed) pending executive approval"
            href="/executive/leave-approvals"
            urgency="high"
          />
          <ApprovalAlert
            count={stats?.pending_executive_approvals.high_value_loans ?? 0}
            label="High-value loan applications pending executive approval"
            href="/hr/loans"
            urgency="high"
          />
        </div>
      )}

      {/* Top-level KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <KpiCard
          label="Total Employees"
          value={stats?.company_overview.total_employees ?? 0}
          sub="Active headcount"
          icon={Users}
          color="info"
          href="/hr/employees"
          trend={stats?.key_metrics.headcount_change
            ? { value: stats.key_metrics.headcount_change, label: 'vs last month' }
            : undefined
          }
        />
        <KpiCard
          label="Departments"
          value={stats?.company_overview.total_departments ?? 0}
          sub="Active departments"
          icon={Building}
          href="/hr/departments"
        />
        <KpiCard
          label="Active Projects"
          value={stats?.company_overview.active_projects ?? 0}
          sub="Ongoing initiatives"
          icon={Briefcase}
          color="success"
        />
        <KpiCard
          label="Avg Tenure"
          value={`${(stats?.key_metrics.avg_tenure_years ?? 0).toFixed(1)} yrs`}
          sub="Company average"
          icon={Calendar}
        />
        <KpiCard
          label="Attrition Rate"
          value={`${(stats?.key_metrics.attrition_rate ?? 0).toFixed(1)}%`}
          sub="Year to date"
          icon={Activity}
          color={(stats?.key_metrics.attrition_rate ?? 0) > 15 ? 'danger' : 'success'}
        />
        <KpiCard
          label="Pending Approvals"
          value={pendingTotal}
          sub={pendingTotal > 0 ? 'Needs attention' : 'All clear'}
          icon={AlertCircle}
          color={pendingTotal > 0 ? 'warning' : 'success'}
          href="/executive/leave-approvals"
        />
      </div>

      {/* Financial Overview KPIs */}
      <div>
        <SectionHeader title="Financial Health" action={{ label: 'View Reports', href: '/reports/financial' }} />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <KpiCard
            label="Current Payroll"
            value={formatPeso(stats?.financial_health.current_month_payroll ?? 0)}
            sub="This month"
            icon={DollarSign}
            href="/payroll/runs"
          />
          <KpiCard
            label="Outstanding AP"
            value={stats?.financial_health.pending_vendor_invoices ?? 0}
            sub="Vendor invoices"
            icon={TrendingDown}
            color={(stats?.financial_health.pending_vendor_invoices ?? 0) > 10 ? 'warning' : 'default'}
            href="/accounting/ap/invoices"
          />
          <KpiCard
            label="Outstanding AR"
            value={stats?.financial_health.pending_customer_invoices ?? 0}
            sub="Customer invoices"
            icon={TrendingUp}
            color={(stats?.financial_health.pending_customer_invoices ?? 0) > 10 ? 'warning' : 'default'}
            href="/ar/invoices"
          />
          {financialRatios && (
            <KpiCard
              label="YTD Revenue"
              value={formatPeso(financialRatios.ytd_revenue)}
              sub={`Expenses: ${formatPeso(financialRatios.ytd_expenses)}`}
              icon={BarChart3}
              color="info"
            />
          )}
        </div>
      </div>

      {/* Charts Row */}
      <DashboardGrid>
        {/* Revenue vs Expense Trend */}
        {analytics?.revenue_expense_trend && analytics.revenue_expense_trend.length > 0 && (
          <WidgetCard title="Revenue vs Expenses (Monthly)">
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={analytics.revenue_expense_trend.slice(-6)}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis dataKey="month" fontSize={10} tick={{ fill: '#999' }} />
                <YAxis fontSize={10} tick={{ fill: '#999' }} tickFormatter={(v: number) => formatPeso(v)} />
                <Tooltip formatter={(v: number) => formatPeso(v)} />
                <Legend wrapperStyle={{ fontSize: 11 }} />
                <Bar dataKey="revenue" name="Revenue" fill="#10b981" radius={[3, 3, 0, 0]} />
                <Bar dataKey="expenses" name="Expenses" fill="#ef4444" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </WidgetCard>
        )}

        {/* Department Cost Allocation */}
        {analytics?.department_cost_allocation && analytics.department_cost_allocation.length > 0 && (
          <WidgetCard title="Department Cost Allocation">
            <MiniDonutChart
              data={analytics.department_cost_allocation.slice(0, 8).map((d, i) => ({
                name: d.department,
                value: Math.round(d.cost / 100),
                color: DEPT_COLORS[i % DEPT_COLORS.length],
              }))}
              height={220}
              centerLabel="Total Depts"
              centerValue={analytics.department_cost_allocation.length}
            />
          </WidgetCard>
        )}

        {/* Headcount by Department */}
        {analytics?.headcount_by_department && analytics.headcount_by_department.length > 0 && (
          <WidgetCard title="Headcount by Department">
            <MiniBarChart
              data={analytics.headcount_by_department.map(d => ({ name: d.name, value: d.count }))}
              color="#6366f1"
              height={220}
            />
          </WidgetCard>
        )}

        {/* Payroll by Department */}
        {analytics?.payroll_by_department && analytics.payroll_by_department.length > 0 && (
          <WidgetCard title="Payroll Cost by Department">
            <div className="space-y-3 max-h-[220px] overflow-y-auto pr-1">
              {analytics.payroll_by_department.map((row) => {
                const maxVal = analytics.payroll_by_department![0].total_payroll
                const pct = maxVal > 0 ? (row.total_payroll / maxVal) * 100 : 0
                return (
                  <div key={row.department}>
                    <div className="flex justify-between text-xs mb-1">
                      <span className="font-medium text-neutral-700 truncate">{row.department}</span>
                      <span className="text-neutral-500 whitespace-nowrap ml-2">
                        {formatPeso(row.total_payroll)} ({row.employee_count} staff)
                      </span>
                    </div>
                    <div className="h-2 bg-neutral-100 rounded-full overflow-hidden">
                      <div className="h-full bg-blue-500 rounded-full transition-all" style={{ width: `${pct}%` }} />
                    </div>
                  </div>
                )
              })}
            </div>
          </WidgetCard>
        )}
      </DashboardGrid>

      {/* Financial Ratios (if available) */}
      {financialRatios && (
        <div>
          <SectionHeader title="Financial Ratios" />
          <div className="grid grid-cols-3 gap-3">
            <Card className="p-4 text-center">
              <p className="text-2xl font-bold text-neutral-900">{financialRatios.gross_profit_margin.toFixed(1)}%</p>
              <p className="text-xs text-neutral-500 mt-1 uppercase tracking-wide">Gross Profit Margin</p>
            </Card>
            <Card className="p-4 text-center">
              <p className="text-2xl font-bold text-neutral-900">{financialRatios.current_ratio.toFixed(2)}</p>
              <p className="text-xs text-neutral-500 mt-1 uppercase tracking-wide">Current Ratio</p>
            </Card>
            <Card className="p-4 text-center">
              <p className="text-2xl font-bold text-neutral-900">{financialRatios.debt_to_equity.toFixed(2)}</p>
              <p className="text-xs text-neutral-500 mt-1 uppercase tracking-wide">Debt to Equity</p>
            </Card>
          </div>
        </div>
      )}

      {/* Quick Navigation */}
      <div>
        <SectionHeader title="Quick Navigation" />
        <QuickActions actions={[
          { label: 'All Employees', href: '/hr/employees', icon: Users },
          { label: 'Payroll', href: '/payroll/runs', icon: DollarSign },
          { label: 'AP Invoices', href: '/accounting/ap/invoices', icon: TrendingDown },
          { label: 'AR Invoices', href: '/ar/invoices', icon: TrendingUp },
          { label: 'Budget', href: '/budget/vs-actual', icon: BarChart3 },
          { label: 'Departments', href: '/hr/departments', icon: Building },
          { label: 'Production', href: '/production/orders', icon: Briefcase },
          { label: 'Reports', href: '/reports/financial', icon: FileText },
        ]} />
      </div>
    </div>
  )
}
