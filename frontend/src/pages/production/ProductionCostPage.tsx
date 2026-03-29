import { useState } from 'react'
import { Factory, TrendingUp } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

interface CostRow {
  order_id: number
  ulid: string
  po_reference: string
  product_name: string
  bom_name: string
  status: string
  qty_required: number
  qty_produced: number
  material_cost: number
  unit_cost: number
  created_at: string
}

interface CostSummary {
  total_orders: number
  total_material_cost: number
  total_output: number
  avg_unit_cost: number
}

function useCostAnalysis(dateFrom?: string, dateTo?: string) {
  return useQuery({
    queryKey: ['production-cost-analysis', dateFrom, dateTo],
    queryFn: async () => {
      const params: Record<string, string> = {}
      if (dateFrom) params.date_from = dateFrom
      if (dateTo) params.date_to = dateTo
      const res = await api.get<{ data: CostRow[]; summary: CostSummary }>('/production/reports/cost-analysis', { params })
      return res.data
    },
    staleTime: 60_000,
  })
}

const fmt = (v: number) => `₱${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const statusColor: Record<string, string> = {
  completed: 'bg-emerald-100 text-emerald-700',
  released: 'bg-blue-100 text-blue-700',
  in_progress: 'bg-amber-100 text-amber-700',
}

export default function ProductionCostPage(): React.ReactElement {
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const { data, isLoading } = useCostAnalysis(dateFrom || undefined, dateTo || undefined)

  const rows = data?.data ?? []
  const summary = data?.summary ?? { total_orders: 0, total_material_cost: 0, total_output: 0, avg_unit_cost: 0 }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Production Cost Analysis"
        subtitle="Material cost tracking per production order"
        icon={<Factory className="w-5 h-5 text-indigo-600" />}
        actions={
          <div className="flex gap-3">
            <div>
              <label className="block text-xs text-neutral-500 mb-1">From</label>
              <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)}
                className="border border-neutral-300 rounded px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="block text-xs text-neutral-500 mb-1">To</label>
              <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)}
                className="border border-neutral-300 rounded px-3 py-2 text-sm" />
            </div>
          </div>
        }
      />

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <SummaryCard label="Total Orders" value={String(summary.total_orders)} icon={<Factory className="w-4 h-4 text-indigo-500" />} />
        <SummaryCard label="Total Material Cost" value={fmt(summary.total_material_cost)} icon={<TrendingUp className="w-4 h-4 text-rose-500" />} />
        <SummaryCard label="Total Output" value={summary.total_output.toLocaleString()} icon={<Factory className="w-4 h-4 text-emerald-500" />} />
        <SummaryCard label="Avg Unit Cost" value={fmt(summary.avg_unit_cost)} icon={<TrendingUp className="w-4 h-4 text-amber-500" />} />
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        {isLoading ? (
          <p className="p-6 text-sm text-neutral-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">No production orders found in selected range.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Reference</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Product</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Status</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Required</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Produced</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Material Cost</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Unit Cost</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {rows.map((r) => (
                <tr key={r.order_id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-medium text-neutral-800">{r.po_reference}</td>
                  <td className="px-4 py-3 text-neutral-700">{r.product_name}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${statusColor[r.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                      {r.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.qty_required}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.qty_produced}</td>
                  <td className="px-4 py-3 text-right tabular-nums font-medium">{r.material_cost > 0 ? fmt(r.material_cost) : '—'}</td>
                  <td className="px-4 py-3 text-right tabular-nums">{r.unit_cost > 0 ? fmt(r.unit_cost) : '—'}</td>
                  <td className="px-4 py-3 text-neutral-500">{r.created_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}

function SummaryCard({ label, value, icon }: { label: string; value: string; icon: React.ReactNode }): React.ReactElement {
  return (
    <div className="bg-white border border-neutral-200 rounded-lg p-4">
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs font-medium text-neutral-500">{label}</span>
      </div>
      <span className="text-lg font-semibold text-neutral-900 tabular-nums">{value}</span>
    </div>
  )
}
