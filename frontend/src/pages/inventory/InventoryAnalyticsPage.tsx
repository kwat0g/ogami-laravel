import { useState } from 'react'
import { BarChart3, Package, AlertTriangle, TrendingDown } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'
import api from '@/lib/api'
import { useQuery } from '@tanstack/react-query'

const fmt = (centavos: number) => `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`

interface AbcItem { item_id: number; item_code: string; name: string; category: string; annual_usage_centavos: number; cumulative_pct: number }
interface TurnoverItem { item_id: number; item_code: string; name: string; turnover_ratio: number; avg_stock: number; cogs: number }
interface DeadStockItem { item_id: number; item_code: string; name: string; last_movement_at: string | null; days_idle: number; quantity_on_hand: number }

function useAbcAnalysis(year?: number) {
  return useQuery({
    queryKey: ['inventory-abc', year],
    queryFn: async () => {
      const { data } = await api.get<{ data: AbcItem[] }>('/inventory/analytics/abc', { params: { year } })
      return data.data
    },
  })
}

function useTurnoverAnalysis(year?: number) {
  return useQuery({
    queryKey: ['inventory-turnover', year],
    queryFn: async () => {
      const { data } = await api.get<{ data: TurnoverItem[] }>('/inventory/analytics/turnover', { params: { year } })
      return data.data
    },
  })
}

function useDeadStock(days = 90) {
  return useQuery({
    queryKey: ['inventory-dead-stock', days],
    queryFn: async () => {
      const { data } = await api.get<{ data: DeadStockItem[] }>('/inventory/analytics/dead-stock', { params: { days } })
      return data.data
    },
  })
}

export default function InventoryAnalyticsPage(): React.ReactElement {
  const [tab, setTab] = useState<'abc' | 'turnover' | 'dead-stock'>('abc')
  const [year, setYear] = useState(new Date().getFullYear())
  const [deadDays, setDeadDays] = useState(90)

  const { data: abcData, isLoading: abcLoading } = useAbcAnalysis(year)
  const { data: turnoverData, isLoading: turnoverLoading } = useTurnoverAnalysis(year)
  const { data: deadData, isLoading: deadLoading } = useDeadStock(deadDays)

  return (
    <div className="space-y-6">
      <PageHeader title="Inventory Analytics" subtitle="ABC analysis, turnover rates, and dead stock detection" />

      <div className="flex gap-2 border-b">
        {[
          { key: 'abc' as const, label: 'ABC Analysis', icon: BarChart3 },
          { key: 'turnover' as const, label: 'Turnover', icon: TrendingDown },
          { key: 'dead-stock' as const, label: 'Dead Stock', icon: AlertTriangle },
        ].map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${tab === key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            <Icon className="h-4 w-4 inline mr-1" /> {label}
          </button>
        ))}
      </div>

      {tab === 'abc' && (
        <Card className="p-4">
          <div className="flex items-center gap-3 mb-4">
            <label className="text-sm">Year:</label>
            <select value={year} onChange={(e) => setYear(Number(e.target.value))} className="input input-sm w-24">
              {[2024, 2025, 2026].map((y) => <option key={y} value={y}>{y}</option>)}
            </select>
          </div>
          {abcLoading ? <SkeletonLoader rows={6} /> : !abcData?.length ? (
            <EmptyState icon={<Package className="h-12 w-12 text-gray-400" />} title="No ABC data" description="No inventory usage data found for this year." />
          ) : (
            <div className="overflow-x-auto">
              <table className="table w-full text-sm">
                <thead><tr><th>Item</th><th>Code</th><th>Category</th><th className="text-right">Annual Usage</th><th className="text-right">Cumulative %</th></tr></thead>
                <tbody>
                  {abcData.map((item) => (
                    <tr key={item.item_id}>
                      <td>{item.name}</td><td className="font-mono">{item.item_code}</td>
                      <td><span className={`badge ${item.category === 'A' ? 'badge-error' : item.category === 'B' ? 'badge-warning' : 'badge-info'}`}>{item.category}</span></td>
                      <td className="text-right font-mono">{fmt(item.annual_usage_centavos)}</td>
                      <td className="text-right">{item.cumulative_pct.toFixed(1)}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {tab === 'turnover' && (
        <Card className="p-4">
          {turnoverLoading ? <SkeletonLoader rows={6} /> : !turnoverData?.length ? (
            <EmptyState icon={<TrendingDown className="h-12 w-12 text-gray-400" />} title="No turnover data" description="No inventory movement data found." />
          ) : (
            <div className="overflow-x-auto">
              <table className="table w-full text-sm">
                <thead><tr><th>Item</th><th>Code</th><th className="text-right">Turnover Ratio</th><th className="text-right">Avg Stock</th></tr></thead>
                <tbody>
                  {turnoverData.map((item) => (
                    <tr key={item.item_id}>
                      <td>{item.name}</td><td className="font-mono">{item.item_code}</td>
                      <td className="text-right font-mono">{item.turnover_ratio.toFixed(2)}</td>
                      <td className="text-right font-mono">{item.avg_stock.toFixed(0)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {tab === 'dead-stock' && (
        <Card className="p-4">
          <div className="flex items-center gap-3 mb-4">
            <label className="text-sm">Days idle threshold:</label>
            <select value={deadDays} onChange={(e) => setDeadDays(Number(e.target.value))} className="input input-sm w-24">
              {[30, 60, 90, 180, 365].map((d) => <option key={d} value={d}>{d} days</option>)}
            </select>
          </div>
          {deadLoading ? <SkeletonLoader rows={6} /> : !deadData?.length ? (
            <EmptyState icon={<AlertTriangle className="h-12 w-12 text-gray-400" />} title="No dead stock" description="No items found with zero movement beyond the threshold." />
          ) : (
            <div className="overflow-x-auto">
              <table className="table w-full text-sm">
                <thead><tr><th>Item</th><th>Code</th><th className="text-right">Qty on Hand</th><th className="text-right">Days Idle</th><th>Last Movement</th></tr></thead>
                <tbody>
                  {deadData.map((item) => (
                    <tr key={item.item_id}>
                      <td>{item.name}</td><td className="font-mono">{item.item_code}</td>
                      <td className="text-right font-mono">{item.quantity_on_hand}</td>
                      <td className="text-right font-mono text-red-600">{item.days_idle}</td>
                      <td className="text-sm text-gray-500">{item.last_movement_at ?? 'Never'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}
    </div>
  )
}
