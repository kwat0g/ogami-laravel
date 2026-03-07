import { useState, useMemo } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, ClipboardCheck, AlertTriangle, Plus, Trash2, CheckCircle2, XCircle, Ban } from 'lucide-react'
import { toast } from 'sonner'

import { useCancelResults, useDeleteInspection, useInspection, useRecordResults } from '@/hooks/useQC'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { InspectionStage, InspectionStatus } from '@/types/qc'

interface ResultRow {
  inspection_template_item_id?: number
  criterion: string
  actual_value: string
  is_conforming: boolean | null
  remarks: string
}

const stageBadge: Record<InspectionStage, string> = {
  iqc:  'bg-blue-100 text-blue-700',
  ipqc: 'bg-amber-100 text-amber-700',
  oqc:  'bg-purple-100 text-purple-700',
}

const statusBadge: Record<InspectionStatus, string> = {
  open:    'bg-gray-100 text-gray-600',
  passed:  'bg-green-100 text-green-700',
  failed:  'bg-red-100 text-red-700',
  on_hold: 'bg-yellow-100 text-yellow-700',
  voided:  'bg-gray-200 text-gray-400',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-4 py-2 border-b border-gray-100 last:border-0">
      <dt className="text-sm text-gray-500 w-36 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-gray-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

export default function InspectionDetailPage(): React.ReactElement {
  const { ulid }   = useParams<{ ulid: string }>()
  const navigate   = useNavigate()
  const { data: inspection, isLoading, isError } = useInspection(ulid ?? null)
  const recordMut  = useRecordResults(ulid ?? '')
  const cancelMut  = useCancelResults(ulid ?? '')
  const deleteMut  = useDeleteInspection()

  // ── Record-results form state ─────────────────────────────────────────────
  const [showForm, setShowForm] = useState(false)
  const [qtyPassed, setQtyPassed] = useState('')
  const [rows, setRows]      = useState<ResultRow[]>([])
  const [formInit, setFormInit] = useState(false)
  const [touchedQty, setTouchedQty] = useState(false)

  // ── Dismiss inspection state ───────────────────────────────────────────────
  const [showDismissConfirm, setShowDismissConfirm] = useState(false)

  async function handleDismiss() {
    if (!ulid) return
    try {
      await deleteMut.mutateAsync(ulid)
      toast.success('Inspection dismissed.')
      navigate('/qc/inspections')
    } catch {
      toast.error('Failed to dismiss inspection.')
    }
  }

  // ── Cancel-results form state ─────────────────────────────────────────────
  const [showCancelForm, setShowCancelForm] = useState(false)
  const [cancelReason, setCancelReason]     = useState('')
  const [cancelTouched, setCancelTouched]   = useState(false)

  const cancelReasonError = useMemo(() => {
    if (!cancelTouched) return undefined
    if (!cancelReason.trim()) return 'Reason is required.'
    if (cancelReason.trim().length < 10) return 'Please provide at least 10 characters.'
    return undefined
  }, [cancelTouched, cancelReason])

  function closeCancelForm() {
    setShowCancelForm(false)
    setCancelReason('')
    setCancelTouched(false)
  }

  async function handleCancelResults(e: React.FormEvent) {
    e.preventDefault()
    setCancelTouched(true)
    if (!cancelReason.trim() || cancelReason.trim().length < 10) return
    try {
      await cancelMut.mutateAsync(cancelReason.trim())
      toast.success('Inspection results cancelled — status reset to open.')
      closeCancelForm()
    } catch {
      toast.error('Failed to cancel inspection results.')
    }
  }

  const qtyInspected = Number(inspection?.qty_inspected ?? 0)
  const parsedPassed = qtyPassed === '' ? NaN : Number(qtyPassed)
  const qtyFailed    = isNaN(parsedPassed) || parsedPassed < 0 ? '' : String(Math.max(0, qtyInspected - parsedPassed))

  const qtyError = useMemo(() => {
    if (!touchedQty) return undefined
    if (qtyPassed === '' || isNaN(parsedPassed)) return 'Qty Passed is required.'
    if (parsedPassed < 0) return 'Must be ≥ 0.'
    if (parsedPassed > qtyInspected) return `Cannot exceed Qty Inspected (${qtyInspected}).`
    return undefined
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [touchedQty, qtyPassed, qtyInspected])

  function closeForm() {
    setShowForm(false)
    setQtyPassed('')
    setTouchedQty(false)
  }

  function openForm() {
    if (!inspection) return
    // Seed rows from template items, or one blank row
    if (!formInit) {
      const templateItems = inspection.template?.items ?? []
      if (templateItems.length > 0) {
        setRows(templateItems.map(ti => ({
          inspection_template_item_id: ti.id,
          criterion: ti.criterion,
          actual_value: '',
          is_conforming: null,
          remarks: '',
        })))
      } else {
        setRows([{ criterion: '', actual_value: '', is_conforming: null, remarks: '' }])
      }
      setFormInit(true)
    }
    setShowForm(true)
  }

  function updateRow(idx: number, field: keyof ResultRow, value: unknown) {
    setRows(prev => prev.map((r, i) => i === idx ? { ...r, [field]: value } : r))
  }

  function addRow() {
    setRows(prev => [...prev, { criterion: '', actual_value: '', is_conforming: null, remarks: '' }])
  }

  function removeRow(idx: number) {
    setRows(prev => prev.filter((_, i) => i !== idx))
  }

  async function handleSubmitResults(e: React.FormEvent) {
    e.preventDefault()
    setTouchedQty(true)
    const passed = Number(qtyPassed)
    const failed = Number(qtyFailed)

    if (qtyPassed === '' || isNaN(passed) || passed < 0) {
      toast.error('Qty Passed must be a valid number ≥ 0.')
      return
    }
    if (passed > qtyInspected) {
      toast.error(`Qty Passed (${passed}) cannot exceed Qty Inspected (${qtyInspected}).`)
      return
    }
    if (rows.some(r => !r.criterion.trim())) {
      toast.error('Every result row must have a criterion.')
      return
    }

    try {
      await recordMut.mutateAsync({
        qty_passed: passed,
        qty_failed: failed,
        results: rows.map(r => ({
          ...(r.inspection_template_item_id ? { inspection_template_item_id: r.inspection_template_item_id } : {}),
          criterion: r.criterion,
          actual_value: r.actual_value || undefined,
          is_conforming: r.is_conforming ?? undefined,
          remarks: r.remarks || undefined,
        })),
      })
      toast.success('Results recorded.')
      closeForm()
    } catch {
      toast.error('Failed to record results.')
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !inspection) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load inspection.
    </div>
  )

  const isOpen = inspection.status === 'open'
  const hasTemplate = (inspection.template?.items?.length ?? 0) > 0

  return (
    <div className="max-w-3xl">
      <div className="flex items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate('/qc/inspections')} className="p-2 hover:bg-gray-100 rounded-lg">
            <ArrowLeft className="w-4 h-4 text-gray-500" />
          </button>
          <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
            <ClipboardCheck className="w-5 h-5 text-teal-600" />
          </div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-gray-900 font-mono">{inspection.inspection_reference}</h1>
            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-bold uppercase ${stageBadge[inspection.stage]}`}>{inspection.stage}</span>
            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${statusBadge[inspection.status]}`}>{inspection.status.replace('_', ' ')}</span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {isOpen && !showForm && (
            <>
              <button
                onClick={openForm}
                className="flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg transition-colors"
              >
                <ClipboardCheck className="w-4 h-4" />
                Record Results
              </button>
              {!showDismissConfirm && (
                <button
                  onClick={() => setShowDismissConfirm(true)}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-500 hover:bg-gray-50 hover:border-red-300 hover:text-red-600 text-sm font-medium rounded-lg transition-colors"
                >
                  <Ban className="w-4 h-4" />
                  Dismiss
                </button>
              )}
              {showDismissConfirm && (
                <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-1.5">
                  <span className="text-xs text-red-700 font-medium">Delete this inspection?</span>
                  <button
                    onClick={() => void handleDismiss()}
                    disabled={deleteMut.isPending}
                    className="px-3 py-1 text-xs bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-medium rounded-md transition-colors"
                  >
                    {deleteMut.isPending ? 'Deleting…' : 'Yes, Delete'}
                  </button>
                  <button
                    onClick={() => setShowDismissConfirm(false)}
                    className="px-3 py-1 text-xs border border-gray-300 hover:bg-white rounded-md text-gray-600"
                  >
                    No
                  </button>
                </div>
              )}
            </>
          )}
          {!isOpen && inspection.status !== 'voided' && !showCancelForm && (
            <button
              onClick={() => setShowCancelForm(true)}
              className="flex items-center gap-2 px-4 py-2 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium rounded-lg transition-colors"
            >
              <XCircle className="w-4 h-4" />
              Cancel Results
            </button>
          )}
          {!isOpen && inspection.status !== 'voided' && showCancelForm && (
            <button
              onClick={closeCancelForm}
              className="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-600"
            >
              Dismiss
            </button>
          )}
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-gray-200 rounded-xl p-6 mb-5">
        <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Inspection Details</h2>
        <dl>
          <InfoRow label="Item"          value={inspection.item_master ? `${inspection.item_master.item_code} — ${inspection.item_master.name}` : null} />
          <InfoRow label="Lot / Batch"   value={inspection.lot_batch?.batch_number} />
          <InfoRow label="Qty Inspected" value={parseFloat(inspection.qty_inspected).toLocaleString('en-PH', { maximumFractionDigits: 4 })} />
          <InfoRow label="Qty Passed"    value={<span className="text-green-700 font-semibold">{parseFloat(inspection.qty_passed).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</span>} />
          <InfoRow label="Qty Failed"    value={<span className="text-red-600 font-semibold">{parseFloat(inspection.qty_failed).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</span>} />
          <InfoRow label="Date"          value={inspection.inspection_date} />
          <InfoRow label="Inspector"     value={inspection.inspector?.name} />
          <InfoRow label="Remarks"       value={inspection.remarks} />
        </dl>
      </div>

      {/* Cancel Results Form */}
      {!isOpen && showCancelForm && (
        <form onSubmit={(e) => void handleCancelResults(e)} className="bg-white border border-red-200 rounded-xl p-6 mb-5">
          <h2 className="text-sm font-semibold text-red-700 mb-1">Cancel Inspection Results</h2>
          <p className="text-xs text-gray-500 mb-4">This will reset the inspection back to <span className="font-semibold">open</span> status, clear all qty and result rows, and append your reason to the remarks.</p>
          <div className="mb-4">
            <label className="block text-xs font-medium text-gray-600 mb-1">Reason for cancellation *</label>
            <textarea
              rows={3}
              className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 outline-none resize-none ${
                cancelReasonError ? 'border-red-400' : 'border-gray-300'
              }`}
              placeholder="Describe why results are being cancelled (min 10 characters)…"
              value={cancelReason}
              onChange={e => setCancelReason(e.target.value)}
              onBlur={() => setCancelTouched(true)}
            />
            {cancelReasonError && <p className="mt-1 text-xs text-red-600">{cancelReasonError}</p>}
          </div>
          <div className="flex justify-end gap-3">
            <button
              type="button"
              onClick={closeCancelForm}
              className="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
            >
              Dismiss
            </button>
            <button
              type="submit"
              disabled={cancelMut.isPending}
              className="flex items-center gap-2 px-5 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
            >
              <XCircle className="w-4 h-4" />
              {cancelMut.isPending ? 'Cancelling…' : 'Confirm Cancel'}
            </button>
          </div>
        </form>
      )}

      {/* Record Results Form */}
      {isOpen && showForm && (
        <form onSubmit={(e) => void handleSubmitResults(e)} className="bg-white border border-teal-200 rounded-xl p-6 mb-5">
          <h2 className="text-sm font-semibold text-gray-800 mb-4">Record Inspection Results</h2>

          {/* Qty row */}
          <div className="grid grid-cols-2 gap-4 mb-5">
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Qty Passed *</label>
              <input
                type="number" min="0" max={qtyInspected} step="1" required
                className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 outline-none ${
                  qtyError ? 'border-red-400 focus:ring-red-400' : 'border-gray-300'
                }`}
                value={qtyPassed}
                onChange={e => setQtyPassed(e.target.value)}
                onBlur={() => setTouchedQty(true)}
                placeholder="0"
              />
              {qtyError
                ? <p className="mt-1 text-xs text-red-600">{qtyError}</p>
                : <p className="mt-1 text-xs text-gray-400">Inspected: {qtyInspected}</p>
              }
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Qty Failed <span className="text-gray-400 font-normal">(auto)</span></label>
              <input
                type="number" readOnly tabIndex={-1}
                className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 cursor-default outline-none"
                value={qtyFailed}
                placeholder="0"
              />
              <p className="mt-1 text-xs text-gray-400">= Inspected − Passed</p>
            </div>
          </div>

          {/* Result rows */}
          <div className="mb-4">
            <div className="grid grid-cols-12 gap-2 mb-1 px-1">
              <span className="col-span-3 text-xs font-medium text-gray-500">Criterion *</span>
              <span className="col-span-2 text-xs font-medium text-gray-500">Actual Value</span>
              <span className="col-span-2 text-xs font-medium text-gray-500">Conforming?</span>
              <span className="col-span-4 text-xs font-medium text-gray-500">Remarks</span>
              <span className="col-span-1" />
            </div>

            <div className="space-y-2">
              {rows.map((row, idx) => (
                <div key={idx} className="grid grid-cols-12 gap-2 items-center">
                  <input
                    className="col-span-3 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none disabled:bg-gray-50"
                    value={row.criterion}
                    onChange={e => updateRow(idx, 'criterion', e.target.value)}
                    placeholder="e.g. Visual appearance"
                    disabled={hasTemplate}
                    required
                  />
                  <input
                    className="col-span-2 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none"
                    value={row.actual_value}
                    onChange={e => updateRow(idx, 'actual_value', e.target.value)}
                    placeholder="Measured value"
                  />
                  <div className="col-span-2 flex gap-1.5">
                    <button
                      type="button"
                      onClick={() => updateRow(idx, 'is_conforming', row.is_conforming === true ? null : true)}
                      className={`flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                        row.is_conforming === true
                          ? 'bg-green-600 border-green-600 text-white'
                          : 'border-gray-300 text-gray-500 hover:border-green-400'
                      }`}
                    >
                      <CheckCircle2 className="w-3 h-3" />Pass
                    </button>
                    <button
                      type="button"
                      onClick={() => updateRow(idx, 'is_conforming', row.is_conforming === false ? null : false)}
                      className={`flex-1 flex items-center justify-center gap-1 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                        row.is_conforming === false
                          ? 'bg-red-600 border-red-600 text-white'
                          : 'border-gray-300 text-gray-500 hover:border-red-400'
                      }`}
                    >
                      <XCircle className="w-3 h-3" />Fail
                    </button>
                  </div>
                  <input
                    className="col-span-4 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none"
                    value={row.remarks}
                    onChange={e => updateRow(idx, 'remarks', e.target.value)}
                    placeholder="Optional remarks"
                  />
                  {!hasTemplate && (
                    <button
                      type="button"
                      onClick={() => removeRow(idx)}
                      disabled={rows.length <= 1}
                      className="col-span-1 flex justify-center text-gray-400 hover:text-red-500 disabled:opacity-30"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              ))}
            </div>

            {!hasTemplate && (
              <button
                type="button"
                onClick={addRow}
                className="mt-2 flex items-center gap-1 text-xs text-teal-600 hover:text-teal-700 font-medium"
              >
                <Plus className="w-3.5 h-3.5" /> Add Row
              </button>
            )}
          </div>

          <div className="flex justify-end gap-3">
            <button
              type="button"
              onClick={closeForm}
              className="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={recordMut.isPending}
              className="flex items-center gap-2 px-5 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
            >
              <ClipboardCheck className="w-4 h-4" />
              {recordMut.isPending ? 'Saving…' : 'Submit Results'}
            </button>
          </div>
        </form>
      )}

      {/* Results */}
      {(inspection.results ?? []).length > 0 && (
        <div className="bg-white border border-gray-200 rounded-xl p-6 mb-5">
          <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Inspection Results</h2>
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                {['Criterion', 'Actual Value', 'Conforming', 'Remarks'].map((h) => (
                  <th key={h} className="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {inspection.results?.map((r) => (
                <tr key={r.id}>
                  <td className="px-3 py-2">{r.criterion}</td>
                  <td className="px-3 py-2 text-gray-500">{r.actual_value ?? '—'}</td>
                  <td className="px-3 py-2">
                    {r.is_conforming === null
                      ? <span className="text-gray-400">—</span>
                      : r.is_conforming
                        ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Yes</span>
                        : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">No</span>}
                  </td>
                  <td className="px-3 py-2 text-gray-400 text-xs">{r.remarks ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* NCRs */}
      {(inspection.ncrs ?? []).length > 0 && (
        <div className="bg-white border border-gray-200 rounded-xl p-6">
          <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Non-Conformance Reports</h2>
          {inspection.ncrs?.map((ncr) => (
            <div key={ncr.id} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
              <div>
                <span className="font-mono text-sm text-teal-700 font-medium">{ncr.ncr_reference}</span>
                <span className="ml-3 text-sm text-gray-600">{ncr.title}</span>
              </div>
              <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${
                ncr.severity === 'critical' ? 'bg-red-100 text-red-700' :
                ncr.severity === 'major'    ? 'bg-orange-100 text-orange-700' :
                                              'bg-yellow-100 text-yellow-700'
              }`}>{ncr.severity}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
