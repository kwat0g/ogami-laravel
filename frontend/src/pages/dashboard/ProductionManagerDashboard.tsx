import { Link } from 'react-router-dom'
import { useProductionOrders } from '@/hooks/useProduction'
import { useBoms } from '@/hooks/useProduction'
import { useDeliverySchedules } from '@/hooks/useProduction'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Factory,
  Package,
  Truck,
  ChevronRight,
  ClipboardList,
} from 'lucide-react'

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
      <div className={`h-10 w-10 rounded-lg flex items-center justify-center ${colorClass}`}>
        <Icon className="h-5 w-5 text-white" />
      </div>
      <span className="text-sm font-semibold text-gray-700 group-hover:text-gray-900">{label}</span>
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
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Production</h1>
        <p className="text-sm text-gray-500 mt-1">
          {new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Released Orders"
          value={releasedOrders?.meta?.total ?? 0}
          sub="In production"
          icon={Factory}
          href="/production/orders"
          colorClass="bg-blue-500"
        />
        <StatCard
          label="Draft Orders"
          value={draftOrders?.meta?.total ?? 0}
          sub="Awaiting release"
          icon={ClipboardList}
          href="/production/orders"
          colorClass="bg-amber-500"
        />
        <StatCard
          label="Active BOMs"
          value={boms?.meta?.total ?? 0}
          sub="Bill of Materials"
          icon={Package}
          href="/production/boms"
          colorClass="bg-indigo-500"
        />
        <StatCard
          label="Delivery Schedules"
          value={schedules?.meta?.total ?? 0}
          sub="Scheduled deliveries"
          icon={Truck}
          href="/production/delivery-schedules"
          colorClass="bg-cyan-500"
        />
      </div>

      {/* Production Modules */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Production</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/production/orders"             label="Production Orders"   icon={Factory}       colorClass="bg-blue-500" />
          <QuickLink href="/production/boms"               label="Bill of Materials"   icon={Package}       colorClass="bg-indigo-500" />
          <QuickLink href="/production/delivery-schedules" label="Delivery Schedules"  icon={Truck}         colorClass="bg-cyan-500" />
        </div>
      </div>

      {/* Inventory Visibility */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Inventory Visibility</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/inventory/stock"        label="Stock Balances"        icon={Package}       colorClass="bg-teal-600" />
          <QuickLink href="/inventory/requisitions" label="Material Requisitions" icon={ClipboardList} colorClass="bg-sky-600" />
          <QuickLink href="/inventory/items"        label="Item Master"           icon={Package}       colorClass="bg-blue-600" />
        </div>
      </div>
    </div>
  )
}
