import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useDeliveryReceipts, useShipments } from '@/hooks/useDelivery'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  Truck,
  Package,
  ClipboardList,
  Building2,
  Ship,
  MapPin,
  ChevronRight,
  Archive,
  BarChart3,
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

export default function ImpexOfficerDashboard(): React.ReactElement {
  useAuth()
  const { data: receiptsData,  isLoading: loadingReceipts }  = useDeliveryReceipts({ status: 'pending',   per_page: '1' })
  const { data: shipmentsData, isLoading: loadingShipments } = useShipments({ status: 'in_transit', per_page: '1' })

  const isLoading = loadingReceipts || loadingShipments

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingReceipts   = (receiptsData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const activeShipments   = (shipmentsData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0

  return (
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        Import / Export
      </h1>

      {/* Delivery Stats */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Delivery Overview</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Pending Inbound Receipts"
            value={pendingReceipts}
            sub="Awaiting confirmation"
            icon={Archive}
            href="/delivery/receipts"
          />
          <StatCard
            label="Active Shipments"
            value={activeShipments}
            sub="Currently in transit"
            icon={Ship}
            href="/delivery/shipments"
          />
          <StatCard
            label="Deliveries Managed"
            value="—"
            sub="View full delivery log"
            icon={MapPin}
            href="/delivery"
          />
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <QuickLink href="/delivery"                     label="Delivery"              icon={Truck}         />
          <QuickLink href="/delivery/receipts"            label="Inbound Receipts"      icon={Archive}       />
          <QuickLink href="/delivery/shipments"           label="Shipments"             icon={Ship}          />
          <QuickLink href="/procurement/purchase-orders"  label="Purchase Orders"       icon={ClipboardList} />
          <QuickLink href="/procurement/goods-receipts"   label="Goods Receipts"        icon={Package}       />
          <QuickLink href="/accounting/vendors"           label="Vendors"               icon={Building2}     />
          <QuickLink href="/inventory/stock"              label="Stock Levels"          icon={BarChart3}     />
          <QuickLink href="/inventory/items"              label="Inventory Items"       icon={Package}       />
        </div>
      </div>
    </div>
  )
}
