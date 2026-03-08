import { Link } from 'react-router-dom'
import { useMolds } from '@/hooks/useMold'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Settings,
  Package,
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

export default function MoldManagerDashboard(): React.ReactElement {
  const { data: activeMolds,  isLoading: l1 } = useMolds({ status: 'active',  per_page: 1 })
  const { data: allMolds,     isLoading: l2 } = useMolds({ per_page: 1 })

  if (l1 || l2) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-6">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">
        Mold Department
      </h1>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <StatCard
          label="Active Molds"
          value={activeMolds?.meta?.total ?? 0}
          sub="Currently in use"
          icon={Settings}
          href="/mold/masters"
        />
        <StatCard
          label="Total Molds"
          value={allMolds?.meta?.total ?? 0}
          sub="All registered molds"
          icon={Settings}
          href="/mold/masters"
        />
      </div>

      {/* Mold Modules */}
      <div>
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Mold</h2>
        <div className="grid grid-cols-2 gap-3">
          <QuickLink href="/mold/masters" label="Mold Masters" icon={Settings} />
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
