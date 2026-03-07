import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { usePurchaseRequests } from '@/hooks/usePurchaseRequests'
import { usePurchaseOrders } from '@/hooks/usePurchaseOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  ShoppingCart,
  ClipboardList,
  Package,
  Truck,
  Building2,
  BarChart3,
  ChevronRight,
  PlusCircle,
  Archive,
} from 'lucide-react'

// ── Helpers ───────────────────────────────────────────────────────────────────

function getGreeting(): string {
  const h = new Date().getHours()
  if (h < 12) return 'morning'
  if (h < 17) return 'afternoon'
  return 'evening'
}

// ── Sub-components ────────────────────────────────────────────────────────────

function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
  colorClass,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  colorClass: string
}) {
  return (
    <Link
      to={href}
      className="flex items-start gap-4 p-5 bg-white rounded-xl border border-gray-200 hover:shadow-md hover:border-gray-300 transition-all duration-200"
    >
      <div className={`h-12 w-12 rounded-xl flex items-center justify-center shadow-sm ${colorClass}`}>
        <Icon className="h-6 w-6 text-white" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-3xl font-bold text-gray-900">{value}</p>
        <p className="text-sm font-medium text-gray-700 mt-0.5">{label}</p>
        {sub && <p className="text-xs text-gray-500 mt-0.5">{sub}</p>}
      </div>
      <ChevronRight className="h-5 w-5 text-gray-300 mt-1 shrink-0" />
    </Link>
  )
}

function QuickLink({
  href,
  label,
  icon: Icon,
  colorClass,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
  colorClass: string
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-4 bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all duration-200 group"
    >
      <div className={`h-10 w-10 rounded-lg flex items-center justify-center transition-colors ${colorClass}`}>
        <Icon className="h-5 w-5 text-white" />
      </div>
      <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">{label}</span>
    </Link>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function PurchasingOfficerDashboard(): React.ReactElement {
  const { user } = useAuth()
  const { data: prData,  isLoading: loadingPR } = usePurchaseRequests({ status: 'submitted', per_page: 1 })
  const { data: poData,  isLoading: loadingPO } = usePurchaseOrders({ status: 'draft',     per_page: 1 })
  const { data: sentPO,  isLoading: loadingSent } = usePurchaseOrders({ status: 'sent',   per_page: 1 })

  const isLoading = loadingPR || loadingPO || loadingSent

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingPRs = (prData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const draftPOs   = (poData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const sentPOs    = (sentPO as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Good {getGreeting()}, {user?.name?.split(' ')[0] ?? 'Purchasing Officer'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Purchasing — Materials Ordering &amp; Procurement Management
          </p>
        </div>
      </div>

      {/* Procurement Stats */}
      <div>
        <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Procurement Overview</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Pending Purchase Requests"
            value={pendingPRs}
            sub="Awaiting your review"
            icon={ClipboardList}
            href="/procurement/purchase-requests"
            colorClass="bg-amber-500"
          />
          <StatCard
            label="Draft Purchase Orders"
            value={draftPOs}
            sub="Not yet sent to vendor"
            icon={ShoppingCart}
            href="/procurement/purchase-orders"
            colorClass="bg-blue-500"
          />
          <StatCard
            label="Sent Purchase Orders"
            value={sentPOs}
            sub="Awaiting vendor fulfillment"
            icon={Truck}
            href="/procurement/purchase-orders"
            colorClass="bg-green-500"
          />
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <QuickLink href="/procurement/purchase-requests"     label="Purchase Requests"   icon={ClipboardList} colorClass="bg-amber-500"   />
          <QuickLink href="/procurement/purchase-orders"       label="Purchase Orders"     icon={ShoppingCart}  colorClass="bg-blue-500"    />
          <QuickLink href="/procurement/purchase-orders/create" label="New Purchase Order" icon={PlusCircle}    colorClass="bg-indigo-500"  />
          <QuickLink href="/procurement/goods-receipts"        label="Goods Receipts"      icon={Archive}       colorClass="bg-teal-500"    />
          <QuickLink href="/procurement/vendors"               label="Vendors"             icon={Building2}     colorClass="bg-purple-500"  />
          <QuickLink href="/inventory/items"                   label="Inventory Items"     icon={Package}       colorClass="bg-cyan-500"    />
          <QuickLink href="/inventory/stock"                   label="Stock Levels"        icon={BarChart3}     colorClass="bg-green-600"   />
          <QuickLink href="/delivery"                          label="Delivery"            icon={Truck}         colorClass="bg-orange-500"  />
        </div>
      </div>
    </div>
  )
}
