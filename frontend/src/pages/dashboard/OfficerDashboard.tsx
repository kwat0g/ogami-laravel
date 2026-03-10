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
  href,
}: {
  label: string
  value: string | number
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href?: string
}) {
  const content = (
    <div className="bg-white border border-neutral-200 rounded p-4">
      <div className="flex items-start justify-between">
        <Icon className="h-5 w-5 text-neutral-500" />
        {href && (
          <ChevronRight className="h-4 w-4 text-neutral-400" />
        )}
      </div>
      <div className="mt-3">
        <p className="text-2xl font-semibold text-neutral-900">{value}</p>
        <p className="text-sm text-neutral-600 mt-1">{label}</p>
        {sub && <p className="text-xs text-neutral-500 mt-1">{sub}</p>}
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
    <div className="bg-white border border-neutral-200 rounded">
      <div className="px-4 py-3 border-b border-neutral-200 flex items-center justify-between">
        <div className="flex items-center gap-2">
          {Icon && <Icon className="h-4 w-4 text-neutral-500" />}
          <h2 className="text-sm font-medium text-neutral-900">{title}</h2>
        </div>
        {action && (
          <Link to={action.href} className="text-xs text-neutral-600 hover:text-neutral-900 flex items-center gap-1">
            {action.label}
            <ChevronRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="p-4">{children}</div>
    </div>
  )
}

function PendingAlert({ count, label, href }: { count: number; label: string; href: string }) {
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

function MetricRow({ label, value, href }: { label: string; value: number; href?: string }) {
  const content = (
    <div className="flex items-center justify-between py-2">
      <span className="text-sm text-neutral-600">{label}</span>
      <span className={`text-sm font-semibold ${value > 0 ? 'text-amber-600' : 'text-neutral-400'}`}>{value}</span>
    </div>
  )
  if (href && value > 0) return <Link to={href} className="block hover:bg-neutral-50 rounded px-1">{content}</Link>
  return <div className="px-1">{content}</div>
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function OfficerDashboard() {
  useAuth()
  const { data: stats, isLoading, error } = useOfficerDashboardStats()

  if (isLoading) return <SkeletonLoader rows={8} />

  const acctg    = stats?.accounting
  const proc     = stats?.procurement
  const delivery = stats?.delivery
  const payroll  = stats?.payroll

  return (
    <div className="space-y-6">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        Officer Dashboard
      </h1>

      {/* Error banner */}
      {error && (
        <div className="border border-red-200 bg-red-50 rounded p-4 flex items-center gap-3">
          <AlertCircle className="h-5 w-5 text-red-500" />
          <span className="text-sm text-red-700">Failed to load dashboard data. Please refresh.</span>
        </div>
      )}

      {/* Accounting KPIs */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Accounting</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <StatCard label="Vendor Invoices Pending" value={acctg?.pending_vendor_invoices ?? 0} icon={FileText}   href="/ap/invoices" />
          <StatCard label="Customer Invoices Pending" value={acctg?.pending_customer_invoices ?? 0} icon={FileText} href="/ar/invoices" />
          <StatCard label="Journal Entries to Post" value={acctg?.journal_entries_to_post ?? 0} icon={BookOpen}   href="/accounting/journal-entries" />
          <StatCard label="Bank Recon Due" value={acctg?.bank_recon_due ?? 0} icon={Landmark} href="/accounting/bank-reconciliation" />
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
          <div className="divide-y divide-neutral-100">
            <MetricRow label="Purchase Requests to Review" value={proc?.pending_pr_review ?? 0} href="/procurement/purchase-requests" />
            <MetricRow label="Open Purchase Orders"        value={proc?.open_pos ?? 0}           href="/procurement/purchase-orders" />
            <MetricRow label="Pending Goods Receipts"      value={proc?.pending_gr ?? 0}         href="/procurement/goods-receipts" />
          </div>
        </SectionCard>

        <SectionCard title="Delivery" icon={Truck} action={{ label: 'Delivery List', href: '/delivery' }}>
          <div className="divide-y divide-neutral-100">
            <MetricRow label="Inbound Drafts"   value={delivery?.inbound_draft ?? 0}       href="/delivery?type=inbound" />
            <MetricRow label="Outbound Drafts"  value={delivery?.outbound_draft ?? 0}      href="/delivery?type=outbound" />
            <MetricRow label="In-Transit Shipments" value={delivery?.in_transit_shipments ?? 0} href="/delivery?status=in_transit" />
          </div>
        </SectionCard>
      </div>

      {/* Payroll + Inventory side by side */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <SectionCard title="Payroll" icon={DollarSign} action={{ label: 'Payroll Runs', href: '/payroll/runs' }}>
          <div className="divide-y divide-neutral-100">
            <MetricRow label="Runs Pending Acctg Approval" value={payroll?.runs_pending_acctg_approval ?? 0} href="/payroll/runs" />
            <div className="flex items-center justify-between py-2 px-1">
              <span className="text-sm text-neutral-600">Next Pay Date</span>
              <span className="text-sm font-medium text-neutral-900">
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
              { label: 'AP Invoices',     href: '/ap/invoices',                       icon: FileText },
              { label: 'AR Invoices',     href: '/ar/invoices',                       icon: FileText },
              { label: 'Journal Entries', href: '/accounting/journal-entries',        icon: BookOpen },
              { label: 'Inventory MRQ',   href: '/inventory/mrqs',                    icon: Package },
              { label: 'PO List',         href: '/procurement/purchase-orders',       icon: ShoppingCart },
              { label: 'Delivery',        href: '/delivery',                          icon: Truck },
              { label: 'Payroll',         href: '/payroll/runs',                      icon: DollarSign },
              { label: 'Bank Recon',      href: '/accounting/bank-reconciliation',   icon: Landmark },
            ].map((link) => (
              <Link
                key={link.href}
                to={link.href}
                className="flex items-center gap-2 p-2 rounded border border-neutral-200 hover:bg-neutral-50"
              >
                <link.icon className="h-4 w-4 text-neutral-500 shrink-0" />
                <span className="text-xs text-neutral-700">{link.label}</span>
              </Link>
            ))}
          </div>
        </SectionCard>
      </div>

      {/* Next pay date reminder */}
      {payroll?.next_pay_date && (
        <div className="border border-green-200 bg-green-50 rounded p-4 flex items-center gap-3">
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
