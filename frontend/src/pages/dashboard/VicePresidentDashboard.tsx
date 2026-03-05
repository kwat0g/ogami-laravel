import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useVicePresidentDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
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

// ── Reusable small components ────────────────────────────────────────────────

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
    blue:   { bg: 'bg-blue-50',   border: 'border-blue-100',   iconBg: 'bg-blue-500',   text: 'text-blue-700',   subText: 'text-blue-600' },
    amber:  { bg: 'bg-amber-50',  border: 'border-amber-100',  iconBg: 'bg-amber-500',  text: 'text-amber-700',  subText: 'text-amber-600' },
    green:  { bg: 'bg-green-50',  border: 'border-green-100',  iconBg: 'bg-green-500',  text: 'text-green-700',  subText: 'text-green-600' },
    red:    { bg: 'bg-red-50',    border: 'border-red-100',    iconBg: 'bg-red-500',    text: 'text-red-700',    subText: 'text-red-600' },
    gray:   { bg: 'bg-gray-50',   border: 'border-gray-200',   iconBg: 'bg-gray-500',   text: 'text-gray-700',   subText: 'text-gray-600' },
    purple: { bg: 'bg-purple-50', border: 'border-purple-100', iconBg: 'bg-purple-500', text: 'text-purple-700', subText: 'text-purple-600' },
    indigo: { bg: 'bg-indigo-50', border: 'border-indigo-100', iconBg: 'bg-indigo-500', text: 'text-indigo-700', subText: 'text-indigo-600' },
  }
  const c = colorMap[color]
  const content = (
    <div className={`${c.bg} border ${c.border} rounded-xl p-5 hover:shadow-md transition-all duration-200`}>
      <div className="flex items-start justify-between">
        <div className={`h-12 w-12 rounded-xl ${c.iconBg} flex items-center justify-center shadow-sm`}>
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
        <p className={`text-sm font-medium ${c.text} mt-1`}>{label}</p>
        {sub && <p className={`text-xs mt-1 ${c.subText}`}>{sub}</p>}
      </div>
    </div>
  )
  if (href) return <Link to={href} className="block">{content}</Link>
  return content
}

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
      <div className="p-6">{children}</div>
    </div>
  )
}

function PendingAlert({ count, label, href }: { count: number; label: string; href: string }) {
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
        <span className="text-xs text-amber-600">Click to review &amp; approve</span>
      </div>
      <ChevronRight className="h-5 w-5 text-amber-600" />
    </Link>
  )
}

function formatCurrency(centavos: number): string {
  return '₱' + (centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function VicePresidentDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading, error } = useVicePresidentDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const pending = stats?.pending_approvals
  const financial = stats?.financial_summary
  const recentApprovals = stats?.recent_approvals ?? []

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Good {getGreeting()}, {user?.name?.split(' ')[0] ?? 'Vice President'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Vice President — Executive Approvals &amp; Financial Oversight
          </p>
        </div>
        <div className="text-right text-sm text-gray-500">
          <p className="font-medium">{new Date().toLocaleDateString('en-PH', { weekday: 'long' })}</p>
          <p>{new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Pending Approvals Alert Bar */}
      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-red-500" />
          <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
        </div>
      ) : pending && pending.total > 0 ? (
        <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-amber-600" />
          <span className="text-sm font-semibold text-amber-800">
            You have <span className="underline">{pending.total}</span> item(s) awaiting your approval.
          </span>
          <Link to="/approvals/pending" className="ml-auto text-xs font-medium text-amber-700 hover:text-amber-900 flex items-center gap-1">
            View All <ChevronRight className="h-3 w-3" />
          </Link>
        </div>
      ) : null}

      {/* Stat Cards — Pending Approvals */}
      <div>
        <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Pending Your Approval</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Loan Requests" value={pending?.loans ?? 0} icon={DollarSign} color="amber" href="/approvals/pending" />
          <StatCard label="Purchase Requests" value={pending?.purchase_requests ?? 0} icon={ClipboardList} color="purple" href="/approvals/pending" />
          <StatCard label="MRQ / Requisitions" value={pending?.mrq ?? 0} icon={Box} color="indigo" href="/approvals/pending" />
          <StatCard label="Total Pending" value={pending?.total ?? 0} icon={AlertCircle} color={((pending?.total ?? 0) > 0) ? 'red' : 'green'} href="/approvals/pending" />
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
        <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Financial Snapshot</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
          <StatCard
            label="Payroll This Month"
            value={financial ? formatCurrency(financial.total_payroll_this_month) : '—'}
            icon={TrendingUp}
            color="green"
            href="/payroll/runs"
          />
          <StatCard
            label="Open Production Orders"
            value={financial?.open_production_orders ?? 0}
            icon={Wrench}
            color="indigo"
            href="/production/orders"
          />
          <StatCard
            label="Pending Approvals"
            value={pending?.total ?? 0}
            icon={AlertCircle}
            color={(pending?.total ?? 0) > 0 ? 'amber' : 'gray'}
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
            <CheckCircle className="h-12 w-12 text-green-300 mb-3" />
            <p className="text-sm text-gray-500 font-medium">All clear — no pending requests.</p>
            <p className="text-xs text-gray-400 mt-1">New items will appear here when forwarded for your approval.</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {recentApprovals.map((item, idx) => (
              <div key={idx} className="flex items-center gap-4 py-3">
                <div className="h-10 w-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                  <FileText className="h-5 w-5 text-indigo-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 truncate">{item.reference}</p>
                  <p className="text-xs text-gray-500">{item.type} · {item.requestor}</p>
                </div>
                {item.amount != null && (
                  <span className="text-sm font-semibold text-gray-700 shrink-0">{formatCurrency(item.amount)}</span>
                )}
                <span className="text-xs text-gray-400 shrink-0">{formatRelative(item.submitted_at)}</span>
              </div>
            ))}
          </div>
        )}
      </SectionCard>

      {/* Quick Links */}
      <SectionCard title="Quick Navigation" icon={ChevronRight}>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {[
            { label: 'Pending Approvals',   href: '/approvals/pending',                 icon: CheckCircle, color: 'text-amber-600' },
            { label: 'Purchase Requests',   href: '/procurement/purchase-requests',      icon: ClipboardList, color: 'text-purple-600' },
            { label: 'Requisitions',        href: '/inventory/requisitions',             icon: Box,         color: 'text-indigo-600' },
            { label: 'Payroll Runs',        href: '/payroll/runs',                       icon: DollarSign,  color: 'text-blue-600' },
            { label: 'Production Orders',   href: '/production/orders',                  icon: Wrench,      color: 'text-teal-600' },
            { label: 'Govt Reports',        href: '/reports/government',                 icon: TrendingUp,  color: 'text-green-600' },
          ].map((link) => (
            <Link
              key={link.href}
              to={link.href}
              className="flex items-center gap-2 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 hover:shadow-sm transition-all duration-150"
            >
              <link.icon className={`h-4 w-4 ${link.color}`} />
              <span className="text-sm font-medium text-gray-700">{link.label}</span>
            </Link>
          ))}
        </div>
      </SectionCard>
    </div>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function getGreeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'morning'
  if (h < 18) return 'afternoon'
  return 'evening'
}

function formatRelative(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60_000)
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  return `${Math.floor(hrs / 24)}d ago`
}
