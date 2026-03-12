import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAccountingDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  FileText,
  ChevronRight,
  DollarSign,
  ShoppingCart,
  BookOpen,
  Landmark,
  AlertCircle,
  CalendarCheck,
  TrendingUp,
  TrendingDown,
  Users,
  CreditCard,
  ArrowUpRight,
  BarChart3,
  Wallet,
} from 'lucide-react'

// ── Reusable components ───────────────────────────────────────────────────────

function KpiCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
  trend,
}: {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href?: string
  trend?: 'up' | 'down' | 'neutral'
}) {
  const content = (
    <Card className="h-full hover:shadow-md transition-shadow">
      <div className="p-5">
        <div className="flex items-start justify-between">
          <div className="p-2 rounded-lg bg-neutral-100">
            <Icon className="h-4 w-4 text-neutral-600" />
          </div>
          {trend === 'up' && <TrendingUp className="h-4 w-4 text-green-500" />}
          {trend === 'down' && <TrendingDown className="h-4 w-4 text-red-500" />}
          {href && !trend && <ArrowUpRight className="h-4 w-4 text-neutral-400" />}
        </div>
        <div className="mt-3">
          <p className="text-2xl font-bold text-neutral-900 tracking-tight">{value}</p>
          <p className="text-sm text-neutral-500 mt-0.5">{label}</p>
          {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
        </div>
      </div>
    </Card>
  )
  if (href) return <Link to={href} className="block">{content}</Link>
  return content
}

function SectionTitle({ title, action }: { title: string; action?: { label: string; href: string } }) {
  return (
    <div className="flex items-center justify-between mb-3">
      <h2 className="text-sm font-semibold text-neutral-800 uppercase tracking-wide">{title}</h2>
      {action && (
        <Link to={action.href} className="text-xs text-neutral-500 hover:text-neutral-800 flex items-center gap-1">
          {action.label} <ChevronRight className="h-3 w-3" />
        </Link>
      )}
    </div>
  )
}

function ActionItem({ count, label, href, urgent }: { count: number; label: string; href: string; urgent?: boolean }) {
  if (count === 0) return null
  return (
    <Link to={href}>
      <Card className={`${urgent ? 'border-amber-200 bg-amber-50/50' : 'border-neutral-200'} hover:shadow-sm transition-all`}>
        <div className="p-4 flex items-center gap-4">
          <div className={`text-xl font-bold ${urgent ? 'text-amber-600' : 'text-neutral-800'}`}>{count}</div>
          <div className="flex-1">
            <p className="text-sm font-medium text-neutral-800">{label}</p>
            <p className="text-xs text-neutral-500">Click to review</p>
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        </div>
      </Card>
    </Link>
  )
}

function AgingBar({ label, value, total, color }: { label: string; value: number; total: number; color: string }) {
  const pct = total > 0 ? Math.min((value / total) * 100, 100) : 0
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between text-xs">
        <span className="text-neutral-600">{label}</span>
        <span className="font-semibold text-neutral-800">{value}</span>
      </div>
      <div className="h-2 bg-neutral-100 rounded-full overflow-hidden">
        <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function OfficerDashboard() {
  useAuth()
  const { data: stats, isLoading, error } = useAccountingDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const p    = stats?.pending_approvals
  const fin  = stats?.financial_summary
  const an   = stats?.analytics
  const fp   = stats?.current_fiscal_period

  const totalApAging = (an?.ap_aging?.current ?? 0) + (an?.ap_aging?.['1_30_days'] ?? 0) +
                       (an?.ap_aging?.['31_60_days'] ?? 0) + (an?.ap_aging?.over_60_days ?? 0)
  const totalArAging = (an?.ar_aging?.current ?? 0) + (an?.ar_aging?.['1_30_days'] ?? 0) +
                       (an?.ar_aging?.['31_60_days'] ?? 0) + (an?.ar_aging?.over_60_days ?? 0)

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-neutral-900">Accounting Dashboard</h1>
          {fp && (
            <p className="text-sm text-neutral-500 mt-0.5">
              Fiscal Period: <span className="font-medium text-neutral-700">{fp.name}</span>
              {' '}({fp.date_from} — {fp.date_to})
            </p>
          )}
        </div>
      </div>

      {error && (
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
          </div>
        </Card>
      )}

      {/* Action Items — what needs attention NOW */}
      {(p?.total ?? 0) > 0 && (
        <div>
          <SectionTitle title="Requires Your Action" />
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <ActionItem count={p?.journal_entries ?? 0} label="Journal Entries to Post" href="/accounting/journal-entries" urgent />
            <ActionItem count={p?.vendor_invoices ?? 0} label="AP Invoices to Approve" href="/accounting/ap/invoices" urgent />
            <ActionItem count={p?.loans_for_accounting ?? 0} label="Loans Pending Review" href="/accounting/loans" urgent />
            <ActionItem count={p?.payroll_for_review ?? 0} label="Payroll Runs to Approve" href="/payroll/runs" urgent />
          </div>
        </div>
      )}

      {/* Financial KPIs */}
      <div>
        <SectionTitle title="Financial Overview" />
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard
            label="Pending AP Invoices"
            value={fin?.pending_vendor_invoices ?? 0}
            icon={FileText}
            href="/accounting/ap/invoices"
          />
          <KpiCard
            label="Pending AR Invoices"
            value={fin?.pending_customer_invoices ?? 0}
            icon={CreditCard}
            href="/ar/invoices"
          />
          <KpiCard
            label="Unreconciled Banks"
            value={fin?.unreconciled_bank_accounts ?? 0}
            icon={Landmark}
            href="/banking/reconciliations"
          />
          <KpiCard
            label="Cash Position"
            value={an?.cash_position?.total_balance != null
              ? `₱${(an.cash_position.total_balance / 100).toLocaleString('en-PH', { minimumFractionDigits: 0 })}`
              : '—'}
            sub={`${an?.cash_position?.account_count ?? 0} bank account(s)`}
            icon={Wallet}
            href="/banking/accounts"
          />
        </div>
      </div>

      {/* AP & AR Aging side by side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader action={
            <Link to="/accounting/ap/aging-report" className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 flex items-center gap-1">
              Full Report <ChevronRight className="h-3 w-3" />
            </Link>
          }>
            <div className="flex items-center gap-2">
              <TrendingDown className="h-4 w-4 text-red-500" />
              AP Aging Summary
            </div>
          </CardHeader>
          <CardBody>
            {an?.ap_aging ? (
              <div className="space-y-3">
                <AgingBar label="Current" value={an.ap_aging.current} total={totalApAging} color="bg-green-400" />
                <AgingBar label="1–30 days" value={an.ap_aging['1_30_days']} total={totalApAging} color="bg-yellow-400" />
                <AgingBar label="31–60 days" value={an.ap_aging['31_60_days']} total={totalApAging} color="bg-orange-400" />
                <AgingBar label="Over 60 days" value={an.ap_aging.over_60_days} total={totalApAging} color="bg-red-500" />
              </div>
            ) : (
              <p className="text-sm text-neutral-400 text-center py-6">No AP aging data available</p>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader action={
            <Link to="/ar/aging-report" className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 flex items-center gap-1">
              Full Report <ChevronRight className="h-3 w-3" />
            </Link>
          }>
            <div className="flex items-center gap-2">
              <TrendingUp className="h-4 w-4 text-green-500" />
              AR Aging Summary
            </div>
          </CardHeader>
          <CardBody>
            {an?.ar_aging ? (
              <div className="space-y-3">
                <AgingBar label="Current" value={an.ar_aging.current} total={totalArAging} color="bg-green-400" />
                <AgingBar label="1–30 days" value={an.ar_aging['1_30_days']} total={totalArAging} color="bg-yellow-400" />
                <AgingBar label="31–60 days" value={an.ar_aging['31_60_days']} total={totalArAging} color="bg-orange-400" />
                <AgingBar label="Over 60 days" value={an.ar_aging.over_60_days} total={totalArAging} color="bg-red-500" />
              </div>
            ) : (
              <p className="text-sm text-neutral-400 text-center py-6">No AR aging data available</p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Revenue vs Expenses Trend + Top Expenses */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <BarChart3 className="h-4 w-4 text-neutral-500" />
              Revenue vs Expenses (Monthly)
            </div>
          </CardHeader>
          <CardBody>
            {an?.revenue_vs_expense && an.revenue_vs_expense.length > 0 ? (
              <div className="space-y-2">
                {an.revenue_vs_expense.slice(-6).map((m) => (
                  <div key={m.month} className="flex items-center gap-3">
                    <span className="text-xs text-neutral-500 w-16 shrink-0">{m.month}</span>
                    <div className="flex-1 flex gap-1">
                      <div className="h-4 bg-green-200 rounded-sm" style={{ width: `${Math.max(1, (m.revenue / Math.max(m.revenue, m.expenses, 1)) * 100)}%` }} title={`Revenue: ₱${(m.revenue / 100).toLocaleString()}`} />
                    </div>
                    <span className={`text-xs font-semibold ${m.net > 0 ? 'text-green-600' : 'text-red-600'}`}>
                      {m.net > 0 ? '+' : ''}₱{(m.net / 100).toLocaleString('en-PH', { maximumFractionDigits: 0 })}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-neutral-400 text-center py-6">No revenue data available yet</p>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <DollarSign className="h-4 w-4 text-neutral-500" />
              Top Expense Categories
            </div>
          </CardHeader>
          <CardBody>
            {an?.top_expense_categories && an.top_expense_categories.length > 0 ? (
              <div className="space-y-3">
                {an.top_expense_categories.slice(0, 5).map((cat, i) => {
                  const maxVal = an.top_expense_categories![0].total
                  const pct = maxVal > 0 ? (cat.total / maxVal) * 100 : 0
                  return (
                    <div key={i} className="space-y-1">
                      <div className="flex items-center justify-between text-xs">
                        <span className="text-neutral-700 font-medium">{cat.name}</span>
                        <span className="text-neutral-500">₱{(cat.total / 100).toLocaleString('en-PH', { maximumFractionDigits: 0 })}</span>
                      </div>
                      <div className="h-1.5 bg-neutral-100 rounded-full overflow-hidden">
                        <div className="h-full bg-neutral-400 rounded-full" style={{ width: `${pct}%` }} />
                      </div>
                    </div>
                  )
                })}
              </div>
            ) : (
              <p className="text-sm text-neutral-400 text-center py-6">No expense data available yet</p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Quick Navigation */}
      <div>
        <SectionTitle title="Quick Navigation" />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'Journal Entries',  href: '/accounting/journal-entries',  icon: BookOpen },
            { label: 'AP Invoices',      href: '/accounting/ap/invoices',     icon: FileText },
            { label: 'AR Invoices',      href: '/ar/invoices',                icon: CreditCard },
            { label: 'Bank Accounts',    href: '/banking/accounts',           icon: Landmark },
            { label: 'Vendors',          href: '/accounting/vendors',         icon: Users },
            { label: 'Customers',        href: '/ar/customers',               icon: Users },
            { label: 'Payroll Runs',     href: '/payroll/runs',               icon: DollarSign },
            { label: 'Purchase Orders',  href: '/procurement/purchase-orders', icon: ShoppingCart },
          ].map((link) => (
            <Link
              key={link.href}
              to={link.href}
              className="flex items-center gap-2.5 p-3 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 transition-all"
            >
              <link.icon className="h-4 w-4 text-neutral-500 shrink-0" />
              <span className="text-sm text-neutral-700 font-medium">{link.label}</span>
            </Link>
          ))}
        </div>
      </div>

      {/* Active Payroll Banner */}
      {stats?.active_payroll && (
        <Link to={`/payroll/runs/${stats.active_payroll.ulid}`}>
          <Card className="border-blue-200 bg-blue-50/50 hover:shadow-sm transition-all">
            <div className="p-4 flex items-center gap-3">
              <CalendarCheck className="h-5 w-5 text-blue-600 shrink-0" />
              <div className="flex-1">
                <p className="text-sm font-medium text-blue-900">Active Payroll Run</p>
                <p className="text-xs text-blue-700">
                  {stats.active_payroll.reference_number} — Status: {stats.active_payroll.status?.replace(/_/g, ' ')}
                </p>
              </div>
              <ChevronRight className="h-4 w-4 text-blue-400" />
            </div>
          </Card>
        </Link>
      )}
    </div>
  )
}
