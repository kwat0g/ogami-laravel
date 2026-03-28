import { Link } from 'react-router-dom'
import { useInspections, useNcrs } from '@/hooks/useQC'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  ClipboardCheck,
  AlertTriangle,
  ShieldCheck,
  ChevronRight,
  Package,
  ArrowUpRight,
  BarChart3,
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

export default function QcManagerDashboard(): React.ReactElement {
  const { data: pendingInspections, isLoading: l1 } = useInspections({ status: 'pending' })
  const { data: allInspections,     isLoading: l2 } = useInspections({ per_page: 1 })
  const { data: openNcrs,           isLoading: l3 } = useNcrs({ status: 'open' })
  const { data: allNcrs,            isLoading: l4 } = useNcrs({ per_page: 1 })

  if (l1 || l2 || l3 || l4) return <SkeletonLoader rows={8} />

  const pendingCount = pendingInspections?.meta?.total ?? 0
  const openNcrCount = openNcrs?.meta?.total ?? 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <DashboardHeader roleLabel="QC Manager" subtitle="Inspections, NCRs, and quality analytics" />
        <p className="text-sm text-neutral-500 mt-0.5">Inspections, non-conformance reports, and corrective actions</p>
      </div>

      {/* Critical NCR Alert */}
      {openNcrCount > 0 && (
        <Link to="/qc/ncrs">
          <Card className="border-red-200 bg-red-50/50 hover:shadow-sm transition-all">
            <div className="p-4 flex items-center gap-4">
              <AlertTriangle className="h-5 w-5 text-red-600 shrink-0" />
              <div className="flex-1">
                <p className="text-sm font-semibold text-red-800">{openNcrCount} Open NCR{openNcrCount > 1 ? 's' : ''} Requiring Action</p>
                <p className="text-xs text-red-600">Non-conformance reports need investigation and corrective actions</p>
              </div>
              <ChevronRight className="h-4 w-4 text-red-400" />
            </div>
          </Card>
        </Link>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard
          label="Pending Inspections"
          value={pendingCount}
          sub="Awaiting QC review"
          icon={ClipboardCheck}
          href="/qc/inspections"
          alert={pendingCount > 0}
        />
        <KpiCard
          label="Total Inspections"
          value={allInspections?.meta?.total ?? 0}
          sub="All time records"
          icon={ClipboardCheck}
          href="/qc/inspections"
        />
        <KpiCard
          label="Open NCRs"
          value={openNcrCount}
          sub="Non-conformance reports"
          icon={AlertTriangle}
          href="/qc/ncrs"
          alert={openNcrCount > 0}
        />
        <KpiCard
          label="Total NCRs"
          value={allNcrs?.meta?.total ?? 0}
          sub="All records"
          icon={AlertTriangle}
          href="/qc/ncrs"
        />
      </div>

      {/* QC Modules + Inventory */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <ClipboardCheck className="h-4 w-4 text-neutral-500" />
              Quality Modules
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/qc/inspections" label="Inspections" icon={ClipboardCheck} desc="In-process and final quality inspections" />
              <ModuleLink href="/qc/ncrs" label="NCR / CAPA" icon={AlertTriangle} desc="Non-conformance and corrective actions" />
              <ModuleLink href="/qc/templates" label="QC Templates" icon={ShieldCheck} desc="Inspection checklists and templates" />
              <ModuleLink href="/qc/defect-rate" label="Defect Rate Analysis" icon={BarChart3} desc="Quality metrics and trend analysis" />
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
              <ModuleLink href="/inventory/items" label="Item Master" icon={Package} desc="Product and material specifications" />
              <ModuleLink href="/inventory/stock" label="Stock Balances" icon={Package} desc="Current raw material / FG stock" />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
