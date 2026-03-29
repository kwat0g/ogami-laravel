import { Link } from 'react-router-dom'
import { useMolds } from '@/hooks/useMold'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  Settings,
  Package,
  ChevronRight,
  ArrowUpRight,
  Wrench,
  ClipboardCheck,
} from 'lucide-react'

function KpiCard({
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
      <Card className="h-full hover:shadow-md transition-all">
        <div className="p-5">
          <div className="flex items-start justify-between">
            <div className="p-2 rounded-lg bg-neutral-100">
              <Icon className="h-4 w-4 text-neutral-600" />
            </div>
            <ArrowUpRight className="h-4 w-4 text-neutral-400" />
          </div>
          <div className="mt-3">
            <p className="text-lg font-semibold tracking-tight text-neutral-900">{value}</p>
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

export default function MoldManagerDashboard(): React.ReactElement {
  const { data: activeMolds,  isLoading: l1 } = useMolds({ status: 'active',  per_page: 1 })
  const { data: allMolds,     isLoading: l2 } = useMolds({ per_page: 1 })

  if (l1 || l2) return <SkeletonLoader rows={8} />

  const activeCount = activeMolds?.meta?.total ?? 0
  const totalCount  = allMolds?.meta?.total ?? 0
  const inactiveCount = totalCount - activeCount

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Mold Department Dashboard</h1>
        <p className="text-sm text-neutral-500 mt-0.5">Mold lifecycle management, shot tracking, and maintenance</p>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <KpiCard
          label="Active Molds"
          value={activeCount}
          sub="Currently in production"
          icon={Settings}
          href="/mold/masters"
        />
        <KpiCard
          label="Inactive / Retired"
          value={inactiveCount >= 0 ? inactiveCount : 0}
          sub="Not in production"
          icon={Settings}
          href="/mold/masters"
        />
        <KpiCard
          label="Total Mold Registry"
          value={totalCount}
          sub="All registered molds"
          icon={Settings}
          href="/mold/masters"
        />
      </div>

      {/* Modules */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Settings className="h-4 w-4 text-neutral-500" />
              Mold Management
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/mold/masters" label="Mold Masters" icon={Settings} desc="Mold registry, specifications, and status" />
              <ModuleLink href="/maintenance/work-orders" label="Maintenance Work Orders" icon={Wrench} desc="Schedule mold maintenance and repairs" />
              <ModuleLink href="/qc/inspections" label="QC Inspections" icon={ClipboardCheck} desc="Inspect mold output quality" />
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Package className="h-4 w-4 text-neutral-500" />
              Related Modules
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              <ModuleLink href="/inventory/items" label="Item Master" icon={Package} desc="Products linked to molds" />
              <ModuleLink href="/inventory/stock" label="Stock Balances" icon={Package} desc="Current material stock levels" />
              <ModuleLink href="/production/orders" label="Production Orders" icon={Settings} desc="Orders using molds" />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
