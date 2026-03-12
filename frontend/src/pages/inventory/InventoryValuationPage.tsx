import { Package, TrendingUp, Layers } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface ValuationRow {
  item_id: number
  item_code: string
  item_name: string
  category: string
  location: string | null
  uom: string | null
  quantity: number
  unit_cost: number | null
  total_value: number
}
interface CategorySummary { category: string; item_count: number; total_qty: number; total_value: number }

function useInventoryValuation() {
  return useQuery({
    queryKey: ['inventory-valuation'],
    queryFn: async () => {
      const res = await api.get<{ data: ValuationRow[]; by_category: CategorySummary[]; grand_total: number }>('/inventory/reports/valuation')
      return res.data
    },
    staleTime: 60_000,
  })
}

const fmt = (v: number) => `₱${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

export default function InventoryValuationPage(): React.ReactElement {
  const { data, isLoading } = useInventoryValuation()

  const rows = data?.data ?? []
  const byCategory = data?.by_category ?? []
  const grandTotal = data?.grand_total ?? 0

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Inventory Valuation Report"
        icon={<Package className="w-5 h-5 text-neutral-600" />}
      />

      {/* Summary Stats */}
      {!isLoading && (
        <div className="grid grid-cols-3 gap-4">
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-5">
            <div className="flex items-center gap-2 mb-1">
              <TrendingUp className="w-4 h-4 text-emerald-600" />
              <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Total Inventory Value</p>
            </div>
            <p className="text-3xl font-bold text-emerald-700 font-mono">{fmt(grandTotal)}</p>
            <p className="text-xs text-emerald-600 mt-1">Current valuation</p>
          </div>
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-5">
            <div className="flex items-center gap-2 mb-1">
              <Package className="w-4 h-4 text-blue-600" />
              <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Total Items</p>
            </div>
            <p className="text-3xl font-bold text-blue-700 mt-1">{rows.length}</p>
            <p className="text-xs text-blue-600 mt-1">In stock</p>
          </div>
          <div className="bg-purple-50 border border-purple-200 rounded-xl p-5">
            <div className="flex items-center gap-2 mb-1">
              <Layers className="w-4 h-4 text-purple-600" />
              <p className="text-xs font-medium text-purple-600 uppercase tracking-wide">Categories</p>
            </div>
            <p className="text-3xl font-bold text-purple-700 mt-1">{byCategory.length}</p>
            <p className="text-xs text-purple-600 mt-1">Active categories</p>
          </div>
        </div>
      )}

      {/* Category summary */}
      <Card>
        <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
          <h2 className="text-sm font-semibold text-neutral-700">Value by Category</h2>
        </div>
        {isLoading ? <SkeletonLoader rows={4} /> : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Category</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Items</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Total Qty</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Total Value</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">%</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {byCategory.map((c) => (
                <tr key={c.category} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-semibold text-neutral-900">{c.category}</td>
                  <td className="px-4 py-3 text-right tabular-nums font-medium text-neutral-700">{c.item_count}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-neutral-600">{Number(c.total_qty).toLocaleString()}</td>
                  <td className="px-4 py-3 text-right tabular-nums">
                    <span className="font-bold text-emerald-700 font-mono">{fmt(c.total_value)}</span>
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums">
                    <div className="flex items-center justify-end gap-2">
                      <div className="w-16 bg-neutral-200 rounded-full h-1.5">
                        <div 
                          className="bg-blue-500 h-1.5 rounded-full"
                          style={{ width: `${grandTotal > 0 ? (c.total_value / grandTotal) * 100 : 0}%` }}
                        />
                      </div>
                      <span className="text-xs text-neutral-500 w-10">
                        {grandTotal > 0 ? ((c.total_value / grandTotal) * 100).toFixed(1) : '0.0'}%
                      </span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {/* Detailed item list */}
      <Card>
        <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
          <h2 className="text-sm font-semibold text-neutral-700">Item Detail</h2>
        </div>
        {isLoading ? (
          <SkeletonLoader rows={8} />
        ) : rows.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">No stock balances found.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Code</th>
                <th className="px-3 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Item</th>
                <th className="px-3 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Category</th>
                <th className="px-3 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Location</th>
                <th className="px-3 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Qty</th>
                <th className="px-3 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Unit Cost</th>
                <th className="px-3 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Total Value</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {rows.map((r, i) => (
                <tr key={`${r.item_id}-${i}`} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-3 py-3 text-neutral-500 font-mono text-xs">{r.item_code}</td>
                  <td className="px-3 py-3 font-medium text-neutral-900">{r.item_name}</td>
                  <td className="px-3 py-3 text-neutral-600">{r.category}</td>
                  <td className="px-3 py-3 text-neutral-500">{r.location ?? '—'}</td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    <span className={`font-medium ${r.quantity <= 0 ? 'text-red-600' : 'text-blue-700'}`}>
                      {Number(r.quantity).toLocaleString()}
                    </span>
                    <span className="text-neutral-400 text-xs ml-1">{r.uom ?? ''}</span>
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    {r.unit_cost ? (
                      <span className="text-neutral-600">{fmt(Number(r.unit_cost))}</span>
                    ) : (
                      <span className="text-neutral-300">—</span>
                    )}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    <span className="font-bold text-emerald-700 font-mono">{fmt(Number(r.total_value))}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
