/**
 * Vice President Dashboard
 *
 * Focuses on pending approvals (loans, PRs, MRQs), financial snapshot,
 * and recent forwarded requests. The VP secures new projects and approves
 * major operational/financial requests.
 */
import { Link } from 'react-router-dom'
import { useVicePresidentDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card } from '@/components/ui/Card'
import {
  KpiCard,
  ApprovalAlert,
  SectionHeader,
  WidgetCard,
  DashboardGrid,
  QuickActions,
  ActivityFeed,
  formatPeso,
} from '@/components/dashboard/DashboardWidgets'
import {
  DollarSign,
  ClipboardList,
  Box,
  AlertCircle,
  CheckCircle,
  Wrench,
  TrendingUp,
  ChevronRight,
  FileText,
  BarChart3,
} from 'lucide-react'

function formatRelative(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60_000)
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  return `${Math.floor(hrs / 24)}d ago`
}

export default function VicePresidentDashboard() {
  const { data: stats, isLoading, error } = useVicePresidentDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const pending = stats?.pending_approvals
  const financial = stats?.financial_summary
  const recentApprovals = stats?.recent_approvals ?? []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-neutral-900">Vice President Dashboard</h1>
        <p className="text-sm text-neutral-500 mt-0.5">
          Approval queue, financial overview, and operational status
        </p>
      </div>

      {/* Error state */}
      {error && (
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
          </div>
        </Card>
      )}

      {/* Global approval banner */}
      {pending && pending.total > 0 && (
        <Link to="/approvals/pending">
          <Card className="border-amber-200 bg-amber-50 hover:shadow-sm transition-shadow">
            <div className="p-4 flex items-center gap-3">
              <AlertCircle className="h-5 w-5 text-amber-600" />
              <span className="text-sm font-medium text-amber-800">
                You have <span className="font-bold underline">{pending.total}</span> item(s) awaiting your approval.
              </span>
              <ChevronRight className="h-4 w-4 text-amber-400 ml-auto" />
            </div>
          </Card>
        </Link>
      )}

      {/* Pending Approval KPIs */}
      <div>
        <SectionHeader title="Pending Your Approval" action={{ label: 'View All', href: '/approvals/pending' }} />
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <KpiCard
            label="Loan Requests"
            value={pending?.loans ?? 0}
            icon={DollarSign}
            color={(pending?.loans ?? 0) > 0 ? 'warning' : 'default'}
            href="/approvals/pending"
          />
          <KpiCard
            label="Purchase Requests"
            value={pending?.purchase_requests ?? 0}
            icon={ClipboardList}
            color={(pending?.purchase_requests ?? 0) > 0 ? 'warning' : 'default'}
            href="/approvals/pending"
          />
          <KpiCard
            label="Material Requisitions"
            value={pending?.mrq ?? 0}
            icon={Box}
            color={(pending?.mrq ?? 0) > 0 ? 'warning' : 'default'}
            href="/approvals/pending"
          />
          <KpiCard
            label="Total Pending"
            value={pending?.total ?? 0}
            icon={AlertCircle}
            color={(pending?.total ?? 0) > 0 ? 'danger' : 'success'}
            sub={pending?.total === 0 ? 'All clear' : 'Needs attention'}
            href="/approvals/pending"
          />
        </div>
      </div>

      {/* Approval alerts (detailed) */}
      {pending && pending.total > 0 && (
        <div className="space-y-2">
          <ApprovalAlert count={pending.loans} label="Loan requests pending VP approval" href="/approvals/pending" urgency="high" />
          <ApprovalAlert count={pending.purchase_requests} label="Purchase requests awaiting VP sign-off" href="/approvals/pending" />
          <ApprovalAlert count={pending.mrq} label="Material requisitions awaiting VP approval" href="/approvals/pending" />
        </div>
      )}

      {/* Financial Snapshot */}
      <div>
        <SectionHeader title="Financial & Operations Snapshot" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <KpiCard
            label="Payroll This Month"
            value={financial ? formatPeso(financial.total_payroll_this_month) : '--'}
            icon={DollarSign}
            color="info"
            href="/payroll/runs"
          />
          <KpiCard
            label="Vendor Invoices"
            value={financial?.pending_vendor_invoices ?? 0}
            sub="Pending payment"
            icon={FileText}
            href="/accounting/ap/invoices"
          />
          <KpiCard
            label="Customer Invoices"
            value={financial?.pending_customer_invoices ?? 0}
            sub="Pending collection"
            icon={TrendingUp}
            href="/ar/invoices"
          />
          <KpiCard
            label="Production Orders"
            value={financial?.open_production_orders ?? 0}
            sub="Currently open"
            icon={Wrench}
            href="/production/orders"
          />
        </div>
      </div>

      {/* Recent Forwarded Requests */}
      <DashboardGrid>
        <WidgetCard
          title="Recently Forwarded Requests"
          action={recentApprovals.length > 0 ? { label: 'View All', href: '/approvals/pending' } : undefined}
        >
          {recentApprovals.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-8 text-center">
              <CheckCircle className="h-10 w-10 text-neutral-300 mb-3" />
              <p className="text-sm text-neutral-500 font-medium">All clear - no pending requests.</p>
              <p className="text-xs text-neutral-400 mt-1">New items will appear here when forwarded for your approval.</p>
            </div>
          ) : (
            <ActivityFeed
              items={recentApprovals.map((item, idx) => ({
                id: idx,
                label: item.reference,
                sub: `${item.type} - ${item.requestor}${item.amount != null ? ` - ${formatPeso(item.amount)}` : ''}`,
                date: formatRelative(item.submitted_at),
                status: 'pending',
                href: '/approvals/pending',
              }))}
            />
          )}
        </WidgetCard>

        {/* Quick Navigation */}
        <WidgetCard title="Quick Navigation">
          <QuickActions actions={[
            { label: 'Pending Approvals', href: '/approvals/pending', icon: CheckCircle },
            { label: 'Purchase Requests', href: '/procurement/purchase-requests', icon: ClipboardList },
            { label: 'Requisitions', href: '/inventory/requisitions', icon: Box },
            { label: 'Payroll Runs', href: '/payroll/runs', icon: DollarSign },
            { label: 'Production Orders', href: '/production/orders', icon: Wrench },
            { label: 'Govt Reports', href: '/reports/government', icon: TrendingUp },
            { label: 'Executive Analytics', href: '/dashboard/executive-analytics', icon: BarChart3 },
            { label: 'Budget', href: '/budget/vs-actual', icon: FileText },
          ]} />
        </WidgetCard>
      </DashboardGrid>
    </div>
  )
}
