import { Link } from 'react-router-dom'
import { useInspections, useNcrs } from '@/hooks/useQC'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  ClipboardCheck,
  AlertTriangle,
  ShieldCheck,
  ChevronRight,
  Package,
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

export default function QcManagerDashboard(): React.ReactElement {
  const { data: pendingInspections, isLoading: l1 } = useInspections({ status: 'pending' })
  const { data: allInspections,     isLoading: l2 } = useInspections({ per_page: 1 })
  const { data: openNcrs,           isLoading: l3 } = useNcrs({ status: 'open' })
  const { data: allNcrs,            isLoading: l4 } = useNcrs({ per_page: 1 })

  if (l1 || l2 || l3 || l4) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-6">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        Quality Control &amp; Assurance
      </h1>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Pending Inspections"
          value={pendingInspections?.meta?.total ?? 0}
          sub="Awaiting inspection"
          icon={ClipboardCheck}
          href="/qc/inspections"
        />
        <StatCard
          label="Total Inspections"
          value={allInspections?.meta?.total ?? 0}
          sub="All time"
          icon={ClipboardCheck}
          href="/qc/inspections"
        />
        <StatCard
          label="Open NCRs"
          value={openNcrs?.meta?.total ?? 0}
          sub="Non-conformance reports"
          icon={AlertTriangle}
          href="/qc/ncrs"
        />
        <StatCard
          label="Total NCRs"
          value={allNcrs?.meta?.total ?? 0}
          sub="All records"
          icon={AlertTriangle}
          href="/qc/ncrs"
        />
      </div>

      {/* QC Modules */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">QC / QA</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/qc/inspections" label="Inspections"    icon={ClipboardCheck} />
          <QuickLink href="/qc/ncrs"        label="NCR / CAPA"     icon={AlertTriangle}  />
          <QuickLink href="/qc/templates"   label="QC Templates"   icon={ShieldCheck}    />
        </div>
      </div>

      {/* Inventory Visibility */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Inventory Visibility</h2>
        <div className="grid grid-cols-2 gap-3">
          <QuickLink href="/inventory/items" label="Item Master"    icon={Package} />
          <QuickLink href="/inventory/stock" label="Stock Balances" icon={Package} />
        </div>
      </div>
    </div>
  )
}
