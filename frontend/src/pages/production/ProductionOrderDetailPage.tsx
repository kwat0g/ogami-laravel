import { useState } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { ArrowLeft, AlertTriangle, Package, ShieldAlert, ShieldCheck, CheckCircle, XCircle } from 'lucide-react'
import { toast } from 'sonner'
import {
  useProductionOrder,
  useReleaseOrder,
  useStartOrder,
  useCompleteOrder,
  useCancelOrder,
  useVoidOrder,
  useLogOutput,
  useStockCheck,
  useForceRelease,
  type StockCheckItem,
} from '@/hooks/useProduction'
import { usePermission } from '@/hooks/usePermission'
import { useEmployees, useDepartments } from '@/hooks/useEmployees'
import { isHandledApiError } from '@/lib/api'
import { firstErrorMessage } from '@/lib/errorHandler'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import StatusTimeline from '@/components/ui/StatusTimeline'
import { getProductionOrderSteps, isRejectedStatus } from '@/lib/workflowSteps'
import type { ProductionOrderStatus } from '@/types/production'

type ConfirmAction = 'release' | 'start' | 'complete' | 'cancel' | null

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
  const [logData, setLogData]           = useState({
    shift: 'A' as 'A' | 'B' | 'C',
    log_date: new Date().toISOString().split('T')[0],
    qty_produced: '',
    qty_rejected: '0',
    operator_id: '',
    remarks: '',
  })
  const [logErrors, setLogErrors] = useState<Record<string, string>>({})

  // ── Stock Check + QC Override state ──────────────────────────────────────
  const [showStockModal, setShowStockModal] = useState(false)
  const [stockItems, setStockItems] = useState<StockCheckItem[]>([])
  const [showQcOverrideModal, setShowQcOverrideModal] = useState(false)
  const [qcErrorMessage, setQcErrorMessage] = useState('')

  const { data: order, isLoading, isError } = useProductionOrder(ulid ?? null)
  const { data: departmentsData } = useDepartments()
  const prodDeptId = departmentsData?.data?.find(d => d.code === 'PROD')?.id
  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true, department_id: prodDeptId })
  const employees = employeesData?.data ?? []

  const canRelease    = usePermission('production.orders.release')
  const canComplete   = usePermission('production.orders.complete')
  const canLogOutput  = usePermission('production.orders.log_output')
  const canQcOverride = usePermission('production.qc-override')
  const canCreate     = usePermission('production.orders.create')

  const releaseMut    = useReleaseOrder(ulid ?? '')
  const startMut      = useStartOrder(ulid ?? '')
  const completeMut   = useCompleteOrder(ulid ?? '')
  const cancelMut     = useCancelOrder(ulid ?? '')
  const voidMut       = useVoidOrder(ulid ?? '')
  const logMut        = useLogOutput(ulid ?? '')
  const stockCheckQ   = useStockCheck(ulid ?? null)
  const forceRelease  = useForceRelease(ulid ?? '')

  const anyPending = releaseMut.isPending || startMut.isPending || completeMut.isPending || cancelMut.isPending || voidMut.isPending

  const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null)
  const [showCompleteConfirm, setShowCompleteConfirm] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)
  const [showVoidConfirm, setShowVoidConfirm] = useState(false)

  // ── PROD-001: Pre-release stock check flow ──────────────────────────────
  const handleReleaseClick = async () => {
    // Always do a stock check first for draft orders
    try {
      const result = await stockCheckQ.refetch()
      const items = result.data ?? []
      setStockItems(items)

      const allSufficient = items.length === 0 || items.every(i => i.sufficient)
      if (allSufficient) {
        // Stock is OK — go straight to confirm dialog
        setConfirmAction('release')
      } else {
        // Show stock shortage modal
        setShowStockModal(true)
      }
    } catch {
      // If stock check fails, still allow release attempt
      setConfirmAction('release')
    }
  }

  const handleAction = async (action: 'release' | 'start' | 'complete' | 'cancel') => {
    if (action === 'release') {
      await handleReleaseClick()
      return
    }

    if (action === 'complete' && order) {
      const produced  = parseFloat(order.qty_produced)
      const required  = parseFloat(order.qty_required)
      if (produced < required) {
        setShowCompleteConfirm(true)
        return
      }
      setConfirmAction('complete')
      return
    }
    
    if (action === 'cancel') {
      setShowCancelConfirm(true)
      return
    }
    
    setConfirmAction(action)
  }

  // ── PROD-002: Handle release with QC gate error detection ───────────────
  const executeAction = async () => {
    if (!confirmAction) return
    try {
      const map = { release: releaseMut, start: startMut, complete: completeMut, cancel: cancelMut }
      await map[confirmAction].mutateAsync()
      const actionText = confirmAction === 'release' ? 'released' : 
                        confirmAction === 'start' ? 'started' : 
                        confirmAction === 'complete' ? 'completed' : 'cancelled'
      toast.success(`Work order ${actionText} successfully.`)
      setConfirmAction(null)
    } catch (err) {
      setConfirmAction(null)
      // PROD-002: Detect QC gate blocked error
      const apiErr = err as { response?: { data?: { error_code?: string; message?: string } } }
      if (apiErr?.response?.data?.error_code === 'PROD_QC_GATE_BLOCKED') {
        setQcErrorMessage(apiErr.response.data.message ?? 'Release blocked by failed QC inspections.')
        setShowQcOverrideModal(true)
        return
      }
      if (isHandledApiError(err)) return
      toast.error(firstErrorMessage(err))
    }
  }
  
  const executeComplete = async () => {
    try {
      await completeMut.mutateAsync()
      toast.success('Work order completed successfully.')
      setShowCompleteConfirm(false)
    } catch (err) {
      if (isHandledApiError(err)) return
      toast.error(firstErrorMessage(err))
    }
  }
  
  const executeCancel = async () => {
    try {
      await cancelMut.mutateAsync()
      toast.success('Work order cancelled successfully.')
      setShowCancelConfirm(false)
    } catch (err) {
      if (isHandledApiError(err)) return
      toast.error(firstErrorMessage(err))
    }
  }
  
  const executeVoid = async () => {
    try {
      await voidMut.mutateAsync()
      toast.success('Work order voided successfully.')
      setShowVoidConfirm(false)
    } catch (err) {
      if (isHandledApiError(err)) return
      toast.error(firstErrorMessage(err))
    }
  }

  // ── PROD-002: Force release with QC override ───────────────────────────
  const handleForceRelease = async () => {
    try {
      await forceRelease.mutateAsync()
      toast.success('Work order released (QC override applied).')
      setShowQcOverrideModal(false)
    } catch (err) {
      if (isHandledApiError(err)) return
      toast.error(firstErrorMessage(err))
    }
  }

  const validateLogData = (): boolean => {
    const errors: Record<string, string> = {}
    
    if (!logData.qty_produced || parseFloat(logData.qty_produced) <= 0) {
      errors.qty_produced = 'Quantity produced must be greater than 0.'
    }
    if (!logData.operator_id) {
      errors.operator_id = 'Operator is required.'
    }
    
    setLogErrors(errors)
    
    if (Object.keys(errors).length > 0) {
      toast.error(Object.values(errors)[0])
      return false
    }
    
    return true
  }

  const handleLogOutput = async () => {
    if (!validateLogData()) return
    
    try {
      await logMut.mutateAsync({
        shift:        logData.shift,
        log_date:     logData.log_date,
        qty_produced: parseFloat(logData.qty_produced),
        qty_rejected: parseFloat(logData.qty_rejected || '0'),
        operator_id:  parseInt(logData.operator_id),
        remarks:      logData.remarks || undefined,
      })
      toast.success('Output logged successfully.')
      setShowLogForm(false)
      setLogData({
        shift: 'A',
        log_date: new Date().toISOString().split('T')[0],
        qty_produced: '',
        qty_rejected: '0',
        operator_id: '',
        remarks: '',
      })
      setLogErrors({})
    } catch (err) {
      if (isHandledApiError(err)) return
      toast.error(firstErrorMessage(err))
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !order) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load work order.
    </div>
  )

  const completionPercentage = order ? ((parseFloat(order.qty_produced || '0') / (parseFloat(order.qty_required || '1') || 1)) * 100).toFixed(1) : '0'

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

      {/* Workflow Timeline */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-5">
        <StatusTimeline
          steps={getProductionOrderSteps(order)}
          currentStatus={order.status}
          direction="horizontal"
          isRejected={isRejectedStatus(order.status)}
        />
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Work Order Details</h2>
        <dl>
          <InfoRow label="Product" value={`${order.product_item?.item_code} — ${order.product_item?.name}`} />
          <InfoRow label="BOM Version" value={order.bom ? `v${order.bom.version}` : '—'} />
          <InfoRow label="Qty Required" value={parseFloat(order.qty_required || '0').toLocaleString('en-PH', { maximumFractionDigits: 4 })} />
          <InfoRow label="Qty Produced" value={
            <div className="flex items-center gap-2">
              <span>{parseFloat(order.qty_produced || '0').toLocaleString('en-PH', { maximumFractionDigits: 4 })}</span>
              <div className="flex items-center gap-1">
                <div className="h-2 w-24 bg-neutral-200 rounded-full overflow-hidden">
                  <div className="h-full bg-neutral-600 rounded-full" style={{ width: `${Math.min(100, order.progress_pct ?? 0)}%` }} />
                </div>
                <span className="text-xs text-neutral-400">{(order.progress_pct ?? 0).toFixed(1)}%</span>
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

      {/* QC Inspections */}
      {(order.inspections ?? []).length > 0 && (
        <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
          <h2 className="text-sm font-medium text-neutral-700 mb-3">QC Inspections</h2>
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {['Reference', 'Date', 'Stage', 'Inspected', 'Passed', 'Failed', 'Status'].map((h) => (
                  <th key={h} className="px-3 py-2 text-left text-xs font-medium text-neutral-600">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {order.inspections?.map((ins: any) => (
                <tr key={ins.ulid}>
                  <td className="px-3 py-2">
                    <Link to={`/qc/inspections/${ins.ulid}`} className="font-mono text-sm text-amber-700 hover:text-amber-900 font-medium underline underline-offset-2">
                      {ins.ulid.slice(0, 8)}...
                    </Link>
                  </td>
                  <td className="px-3 py-2 text-neutral-500 text-xs">{ins.inspection_date ?? '—'}</td>
                  <td className="px-3 py-2 font-semibold uppercase text-neutral-700 text-xs">{ins.stage}</td>
                  <td className="px-3 py-2 tabular-nums text-neutral-900">{parseFloat(ins.qty_inspected || 0).toLocaleString('en-PH')}</td>
                  <td className="px-3 py-2 tabular-nums text-emerald-600 font-medium">{parseFloat(ins.qty_passed || 0).toLocaleString('en-PH')}</td>
                  <td className="px-3 py-2 tabular-nums text-red-600 font-medium">{parseFloat(ins.qty_failed || 0).toLocaleString('en-PH')}</td>
                  <td className="px-3 py-2">
                    <span className={`inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                      ins.status === 'passed' ? 'bg-emerald-100 text-emerald-800' :
                      ins.status === 'failed' ? 'bg-red-100 text-red-800' :
                      'bg-amber-100 text-amber-800'
                    }`}>
                      {ins.status}
                    </span>
                  </td>
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
                <label className="block text-xs font-medium text-neutral-600 mb-1">Operator *</label>
                <select
                  value={logData.operator_id}
                  onChange={(e) => {
                    setLogData((d) => ({ ...d, operator_id: e.target.value }))
                    if (logErrors.operator_id) {
                      setLogErrors(prev => ({ ...prev, operator_id: '' }))
                    }
                  }}
                  className={`w-full text-sm border rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${logErrors.operator_id ? 'border-red-400' : 'border-neutral-300'}`}
                >
                  <option value="">— Select Operator —</option>
                  {employees.map(emp => (
                    <option key={emp.id} value={emp.id}>{emp.full_name}{emp.position?.title ? ` — ${emp.position.title}` : ''}</option>
                  ))}
                </select>
                {logErrors.operator_id && <p className="mt-1 text-xs text-red-600">{logErrors.operator_id}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Qty Produced *</label>
                <input
                  type="number"
                  step="0.0001"
                  min="0.0001"
                  value={logData.qty_produced}
                  onChange={(e) => {
                    setLogData((d) => ({ ...d, qty_produced: e.target.value }))
                    if (logErrors.qty_produced) {
                      setLogErrors(prev => ({ ...prev, qty_produced: '' }))
                    }
                  }}
                  className={`w-full text-sm border rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${logErrors.qty_produced ? 'border-red-400' : 'border-neutral-300'}`}
                />
                {logErrors.qty_produced && <p className="mt-1 text-xs text-red-600">{logErrors.qty_produced}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">Qty Rejected</label>
                <input
                  type="number"
                  step="0.0001"
                  min="0"
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
                disabled={logMut.isPending}
                className="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Submit Log
              </button>
              <button 
                onClick={() => {
                  setShowLogForm(false)
                  setLogErrors({})
                }} 
                className="px-4 py-2 border border-neutral-300 text-neutral-600 text-sm rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
            </div>
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {order.status === 'draft' && canRelease && (
            <ConfirmDialog
              title="Release Work Order?"
              description="This will release the work order to production and deduct BOM materials from inventory."
              confirmLabel="Release"
              onConfirm={async () => {
                await handleAction('release')
              }}
            >
              <button disabled={anyPending || stockCheckQ.isFetching} className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
                {stockCheckQ.isFetching ? 'Checking stock…' : 'Release'}
              </button>
            </ConfirmDialog>
          )}
          {order.status === 'released' && canRelease && (
            <ConfirmDialog
              title="Start Production?"
              description="This will mark the work order as in progress."
              confirmLabel="Start"
              onConfirm={async () => {
                setConfirmAction('start')
                await executeAction()
              }}
            >
              <button disabled={anyPending} className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">
                Start Production
              </button>
            </ConfirmDialog>
          )}
          {order.status === 'in_progress' && canComplete && !showLogForm && (
            <ConfirmDialog
              title="Complete Work Order?"
              description={`This will mark the work order as completed. Current progress: ${completionPercentage}%`}
              confirmLabel="Complete"
              onConfirm={async () => {
                await handleAction('complete')
              }}
            >
              <button
                disabled={anyPending || parseFloat(order.qty_produced) <= 0}
                title={parseFloat(order.qty_produced) <= 0 ? 'Log production output before completing' : undefined}
                className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Mark Complete
              </button>
            </ConfirmDialog>
          )}
          {order.status === 'in_progress' && canLogOutput && !showLogForm && (
            <button onClick={() => setShowLogForm(true)} className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-700 hover:bg-neutral-50 rounded">
              Log Output
            </button>
          )}
          {['draft', 'released'].includes(order.status) && canCreate && (
            <ConfirmDialog
              title="Cancel Work Order?"
              description="This will cancel the work order. This action cannot be undone."
              confirmLabel="Cancel WO"
              variant="danger"
              onConfirm={async () => {
                await handleAction('cancel')
              }}
            >
              <button disabled={anyPending} className="px-4 py-2 text-sm font-medium border border-amber-300 text-amber-700 hover:bg-amber-50 rounded">
                Cancel WO
              </button>
            </ConfirmDialog>
          )}
          {order.status === 'in_progress' && parseFloat(order.qty_produced) === 0 && !showLogForm && canCreate && (
            <ConfirmDestructiveDialog
              title="Void Work Order?"
              description="This work order will be voided and cannot be restarted. This action is irreversible."
              confirmWord="VOID"
              confirmLabel="Void WO"
              onConfirm={executeVoid}
            >
              <button
                disabled={voidMut.isPending}
                className="px-4 py-2 text-sm font-medium border border-red-200 text-red-600 hover:bg-red-50 rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Void WO
              </button>
            </ConfirmDestructiveDialog>
          )}
        </div>
      </div>

      {/* ── PROD-001: Stock Check Modal ──────────────────────────────────────── */}
      {showStockModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
            <div className="flex items-center gap-3 px-6 py-4 border-b border-neutral-200">
              <Package className="w-5 h-5 text-amber-600" />
              <h3 className="text-base font-semibold text-neutral-900">Stock Availability Check</h3>
            </div>
            <div className="px-6 py-4">
              <p className="text-sm text-neutral-600 mb-4">
                Some BOM components have insufficient stock. The release will attempt to deduct materials — if stock is truly insufficient, it will fail.
              </p>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-neutral-200">
                    <th className="text-left text-xs font-medium text-neutral-500 py-2">Component</th>
                    <th className="text-right text-xs font-medium text-neutral-500 py-2">Required</th>
                    <th className="text-right text-xs font-medium text-neutral-500 py-2">Available</th>
                    <th className="text-center text-xs font-medium text-neutral-500 py-2">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {stockItems.map((item) => (
                    <tr key={item.component_item_id}>
                      <td className="py-2 text-neutral-800">{item.item_name}</td>
                      <td className="py-2 text-right tabular-nums">{item.required_qty.toLocaleString('en-PH', { maximumFractionDigits: 4 })} {item.unit_of_measure}</td>
                      <td className="py-2 text-right tabular-nums">{item.available_qty.toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                      <td className="py-2 text-center">
                        {item.sufficient ? (
                          <CheckCircle className="w-4 h-4 text-emerald-500 mx-auto" />
                        ) : (
                          <XCircle className="w-4 h-4 text-red-500 mx-auto" />
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 border-t border-neutral-200 bg-neutral-50 rounded-b-lg">
              <button
                onClick={() => setShowStockModal(false)}
                className="px-4 py-2 text-sm text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-100"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  setShowStockModal(false)
                  setConfirmAction('release')
                }}
                className="px-4 py-2 text-sm font-medium bg-amber-600 hover:bg-amber-700 text-white rounded"
              >
                Release Anyway
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── PROD-002: QC Override Modal ───────────────────────────────────────── */}
      {showQcOverrideModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="flex items-center gap-3 px-6 py-4 border-b border-neutral-200">
              <ShieldAlert className="w-5 h-5 text-red-600" />
              <h3 className="text-base font-semibold text-neutral-900">QC Gate Blocked</h3>
            </div>
            <div className="px-6 py-4">
              <p className="text-sm text-neutral-700 mb-3">{qcErrorMessage}</p>
              {canQcOverride ? (
                <div className="bg-amber-50 border border-amber-200 rounded p-3">
                  <div className="flex items-start gap-2">
                    <ShieldCheck className="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" />
                    <p className="text-sm text-amber-800">
                      You have the <strong>QC Override</strong> permission. You may force-release this order despite the failed inspections.
                    </p>
                  </div>
                </div>
              ) : (
                <div className="bg-red-50 border border-red-200 rounded p-3">
                  <p className="text-sm text-red-700">
                    You do not have permission to override QC blocks. Contact a user with the <strong>QC Override</strong> permission, or resolve the failed inspection(s) first.
                  </p>
                </div>
              )}
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 border-t border-neutral-200 bg-neutral-50 rounded-b-lg">
              <button
                onClick={() => setShowQcOverrideModal(false)}
                className="px-4 py-2 text-sm text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-100"
              >
                {canQcOverride ? 'Cancel' : 'Close'}
              </button>
              {canQcOverride && (
                <button
                  onClick={handleForceRelease}
                  disabled={forceRelease.isPending}
                  className="px-4 py-2 text-sm font-medium bg-red-600 hover:bg-red-700 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {forceRelease.isPending ? 'Releasing…' : 'Override QC & Release'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Action Confirmation Dialog */}
      <ConfirmDialog
        open={confirmAction !== null}
        onClose={() => setConfirmAction(null)}
        onConfirm={executeAction}
        title={`${confirmAction?.charAt(0).toUpperCase()}${confirmAction?.slice(1)} work order?`}
        description={
          confirmAction === 'release' ? 'This will release the work order to production and deduct BOM materials from inventory.' :
          confirmAction === 'start' ? 'This will mark the work order as in progress.' :
          confirmAction === 'complete' ? 'This will mark the work order as completed.' :
          confirmAction === 'cancel' ? 'This will cancel the work order.' :
          'Are you sure you want to proceed?'
        }
        confirmLabel={confirmAction ? `${confirmAction.charAt(0).toUpperCase()}${confirmAction.slice(1)}` : 'Confirm'}
        loading={
          confirmAction === 'release' ? releaseMut.isPending :
          confirmAction === 'start' ? startMut.isPending :
          confirmAction === 'complete' ? completeMut.isPending :
          confirmAction === 'cancel' ? cancelMut.isPending :
          false
        }
        variant={confirmAction === 'cancel' ? 'warning' : 'primary'}
      />
      
      {/* Complete Short Production Confirmation */}
      <ConfirmDialog
        open={showCompleteConfirm}
        onClose={() => setShowCompleteConfirm(false)}
        onConfirm={executeComplete}
        title="Complete Work Order Short?"
        description={`Only ${parseFloat(order.qty_produced).toLocaleString('en-PH')} of ${parseFloat(order.qty_required).toLocaleString('en-PH')} units produced (${completionPercentage}%). Complete the work order short?`}
        confirmLabel="Complete Short"
        loading={completeMut.isPending}
        variant="warning"
      />
      
      {/* Cancel Confirmation */}
      <ConfirmDialog
        open={showCancelConfirm}
        onClose={() => setShowCancelConfirm(false)}
        onConfirm={executeCancel}
        title="Cancel Work Order?"
        description="This will cancel the work order. This action cannot be undone."
        confirmLabel="Cancel WO"
        loading={cancelMut.isPending}
        variant="danger"
      />

      {/* Void Confirmation Dialog - using ConfirmDestructiveDialog inline */}
      <ConfirmDialog
        open={showVoidConfirm}
        onClose={() => setShowVoidConfirm(false)}
        onConfirm={executeVoid}
        title="Void Work Order?"
        description="This work order will be voided and cannot be restarted."
        confirmLabel="Void WO"
        loading={voidMut.isPending}
        variant="danger"
      />
    </div>
  )
}
