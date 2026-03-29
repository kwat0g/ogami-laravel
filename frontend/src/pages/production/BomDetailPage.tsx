import React from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Layers, Wrench, Pencil, Calculator, Archive } from 'lucide-react'
import api from '@/lib/api'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import type { Bom } from '@/types/production'

// ── Types for cost breakdown API response ────────────────────────────────────

interface CostComponent {
  item_id: number; item_code: string; item_name: string
  qty_per_unit: number; scrap_factor_pct: number; gross_qty: number
  unit_cost_centavos: number; line_cost_centavos: number
  bom_level: number; is_sub_assembly: boolean
}

interface RoutingStep {
  sequence: number; operation_name: string
  work_center_code: string; work_center_name: string
  setup_hours: number; run_hours_per_unit: number; total_hours: number
  hourly_rate_centavos: number; overhead_rate_centavos: number
  labor_cost_centavos: number; overhead_cost_centavos: number
  total_step_cost_centavos: number
}

interface CostBreakdown {
  product_item_id: number; product_name: string; bom_version: string
  material_cost_centavos: number; labor_cost_centavos: number
  overhead_cost_centavos: number; total_standard_cost_centavos: number
  components: CostComponent[]; routings: RoutingStep[]
}

function peso(centavos: number): string {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(centavos / 100)
}

function pct(part: number, total: number): string {
  return total > 0 ? `${((part / total) * 100).toFixed(1)}%` : '0%'
}

// ── Main Component ───────────────────────────────────────────────────────────

