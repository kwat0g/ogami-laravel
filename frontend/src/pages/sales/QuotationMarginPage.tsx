import React from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, TrendingUp, TrendingDown, AlertTriangle, DollarSign } from 'lucide-react'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface MarginLine {
  item_id: number
  item_name: string
  item_code: string
  quantity: number
  unit_price_centavos: number
  unit_cost_centavos: number
  margin_per_unit_centavos: number
  margin_pct: number
  line_revenue_centavos: number
  line_cost_centavos: number
  line_margin_centavos: number
  has_bom: boolean
  below_cost: boolean
}

interface MarginData {
  quotation_id: number
  quotation_number: string
  total_revenue_centavos: number
  total_cost_centavos: number
  total_margin_centavos: number
  overall_margin_pct: number
  lines: MarginLine[]
}

function formatPeso(centavos: number): string {
  return `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

export default function QuotationMarginPage() {
  const { ulid } = useParams<{ ulid: string }>()

  const { data, isLoading, error } = useQuery({
    queryKey: ['quotation-margin', ulid],
    queryFn: async () => {
      const { data } = await api.get<{ data: MarginData }>(`/sales/quotations/${ulid}/margin`)
      return data.data
    },
    enabled: !!ulid,
  })

  if (isLoading) return <SkeletonLoader rows={8} />
  if (error || !data) return <div className="p-6 text-red-500">Failed to load margin analysis.</div>

  const hasWarnings = data.lines.some(l => l.below_cost)
  const marginColor = data.overall_margin_pct >= 20 ? 'text-green-600' : data.overall_margin_pct >= 0 ? 'text-amber-600' : 'text-red-600'

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to={`/sales/quotations/${ulid}`} className="text-neutral-400 hover:text-neutral-600">
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <PageHeader
          title={`Margin Analysis: ${data.quotation_number}`}
          icon={<DollarSign className="w-5 h-5 text-neutral-600" />}
        />
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card className="p-4">
          <div className="text-xs text-neutral-500 mb-1">Total Revenue</div>
          <div className="text-lg font-semibold text-neutral-900">{formatPeso(data.total_revenue_centavos)}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs text-neutral-500 mb-1">Total Cost (BOM)</div>
          <div className="text-lg font-semibold text-neutral-900">{formatPeso(data.total_cost_centavos)}</div>
        </Card>
        <Card className="p-4">
          <div className="text-xs text-neutral-500 mb-1">Total Margin</div>
          <div className={`text-lg font-semibold ${data.total_margin_centavos >= 0 ? 'text-green-600' : 'text-red-600'}`}>
            {formatPeso(data.total_margin_centavos)}
          </div>
        </Card>
        <Card className="p-4">
          <div className="text-xs text-neutral-500 mb-1">Overall Margin %</div>
          <div className={`text-lg font-semibold flex items-center gap-1 ${marginColor}`}>
            {data.overall_margin_pct >= 0 ? <TrendingUp className="w-4 h-4" /> : <TrendingDown className="w-4 h-4" />}
            {data.overall_margin_pct.toFixed(1)}%
          </div>
        </Card>
      </div>

      {hasWarnings && (
        <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
          <AlertTriangle className="w-4 h-4 flex-shrink-0" />
          <span>One or more items are priced below production cost. Review highlighted rows before sending this quotation.</span>
        </div>
      )}

      {/* Line Item Detail */}
      <Card className="overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left p-3 text-xs font-medium text-neutral-600">Item</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Qty</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Unit Price</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Unit Cost</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Margin/Unit</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Margin %</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Line Margin</th>
              <th className="text-center p-3 text-xs font-medium text-neutral-600">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {data.lines.map((line) => (
              <tr
                key={line.item_id}
                className={`${line.below_cost ? 'bg-red-50/60' : 'hover:bg-neutral-50'}`}
              >
                <td className="p-3">
                  <div className="font-medium text-neutral-900">{line.item_name}</div>
                  <div className="text-xs text-neutral-400 font-mono">{line.item_code}</div>
                </td>
                <td className="p-3 text-right tabular-nums text-neutral-700">{line.quantity}</td>
                <td className="p-3 text-right tabular-nums text-neutral-700">{formatPeso(line.unit_price_centavos)}</td>
                <td className="p-3 text-right tabular-nums text-neutral-500">
                  {line.has_bom ? formatPeso(line.unit_cost_centavos) : <span className="text-neutral-300">N/A</span>}
                </td>
                <td className={`p-3 text-right tabular-nums font-medium ${line.margin_per_unit_centavos >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {formatPeso(line.margin_per_unit_centavos)}
                </td>
                <td className={`p-3 text-right tabular-nums font-semibold ${line.margin_pct >= 20 ? 'text-green-600' : line.margin_pct >= 0 ? 'text-amber-600' : 'text-red-600'}`}>
                  {line.margin_pct.toFixed(1)}%
                </td>
                <td className={`p-3 text-right tabular-nums ${line.line_margin_centavos >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {formatPeso(line.line_margin_centavos)}
                </td>
                <td className="p-3 text-center">
                  {line.below_cost ? (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                      <AlertTriangle className="w-3 h-3" /> Below Cost
                    </span>
                  ) : !line.has_bom ? (
                    <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-500">No BOM</span>
                  ) : (
                    <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">OK</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  )
}
