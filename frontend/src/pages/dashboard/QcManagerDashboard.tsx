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

export default function QcManagerDashboard(): React.ReactElement {
  const { data: pendingInspections, isLoading: l1 } = useInspections({ status: 'pending' })
  const { data: allInspections,     isLoading: l2 } = useInspections({ per_page: 1 })
  const { data: openNcrs,           isLoading: l3 } = useNcrs({ status: 'open' })
  const { data: allNcrs,            isLoading: l4 } = useNcrs({ per_page: 1 })

  if (l1 || l2 || l3 || l4) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Quality Control &amp; Assurance</h1>
        <p className="text-sm text-gray-500 mt-1">
          {new Date().toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Pending Inspections"
          value={pendingInspections?.meta?.total ?? 0}
          sub="Awaiting inspection"
          icon={ClipboardCheck}
          href="/qc/inspections"
          colorClass="bg-green-500"
        />
        <StatCard
          label="Total Inspections"
          value={allInspections?.meta?.total ?? 0}
          sub="All time"
          icon={ClipboardCheck}
          href="/qc/inspections"
          colorClass="bg-teal-500"
        />
        <StatCard
          label="Open NCRs"
          value={openNcrs?.meta?.total ?? 0}
          sub="Non-conformance reports"
          icon={AlertTriangle}
          href="/qc/ncrs"
          colorClass={openNcrs?.meta?.total ? 'bg-red-500' : 'bg-gray-400'}
        />
        <StatCard
          label="Total NCRs"
          value={allNcrs?.meta?.total ?? 0}
          sub="All records"
          icon={AlertTriangle}
          href="/qc/ncrs"
          colorClass="bg-rose-400"
        />
      </div>

      {/* QC Modules */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">QC / QA</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <QuickLink href="/qc/inspections" label="Inspections"    icon={ClipboardCheck} colorClass="bg-green-500" />
          <QuickLink href="/qc/ncrs"        label="NCR / CAPA"     icon={AlertTriangle}  colorClass="bg-red-500" />
          <QuickLink href="/qc/templates"   label="QC Templates"   icon={ShieldCheck}    colorClass="bg-emerald-600" />
        </div>
      </div>

      {/* Inventory Visibility */}
      <div>
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Inventory Visibility</h2>
        <div className="grid grid-cols-2 gap-3">
          <QuickLink href="/inventory/items" label="Item Master"    icon={Package} colorClass="bg-blue-600" />
          <QuickLink href="/inventory/stock" label="Stock Balances" icon={Package} colorClass="bg-teal-600" />
        </div>
      </div>
    </div>
  )
}
