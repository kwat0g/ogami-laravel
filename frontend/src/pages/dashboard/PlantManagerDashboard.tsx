import { Link } from 'react-router-dom'
import { useProductionOrders } from '@/hooks/useProduction'
import { useWorkOrders } from '@/hooks/useMaintenance'
import { useInspections, useNcrs } from '@/hooks/useQC'
import { useMolds } from '@/hooks/useMold'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Factory,
  Wrench,
  ClipboardCheck,
  AlertTriangle,
  Package,
  Settings,
  Truck,
  ShieldCheck,
  ChevronRight,
} from 'lucide-react'

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
    <Link
      to={href}
      className="flex items-start gap-4 p-4 bg-white border border-neutral-200 rounded hover:border-neutral-300"
    >
      <Icon className="h-5 w-5 text-neutral-500 mt-0.5" />
      <div className="flex-1 min-w-0">
        <p className="text-2xl font-semibold text-neutral-900">{value}</p>
        <p className="text-sm text-neutral-600 mt-0.5">{label}</p>
        {sub && <p className="text-xs text-neutral-500 mt-0.5">{sub}</p>}
      </div>
      <ChevronRight className="h-4 w-4 text-neutral-300 mt-1 shrink-0" />
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
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded hover:border-neutral-300"
    >
      <Icon className="h-4 w-4 text-neutral-500" />
      <span className="text-sm font-medium text-neutral-700">{label}</span>
    </Link>
  )
}

export default function PlantManagerDashboard(): React.ReactElement {
  const { data: productionOrders, isLoading: loadingProd }  = useProductionOrders({ status: 'released', per_page: 1 })
  const { data: workOrders,       isLoading: loadingMaint } = useWorkOrders({ status: 'open', per_page: 1 })
  const { data: inspections,      isLoading: loadingQC }    = useInspections({ status: 'pending' })
  const { data: ncrs,             isLoading: loadingNcr }   = useNcrs({ status: 'open' })
  const { data: molds,            isLoading: loadingMold }  = useMolds({ per_page: 1 })

  const isLoading = loadingProd || loadingMaint || loadingQC || loadingNcr || loadingMold

  if (isLoading) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-6">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        Plant Operations
      </h1>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Open Production Orders"
          value={productionOrders?.meta?.total ?? 0}
          sub="Status: released"
          icon={Factory}
          href="/production/orders"
        />
        <StatCard
          label="Open Work Orders"
          value={workOrders?.meta?.total ?? 0}
          sub="Maintenance pending"
          icon={Wrench}
          href="/maintenance/work-orders"
        />
        <StatCard
          label="Pending Inspections"
          value={inspections?.meta?.total ?? 0}
          sub="QC/QA queue"
          icon={ClipboardCheck}
          href="/qc/inspections"
        />
        <StatCard
          label="Open NCRs"
          value={ncrs?.meta?.total ?? 0}
          sub="Non-conformance reports"
          icon={AlertTriangle}
          href="/qc/ncrs"
        />
      </div>

      {/* Quick Access */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Modules</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <QuickLink href="/production/orders"              label="Production Orders"   icon={Factory}        />
          <QuickLink href="/production/boms"                label="Bill of Materials"   icon={Package}        />
          <QuickLink href="/production/delivery-schedules"  label="Delivery Schedules"  icon={Truck}          />
          <QuickLink href="/qc/inspections"                 label="QC Inspections"      icon={ClipboardCheck} />
          <QuickLink href="/qc/ncrs"                        label="NCR / CAPA"          icon={AlertTriangle}  />
          <QuickLink href="/maintenance/work-orders"        label="Maintenance WOs"     icon={Wrench}         />
          <QuickLink href="/maintenance/equipment"          label="Equipment"           icon={Settings}       />
          <QuickLink href="/mold/masters"                   label="Mold Masters"        icon={Settings}       />
          <QuickLink href="/delivery/shipments"             label="Shipments"           icon={Truck}          />
          <QuickLink href="/delivery/receipts"              label="Delivery Receipts"   icon={Truck}          />
          <QuickLink href="/iso/documents"                  label="ISO Documents"       icon={ShieldCheck}    />
          <QuickLink href="/iso/audits"                     label="ISO Audits"          icon={ShieldCheck}    />
        </div>
      </div>

      {/* Inventory Overview */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Inventory Visibility</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/inventory/stock"        label="Stock Balances"      icon={Package}  />
          <QuickLink href="/inventory/requisitions" label="Material Requisitions" icon={Package} />
          <QuickLink href="/inventory/items"        label="Item Master"         icon={Package}  />
        </div>
      </div>

      {/* Mold Summary */}
      <div className="bg-white border border-neutral-200 rounded p-4">
        <div className="flex items-center justify-between mb-2">
          <h2 className="text-sm font-medium text-neutral-700 flex items-center gap-2">
            <Settings className="h-4 w-4 text-neutral-500" />
            Mold Registry
          </h2>
          <Link to="/mold/masters" className="text-xs text-neutral-600 hover:text-neutral-900 font-medium flex items-center gap-1">
            View all <ChevronRight className="h-3 w-3" />
          </Link>
        </div>
        <p className="text-2xl font-semibold text-neutral-900">{molds?.meta?.total ?? 0}</p>
        <p className="text-sm text-neutral-500 mt-0.5">Total molds registered</p>
      </div>
    </div>
  )
}
