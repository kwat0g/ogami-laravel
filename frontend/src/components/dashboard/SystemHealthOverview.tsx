/**
 * System Health Overview — 12-module command center view.
 *
 * Shows a single-glance "module pulse" for every module in the ERP system.
 * Designed for the Executive Dashboard to demonstrate full system scope.
 */
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { Link } from 'react-router-dom'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  Users, Banknote, BookOpen, ShoppingCart, Package, Factory,
  ShieldCheck, Wrench, Truck, Handshake, Wallet, Landmark,
} from 'lucide-react'

interface ModuleMetric {
  label: string
  value: string | number
}

interface ModulePulse {
  module: string
  icon: string
  color: string
  metrics: ModuleMetric[]
  href: string
}

const ICON_MAP: Record<string, React.ComponentType<{ className?: string }>> = {
  users: Users,
  banknote: Banknote,
  'book-open': BookOpen,
  'shopping-cart': ShoppingCart,
  'package': Package,
  factory: Factory,
  'shield-check': ShieldCheck,
  wrench: Wrench,
  truck: Truck,
  handshake: Handshake,
  wallet: Wallet,
  landmark: Landmark,
}

function useSystemHealth() {
  return useQuery({
    queryKey: ['system-health'],
    queryFn: async () => {
      const res = await api.get<{ data: ModulePulse[] }>('/dashboard/system-health')
      return res.data.data
    },
    staleTime: 60_000,
    refetchInterval: 120_000,
  })
}

export default function SystemHealthOverview() {
  const { data: modules, isLoading, isError } = useSystemHealth()

  if (isLoading) return <SkeletonLoader rows={6} />
  if (isError || !modules) return null

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-bold text-neutral-900 dark:text-white">System Overview</h2>
          <p className="text-xs text-neutral-500">Real-time health across all 12 modules</p>
        </div>
        <span className="inline-flex items-center gap-1.5 text-[10px] font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full">
          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
          Live
        </span>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        {modules.map((mod) => {
          const IconComponent = ICON_MAP[mod.icon] ?? Package
          return (
            <Link key={mod.module} to={mod.href} className="block group">
              <Card className="h-full hover:shadow-md hover:border-neutral-300 dark:hover:border-neutral-600 transition-all duration-200">
                <div className="p-3.5">
                  {/* Header */}
                  <div className="flex items-center gap-2 mb-2.5">
                    <div
                      className="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                      style={{ backgroundColor: mod.color + '15' }}
                    >
                      <IconComponent
                        className="w-3.5 h-3.5"
                        style={{ color: mod.color }}
                      />
                    </div>
                    <h3 className="text-xs font-semibold text-neutral-800 dark:text-neutral-200 group-hover:text-neutral-950 dark:group-hover:text-white truncate">
                      {mod.module}
                    </h3>
                  </div>

                  {/* Metrics */}
                  <div className="space-y-1">
                    {mod.metrics.map((metric, idx) => (
                      <div key={idx} className="flex items-center justify-between">
                        <span className="text-[10px] text-neutral-500 dark:text-neutral-400 truncate">
                          {metric.label}
                        </span>
                        <span className="text-xs font-semibold text-neutral-900 dark:text-neutral-100 tabular-nums ml-2">
                          {metric.value}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </Card>
            </Link>
          )
        })}
      </div>
    </div>
  )
}
