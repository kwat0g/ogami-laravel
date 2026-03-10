import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { usePurchaseRequests } from '@/hooks/usePurchaseRequests'
import { usePurchaseOrders } from '@/hooks/usePurchaseOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
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

// ── Sub-components ────────────────────────────────────────────────────────────

function StatCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
}) {
  return (
    <Link to={href}>
      <Card className="h-full hover:border-neutral-300 transition-colors">
        <div className="p-5 flex items-start gap-4">
          <Icon className="h-5 w-5 text-neutral-500 mt-0.5" />
          <div className="flex-1 min-w-0">
            <p className="text-2xl font-semibold text-neutral-900">{value}</p>
            <p className="text-sm text-neutral-600 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-500 mt-0.5">{sub}</p>}
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-300 mt-1 shrink-0" />
        </div>
      </Card>
    </Link>
  )
}

function QuickLink({
  href,
  label,
  icon: Icon,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle transition-colors"
    >
      <Icon className="h-4 w-4 text-neutral-500" />
      <span className="text-sm font-medium text-neutral-700">{label}</span>
    </Link>
  )
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function PurchasingOfficerDashboard(): React.ReactElement {
  useAuth()
  const { data: prData,  isLoading: loadingPR } = usePurchaseRequests({ status: 'submitted', per_page: 1 })
  const { data: poData,  isLoading: loadingPO } = usePurchaseOrders({ status: 'draft',     per_page: 1 })
  const { data: sentPO,  isLoading: loadingSent } = usePurchaseOrders({ status: 'sent',   per_page: 1 })

  const isLoading = loadingPR || loadingPO || loadingSent

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingPRs = (prData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const draftPOs   = (poData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const sentPOs    = (sentPO as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0

  return (
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        Purchasing
      </h1>

      {/* Procurement Stats */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Procurement Overview</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Pending Purchase Requests"
            value={pendingPRs}
            sub="Awaiting your review"
            icon={ClipboardList}
            href="/procurement/purchase-requests"
          />
          <StatCard
            label="Draft Purchase Orders"
            value={draftPOs}
            sub="Not yet sent to vendor"
            icon={ShoppingCart}
            href="/procurement/purchase-orders"
          />
          <StatCard
            label="Sent Purchase Orders"
            value={sentPOs}
            sub="Awaiting vendor fulfillment"
            icon={Truck}
            href="/procurement/purchase-orders"
          />
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <QuickLink href="/procurement/purchase-requests"     label="Purchase Requests"   icon={ClipboardList} />
          <QuickLink href="/procurement/purchase-orders"       label="Purchase Orders"     icon={ShoppingCart}  />
          <QuickLink href="/procurement/purchase-orders/new"    label="New Purchase Order" icon={PlusCircle}    />
          <QuickLink href="/procurement/goods-receipts"        label="Goods Receipts"      icon={Archive}       />
          <QuickLink href="/accounting/vendors"                label="Vendors"             icon={Building2}     />
          <QuickLink href="/inventory/items"                   label="Inventory Items"     icon={Package}       />
          <QuickLink href="/inventory/stock"                   label="Stock Levels"        icon={BarChart3}     />
          <QuickLink href="/delivery"                          label="Delivery"            icon={Truck}         />
        </div>
      </div>
    </div>
  )
}
