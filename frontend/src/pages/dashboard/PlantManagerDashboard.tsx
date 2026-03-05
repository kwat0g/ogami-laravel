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
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Plant Operations</h1>
        <p className="text-sm text-gray-500 mt-1">
          {new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </p>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Open Production Orders"
          value={productionOrders?.meta?.total ?? 0}
          sub="Status: released"
          icon={Factory}
          href="/production/orders"
          colorClass="bg-blue-500"
        />
        <StatCard
          label="Open Work Orders"
          value={workOrders?.meta?.total ?? 0}
          sub="Maintenance pending"
          icon={Wrench}
          href="/maintenance/work-orders"
          colorClass="bg-orange-500"
        />
        <StatCard
          label="Pending Inspections"
          value={inspections?.meta?.total ?? 0}
          sub="QC/QA queue"
          icon={ClipboardCheck}
          href="/qc/inspections"
          colorClass="bg-green-500"
        />
        <StatCard
          label="Open NCRs"
          value={ncrs?.meta?.total ?? 0}
          sub="Non-conformance reports"
          icon={AlertTriangle}
          href="/qc/ncrs"
          colorClass={ncrs?.meta?.total ? 'bg-red-500' : 'bg-gray-400'}
        />
      </div>

      {/* Quick Access */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Modules</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <QuickLink href="/production/orders"              label="Production Orders"   icon={Factory}        colorClass="bg-blue-500" />
          <QuickLink href="/production/boms"                label="Bill of Materials"   icon={Package}        colorClass="bg-indigo-500" />
          <QuickLink href="/production/delivery-schedules"  label="Delivery Schedules"  icon={Truck}          colorClass="bg-cyan-500" />
          <QuickLink href="/qc/inspections"                 label="QC Inspections"      icon={ClipboardCheck} colorClass="bg-green-500" />
          <QuickLink href="/qc/ncrs"                        label="NCR / CAPA"          icon={AlertTriangle}  colorClass="bg-red-500" />
          <QuickLink href="/maintenance/work-orders"        label="Maintenance WOs"     icon={Wrench}         colorClass="bg-orange-500" />
          <QuickLink href="/maintenance/equipment"          label="Equipment"           icon={Settings}       colorClass="bg-yellow-500" />
          <QuickLink href="/mold/masters"                   label="Mold Masters"        icon={Settings}       colorClass="bg-purple-500" />
          <QuickLink href="/delivery/shipments"             label="Shipments"           icon={Truck}          colorClass="bg-teal-500" />
          <QuickLink href="/delivery/receipts"              label="Delivery Receipts"   icon={Truck}          colorClass="bg-emerald-500" />
          <QuickLink href="/iso/documents"                  label="ISO Documents"       icon={ShieldCheck}    colorClass="bg-slate-500" />
          <QuickLink href="/iso/audits"                     label="ISO Audits"          icon={ShieldCheck}    colorClass="bg-gray-600" />
        </div>
      </div>

      {/* Inventory Overview */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Inventory Visibility</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/inventory/stock"        label="Stock Balances"      icon={Package}  colorClass="bg-teal-600" />
          <QuickLink href="/inventory/requisitions" label="Material Requisitions" icon={Package} colorClass="bg-sky-600" />
          <QuickLink href="/inventory/items"        label="Item Master"         icon={Package}  colorClass="bg-blue-600" />
        </div>
      </div>

      {/* Mold Summary */}
      <div className="bg-white rounded-xl border border-gray-200 p-5">
        <div className="flex items-center justify-between mb-2">
          <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <Settings className="h-4 w-4 text-purple-500" />
            Mold Registry
          </h2>
          <Link to="/mold/masters" className="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
            View all <ChevronRight className="h-3 w-3" />
          </Link>
        </div>
        <p className="text-3xl font-bold text-gray-900">{molds?.meta?.total ?? 0}</p>
        <p className="text-sm text-gray-500 mt-0.5">Total molds registered</p>
      </div>
    </div>
  )
}
