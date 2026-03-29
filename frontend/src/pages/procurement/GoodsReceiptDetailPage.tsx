import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, ClipboardCheck, Trash2, XCircle, FlaskConical, Pencil } from 'lucide-react'
import { usePermission } from '@/hooks/usePermission'
import {
  useGoodsReceipt,
  useConfirmGoodsReceipt,
  useDeleteGoodsReceipt,
  useRejectGoodsReceipt,
  useSubmitForQc,
  useUpdateGoodsReceiptItem,
  useAcceptWithDefects,
  useReturnToSupplier,
  useResubmitForQc,
} from '@/hooks/useGoodsReceipts'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { GoodsReceiptCondition } from '@/types/procurement'
import ChainRecordTimeline from '@/components/ui/ChainRecordTimeline'
import StatusTimeline from '@/components/ui/StatusTimeline'

const conditionBadgeClass: Record<GoodsReceiptCondition, string> = {
  good:     'bg-green-100 text-green-700',
  damaged:  'bg-amber-100 text-amber-700',
  partial:  'bg-blue-100 text-blue-700',
  rejected: 'bg-red-100 text-red-600',
}

const CONDITIONS: GoodsReceiptCondition[] = ['good', 'damaged', 'partial', 'rejected']

const conditionLabel: Record<GoodsReceiptCondition, string> = {
  good:     'Good',
  damaged:  'Damaged',
  partial:  'Partial',
  rejected: 'Rejected',
}

