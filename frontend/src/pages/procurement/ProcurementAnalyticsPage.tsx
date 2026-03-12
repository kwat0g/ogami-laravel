import { useState } from 'react'
import { ShoppingCart, TrendingUp, DollarSign, Package, Users } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface SpendRow { vendor?: string; category?: string; total_spend: number }
interface ProcSummary { total_pos: number; total_spend: number; avg_po_value: number; active_vendors: number }

function useProcurementAnalytics(year: number) {
  return useQuery({
    queryKey: ['procurement-analytics', year],
    queryFn: async () => {
      const res = await api.get<{ by_vendor: SpendRow[]; by_category: SpendRow[]; summary: ProcSummary; year: number }>('/procurement/reports/analytics', { params: { year } })
      return res.data
    },
    staleTime: 60_000,
  })
}

const fmt = (v: number) => `₱${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

export default function ProcurementAnalyticsPage(): React.ReactElement {
  const [year, setYear] = useState(new Date().getFullYear())
  const { data, isLoading } = useProcurementAnalytics(year)

  const summary = data?.summary ?? { total_pos: 0, total_spend: 0, avg_po_value: 0, active_vendors: 0 }
  const byVendor = data?.by_vendor ?? []
  const byCategory = data?.by_category ?? []

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Procurement Analytics"
        icon={<ShoppingCart className="w-5 h-5 text-neutral-600" />}
        actions={
          <select value={year} onChange={(e) => setYear(Number(e.target.value))}
            className="border border-neutral-300 rounded-lg px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
            {[0,1,2].map((y) => {
              const yr = new Date().getFullYear() - y
              return <option key={yr} value={yr}>{yr}</option>
            })}
          </select>
        }
      />

      {/* Summary Stats */}
      {isLoading ? (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <SkeletonLoader rows={1} />
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <Package className="w-4 h-4 text-blue-600" />
              <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Total POs</p>
            </div>
            <p className="text-2xl font-bold text-blue-700 mt-1">{summary.total_pos}</p>
          </div>
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <TrendingUp className="w-4 h-4 text-emerald-600" />
              <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Total Spend</p>
            </div>
            <p className="text-2xl font-bold text-emerald-700 font-mono mt-1">{fmt(summary.total_spend)}</p>
          </div>
          <div className="bg-purple-50 border border-purple-200 rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <DollarSign className="w-4 h-4 text-purple-600" />
              <p className="text-xs font-medium text-purple-600 uppercase tracking-wide">Avg PO Value</p>
            </div>
            <p className="text-2xl font-bold text-purple-700 font-mono mt-1">{fmt(summary.avg_po_value)}</p>
          </div>
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div className="flex items-center gap-2 mb-1">
              <Users className="w-4 h-4 text-amber-600" />
              <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">Active Vendors</p>
            </div>
            <p className="text-2xl font-bold text-amber-700 mt-1">{summary.active_vendors}</p>
          </div>
        </div>
      )}

      {isLoading ? (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <SkeletonLoader rows={8} />
          <SkeletonLoader rows={8} />
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Spend by Vendor */}
          <Card>
            <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
              <h2 className="text-sm font-semibold text-neutral-700">Spend by Vendor (Top 15)</h2>
            </div>
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">#</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Vendor</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Total Spend</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {byVendor.map((r, i) => (
                  <tr key={r.vendor} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                    <td className="px-4 py-3 text-neutral-400 text-xs">{i + 1}</td>
                    <td className="px-4 py-3 font-medium text-neutral-900">{r.vendor}</td>
                    <td className="px-4 py-3 text-right tabular-nums">
                      <span className="font-bold text-emerald-700 font-mono">{fmt(Number(r.total_spend))}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          {/* Spend by Category */}
          <Card>
            <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
              <h2 className="text-sm font-semibold text-neutral-700">Spend by Item Category</h2>
            </div>
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Category</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Total Spend</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {byCategory.map((r) => (
                  <tr key={r.category} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                    <td className="px-4 py-3 font-medium text-neutral-900">{r.category}</td>
                    <td className="px-4 py-3 text-right tabular-nums">
                      <span className="font-bold text-emerald-700 font-mono">{fmt(Number(r.total_spend))}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
        </div>
      )}
    </div>
  )
}
