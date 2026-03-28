import React from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Layers, Wrench, Factory } from 'lucide-react'
import api from '@/lib/api'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface CostComponent {
  item_id: number
  item_code: string
  item_name: string
  qty_per_unit: number
  scrap_factor_pct: number
  gross_qty: number
  unit_cost_centavos: number
  line_cost_centavos: number
  bom_level: number
  is_sub_assembly: boolean
}

interface RoutingStep {
  sequence: number
  operation_name: string
  work_center_code: string
  work_center_name: string
  setup_hours: number
  run_hours_per_unit: number
  total_hours: number
  hourly_rate_centavos: number
  overhead_rate_centavos: number
  labor_cost_centavos: number
  overhead_cost_centavos: number
  total_step_cost_centavos: number
}

interface CostBreakdown {
  product_item_id: number
  product_name: string
  bom_version: string
  material_cost_centavos: number
  labor_cost_centavos: number
  overhead_cost_centavos: number
  total_standard_cost_centavos: number
  components: CostComponent[]
  routings: RoutingStep[]
}

function formatPeso(centavos: number): string {
  return `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function pctOf(part: number, total: number): string {
  return total > 0 ? `${((part / total) * 100).toFixed(1)}%` : '0%'
}

export default function BomCostBreakdownPage() {
  const { id } = useParams<{ id: string }>()

  const { data, isLoading, error } = useQuery({
    queryKey: ['bom-cost-breakdown', id],
    queryFn: async () => {
      const { data } = await api.get<{ data: CostBreakdown }>(`/production/boms/${id}/cost-breakdown`)
      return data.data
    },
    enabled: !!id,
  })

  if (isLoading) return <SkeletonLoader rows={10} />
  if (error || !data) return <div className="p-6 text-red-500">Failed to load cost breakdown.</div>

  const total = data.total_standard_cost_centavos

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/production/boms" className="text-neutral-400 hover:text-neutral-600">
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <PageHeader
          title={`Cost Breakdown: ${data.product_name} (v${data.bom_version})`}
          icon={<Layers className="w-5 h-5 text-neutral-600" />}
        />
      </div>

      {/* Cost Summary Bar */}
      <Card className="p-5">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-semibold text-neutral-700">Standard Cost per Unit</h3>
          <span className="text-xl font-bold text-neutral-900">{formatPeso(total)}</span>
        </div>

        {/* Visual cost breakdown bar */}
        <div className="flex h-8 rounded-lg overflow-hidden mb-3">
          {data.material_cost_centavos > 0 && (
            <div
              className="bg-blue-500 flex items-center justify-center text-white text-xs font-medium"
              style={{ width: pctOf(data.material_cost_centavos, total) }}
              title={`Material: ${formatPeso(data.material_cost_centavos)}`}
            >
              {pctOf(data.material_cost_centavos, total)}
            </div>
          )}
          {data.labor_cost_centavos > 0 && (
            <div
              className="bg-amber-500 flex items-center justify-center text-white text-xs font-medium"
              style={{ width: pctOf(data.labor_cost_centavos, total) }}
              title={`Labor: ${formatPeso(data.labor_cost_centavos)}`}
            >
              {pctOf(data.labor_cost_centavos, total)}
            </div>
          )}
          {data.overhead_cost_centavos > 0 && (
            <div
              className="bg-purple-500 flex items-center justify-center text-white text-xs font-medium"
              style={{ width: pctOf(data.overhead_cost_centavos, total) }}
              title={`Overhead: ${formatPeso(data.overhead_cost_centavos)}`}
            >
              {pctOf(data.overhead_cost_centavos, total)}
            </div>
          )}
        </div>

        <div className="grid grid-cols-3 gap-4 text-sm">
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded bg-blue-500" />
            <span className="text-neutral-600">Material:</span>
            <span className="font-medium">{formatPeso(data.material_cost_centavos)}</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded bg-amber-500" />
            <span className="text-neutral-600">Labor:</span>
            <span className="font-medium">{formatPeso(data.labor_cost_centavos)}</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded bg-purple-500" />
            <span className="text-neutral-600">Overhead:</span>
            <span className="font-medium">{formatPeso(data.overhead_cost_centavos)}</span>
          </div>
        </div>
      </Card>

      {/* Material Breakdown */}
      <Card className="overflow-hidden">
        <div className="p-4 border-b border-neutral-200 flex items-center gap-2">
          <Layers className="w-4 h-4 text-blue-500" />
          <h3 className="font-semibold text-neutral-800">Material Breakdown</h3>
        </div>
        <table className="w-full text-sm">
          <thead className="bg-neutral-50">
            <tr>
              <th className="text-left p-3 text-xs font-medium text-neutral-600">Level</th>
              <th className="text-left p-3 text-xs font-medium text-neutral-600">Component</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Qty/Unit</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Scrap %</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Gross Qty</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Unit Cost</th>
              <th className="text-right p-3 text-xs font-medium text-neutral-600">Line Cost</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {data.components.map((comp, idx) => (
              <tr key={`${comp.item_id}-${idx}`} className={`hover:bg-neutral-50 ${comp.is_sub_assembly ? 'bg-blue-50/30 font-medium' : ''}`}>
                <td className="p-3 text-neutral-400">
                  {'→'.repeat(comp.bom_level)}
                </td>
                <td className="p-3">
                  <div className="text-neutral-900">{comp.item_name}</div>
                  <div className="text-xs text-neutral-400 font-mono">{comp.item_code}</div>
                  {comp.is_sub_assembly && <span className="text-xs text-blue-600 font-medium">Sub-assembly</span>}
                </td>
                <td className="p-3 text-right tabular-nums">{comp.qty_per_unit}</td>
                <td className="p-3 text-right tabular-nums text-neutral-500">{comp.scrap_factor_pct}%</td>
                <td className="p-3 text-right tabular-nums">{comp.gross_qty}</td>
                <td className="p-3 text-right tabular-nums">{formatPeso(comp.unit_cost_centavos)}</td>
                <td className="p-3 text-right tabular-nums font-medium">{formatPeso(comp.line_cost_centavos)}</td>
              </tr>
            ))}
            <tr className="bg-neutral-50 font-semibold">
              <td colSpan={6} className="p-3 text-right">Total Material Cost</td>
              <td className="p-3 text-right tabular-nums">{formatPeso(data.material_cost_centavos)}</td>
            </tr>
          </tbody>
        </table>
      </Card>

      {/* Routing / Labor Breakdown */}
      {data.routings.length > 0 && (
        <Card className="overflow-hidden">
          <div className="p-4 border-b border-neutral-200 flex items-center gap-2">
            <Wrench className="w-4 h-4 text-amber-500" />
            <h3 className="font-semibold text-neutral-800">Routing / Labor Breakdown</h3>
          </div>
          <table className="w-full text-sm">
            <thead className="bg-neutral-50">
              <tr>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">Step</th>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">Operation</th>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">Work Center</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Setup (hrs)</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Run (hrs)</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Labor Cost</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Overhead</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {data.routings.map((step) => (
                <tr key={step.sequence} className="hover:bg-neutral-50">
                  <td className="p-3 text-neutral-500">{step.sequence}</td>
                  <td className="p-3 font-medium text-neutral-900">{step.operation_name}</td>
                  <td className="p-3 text-neutral-600">
                    <div>{step.work_center_name}</div>
                    <div className="text-xs text-neutral-400 font-mono">{step.work_center_code}</div>
                  </td>
                  <td className="p-3 text-right tabular-nums">{step.setup_hours}</td>
                  <td className="p-3 text-right tabular-nums">{step.run_hours_per_unit}</td>
                  <td className="p-3 text-right tabular-nums text-amber-600">{formatPeso(step.labor_cost_centavos)}</td>
                  <td className="p-3 text-right tabular-nums text-purple-600">{formatPeso(step.overhead_cost_centavos)}</td>
                  <td className="p-3 text-right tabular-nums font-medium">{formatPeso(step.total_step_cost_centavos)}</td>
                </tr>
              ))}
              <tr className="bg-neutral-50 font-semibold">
                <td colSpan={5} className="p-3 text-right">Total Labor + Overhead</td>
                <td className="p-3 text-right tabular-nums text-amber-600">{formatPeso(data.labor_cost_centavos)}</td>
                <td className="p-3 text-right tabular-nums text-purple-600">{formatPeso(data.overhead_cost_centavos)}</td>
                <td className="p-3 text-right tabular-nums">{formatPeso(data.labor_cost_centavos + data.overhead_cost_centavos)}</td>
              </tr>
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
