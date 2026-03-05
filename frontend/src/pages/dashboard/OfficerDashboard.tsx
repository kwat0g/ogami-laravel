import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useOfficerDashboardStats } from '@/hooks/useDashboard'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  FileText,
  ChevronRight,
  DollarSign,
  Truck,
  ClipboardList,
  ShoppingCart,
  BookOpen,
  Landmark,
  AlertCircle,
  CalendarCheck,
  Package,
} from 'lucide-react'

// ── Reusable components ───────────────────────────────────────────────────────

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
        <span className="text-xs text-amber-600">Click to review</span>
      </div>
      <ChevronRight className="h-5 w-5 text-amber-600" />
    </Link>
  )
}

function MetricRow({ label, value, href }: { label: string; value: number; href?: string }) {
  const content = (
    <div className="flex items-center justify-between py-2">
      <span className="text-sm text-gray-600">{label}</span>
      <span className={`text-sm font-bold ${value > 0 ? 'text-amber-600' : 'text-gray-400'}`}>{value}</span>
    </div>
  )
  if (href && value > 0) return <Link to={href} className="block hover:bg-gray-50 rounded px-1">{content}</Link>
  return <div className="px-1">{content}</div>
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function OfficerDashboard() {
  const { user } = useAuth()
  const { data: stats, isLoading, error } = useOfficerDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const acctg    = stats?.accounting
  const proc     = stats?.procurement
  const delivery = stats?.delivery
  const payroll  = stats?.payroll

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Good {getGreeting()}, {user?.name?.split(' ')[0] ?? 'Officer'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Officer — Accounting, Procurement &amp; Delivery Review
          </p>
        </div>
        <div className="text-right text-sm text-gray-500">
          <p className="font-medium">{new Date().toLocaleDateString('en-PH', { weekday: 'long' })}</p>
          <p>{new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
        </div>
      </div>

      {/* Error banner */}
      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-red-500" />
          <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
        </div>
      )}

      {/* Accounting KPIs */}
      <div>
        <h2 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Accounting</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Vendor Invoices Pending" value={acctg?.pending_vendor_invoices ?? 0} icon={FileText}   color="amber" href="/ap/invoices" />
          <StatCard label="Customer Invoices Pending" value={acctg?.pending_customer_invoices ?? 0} icon={FileText} color="blue" href="/ar/invoices" />
          <StatCard label="Journal Entries to Post" value={acctg?.journal_entries_to_post ?? 0} icon={BookOpen}   color="purple" href="/accounting/journal-entries" />
          <StatCard label="Bank Recon Due" value={acctg?.bank_recon_due ?? 0} icon={Landmark} color={(acctg?.bank_recon_due ?? 0) > 0 ? 'red' : 'green'} href="/accounting/bank-reconciliation" />
        </div>
      </div>

      {/* Pending alerts */}
      <div className="space-y-3">
        <PendingAlert count={acctg?.journal_entries_to_post ?? 0} label="Journal entries awaiting posting" href="/accounting/journal-entries" />
        <PendingAlert count={proc?.pending_pr_review ?? 0} label="Purchase requests for your review" href="/procurement/purchase-requests" />
        <PendingAlert count={payroll?.runs_pending_acctg_approval ?? 0} label="Payroll runs pending accounting approval" href="/payroll/runs" />
      </div>

      {/* Procurement + Delivery side by side */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <SectionCard title="Procurement" icon={ShoppingCart} action={{ label: 'Purchase Requests', href: '/procurement/purchase-requests' }}>
          <div className="divide-y divide-gray-100">
            <MetricRow label="Purchase Requests to Review" value={proc?.pending_pr_review ?? 0} href="/procurement/purchase-requests" />
            <MetricRow label="Open Purchase Orders"        value={proc?.open_pos ?? 0}           href="/procurement/purchase-orders" />
            <MetricRow label="Pending Goods Receipts"      value={proc?.pending_gr ?? 0}         href="/procurement/goods-receipts" />
          </div>
        </SectionCard>

        <SectionCard title="Delivery" icon={Truck} action={{ label: 'Delivery List', href: '/delivery' }}>
          <div className="divide-y divide-gray-100">
            <MetricRow label="Inbound Drafts"   value={delivery?.inbound_draft ?? 0}       href="/delivery?type=inbound" />
            <MetricRow label="Outbound Drafts"  value={delivery?.outbound_draft ?? 0}      href="/delivery?type=outbound" />
            <MetricRow label="In-Transit Shipments" value={delivery?.in_transit_shipments ?? 0} href="/delivery?status=in_transit" />
          </div>
        </SectionCard>
      </div>

      {/* Payroll + Inventory side by side */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <SectionCard title="Payroll" icon={DollarSign} action={{ label: 'Payroll Runs', href: '/payroll/runs' }}>
          <div className="divide-y divide-gray-100">
            <MetricRow label="Runs Pending Acctg Approval" value={payroll?.runs_pending_acctg_approval ?? 0} href="/payroll/runs" />
            <div className="flex items-center justify-between py-2 px-1">
              <span className="text-sm text-gray-600">Next Pay Date</span>
              <span className="text-sm font-semibold text-gray-900">
                {payroll?.next_pay_date
                  ? new Date(payroll.next_pay_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
                  : '—'}
              </span>
            </div>
          </div>
        </SectionCard>

        <SectionCard title="Quick Navigation" icon={ClipboardList}>
          <div className="grid grid-cols-2 gap-3">
            {[
              { label: 'AP Invoices',     href: '/ap/invoices',                       icon: FileText,     color: 'text-amber-600' },
              { label: 'AR Invoices',     href: '/ar/invoices',                       icon: FileText,     color: 'text-blue-600' },
              { label: 'Journal Entries', href: '/accounting/journal-entries',        icon: BookOpen,     color: 'text-purple-600' },
              { label: 'Inventory MRQ',   href: '/inventory/mrqs',                    icon: Package,      color: 'text-indigo-600' },
              { label: 'PO List',         href: '/procurement/purchase-orders',       icon: ShoppingCart, color: 'text-green-600' },
              { label: 'Delivery',        href: '/delivery',                          icon: Truck,        color: 'text-red-600' },
              { label: 'Payroll',         href: '/payroll/runs',                      icon: DollarSign,   color: 'text-teal-600' },
              { label: 'Bank Recon',      href: '/accounting/bank-reconciliation',   icon: Landmark,     color: 'text-gray-600' },
            ].map((link) => (
              <Link
                key={link.href}
                to={link.href}
                className="flex items-center gap-2 p-2 rounded-lg border border-gray-200 hover:bg-gray-50 hover:shadow-sm transition-all duration-150"
              >
                <link.icon className={`h-4 w-4 ${link.color} shrink-0`} />
                <span className="text-xs font-medium text-gray-700">{link.label}</span>
              </Link>
            ))}
          </div>
        </SectionCard>
      </div>

      {/* Next pay date reminder */}
      {payroll?.next_pay_date && (
        <div className="rounded-xl border border-green-200 bg-green-50 p-4 flex items-center gap-3">
          <CalendarCheck className="h-5 w-5 text-green-600 shrink-0" />
          <span className="text-sm text-green-800">
            Next payroll date:{' '}
            <span className="font-semibold">
              {new Date(payroll.next_pay_date).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
            </span>
          </span>
        </div>
      )}
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
