import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useDeliveryReceipts, useShipments } from '@/hooks/useDelivery'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
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

export default function ImpexOfficerDashboard(): React.ReactElement {
  const { user } = useAuth()
  const { data: receiptsData,  isLoading: loadingReceipts }  = useDeliveryReceipts({ status: 'pending',   per_page: '1' })
  const { data: shipmentsData, isLoading: loadingShipments } = useShipments({ status: 'in_transit', per_page: '1' })

  const isLoading = loadingReceipts || loadingShipments

  if (isLoading) return <SkeletonLoader rows={8} />

  const pendingReceipts   = (receiptsData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0
  const activeShipments   = (shipmentsData as { meta?: { total?: number } } | undefined)?.meta?.total ?? 0

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Good {getGreeting()}, {user?.name?.split(' ')[0] ?? 'ImpEx Officer'}
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Import / Export — Shipment &amp; Delivery Documentation Management
          </p>
        </div>
      </div>

      {/* Delivery Stats */}
      <div>
        <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Delivery Overview</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label="Pending Inbound Receipts"
            value={pendingReceipts}
            sub="Awaiting confirmation"
            icon={Archive}
            href="/delivery/receipts"
            colorClass="bg-amber-500"
          />
          <StatCard
            label="Active Shipments"
            value={activeShipments}
            sub="Currently in transit"
            icon={Ship}
            href="/delivery/shipments"
            colorClass="bg-blue-500"
          />
          <StatCard
            label="Deliveries Managed"
            value="—"
            sub="View full delivery log"
            icon={MapPin}
            href="/delivery"
            colorClass="bg-green-500"
          />
        </div>
      </div>

      {/* Quick Actions */}
      <div>
        <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-4">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <QuickLink href="/delivery"                     label="Delivery"              icon={Truck}         colorClass="bg-blue-500"    />
          <QuickLink href="/delivery/receipts"            label="Inbound Receipts"      icon={Archive}       colorClass="bg-amber-500"   />
          <QuickLink href="/delivery/shipments"           label="Shipments"             icon={Ship}          colorClass="bg-indigo-500"  />
          <QuickLink href="/procurement/purchase-orders"  label="Purchase Orders"       icon={ClipboardList} colorClass="bg-teal-500"    />
          <QuickLink href="/procurement/goods-receipts"   label="Goods Receipts"        icon={Package}       colorClass="bg-purple-500"  />
          <QuickLink href="/procurement/vendors"          label="Vendors"               icon={Building2}     colorClass="bg-cyan-500"    />
          <QuickLink href="/inventory/stock"              label="Stock Levels"          icon={BarChart3}     colorClass="bg-green-600"   />
          <QuickLink href="/inventory/items"              label="Inventory Items"       icon={Package}       colorClass="bg-orange-500"  />
        </div>
      </div>
    </div>
  )
}
