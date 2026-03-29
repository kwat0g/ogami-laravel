import { Link } from 'react-router-dom'
import { useProductionOrders, useBoms, useDeliverySchedules } from '@/hooks/useProduction'
import { useInspections } from '@/hooks/useQC'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import { MiniDonutChart } from '@/components/dashboard/DashboardWidgets'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  Factory,
  Package,
  Truck,
  ChevronRight,
  ClipboardList,
  ArrowUpRight,
  AlertCircle,
  ShieldCheck,
  Activity,
} from 'lucide-react'

function KpiCard({
  label,
  value,
  sub,
  icon: Icon,
  href,
  alert,
}: {
  label: string
  value: number | string
  sub?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  alert?: boolean
}) {
  return (
    <Link to={href}>
      <Card className={`h-full hover:shadow-md transition-all ${alert ? 'border-amber-200 bg-amber-50/30' : ''}`}>
        <div className="p-5">
          <div className="flex items-start justify-between">
            <div className={`p-2 rounded-lg ${alert ? 'bg-amber-100' : 'bg-neutral-100'}`}>
              <Icon className={`h-4 w-4 ${alert ? 'text-amber-600' : 'text-neutral-600'}`} />
            </div>
            <ArrowUpRight className="h-4 w-4 text-neutral-400" />
          </div>
          <div className="mt-3">
            <p className={`text-lg font-semibold tracking-tight ${alert ? 'text-amber-700' : 'text-neutral-900'}`}>{value}</p>
            <p className="text-sm text-neutral-500 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
          </div>
        </div>
      </Card>
    </Link>
  )
}

function ModuleLink({
  href,
  label,
  icon: Icon,
  desc,
}: {
  href: string
  label: string
  icon: React.ComponentType<{ className?: string }>
  desc?: string
}) {
  return (
    <Link
      to={href}
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-lg hover:bg-neutral-50 hover:border-neutral-300 transition-all"
    >
      <div className="p-1.5 rounded bg-neutral-100">
        <Icon className="h-4 w-4 text-neutral-600" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-neutral-700">{label}</p>
        {desc && <p className="text-xs text-neutral-400 truncate">{desc}</p>}
      </div>
      <ChevronRight className="h-4 w-4 text-neutral-300 shrink-0" />
    </Link>
  )
}

