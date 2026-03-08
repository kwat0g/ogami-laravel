import { useState } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { ArrowLeft, Factory, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import {
  useProductionOrder,
  useReleaseOrder,
  useStartOrder,
  useCompleteOrder,
  useCancelOrder,
  useVoidOrder,
  useLogOutput,
} from '@/hooks/useProduction'
import { usePermission } from '@/hooks/usePermission'
import { useEmployees, useDepartments } from '@/hooks/useEmployees'
import { isHandledApiError } from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import type { ProductionOrderStatus } from '@/types/production'

const statusBadge: Record<ProductionOrderStatus, string> = {
  draft:       'bg-neutral-100 text-neutral-600',
  released:    'bg-neutral-200 text-neutral-800',
  in_progress: 'bg-neutral-100 text-neutral-700',
  completed:   'bg-neutral-200 text-neutral-800',
  cancelled:   'bg-neutral-100 text-neutral-400',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-4 py-2 border-b border-neutral-100 last:border-0">
      <dt className="text-sm text-neutral-500 w-36 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-neutral-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

export default function ProductionOrderDetailPage(): React.ReactElement {
  const { ulid }  = useParams<{ ulid: string }>()
  const navigate  = useNavigate()
  const [showLogForm, setShowLogForm]   = useState(false)
  const [showVoidConfirm, setShowVoidConfirm] = useState(false)
  const [logData, setLogData]           = useState({
    shift: 'A' as 'A' | 'B' | 'C',
    log_date: new Date().toISOString().split('T')[0],
    qty_produced: '',
    qty_rejected: '0',
    operator_id: '',
    remarks: '',
  })

  const { data: order, isLoading, isError } = useProductionOrder(ulid ?? null)
  const { data: departmentsData } = useDepartments()
  const prodDeptId = departmentsData?.data?.find(d => d.code === 'PROD')?.id
  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true, department_id: prodDeptId })
  const employees = employeesData?.data ?? []

  const canRelease    = usePermission('production.orders.release')
  const canComplete   = usePermission('production.orders.complete')
  const canLogOutput  = usePermission('production.orders.log_output')

  const releaseMut  = useReleaseOrder(ulid ?? '')
  const startMut    = useStartOrder(ulid ?? '')
  const completeMut = useCompleteOrder(ulid ?? '')
  const cancelMut   = useCancelOrder(ulid ?? '')
  const voidMut     = useVoidOrder(ulid ?? '')
  const logMut      = useLogOutput(ulid ?? '')

  const anyPending = releaseMut.isPending || startMut.isPending || completeMut.isPending || cancelMut.isPending || voidMut.isPending

  const handleAction = async (action: 'release' | 'start' | 'complete' | 'cancel') => {
    if (action === 'complete' && order) {
      const produced  = parseFloat(order.qty_produced)
      const required  = parseFloat(order.qty_required)
      if (produced < required) {
        const pct = ((produced / required) * 100).toFixed(1)
        const ok  = window.confirm(
          `Only ${produced.toLocaleString('en-PH')} of ${required.toLocaleString('en-PH')} units produced (${pct}%).\n\nComplete the work order short?`
        )
        if (!ok) return
      }
    }
    try {
      const map = { release: releaseMut, start: startMut, complete: completeMut, cancel: cancelMut }
      await map[action].mutateAsync()
      toast.success(`Work order ${action}d.`)
    } catch (err) {
      if (isHandledApiError(err)) return
      const msg = (err as { message?: string })?.message
      toast.error(msg ?? `Failed to ${action} order.`)
    }
  }

  const handleLogOutput = async () => {
    try {
      await logMut.mutateAsync({
        shift:        logData.shift,
        log_date:     logData.log_date,
        qty_produced: parseFloat(logData.qty_produced),
        qty_rejected: parseFloat(logData.qty_rejected || '0'),
        operator_id:  parseInt(logData.operator_id),
        remarks:      logData.remarks || undefined,
      })
      toast.success('Output logged.')
      setShowLogForm(false)
    } catch (err) {
      if (isHandledApiError(err)) return
      toast.error('Failed to log output.')
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !order) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load work order.
    </div>
  )

  return (
    <div className="max-w-7xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <button onClick={() => navigate('/production/orders')} className="p-2 hover:bg-neutral-100 rounded">
          <ArrowLeft className="w-4 h-4 text-neutral-500" />
        </button>
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-neutral-900 font-mono">{order.po_reference}</h1>
            {order.status === 'released' && order.mrq_pending ? (
              <span className="inline-flex px-2.5 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700">
                Released — Pending MRQ
              </span>
            ) : (
              <span className={`inline-flex px-2.5 py-1 rounded text-xs font-medium capitalize ${statusBadge[order.status]}`}>
                {order.status?.replace('_', ' ') || 'Unknown'}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Work Order Details</h2>
        <dl>
          <InfoRow label="Product" value={`${order.product_item?.item_code} — ${order.product_item?.name}`} />
          <InfoRow label="BOM Version" value={order.bom ? `v${order.bom.version}` : '—'} />
          <InfoRow label="Qty Required" value={parseFloat(order.qty_required).toLocaleString('en-PH', { maximumFractionDigits: 4 })} />
          <InfoRow label="Qty Produced" value={
            <div className="flex items-center gap-2">
              <span>{parseFloat(order.qty_produced).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</span>
              <div className="flex items-center gap-1">
                <div className="h-2 w-24 bg-neutral-200 rounded-full overflow-hidden">
                  <div className="h-full bg-neutral-600 rounded-full" style={{ width: `${Math.min(100, order.progress_pct)}%` }} />
                </div>
                <span className="text-xs text-neutral-400">{order.progress_pct.toFixed(1)}%</span>
              </div>
            </div>
          } />
          <InfoRow label="Target Start" value={order.target_start_date} />
          <InfoRow label="Target End"   value={order.target_end_date} />
          {order.delivery_schedule && (
            <InfoRow 
              label="Delivery Schedule" 
              value={
                <Link 
                  to={`/production/delivery-schedules/${order.delivery_schedule.ulid}`}
                  className="underline underline-offset-2 text-neutral-700 hover:text-neutral-900 font-medium"
                >
                  {order.delivery_schedule.ds_reference}
                </Link>
              } 
            />
          )}
          <InfoRow label="Created By" value={order.created_by?.name} />
        </dl>
      </div>

      {/* BOM Components */}
      {order.bom && (order.bom.components ?? []).length > 0 && (
        <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
          <h2 className="text-sm font-medium text-neutral-700 mb-3">BOM Components</h2>
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {['Component', 'Qty/Unit', 'UOM', 'Scrap %'].map((h) => (
                  <th key={h} className="px-3 py-2 text-left text-xs font-medium text-neutral-600">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {order.bom.components?.map((comp) => (
                <tr key={comp.id}>
                  <td className="px-3 py-2">
                    <div className="font-mono text-xs text-neutral-400">{comp.component_item?.item_code}</div>
                    <div className="text-sm text-neutral-800">{comp.component_item?.name}</div>
                  </td>
                  <td className="px-3 py-2 tabular-nums">{parseFloat(comp.qty_per_unit).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                  <td className="px-3 py-2 text-neutral-400">{comp.unit_of_measure}</td>
                  <td className="px-3 py-2 text-neutral-400">{comp.scrap_factor_pct}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Output Logs */}
      {(order.output_logs ?? []).length > 0 && (
        <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
          <h2 className="text-sm font-medium text-neutral-700 mb-3">Output Logs</h2>
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {['Date', 'Shift', 'Produced', 'Rejected', 'Operator', 'Recorded By'].map((h) => (
                  <th key={h} className="px-3 py-2 text-left text-xs font-medium text-neutral-600">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {order.output_logs?.map((log) => (
                <tr key={log.id}>
                  <td className="px-3 py-2 text-neutral-500 text-xs">{log.log_date}</td>
                  <td className="px-3 py-2 font-semibold">{log.shift}</td>
                  <td className="px-3 py-2 tabular-nums text-neutral-900 font-semibold">{parseFloat(log.qty_produced).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                  <td className="px-3 py-2 tabular-nums text-red-600">{parseFloat(log.qty_rejected).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                  <td className="px-3 py-2 text-neutral-500">{log.operator?.name ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-400 text-xs">{log.recorded_by?.name ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Actions */}
      <div className="bg-white border border-neutral-200 rounded p-6">
        <h2 className="text-sm font-medium text-neutral-700 mb-4">Actions</h2>

        {showLogForm && (
          <div className="bg-neutral-50 border border-neutral-200 rounded p-4 mb-4 space-y-3">
            <h3 className="text-sm font-medium text-neutral-700">Log Production Output</h3>
            <div className="grid grid-cols-3 gap-3">
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Shift</label>
                <select
                  value={logData.shift}
                  onChange={(e) => setLogData((d) => ({ ...d, shift: e.target.value as 'A' | 'B' | 'C' }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="A">Shift A</option>
                  <option value="B">Shift B</option>
                  <option value="C">Shift C</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Date</label>
                <input
                  type="date"
                  value={logData.log_date}
                  onChange={(e) => setLogData((d) => ({ ...d, log_date: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Operator</label>
                <select
                  value={logData.operator_id}
                  onChange={(e) => setLogData((d) => ({ ...d, operator_id: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">— Select Operator —</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.full_name}{emp.position?.title ? ` — ${emp.position.title}` : ''}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Qty Produced</label>
                <input
                  type="number"
                  step="0.0001"
                  value={logData.qty_produced}
                  onChange={(e) => setLogData((d) => ({ ...d, qty_produced: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Qty Rejected</label>
                <input
                  type="number"
                  step="0.0001"
                  value={logData.qty_rejected}
                  onChange={(e) => setLogData((d) => ({ ...d, qty_rejected: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Remarks</label>
                <input
                  value={logData.remarks}
                  onChange={(e) => setLogData((d) => ({ ...d, remarks: e.target.value }))}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
            </div>
            <div className="flex gap-2">
              <button
                onClick={handleLogOutput}
                disabled={logMut.isPending || !logData.qty_produced}
                className="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Submit Log
              </button>
              <button onClick={() => setShowLogForm(false)} className="px-4 py-2 border border-neutral-300 text-neutral-600 text-sm rounded hover:bg-neutral-50">
                Cancel
              </button>
            </div>
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {order.status === 'draft' && canRelease && (
            <button onClick={() => handleAction('release')} disabled={anyPending} className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
              Release
            </button>
          )}
          {order.status === 'released' && canRelease && (
            <button onClick={() => handleAction('start')} disabled={anyPending} className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
              Start Production
            </button>
          )}
          {order.status === 'in_progress' && canComplete && !showLogForm && (
            <button
              onClick={() => handleAction('complete')}
              disabled={anyPending || parseFloat(order.qty_produced) <= 0}
              title={parseFloat(order.qty_produced) <= 0 ? 'Log production output before completing' : undefined}
              className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Mark Complete
            </button>
          )}
          {order.status === 'in_progress' && canLogOutput && !showLogForm && (
            <button onClick={() => setShowLogForm(true)} className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-700 hover:bg-neutral-50 rounded">
              Log Output
            </button>
          )}
          {['draft', 'released'].includes(order.status) && (
            <button onClick={() => handleAction('cancel')} disabled={anyPending} className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-600 hover:bg-neutral-50 rounded">
              Cancel WO
            </button>
          )}
          {order.status === 'in_progress' && parseFloat(order.qty_produced) === 0 && !showLogForm && (
            <button
              onClick={() => setShowVoidConfirm(true)}
              disabled={voidMut.isPending}
              className="px-4 py-2 text-sm font-medium border border-red-200 text-red-600 hover:bg-red-50 rounded disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Void WO
            </button>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={showVoidConfirm}
        onClose={() => setShowVoidConfirm(false)}
        onConfirm={async () => {
          try {
            await voidMut.mutateAsync()
            toast.success('Work order voided.')
            setShowVoidConfirm(false)
          } catch (err) {
            if (isHandledApiError(err)) return
            toast.error('Failed to void work order.')
          }
        }}
        title="Void work order?"
        description="This work order will be cancelled and cannot be restarted."
        confirmLabel="Void WO"
        loading={voidMut.isPending}
        variant="danger"
      />
    </div>
  )
}
