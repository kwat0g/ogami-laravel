import { Link } from 'react-router-dom'
import { useMolds } from '@/hooks/useMold'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import {
  Settings,
  Package,
  ChevronRight,
} from 'lucide-react'

// Stat card using Card component
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
    <Link to={href}>
      <Card className="h-full hover:border-neutral-300 transition-colors">
        <div className="p-5 flex items-start gap-4">
          <Icon className="h-5 w-5 text-neutral-500 mt-0.5" />
          <div className="flex-1 min-w-0">
            <p className="text-2xl font-semibold text-neutral-900">{value}</p>
            <p className="text-sm text-neutral-600 mt-0.5">{label}</p>
            {sub && <p className="text-xs text-neutral-500 mt-0.5">{sub}</p>}
          </div>
          <ChevronRight className="h-4 w-4 text-neutral-300 mt-1 shrink-0" />
        </div>
      </Card>
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
      className="flex items-center gap-3 p-3 border border-neutral-200 bg-white rounded-xl hover:border-neutral-300 shadow-subtle transition-colors"
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
    <div className="space-y-5">
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900">
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
