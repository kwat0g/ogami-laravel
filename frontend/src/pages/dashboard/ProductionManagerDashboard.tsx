import { Link } from 'react-router-dom'
import { useProductionOrders } from '@/hooks/useProduction'
import { useBoms } from '@/hooks/useProduction'
import { useDeliverySchedules } from '@/hooks/useProduction'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  Factory,
  Package,
  Truck,
  ChevronRight,
  ClipboardList,
} from 'lucide-react'

// Stat card using Card component
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

export default function ProductionManagerDashboard(): React.ReactElement {
  const { data: releasedOrders, isLoading: l1 } = useProductionOrders({ status: 'released', per_page: 1 })
  const { data: draftOrders,    isLoading: l2 } = useProductionOrders({ status: 'draft',    per_page: 1 })
  const { data: boms,           isLoading: l3 } = useBoms({ per_page: 1 })
  const { data: schedules,      isLoading: l4 } = useDeliverySchedules({ per_page: 1 })

  if (l1 || l2 || l3 || l4) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
        Production
      </h1>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Released Orders"
          value={releasedOrders?.meta?.total ?? 0}
          sub="In production"
          icon={Factory}
          href="/production/orders"
        />
        <StatCard
          label="Draft Orders"
          value={draftOrders?.meta?.total ?? 0}
          sub="Awaiting release"
          icon={ClipboardList}
          href="/production/orders"
        />
        <StatCard
          label="Active BOMs"
          value={boms?.meta?.total ?? 0}
          sub="Bill of Materials"
          icon={Package}
          href="/production/boms"
        />
        <StatCard
          label="Delivery Schedules"
          value={schedules?.meta?.total ?? 0}
          sub="Scheduled deliveries"
          icon={Truck}
          href="/production/delivery-schedules"
        />
      </div>

      {/* Production Modules */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Production</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/production/orders"             label="Production Orders"   icon={Factory}       />
          <QuickLink href="/production/boms"               label="Bill of Materials"   icon={Package}       />
          <QuickLink href="/production/delivery-schedules" label="Delivery Schedules"  icon={Truck}         />
        </div>
      </div>

      {/* Inventory Visibility */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Inventory Visibility</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/inventory/stock"        label="Stock Balances"        icon={Package}       />
          <QuickLink href="/inventory/mrqs" label="Material Requisitions" icon={ClipboardList} />
          <QuickLink href="/inventory/items"        label="Item Master"           icon={Package}       />
        </div>
      </div>
    </div>
  )
}
