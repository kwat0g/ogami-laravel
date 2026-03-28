import { Link } from 'react-router-dom'
import { useProductionOrders } from '@/hooks/useProduction'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import { useWorkOrders } from '@/hooks/useMaintenance'
import { useInspections, useNcrs } from '@/hooks/useQC'
import { useMolds } from '@/hooks/useMold'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
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
  ArrowUpRight,
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
            <p className={`text-2xl font-bold tracking-tight ${alert ? 'text-amber-700' : 'text-neutral-900'}`}>{value}</p>
            <p className="text-sm text-neutral-500 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
          </div>
        </div>
      </Card>
    </Link>
  )
}

function SectionTitle({ title }: { title: string }) {
  return <h2 className="text-sm font-semibold text-neutral-800 uppercase tracking-wide mb-3">{title}</h2>
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

export default function PlantManagerDashboard(): React.ReactElement {
  const { data: productionOrders, isLoading: loadingProd }  = useProductionOrders({ status: 'released', per_page: 1 })
  const { data: workOrders,       isLoading: loadingMaint } = useWorkOrders({ status: 'open', per_page: 1 })
  const { data: inspections,      isLoading: loadingQC }    = useInspections({ status: 'pending' })
  const { data: ncrs,             isLoading: loadingNcr }   = useNcrs({ status: 'open' })
  const { data: molds,            isLoading: loadingMold }  = useMolds({ per_page: 1 })

  const isLoading = loadingProd || loadingMaint || loadingQC || loadingNcr || loadingMold

  if (isLoading) return <SkeletonLoader rows={8} />

  const openNCRs = ncrs?.meta?.total ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <DashboardHeader roleLabel="Plant Manager" subtitle="Production, maintenance, quality, and mold operations" />
        <p className="text-sm text-neutral-500 mt-0.5">Overview of all plant departments — Production, QC, Maintenance, Mold, Delivery, ISO</p>
      </div>

      {/* Critical Alerts */}
      {openNCRs > 0 && (
        <Link to="/qc/ncrs">
          <Card className="border-red-200 bg-red-50/50 hover:shadow-sm transition-all">
            <div className="p-4 flex items-center gap-4">
              <AlertTriangle className="h-5 w-5 text-red-600 shrink-0" />
              <div className="flex-1">
                <p className="text-sm font-semibold text-red-800">{openNCRs} Open Non-Conformance Report{openNCRs > 1 ? 's' : ''}</p>
                <p className="text-xs text-red-600">Quality issues requiring attention</p>
              </div>
              <ChevronRight className="h-4 w-4 text-red-400" />
            </div>
          </Card>
        </Link>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard
          label="Production Orders"
          value={productionOrders?.meta?.total ?? 0}
          sub="Currently released"
          icon={Factory}
          href="/production/orders"
        />
        <KpiCard
          label="Maintenance Backlog"
          value={workOrders?.meta?.total ?? 0}
          sub="Open work orders"
          icon={Wrench}
          href="/maintenance/work-orders"
          alert={(workOrders?.meta?.total ?? 0) > 5}
        />
        <KpiCard
          label="QC Queue"
          value={inspections?.meta?.total ?? 0}
          sub="Pending inspections"
          icon={ClipboardCheck}
          href="/qc/inspections"
        />
        <KpiCard
          label="Mold Registry"
          value={molds?.meta?.total ?? 0}
          sub="Total molds"
          icon={Settings}
          href="/mold/masters"
        />
      </div>

      {/* Department Modules (2 columns) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Factory className="h-4 w-4 text-neutral-500" />
              Production & Quality
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/production/orders" label="Production Orders" icon={Factory} desc="Create, release, track work orders" />
              <ModuleLink href="/production/boms" label="Bill of Materials" icon={Package} desc="Material specifications per product" />
              <ModuleLink href="/production/delivery-schedules" label="Delivery Schedules" icon={Truck} desc="Customer delivery commitments" />
              <ModuleLink href="/qc/inspections" label="QC Inspections" icon={ClipboardCheck} desc="In-process and final inspections" />
              <ModuleLink href="/qc/ncrs" label="NCR / CAPA" icon={AlertTriangle} desc="Non-conformance and corrective actions" />
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Wrench className="h-4 w-4 text-neutral-500" />
              Maintenance & Support
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/maintenance/work-orders" label="Maintenance Work Orders" icon={Wrench} desc="Schedule and track repairs" />
              <ModuleLink href="/maintenance/equipment" label="Equipment Registry" icon={Settings} desc="All plant equipment records" />
              <ModuleLink href="/mold/masters" label="Mold Masters" icon={Settings} desc="Mold lifecycle and shot tracking" />
              <ModuleLink href="/delivery/shipments" label="Shipments" icon={Truck} desc="Outbound shipment tracking" />
              <ModuleLink href="/iso/documents" label="ISO Documents" icon={ShieldCheck} desc="Controlled documents and revisions" />
              <ModuleLink href="/iso/audits" label="ISO Audits" icon={ShieldCheck} desc="Internal audit schedule and findings" />
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Inventory Visibility */}
      <div>
        <SectionTitle title="Inventory Visibility" />
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <ModuleLink href="/inventory/stock" label="Stock Balances" icon={Package} desc="Current stock levels" />
          <ModuleLink href="/inventory/requisitions" label="Material Requisitions" icon={Package} desc="Pending MRQs" />
          <ModuleLink href="/inventory/items" label="Item Master" icon={Package} desc="All registered items" />
        </div>
      </div>
    </div>
  )
}