export default function BomDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const canManage = useAuthStore(s => s.hasPermission('production.bom.manage'))

  // Fetch BOM details
  const { data: bom, isLoading: bomLoading } = useQuery<{ data: Bom }>({
    queryKey: ['bom', ulid],
    queryFn: async () => { const { data } = await api.get(`/production/boms/${ulid}`); return data },
    enabled: !!ulid,
  })

  // Fetch cost breakdown
  const { data: cost, isLoading: costLoading } = useQuery<{ data: CostBreakdown }>({
    queryKey: ['bom-cost-breakdown', ulid],
    queryFn: async () => { const { data } = await api.get(`/production/boms/${ulid}/cost-breakdown`); return data },
    enabled: !!ulid,
  })

  // Recalculate cost mutation
  const rollup = useMutation({
    mutationFn: async () => { const { data } = await api.post(`/production/boms/${ulid}/rollup-cost`); return data },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bom', ulid] })
      qc.invalidateQueries({ queryKey: ['bom-cost-breakdown', ulid] })
      qc.invalidateQueries({ queryKey: ['boms'] })
      toast.success('Cost recalculated successfully.')
    },
    onError: (err) => toast.error(firstErrorMessage(err, 'Cost rollup failed.')),
  })

  // Archive mutation
  const archive = useMutation({
    mutationFn: async () => { await api.delete(`/production/boms/${ulid}`); },
    onSuccess: () => {
      toast.success('BOM archived.')
      navigate('/production/boms')
    },
    onError: (err) => toast.error(firstErrorMessage(err, 'Archive failed.')),
  })

  if (bomLoading || costLoading) return <SkeletonLoader rows={12} />

  const b = bom?.data
  const c = cost?.data
  const total = c?.total_standard_cost_centavos ?? 0

  if (!b) return <div className="p-6 text-red-500">BOM not found.</div>

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Link to="/production/boms" className="text-neutral-400 hover:text-neutral-600">
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-lg font-semibold text-neutral-900">
              {b.product_item?.name ?? 'BOM'} <span className="text-neutral-400 text-sm font-normal">v{b.version}</span>
            </h1>
            <p className="text-sm text-neutral-500 font-mono">{b.product_item?.item_code}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {b.is_active
            ? <StatusBadge className="bg-green-100 text-green-700">Active</StatusBadge>
            : <StatusBadge className="bg-neutral-100 text-neutral-500">Inactive</StatusBadge>}
        </div>
      </div>

      {/* Action Buttons */}
      {canManage && (
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => navigate(`/production/boms/${ulid}/edit`)}
            className="flex items-center gap-1.5 px-3 py-2 text-sm border border-neutral-300 rounded bg-white text-neutral-700 hover:bg-neutral-50 font-medium"
          >
            <Pencil className="w-4 h-4" /> Edit Components
          </button>
          <button
            onClick={() => rollup.mutate()}
            disabled={rollup.isPending}
            className="flex items-center gap-1.5 px-3 py-2 text-sm border border-blue-300 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 font-medium disabled:opacity-50"
          >
            <Calculator className="w-4 h-4" /> {rollup.isPending ? 'Calculating...' : 'Recalculate Cost'}
          </button>
          <button
            onClick={() => { if (confirm('Archive this BOM?')) archive.mutate() }}
            disabled={archive.isPending}
            className="flex items-center gap-1.5 px-3 py-2 text-sm border border-red-200 rounded bg-white text-red-600 hover:bg-red-50 font-medium disabled:opacity-50"
          >
            <Archive className="w-4 h-4" /> Archive
          </button>
        </div>
      )}

      {/* Cost Summary */}
      {c && (
        <Card className="p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-semibold text-neutral-700">Standard Cost per Unit</h3>
            <span className="text-2xl font-bold text-neutral-900">{peso(total)}</span>
          </div>

          {/* Visual cost breakdown bar */}
          {total > 0 && (
            <div className="flex h-8 rounded-lg overflow-hidden mb-3">
              {c.material_cost_centavos > 0 && (
                <div className="bg-blue-500 flex items-center justify-center text-white text-xs font-medium"
                  style={{ width: pct(c.material_cost_centavos, total) }}
                  title={`Material: ${peso(c.material_cost_centavos)}`}
                >{pct(c.material_cost_centavos, total)}</div>
              )}
              {c.labor_cost_centavos > 0 && (
                <div className="bg-amber-500 flex items-center justify-center text-white text-xs font-medium"
                  style={{ width: pct(c.labor_cost_centavos, total) }}
                  title={`Labor: ${peso(c.labor_cost_centavos)}`}
                >{pct(c.labor_cost_centavos, total)}</div>
              )}
              {c.overhead_cost_centavos > 0 && (
                <div className="bg-purple-500 flex items-center justify-center text-white text-xs font-medium"
                  style={{ width: pct(c.overhead_cost_centavos, total) }}
                  title={`Overhead: ${peso(c.overhead_cost_centavos)}`}
                >{pct(c.overhead_cost_centavos, total)}</div>
              )}
            </div>
          )}

          <div className="grid grid-cols-3 gap-4 text-sm">
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded bg-blue-500" />
              <span className="text-neutral-600">Material:</span>
              <span className="font-semibold">{peso(c.material_cost_centavos)}</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded bg-amber-500" />
              <span className="text-neutral-600">Labor:</span>
              <span className="font-semibold">{peso(c.labor_cost_centavos)}</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded bg-purple-500" />
              <span className="text-neutral-600">Overhead:</span>
              <span className="font-semibold">{peso(c.overhead_cost_centavos)}</span>
            </div>
          </div>

          {b.last_cost_rollup_at && (
            <p className="text-xs text-neutral-400 mt-3">
              Last calculated: {new Date(b.last_cost_rollup_at).toLocaleString('en-PH')}
            </p>
          )}
        </Card>
      )}

      {/* Component List */}
      <Card className="overflow-hidden">
        <div className="p-4 border-b border-neutral-200 flex items-center gap-2">
          <Layers className="w-4 h-4 text-blue-500" />
          <h3 className="font-semibold text-neutral-800">Components ({b.components?.length ?? 0})</h3>
        </div>
        {(!b.components || b.components.length === 0) ? (
          <div className="p-8 text-center text-neutral-400 text-sm">
            No components added yet. Click "Edit Components" to add materials.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50">
              <tr>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">Component</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Qty/Unit</th>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">UOM</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Scrap %</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Unit Cost</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Line Cost</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(c?.components ?? []).map((comp, idx) => (
                <tr key={`${comp.item_id}-${idx}`} className={`hover:bg-neutral-50 ${comp.is_sub_assembly ? 'bg-blue-50/30' : ''}`}>
                  <td className="p-3">
                    <div className="font-medium text-neutral-900">{comp.item_name}</div>
                    <div className="text-xs text-neutral-400 font-mono">{comp.item_code}</div>
                    {comp.is_sub_assembly && <span className="text-xs text-blue-600 font-medium">Sub-assembly</span>}
                  </td>
                  <td className="p-3 text-right tabular-nums">{comp.qty_per_unit}</td>
                  <td className="p-3 text-neutral-500">{b.components?.[idx]?.unit_of_measure ?? '-'}</td>
                  <td className="p-3 text-right tabular-nums text-neutral-500">{comp.scrap_factor_pct}%</td>
                  <td className="p-3 text-right tabular-nums">{peso(comp.unit_cost_centavos)}</td>
                  <td className="p-3 text-right tabular-nums font-medium">{peso(comp.line_cost_centavos)}</td>
                </tr>
              ))}
              {c && (
                <tr className="bg-neutral-50 font-semibold">
                  <td colSpan={5} className="p-3 text-right">Total Material Cost</td>
                  <td className="p-3 text-right tabular-nums">{peso(c.material_cost_centavos)}</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </Card>

      {/* Routing / Labor Breakdown */}
      {c && c.routings.length > 0 && (
        <Card className="overflow-hidden">
          <div className="p-4 border-b border-neutral-200 flex items-center gap-2">
            <Wrench className="w-4 h-4 text-amber-500" />
            <h3 className="font-semibold text-neutral-800">Routing / Labor ({c.routings.length} steps)</h3>
          </div>
          <table className="w-full text-sm">
            <thead className="bg-neutral-50">
              <tr>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">#</th>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">Operation</th>
                <th className="text-left p-3 text-xs font-medium text-neutral-600">Work Center</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Setup (hrs)</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Run (hrs)</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Labor</th>
                <th className="text-right p-3 text-xs font-medium text-neutral-600">Overhead</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {c.routings.map((step) => (
                <tr key={step.sequence} className="hover:bg-neutral-50">
                  <td className="p-3 text-neutral-500">{step.sequence}</td>
                  <td className="p-3 font-medium">{step.operation_name}</td>
                  <td className="p-3 text-neutral-600">{step.work_center_name}</td>
                  <td className="p-3 text-right tabular-nums">{step.setup_hours}</td>
                  <td className="p-3 text-right tabular-nums">{step.run_hours_per_unit}</td>
                  <td className="p-3 text-right tabular-nums text-amber-600">{peso(step.labor_cost_centavos)}</td>
                  <td className="p-3 text-right tabular-nums text-purple-600">{peso(step.overhead_cost_centavos)}</td>
                </tr>
              ))}
              <tr className="bg-neutral-50 font-semibold">
                <td colSpan={5} className="p-3 text-right">Total Labor + Overhead</td>
                <td className="p-3 text-right tabular-nums text-amber-600">{peso(c.labor_cost_centavos)}</td>
                <td className="p-3 text-right tabular-nums text-purple-600">{peso(c.overhead_cost_centavos)}</td>
              </tr>
            </tbody>
          </table>
        </Card>
      )}

      {/* BOM Info */}
      <Card className="p-4">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span className="text-neutral-500">Production Days</span>
            <p className="font-medium">{b.standard_production_days ?? '-'}</p>
          </div>
          <div>
            <span className="text-neutral-500">Created</span>
            <p className="font-medium">{b.created_at ? new Date(b.created_at).toLocaleDateString('en-PH') : '-'}</p>
          </div>
          <div>
            <span className="text-neutral-500">Updated</span>
            <p className="font-medium">{b.updated_at ? new Date(b.updated_at).toLocaleDateString('en-PH') : '-'}</p>
          </div>
          <div>
            <span className="text-neutral-500">Notes</span>
            <p className="font-medium">{b.notes ?? '-'}</p>
          </div>
        </div>
      </Card>
    </div>
  )
}