export default function ProductionManagerDashboard(): React.ReactElement {
  const { data: releasedOrders, isLoading: l1 } = useProductionOrders({ status: 'released', per_page: 1 })
  const { data: draftOrders,    isLoading: l2 } = useProductionOrders({ status: 'draft',    per_page: 1 })
  const { data: inProgressOrders, isLoading: l2b } = useProductionOrders({ status: 'in_progress', per_page: 1 })
  const { data: completedOrders,  isLoading: l2c } = useProductionOrders({ status: 'completed', per_page: 1 })
  const { data: boms,           isLoading: l3 } = useBoms({ per_page: 1 })
  const { data: schedules,      isLoading: l4 } = useDeliverySchedules({ per_page: 1 })
  const { data: inspections,    isLoading: l5 } = useInspections({ stage: 'ipqc', per_page: 1 })

  if (l1 || l2 || l2b || l2c || l3 || l4 || l5) return <SkeletonLoader rows={8} />

  const draftCount = draftOrders?.meta?.total ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <DashboardHeader roleLabel="Production Manager" subtitle="Production orders, BOMs, and delivery schedules" />
        <p className="text-sm text-neutral-500 mt-0.5">Supervise production activities, BOMs, and delivery schedules</p>
      </div>

      {/* Draft orders alert */}
      {draftCount > 0 && (
        <Link to="/production/orders">
          <Card className="border-amber-200 bg-amber-50/50 hover:shadow-sm transition-all">
            <div className="p-4 flex items-center gap-4">
              <AlertCircle className="h-5 w-5 text-amber-600 shrink-0" />
              <div className="flex-1">
                <p className="text-sm font-semibold text-amber-800">{draftCount} Draft Production Order{draftCount > 1 ? 's' : ''}</p>
                <p className="text-xs text-amber-600">Awaiting release to production floor</p>
              </div>
              <ChevronRight className="h-4 w-4 text-amber-400" />
            </div>
          </Card>
        </Link>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
        <KpiCard
          label="In Production"
          value={releasedOrders?.meta?.total ?? 0}
          sub="Released work orders"
          icon={Factory}
          href="/production/orders"
        />
        <KpiCard
          label="Draft Orders"
          value={draftCount}
          sub="Awaiting release"
          icon={ClipboardList}
          href="/production/orders"
          alert={draftCount > 0}
        />
        <KpiCard
          label="Bill of Materials"
          value={boms?.meta?.total ?? 0}
          sub="Active BOMs"
          icon={Package}
          href="/production/boms"
        />
        <KpiCard
          label="In Progress"
          value={inProgressOrders?.meta?.total ?? 0}
          sub="Active on floor"
          icon={Activity}
          href="/production/orders"
        />
        <KpiCard
          label="Completed"
          value={completedOrders?.meta?.total ?? 0}
          sub="Awaiting closure"
          icon={Package}
          href="/production/orders"
        />
        <KpiCard
          label="QC Inspections"
          value={inspections?.meta?.total ?? 0}
          sub="In-process QC"
          icon={ShieldCheck}
          href="/qc/inspections"
        />
      </div>

      {/* Production Status Distribution */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Factory className="h-4 w-4 text-neutral-500" />
              Order Status Distribution
            </div>
          </CardHeader>
          <CardBody>
            <MiniDonutChart
              data={[
                { name: 'Draft', value: draftCount, color: '#9ca3af' },
                { name: 'Released', value: releasedOrders?.meta?.total ?? 0, color: '#3b82f6' },
                { name: 'In Progress', value: inProgressOrders?.meta?.total ?? 0, color: '#f59e0b' },
                { name: 'Completed', value: completedOrders?.meta?.total ?? 0, color: '#10b981' },
              ].filter(d => d.value > 0)}
              height={180}
              centerLabel="Total"
              centerValue={(draftCount + (releasedOrders?.meta?.total ?? 0) + (inProgressOrders?.meta?.total ?? 0) + (completedOrders?.meta?.total ?? 0))}
            />
          </CardBody>
        </Card>
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Truck className="h-4 w-4 text-neutral-500" />
              Delivery Commitments
            </div>
          </CardHeader>
          <CardBody>
            <div className="flex items-center justify-center h-[180px]">
              <div className="text-center">
                <p className="text-4xl font-bold text-neutral-900">{schedules?.meta?.total ?? 0}</p>
                <p className="text-sm text-neutral-500 mt-2">Active Delivery Schedules</p>
                <Link to="/production/delivery-schedules" className="text-xs text-blue-600 hover:underline mt-1 inline-block">
                  View all schedules
                </Link>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Production + Inventory side by side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Factory className="h-4 w-4 text-neutral-500" />
              Production Modules
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/production/orders" label="Production Orders" icon={Factory} desc="Create, release, and track work orders" />
              <ModuleLink href="/production/boms" label="Bill of Materials" icon={Package} desc="Material specifications per product" />
              <ModuleLink href="/production/delivery-schedules" label="Delivery Schedules" icon={Truck} desc="Customer delivery commitments" />
              <ModuleLink href="/production/cost-analysis" label="Cost Analysis" icon={ClipboardList} desc="Production cost breakdowns" />
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Package className="h-4 w-4 text-neutral-500" />
              Inventory Visibility
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/inventory/stock" label="Stock Balances" icon={Package} desc="Current raw material levels" />
              <ModuleLink href="/inventory/requisitions" label="Material Requisitions" icon={ClipboardList} desc="Request materials for production" />
              <ModuleLink href="/inventory/items" label="Item Master" icon={Package} desc="All registered inventory items" />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