export default function GoodsReceiptDetailPage(): React.ReactElement {
  const { ulid }  = useParams<{ ulid: string }>()
  const navigate  = useNavigate()
  const canConfirmPermission = usePermission('procurement.goods-receipt.confirm')

  const { data: gr, isLoading, isError } = useGoodsReceipt(ulid ?? null)
  const confirmMutation    = useConfirmGoodsReceipt()
  const deleteMutation     = useDeleteGoodsReceipt()
  const rejectMutation     = useRejectGoodsReceipt()
  const submitForQcMutation = useSubmitForQc()
  const updateItemMutation  = useUpdateGoodsReceiptItem()

  const [showRejectModal, setShowRejectModal] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [editingItemId, setEditingItemId] = useState<number | null>(null)

  function handleDelete(): void {
    if (!gr) return
    deleteMutation.mutate(gr.ulid, {
      onSuccess: () => {
        toast.success('Goods Receipt cancelled.')
        navigate(-1)
      },
      onError: (err) => {
        const message = firstErrorMessage(err)
        toast.error(message ?? 'Failed to cancel Goods Receipt.')
      },
    })
  }

  function handleConfirm(): void {
    if (!gr) return
    confirmMutation.mutate(gr.ulid, {
      onSuccess: (updated) => {
        const matchLabel = updated.three_way_match_passed
          ? 'Three-way match PASSED'
          : 'Three-way match WARNING — discrepancy detected'
        toast.success(`GR confirmed. ${matchLabel}`)
      },
      onError: (err) => {
        const message = firstErrorMessage(err)
        toast.error(message ?? 'Failed to confirm Goods Receipt.')
      },
    })
  }

  function handleReject(): void {
    if (!gr || !rejectReason.trim()) return
    rejectMutation.mutate(
      { ulid: gr.ulid, reason: rejectReason },
      {
        onSuccess: () => {
          toast.success('Goods Receipt rejected.')
          setShowRejectModal(false)
          setRejectReason('')
        },
        onError: (err) => toast.error(firstErrorMessage(err) ?? 'Failed to reject GR.'),
      }
    )
  }

  // ── render states ────────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto p-6 space-y-4">
        <SkeletonLoader />
      </div>
    )
  }

  if (isError || !gr) {
    return (
      <div className="max-w-7xl mx-auto p-6">
        <div className="flex items-center gap-3 text-red-600 bg-red-50 rounded p-4">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <p className="text-sm">Failed to load Goods Receipt. It may have been deleted or you lack access.</p>
        </div>
      </div>
    )
  }

  const isDraft = gr.status === 'draft'
  const isPendingQc = gr.status === 'pending_qc'
  const isQcPassed = gr.status === 'qc_passed'
  const isQcFailed = gr.status === 'qc_failed'
  const isPartialAccept = gr.status === 'partial_accept'
  const isConfirmed = gr.status === 'confirmed'
  const canConfirm = isQcPassed || isPartialAccept
  const canReject = isDraft || isPendingQc || isQcFailed
  const acceptWithDefectsMutation = useAcceptWithDefects()
  const returnToSupplierMutation = useReturnToSupplier()
  const resubmitForQcMutation = useResubmitForQc()
  const anyPending = confirmMutation.isPending || deleteMutation.isPending || rejectMutation.isPending || submitForQcMutation.isPending || acceptWithDefectsMutation.isPending || returnToSupplierMutation.isPending

  function handleSubmitForQc(): void {
    if (!gr) return
    submitForQcMutation.mutate(gr.ulid, {
      onSuccess: () => toast.success('GR submitted for incoming quality control.'),
      onError: (err: any) => toast.error(firstErrorMessage(err) ?? 'Failed to submit for QC.'),
    })
  }

  function handleItemConditionChange(itemId: number, condition: GoodsReceiptCondition): void {
    if (!gr) return
    updateItemMutation.mutate(
      { ulid: gr.ulid, itemId, data: { condition } },
      {
        onSuccess: () => toast.success('Item condition updated.'),
        onError: (err: any) => toast.error(firstErrorMessage(err) ?? 'Failed to update item.'),
      },
    )
  }

  function handleItemRemarksChange(itemId: number, remarks: string): void {
    if (!gr) return
    updateItemMutation.mutate(
      { ulid: gr.ulid, itemId, data: { remarks } },
      {
        onSuccess: () => toast.success('Remarks saved.'),
        onError: (err: any) => toast.error(firstErrorMessage(err) ?? 'Failed to save remarks.'),
      },
    )
  }

  const headerActions = (
    <>
      {canConfirmPermission && (
        <>
          {/* Confirm button: only when QC has passed or defects accepted */}
          {canConfirm && (
            <ConfirmDialog
              title="Post Goods Receipt?"
              description="This will confirm the receipt, update inventory levels, and trigger three-way matching."
              onConfirm={handleConfirm}
            >
              <button
                type="button"
                disabled={anyPending}
                className="flex items-center gap-2 px-5 py-2.5 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <ClipboardCheck className="w-4 h-4" />
                {confirmMutation.isPending ? 'Confirming…' : 'Confirm Receipt & Run 3-Way Match'}
              </button>
            </ConfirmDialog>
          )}

          {/* Submit for QC: only from draft */}
          {isDraft && (
            <button
              type="button"
              disabled={anyPending}
              onClick={handleSubmitForQc}
              className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-blue-300 text-blue-600 text-sm font-medium hover:bg-blue-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <FlaskConical className="w-4 h-4" />
              {submitForQcMutation.isPending ? 'Submitting…' : 'Submit for QC'}
            </button>
          )}

          {/* Resubmit for QC: only when QC failed */}
          {isQcFailed && (
            <button
              type="button"
              disabled={anyPending}
              onClick={() => {
                if (!gr) return
                resubmitForQcMutation.mutate(gr.ulid, {
                  onSuccess: () => toast.success('GR resubmitted for QC re-inspection.'),
                  onError: (err: any) => toast.error(firstErrorMessage(err) ?? 'Failed to resubmit for QC.'),
                })
              }}
              className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-blue-300 text-blue-600 text-sm font-medium hover:bg-blue-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <FlaskConical className="w-4 h-4" />
              {resubmitForQcMutation.isPending ? 'Resubmitting...' : 'Resubmit for QC'}
            </button>
          )}

          {/* Accept with Defects: only when QC failed */}
          {isQcFailed && (
            <button
              type="button"
              disabled={anyPending}
              onClick={() => {
                if (!gr) return
                // Simple auto-accept: accept all received quantities (user can refine via API)
                const items = gr.items.map(item => ({
                  gr_item_id: item.id,
                  quantity_accepted: item.quantity_accepted ?? item.quantity_received,
                  quantity_rejected: item.quantity_rejected ?? 0,
                  defect_type: item.defect_type ?? undefined,
                  defect_description: item.defect_description ?? undefined,
                }))
                acceptWithDefectsMutation.mutate(
                  { ulid: gr.ulid, items, notes: gr.qc_notes ?? undefined },
                  {
                    onSuccess: () => toast.success('GR accepted with defects. NCRs documented.'),
                    onError: (err: any) => toast.error(firstErrorMessage(err) ?? 'Failed to accept with defects.'),
                  }
                )
              }}
              className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-amber-300 text-amber-600 text-sm font-medium hover:bg-amber-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <CheckCircle2 className="w-4 h-4" />
              {acceptWithDefectsMutation.isPending ? 'Accepting…' : 'Accept with Defects'}
            </button>
          )}

          {/* Reject: from draft, pending_qc, or qc_failed */}
          {canReject && (
            <button
              type="button"
              disabled={anyPending}
              onClick={() => setShowRejectModal(true)}
              className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-orange-300 text-orange-600 text-sm font-medium hover:bg-orange-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <XCircle className="w-4 h-4" />
              Reject GR
            </button>
          )}

          {/* Return to Supplier: only when confirmed */}
          {isConfirmed && (
            <button
              type="button"
              disabled={anyPending}
              onClick={() => {
                const reason = prompt('Reason for returning goods to supplier:')
                if (!reason || !gr) return
                returnToSupplierMutation.mutate(
                  { ulid: gr.ulid, reason },
                  {
                    onSuccess: () => toast.success('Goods returned to supplier.'),
                    onError: (err: any) => toast.error(firstErrorMessage(err) ?? 'Failed to return goods.'),
                  }
                )
              }}
              className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-purple-300 text-purple-600 text-sm font-medium hover:bg-purple-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Trash2 className="w-4 h-4" />
              {returnToSupplierMutation.isPending ? 'Returning…' : 'Return to Supplier'}
            </button>
          )}
        </>
      )}
      {isDraft && (
        <ConfirmDialog
          title="Cancel Goods Receipt?"
          description="This will cancel the draft Goods Receipt. This action cannot be undone."
          onConfirm={handleDelete}
        >
          <button
            type="button"
            disabled={anyPending}
            className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Trash2 className="w-4 h-4" />
            Cancel GR
          </button>
        </ConfirmDialog>
      )}
    </>
  )

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      {/* ── Header ──────────────────────────────────────────────────────────── */}
      <PageHeader
        backTo="/procurement/goods-receipts"
        title={gr.gr_reference}
        subtitle="Goods Receipt"
        actions={headerActions}
      >
        <StatusBadge status={gr.status}>{gr.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
      </PageHeader>

      {/* ── Receipt Details ─────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>Receipt Details</CardHeader>
        <CardBody>
          <InfoList columns={2}>
            <InfoRow 
              label="Purchase Order"
              value={
                gr.purchase_order ? (
                  <Link
                    to={`/procurement/purchase-orders/${gr.purchase_order.ulid}`}
                    className="text-neutral-700 hover:text-neutral-900 font-medium underline underline-offset-2"
                  >
                    {gr.purchase_order.po_reference}
                  </Link>
                ) : (
                  '—'
                )
              }
            />
            <InfoRow 
              label="Received Date"
              value={gr.received_date ? new Date(gr.received_date).toLocaleDateString('en-PH') : '—'}
            />
            <InfoRow 
              label="Received By"
              value={gr.received_by?.name ?? '—'}
            />
            {gr.delivery_note_number && (
              <InfoRow 
                label="Delivery Note #"
                value={<span className="font-mono">{gr.delivery_note_number}</span>}
              />
            )}
            {gr.condition_notes && (
              <InfoRow 
                label="Condition Notes" 
                fullWidth
                value={<span className="text-neutral-600 italic">{gr.condition_notes}</span>}
              />
            )}
            {gr.confirmed_at && (
              <>
                <InfoRow 
                  label="Confirmed By"
                  value={gr.confirmed_by?.name ?? '—'}
                />
                <InfoRow 
                  label="Confirmed At"
                  value={new Date(gr.confirmed_at).toLocaleDateString('en-PH')}
                />
              </>
            )}
          </InfoList>
        </CardBody>
      </Card>

      {/* ── Three-way match status ─────────────────────────────────────────── */}
      {gr.status === 'confirmed' && (
        <Card className={gr.three_way_match_passed ? '' : 'border-amber-200'}>
          <CardBody>
            <div className="flex items-center gap-3">
              {gr.three_way_match_passed ? (
                <CheckCircle2 className="w-5 h-5 text-neutral-600 shrink-0" />
              ) : (
                <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0" />
              )}
              <div>
                <p
                  className={`text-sm font-semibold ${
                    gr.three_way_match_passed ? 'text-neutral-700' : 'text-amber-700'
                  }`}
                >
                  Three-Way Match: {gr.three_way_match_passed ? 'Passed' : 'Discrepancy Detected'}
                </p>
                {gr.ap_invoice_created && (
                  <p className="text-xs text-neutral-500 mt-0.5">AP Invoice has been created.</p>
                )}
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Received Items ──────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>Received Items</CardHeader>
        <CardBody>
          <div className="overflow-x-auto -mx-6 -mb-6">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600">#</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600">PO Item ID</th>
                  <th className="text-right px-4 py-3 font-medium text-neutral-600">Qty Received</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600">UOM</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600">Condition</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600">Remarks</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {gr.items.map((item: any, idx: number) => (
                  <tr key={item.id} className="even:bg-neutral-50/50 hover:bg-neutral-50">
                    <td className="px-4 py-3 text-neutral-400">{idx + 1}</td>
                    <td className="px-4 py-3 text-neutral-600 font-mono text-xs">{item.po_item_id}</td>
                    <td className="px-4 py-3 text-right text-neutral-800 font-medium">
                      {item.quantity_received}
                    </td>
                    <td className="px-4 py-3 text-neutral-600">{item.unit_of_measure}</td>
                    <td className="px-4 py-3">
                      {isDraft ? (
                        <select
                          value={item.condition}
                          onChange={(e) => handleItemConditionChange(item.id, e.target.value as GoodsReceiptCondition)}
                          disabled={updateItemMutation.isPending}
                          className={`text-xs font-medium rounded px-2 py-1 border-0 cursor-pointer ${conditionBadgeClass[item.condition as GoodsReceiptCondition]}`}
                        >
                          {CONDITIONS.map((c) => (
                            <option key={c} value={c}>{conditionLabel[c]}</option>
                          ))}
                        </select>
                      ) : (
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                            conditionBadgeClass[item.condition as GoodsReceiptCondition]
                          }`}
                        >
                          {conditionLabel[item.condition as GoodsReceiptCondition]}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {isDraft && editingItemId === item.id ? (
                        <input
                          type="text"
                          defaultValue={item.remarks ?? ''}
                          className="text-xs border border-neutral-300 rounded px-2 py-1 w-full"
                          placeholder="Add remarks..."
                          autoFocus
                          onBlur={(e) => {
                            handleItemRemarksChange(item.id, e.target.value)
                            setEditingItemId(null)
                          }}
                          onKeyDown={(e: any) => {
                            if (e.key === 'Enter') {
                              handleItemRemarksChange(item.id, e.target.value)
                              setEditingItemId(null)
                            }
                          }}
                        />
                      ) : (
                        <span
                          className={`text-xs italic ${isDraft ? 'cursor-pointer hover:text-neutral-700' : ''} text-neutral-500`}
                          onClick={() => isDraft && setEditingItemId(item.id)}
                        >
                          {item.remarks ?? (isDraft ? 'Click to add remarks…' : '—')}
                          {isDraft && <Pencil className="w-3 h-3 inline ml-1 text-neutral-400" />}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      {/* ── Rejection Banner ─────────────────────────────────────────────────── */}
      {gr.status === 'rejected' && (
        <div className="flex items-start gap-3 bg-orange-50 border border-orange-200 rounded-lg p-4">
          <XCircle className="w-5 h-5 text-orange-500 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-semibold text-orange-800">Goods Receipt Rejected</p>
            {(gr as unknown as { rejection_reason?: string }).rejection_reason && (
              <p className="text-sm text-orange-700 mt-1">
                {(gr as unknown as { rejection_reason: string }).rejection_reason}
              </p>
            )}
          </div>
        </div>
      )}

      {/* ── Reject Modal ─────────────────────────────────────────────────────── */}
      {showRejectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4 space-y-4">
            <h2 className="text-base font-semibold text-neutral-900">Reject Goods Receipt</h2>
            <p className="text-sm text-neutral-600">
              Provide a reason for rejecting this GR (wrong items, damaged goods, etc.).
            </p>
            <textarea
              rows={4}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-orange-400"
              placeholder="Explain why this GR is being rejected…"
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
            />
            <div className="flex justify-end gap-3">
              <button
                type="button"
                onClick={() => { setShowRejectModal(false); setRejectReason('') }}
                className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={!rejectReason.trim() || rejectMutation.isPending}
                onClick={handleReject}
                className="px-4 py-2 text-sm bg-orange-600 text-white rounded hover:bg-orange-700 disabled:opacity-50"
              >
                {rejectMutation.isPending ? 'Rejecting…' : 'Reject GR'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Document Chain */}
      <Card>
        <CardHeader>Document Chain</CardHeader>
        <CardBody>
          <ChainRecordTimeline documentType="goods_receipt" documentId={gr.id} />
        </CardBody>
      </Card>

      {/* Activity Timeline */}
      <Card>
        <CardHeader>Activity Timeline</CardHeader>
        <CardBody>
          <StatusTimeline auditableType="goods_receipt" auditableId={gr.id} />
        </CardBody>
      </Card>
    </div>
  )
}
