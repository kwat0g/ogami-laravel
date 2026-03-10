import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useVicePresidentDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  CheckCircle,
  ChevronRight,
  DollarSign,
  FileText,
  ClipboardList,
  Wrench,
  TrendingUp,
  AlertCircle,
  Clock,
  Box,
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
  if (href) return <Link to={href} className="block">{content}</Link>
  return content
}

// Section card using Card component
function SectionCard({
  title,
  icon: Icon,
  children,
  action,
}: {
  title: string
  icon?: React.ComponentType<{ className?: string }>
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
        <div className="flex items-center gap-2">
          {Icon && <Icon className="h-4 w-4 text-neutral-500" />}
          {title}
        </div>
      </CardHeader>
      <CardBody>{children}</CardBody>
    </Card>
  )
}

// Pending alert using Card component
function PendingAlert({ count, label, href }: { count: number; label: string; href: string }) {
  if (count === 0) return null
  return (
    <Link to={href}>
      <Card className="border-amber-200 bg-amber-50 hover:border-amber-300 transition-colors">
        <div className="p-4 flex items-center gap-4">
          <span className="text-lg font-semibold text-amber-700">{count}</span>
          <div className="flex-1">
            <span className="text-sm font-medium text-neutral-800 block">{label}</span>
            <span className="text-xs text-neutral-600">Click to review &amp; approve</span>
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        </div>
      </Card>
    </Link>
  )
}

function formatCurrency(centavos: number): string {
  return '₱' + (centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function VicePresidentDashboard() {
  useAuth()
  const { data: stats, isLoading, error } = useVicePresidentDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const pending = stats?.pending_approvals
  const financial = stats?.financial_summary
  const recentApprovals = stats?.recent_approvals ?? []

  return (
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        Vice President
      </h1>

      {/* Pending Approvals Alert Bar */}
      {error ? (
        <Card className="border-red-200">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-red-500" />
            <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
          </div>
        </Card>
      ) : pending && pending.total > 0 ? (
        <Card className="border-amber-200 bg-amber-50">
          <div className="p-4 flex items-center gap-3">
            <AlertCircle className="h-5 w-5 text-amber-600" />
            <span className="text-sm font-medium text-amber-800">
              You have <span className="underline">{pending.total}</span> item(s) awaiting your approval.
            </span>
            <Link to="/approvals/pending" className="ml-auto text-xs font-medium text-neutral-700 hover:text-neutral-900 flex items-center gap-1">
              View All <ChevronRight className="h-3 w-3" />
            </Link>
          </div>
        </Card>
      ) : null}

      {/* Stat Cards — Pending Approvals */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Pending Your Approval</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Loan Requests" value={pending?.loans ?? 0} icon={DollarSign} href="/approvals/pending" />
          <StatCard label="Purchase Requests" value={pending?.purchase_requests ?? 0} icon={ClipboardList} href="/approvals/pending" />
          <StatCard label="MRQ / Requisitions" value={pending?.mrq ?? 0} icon={Box} href="/approvals/pending" />
          <StatCard label="Total Pending" value={pending?.total ?? 0} icon={AlertCircle} href="/approvals/pending" />
        </div>
      </div>

      {/* Inline pending alert links */}
      <div className="space-y-3">
        <PendingAlert count={pending?.loans ?? 0} label="Loan requests pending VP approval" href="/approvals/pending" />
        <PendingAlert count={pending?.purchase_requests ?? 0} label="Purchase requests awaiting VP sign-off" href="/approvals/pending" />
        <PendingAlert count={pending?.mrq ?? 0} label="Material Requisitions awaiting VP approval" href="/approvals/pending" />
      </div>

      {/* Financial Summary */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Financial Snapshot</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
          <StatCard
            label="Payroll This Month"
            value={financial ? formatCurrency(financial.total_payroll_this_month) : '—'}
            icon={TrendingUp}
            href="/payroll/runs"
          />
          <StatCard
            label="Open Production Orders"
            value={financial?.open_production_orders ?? 0}
            icon={Wrench}
            href="/production/orders"
          />
          <StatCard
            label="Pending Approvals"
            value={pending?.total ?? 0}
            icon={AlertCircle}
            href="/approvals/pending"
          />
        </div>
      </div>

      {/* Recent Approvals Queue */}
      <SectionCard
        title="Recent Forwarded Requests"
        icon={Clock}
        action={recentApprovals.length > 0 ? { label: 'View All', href: '/approvals/pending' } : undefined}
      >
        {recentApprovals.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <CheckCircle className="h-10 w-10 text-neutral-300 mb-3" />
            <p className="text-sm text-neutral-500 font-medium">All clear — no pending requests.</p>
            <p className="text-xs text-neutral-400 mt-1">New items will appear here when forwarded for your approval.</p>
          </div>
        ) : (
          <div className="divide-y divide-neutral-100">
            {recentApprovals.map((item, idx) => (
              <div key={idx} className="flex items-center gap-4 py-3">
                <FileText className="h-4 w-4 text-neutral-400" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-neutral-900 truncate">{item.reference}</p>
                  <p className="text-xs text-neutral-500">{item.type} · {item.requestor}</p>
                </div>
                {item.amount != null && (
                  <span className="text-sm font-medium text-neutral-700 shrink-0">{formatCurrency(item.amount)}</span>
                )}
                <span className="text-xs text-neutral-400 shrink-0">{formatRelative(item.submitted_at)}</span>
              </div>
            ))}
          </div>
        )}
      </SectionCard>

      {/* Quick Links */}
      <SectionCard title="Quick Navigation" icon={ChevronRight}>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {[
            { label: 'Pending Approvals',   href: '/approvals/pending',                 icon: CheckCircle },
            { label: 'Purchase Requests',   href: '/procurement/purchase-requests',      icon: ClipboardList },
            { label: 'Requisitions',        href: '/inventory/mrqs',                     icon: Box },
            { label: 'Payroll Runs',        href: '/payroll/runs',                       icon: DollarSign },
            { label: 'Production Orders',   href: '/production/orders',                  icon: Wrench },
            { label: 'Govt Reports',        href: '/reports/government',                 icon: TrendingUp },
          ].map((link) => (
            <Link
              key={link.href}
              to={link.href}
              className="flex items-center gap-2 p-2 rounded border border-neutral-200 hover:bg-neutral-50"
            >
              <link.icon className="h-4 w-4 text-neutral-500" />
              <span className="text-sm text-neutral-700">{link.label}</span>
            </Link>
          ))}
        </div>
      </SectionCard>
    </div>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatRelative(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60_000)
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  return `${Math.floor(hrs / 24)}d ago`
}
