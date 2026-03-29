import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, Send, XCircle, PackageCheck, Calendar, Download, MessageSquare, ThumbsUp, ThumbsDown } from 'lucide-react'
import {
  usePurchaseOrder,
  useSendPurchaseOrder,
  useAcceptChanges,
  useRejectChanges,
} from '@/hooks/usePurchaseOrders'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { ExportPdfButton } from '@/components/ui/ExportPdfButton'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import StatusTimeline from '@/components/ui/StatusTimeline'
import ChainRecordTimeline from '@/components/ui/ChainRecordTimeline'
import NegotiationHistoryPanel from '@/components/procurement/NegotiationHistoryPanel'
import { getPurchaseOrderSteps, isRejectedStatus } from '@/lib/workflowSteps'
import type { PurchaseOrder, PurchaseOrderItem, PurchaseOrderStatus } from '@/types/procurement'

const statusLabel: Record<PurchaseOrderStatus, string> = {
  draft:              'Draft',
  sent:               'Sent to Vendor',
  negotiating:        'Under Negotiation',
  acknowledged:       'Acknowledged',
  in_transit:         'In Transit',
  partially_received: 'Partially Received',
  fully_received:     'Fully Received',
  closed:             'Closed',
  cancelled:          'Cancelled',
}



// ── Send to Vendor modal (delivery date) ─────────────────────────────────────

function SendToVendorModal({
  onConfirm,
  onClose,
  loading,
}: {
  onConfirm: (deliveryDate: string) => void
  onClose: () => void
  loading: boolean
}): React.ReactElement {
  const [deliveryDate, setDeliveryDate] = useState('')
  const today = new Date().toISOString().split('T')[0]

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded w-full max-w-md max-h-[90vh] overflow-y-auto mx-4 p-4 sm:p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-2">Send to Vendor</h2>
        <p className="text-sm text-neutral-500 mb-4">
          Please specify the expected delivery date before sending this Purchase Order to the vendor.
        </p>
        <div className="mb-4">
          <label className="block text-sm font-medium text-neutral-700 mb-1">
            Delivery Date <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <input
              type="date"
              className="w-full border border-neutral-300 rounded p-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              value={deliveryDate}
              min={today}
              onChange={(e) => setDeliveryDate(e.target.value)}
            />
            <Calendar className="absolute right-3 top-2.5 w-4 h-4 text-neutral-400 pointer-events-none" />
          </div>
        </div>
        <div className="flex flex-col-reverse sm:flex-row gap-2 sm:gap-2 mt-4 justify-end">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 rounded text-sm text-neutral-600 border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={!deliveryDate || loading}
            onClick={() => onConfirm(deliveryDate)}
            className="px-4 py-2 rounded text-sm font-medium bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Sending…' : 'Send to Vendor'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Negotiation Review Modal ──────────────────────────────────────────────────

function NegotiationReviewModal({
  po,
  onAccept,
  onReject,
  onClose,
  loading,
}: {
  po: PurchaseOrder
  onAccept: (remarks: string) => void
  onReject: (remarks: string) => void
  onClose: () => void
  loading: boolean
}): React.ReactElement {
  const [remarks, setRemarks] = useState('')
  const [action, setAction] = useState<'accept' | 'reject' | null>(null)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded w-full max-w-2xl max-h-[90vh] overflow-y-auto mx-4 p-4 sm:p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-1">Review Vendor's Proposed Changes</h2>
        <p className="text-sm text-neutral-500 mb-4">
          Round {po.negotiation_round} · Requested {po.change_requested_at ? new Date(po.change_requested_at).toLocaleDateString('en-PH') : '—'}
        </p>

        {/* Vendor remarks */}
        <div className="bg-amber-50 border border-amber-200 rounded p-3 mb-4">
          <p className="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">Vendor Remarks</p>
          <p className="text-sm text-amber-900">{po.vendor_remarks ?? '—'}</p>
        </div>

        {/* Proposed item changes */}
        <div className="mb-4">
          <p className="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-2">Proposed Item Quantities</p>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm border border-neutral-200 rounded">
              <thead className="bg-neutral-50">
                <tr>
                  <th className="text-left px-3 py-2 text-neutral-600 font-medium">Item</th>
                  <th className="text-right px-3 py-2 text-neutral-600 font-medium">Ordered</th>
                  <th className="text-right px-3 py-2 text-neutral-600 font-medium">Proposed</th>
                  <th className="text-left px-3 py-2 text-neutral-600 font-medium">Vendor Note</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {po.items.map((item) => (
                  <tr key={item.id}>
                    <td className="px-3 py-2 text-neutral-800">{item.item_description}</td>
                    <td className="px-3 py-2 text-right text-neutral-600">{item.quantity_ordered} {item.unit_of_measure}</td>
                    <td className={`px-3 py-2 text-right font-medium ${item.negotiated_quantity !== null && item.negotiated_quantity < item.quantity_ordered ? 'text-amber-600' : 'text-neutral-800'}`}>
                      {item.negotiated_quantity !== null ? `${item.negotiated_quantity} ${item.unit_of_measure}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-neutral-500 text-xs italic">{item.vendor_item_notes ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Officer remarks */}
        <div className="mb-4">
          <label className="block text-sm font-medium text-neutral-700 mb-1">
            Your Remarks {action === 'reject' && <span className="text-red-500">*</span>}
          </label>
          <textarea
            rows={3}
            className="w-full border border-neutral-300 rounded p-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
            placeholder={action === 'reject' ? 'Required — explain why you are rejecting...' : 'Optional note for the vendor...'}
            value={remarks}
            onChange={(e) => setRemarks(e.target.value)}
          />
        </div>

        <div className="flex flex-col-reverse sm:flex-row gap-2 justify-end">
          <button type="button" onClick={onClose} className="px-4 py-2 rounded text-sm text-neutral-600 border border-neutral-300 hover:bg-neutral-50">
            Cancel
          </button>
          <button
            type="button"
            disabled={loading}
            onClick={() => { setAction('reject'); onReject(remarks) }}
            className="flex items-center justify-center gap-2 px-4 py-2 rounded text-sm font-medium bg-red-50 text-red-700 border border-red-300 hover:bg-red-100 disabled:opacity-50"
          >
            <ThumbsDown className="w-4 h-4" />
            Reject Changes
          </button>
          <button
            type="button"
            disabled={loading}
            onClick={() => { setAction('accept'); onAccept(remarks) }}
            className="flex items-center justify-center gap-2 px-4 py-2 rounded text-sm font-medium bg-green-600 text-white hover:bg-green-700 disabled:opacity-50"
          >
            <ThumbsUp className="w-4 h-4" />
            Accept Changes
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────

export default function PurchaseOrderDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const _navigate  = useNavigate()
  const { hasPermission } = useAuthStore()

  const { data: po, isLoading, isError } = usePurchaseOrder(ulid ?? null)

  const sendMutation = useSendPurchaseOrder()
  const acceptChangesMutation = useAcceptChanges()
  const rejectChangesMutation = useRejectChanges()

  const [showSendModal, setShowSendModal] = useState(false)
  const [showNegotiationModal, setShowNegotiationModal] = useState(false)

  // ── handlers ────────────────────────────────────────────────────────────────

  function handleSend(): void {
    setShowSendModal(true)
  }

  function handleSendConfirm(deliveryDate: string): void {
    if (!po) return
    sendMutation.mutate(
      { ulid: po.ulid, delivery_date: deliveryDate },
      {
        onSuccess: () => {
          toast.success('Purchase Order sent to vendor.')
          setShowSendModal(false)
        },
        onError: (err) => {
          const message = firstErrorMessage(err)
          toast.error(message ?? 'Failed to send PO. Please try again.')
        },
      }
    )
  }

  function handleAcceptChanges(remarks: string): void {
    if (!po) return
    acceptChangesMutation.mutate(
      { ulid: po.ulid, remarks },
      {
        onSuccess: () => {
          toast.success('Changes accepted. PO is now acknowledged — vendor can proceed to ship.')
          setShowNegotiationModal(false)
        },
        onError: (err) => {
          toast.error(firstErrorMessage(err) ?? 'Failed to accept changes.')
        },
      }
    )
  }

  function handleRejectChanges(remarks: string): void {
    if (!po) return
    if (!remarks.trim()) {
      toast.error('Please provide a rejection reason.')
      return
    }
    rejectChangesMutation.mutate(
      { ulid: po.ulid, remarks },
      {
        onSuccess: () => {
          toast.success('Changes rejected. Vendor has been notified to revise or acknowledge the original PO.')
          setShowNegotiationModal(false)
        },
        onError: (err) => {
          toast.error(firstErrorMessage(err) ?? 'Failed to reject changes.')
        },
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

  if (isError || !po) {
    return (
      <div className="max-w-7xl mx-auto p-6">
        <div className="flex items-center gap-3 text-red-600 bg-red-50 rounded p-4">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <p className="text-sm">Failed to load Purchase Order. It may have been deleted or you lack access.</p>
        </div>
      </div>
    )
  }

  const canManagePo       = hasPermission('procurement.purchase-order.manage')
  const canSend           = po.status === 'draft' && canManagePo
  const canReviewNegotiation = po.status === 'negotiating' && canManagePo
  const canExportPdf      = po.status !== 'draft'

  const negoBusy = acceptChangesMutation.isPending || rejectChangesMutation.isPending

  const actions = (
    <div className="flex flex-wrap items-center gap-2">
      {canSend && (
        <button
          type="button"
          onClick={handleSend}
          disabled={sendMutation.isPending}
          className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm font-medium rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Send className="w-4 h-4" />
          {sendMutation.isPending ? 'Sending…' : 'Send to Vendor'}
        </button>
      )}
      {canReviewNegotiation && (
        <button
          type="button"
          onClick={() => setShowNegotiationModal(true)}
          disabled={negoBusy}
          className="flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <MessageSquare className="w-4 h-4" />
          Review Vendor Changes
        </button>
      )}
      {canExportPdf && (
        <ExportPdfButton href={`/api/v1/procurement/purchase-orders/${po.ulid}/pdf`} />
      )}
    </div>
  )

  return (
    <>
      {showSendModal && (
        <SendToVendorModal
          onConfirm={handleSendConfirm}
          onClose={() => setShowSendModal(false)}
          loading={sendMutation.isPending}
        />
      )}
      {showNegotiationModal && (
        <NegotiationReviewModal
          po={po}
          onAccept={handleAcceptChanges}
          onReject={handleRejectChanges}
          onClose={() => setShowNegotiationModal(false)}
          loading={negoBusy}
        />
      )}

      <div className="max-w-7xl mx-auto p-6 space-y-6">
        {/* ── Header ────────────────────────────────────────────────────────── */}
        <PageHeader
          title={po.po_reference}
          subtitle="Purchase Order"
          backTo="/procurement/purchase-orders"
          status={<StatusBadge status={po.status}>{statusLabel[po.status]}</StatusBadge>}
          actions={actions}
        />

        {/* ── Workflow Timeline ─────────────────────────────────────────────── */}
        <div className="bg-white border border-neutral-200 rounded p-4">
          <StatusTimeline
            steps={getPurchaseOrderSteps(po)}
            currentStatus={po.status}
            direction="horizontal"
            isRejected={isRejectedStatus(po.status)}
          />
        </div>

        {/* ── Negotiation alert ─────────────────────────────────────────────── */}
        {po.status === 'negotiating' && (
          <div className="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded p-4">
            <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div className="flex-1">
              <p className="text-sm font-semibold text-amber-800">Vendor Proposed Changes (Round {po.negotiation_round})</p>
              <p className="text-sm text-amber-700 mt-0.5">{po.vendor_remarks}</p>
              <p className="text-xs text-amber-600 mt-1">
                Received {po.change_requested_at ? new Date(po.change_requested_at).toLocaleDateString('en-PH') : '—'} — review the proposed item quantities and accept or reject.
              </p>
            </div>
            {canReviewNegotiation && (
              <button
                type="button"
                onClick={() => setShowNegotiationModal(true)}
                className="shrink-0 px-3 py-1.5 text-xs font-medium bg-amber-600 text-white rounded hover:bg-amber-700"
              >
                Review
              </button>
            )}
          </div>
        )}

        {/* ── Order info card ────────────────────────────────────────────────── */}
        <Card>
          <CardHeader>Order Details</CardHeader>
          <CardBody>
            <InfoList columns={2}>
              <InfoRow label="Vendor" value={po.vendor?.name ?? '—'} />
              <InfoRow 
                label="PO Date" 
                value={new Date(po.po_date).toLocaleDateString('en-PH')} 
              />
              <InfoRow 
                label="Delivery Date" 
                value={new Date(po.delivery_date).toLocaleDateString('en-PH')} 
              />
              <InfoRow label="Payment Terms" value={po.payment_terms} />
              <InfoRow 
                label="Total Amount" 
                value={
                  <span className="text-lg font-bold text-neutral-900">
                    ₱{po.total_po_amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                  </span>
                } 
              />
              {po.delivery_address && (
                <InfoRow label="Delivery Address" value={po.delivery_address} className="sm:col-span-2" />
              )}
              {po.purchase_request && (
                <InfoRow 
                  label="Source PR" 
                  value={
                    <Link
                      to={`/procurement/purchase-requests/${po.purchase_request.ulid}`}
                      className="text-neutral-700 hover:text-neutral-900 font-medium underline underline-offset-2"
                    >
                      {po.purchase_request.pr_reference}
                    </Link>
                  } 
                />
              )}
              <InfoRow label="Created By" value={po.created_by?.name ?? '—'} />
              {po.sent_at && (
                <InfoRow 
                  label="Sent At" 
                  value={new Date(po.sent_at).toLocaleDateString('en-PH')} 
                />
              )}
              {po.notes && (
                <InfoRow label="Notes" value={<span className="italic">{po.notes}</span>} className="sm:col-span-2" />
              )}
              {po.cancellation_reason && (
                <InfoRow 
                  label="Cancellation Reason" 
                  value={<span className="text-red-600">{po.cancellation_reason}</span>} 
                  className="sm:col-span-2" 
                />
              )}
            </InfoList>
          </CardBody>
        </Card>

        {/* ── Line items ────────────────────────────────────────────────────── */}
        <Card>
          <CardHeader>Line Items</CardHeader>
          <CardBody className="p-0">
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    <th className="text-left px-4 py-3 font-medium text-neutral-600">#</th>
                    <th className="text-left px-4 py-3 font-medium text-neutral-600">Description</th>
                    <th className="text-right px-4 py-3 font-medium text-neutral-600">Qty Ordered</th>
                    <th className="text-right px-4 py-3 font-medium text-neutral-600">Agreed Qty</th>
                    <th className="text-left px-4 py-3 font-medium text-neutral-600">UOM</th>
                    <th className="text-right px-4 py-3 font-medium text-neutral-600">Unit Cost</th>
                    <th className="text-right px-4 py-3 font-medium text-neutral-600">Total</th>
                    <th className="text-right px-4 py-3 font-medium text-neutral-600">Received</th>
                    <th className="text-right px-4 py-3 font-medium text-neutral-600">Pending</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {po.items.map((item) => (
                    <tr key={item.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                      <td className="px-4 py-3 text-neutral-400">{item.line_order}</td>
                      <td className="px-4 py-3 text-neutral-800">{item.item_description}</td>
                      <td className="px-4 py-3 text-right text-neutral-800">{item.quantity_ordered}</td>
                      <td className={`px-4 py-3 text-right font-medium ${item.negotiated_quantity !== null && item.negotiated_quantity < item.quantity_ordered ? 'text-amber-600' : 'text-neutral-400'}`}>
                        {item.negotiated_quantity !== null ? item.negotiated_quantity : '—'}
                      </td>
                      <td className="px-4 py-3 text-neutral-600">{item.unit_of_measure}</td>
                      <td className="px-4 py-3 text-right text-neutral-800">
                        ₱{item.agreed_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 text-right text-neutral-800 font-medium">
                        ₱{item.total_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 text-right text-neutral-600">{item.quantity_received}</td>
                      <td className="px-4 py-3 text-right">
                        <span
                          className={`font-medium ${
                            item.quantity_pending > 0 ? 'text-neutral-700 font-semibold' : 'text-neutral-400'
                          }`}
                        >
                          {item.quantity_pending}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-neutral-50">
                  <tr>
                    <td colSpan={6} className="px-4 py-3 text-right text-sm font-semibold text-neutral-600">
                      Grand Total
                    </td>
                    <td className="px-4 py-3 text-right text-sm font-bold text-neutral-800">
                      ₱{po.total_po_amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td colSpan={2} />
                  </tr>
                </tfoot>
              </table>
            </div>
          </CardBody>
        </Card>

        {/* ── Status timeline ────────────────────────────────────────────────── */}
        <Card>
          <CardHeader>Timeline</CardHeader>
          <CardBody>
            <div className="space-y-3">
              <div className="flex items-start gap-3">
                <div className="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 bg-neutral-100">
                  <CheckCircle2 className="w-4 h-4 text-neutral-600" />
                </div>
                <div>
                  <p className="text-sm font-medium text-neutral-800">Created</p>
                  <p className="text-xs text-neutral-500 mt-0.5">
                    {po.created_by?.name ?? '—'}
                    <span className="ml-2 text-neutral-400">
                      {new Date(po.created_at).toLocaleDateString('en-PH')}
                    </span>
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <div
                  className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 ${
                    po.sent_at ? 'bg-neutral-100' : 'bg-neutral-50'
                  }`}
                >
                  {po.sent_at ? (
                    <CheckCircle2 className="w-4 h-4 text-neutral-600" />
                  ) : (
                    <div className="w-2 h-2 rounded-full bg-neutral-400" />
                  )}
                </div>
                <div>
                  <p
                    className={`text-sm font-medium ${
                      po.sent_at ? 'text-neutral-800' : 'text-neutral-400'
                    }`}
                  >
                    Sent to Vendor
                  </p>
                  {po.sent_at && (
                    <p className="text-xs text-neutral-500 mt-0.5">
                      {new Date(po.sent_at).toLocaleDateString('en-PH')}
                    </p>
                  )}
                </div>
              </div>

              {(po.status === 'negotiating' || po.negotiation_round > 0) && (
                <div className="flex items-start gap-3">
                  <div className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 ${po.status === 'negotiating' ? 'bg-amber-100' : 'bg-neutral-100'}`}>
                    <MessageSquare className={`w-4 h-4 ${po.status === 'negotiating' ? 'text-amber-600' : 'text-neutral-400'}`} />
                  </div>
                  <div>
                    <p className={`text-sm font-medium ${po.status === 'negotiating' ? 'text-amber-700' : 'text-neutral-400'}`}>
                      Negotiation {po.status === 'negotiating' ? `(Round ${po.negotiation_round} — Pending Review)` : `(${po.negotiation_round} round${po.negotiation_round > 1 ? 's' : ''})`}
                    </p>
                    {po.change_requested_at && (
                      <p className="text-xs text-neutral-500 mt-0.5">
                        Last proposed: {new Date(po.change_requested_at).toLocaleDateString('en-PH')}
                      </p>
                    )}
                  </div>
                </div>
              )}

              {(po.vendor_acknowledged_at || po.status === 'acknowledged' || po.status === 'in_transit' || po.status === 'partially_received' || po.status === 'fully_received' || po.status === 'closed') && (
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 bg-neutral-100">
                    <CheckCircle2 className="w-4 h-4 text-neutral-600" />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-neutral-800">Vendor Acknowledged</p>
                    {po.vendor_acknowledged_at && (
                      <p className="text-xs text-neutral-500 mt-0.5">{new Date(po.vendor_acknowledged_at).toLocaleDateString('en-PH')}</p>
                    )}
                  </div>
                </div>
              )}

              {po.status === 'cancelled' && (
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 bg-red-100">
                    <XCircle className="w-4 h-4 text-red-500" />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-red-600">Cancelled</p>
                    {po.cancellation_reason && (
                      <p className="text-xs text-neutral-500 mt-0.5">"{po.cancellation_reason}"</p>
                    )}
                  </div>
                </div>
              )}
            </div>
          </CardBody>
        </Card>

        {/* ── Fulfillment history ───────────────────────────────────────────── */}
        {po.fulfillment_notes && po.fulfillment_notes.length > 0 && (
          <Card>
            <CardHeader>Fulfillment History</CardHeader>
            <CardBody>
              <div className="space-y-3">
                {po.fulfillment_notes.map((note) => {
                  const noteLabel: Record<string, string> = {
                    acknowledged: 'Vendor Acknowledged PO',
                    change_requested: 'Vendor Proposed Changes',
                    change_accepted: 'Changes Accepted',
                    change_rejected: 'Changes Rejected',
                    in_transit: 'Marked In-Transit',
                    delivered: 'Delivery Confirmed',
                    partial: 'Partial Delivery',
                  }
                  const noteColor: Record<string, string> = {
                    acknowledged: 'bg-green-50 border-green-200 text-green-800',
                    change_requested: 'bg-amber-50 border-amber-200 text-amber-800',
                    change_accepted: 'bg-green-50 border-green-200 text-green-800',
                    change_rejected: 'bg-red-50 border-red-200 text-red-800',
                    in_transit: 'bg-blue-50 border-blue-200 text-blue-800',
                    delivered: 'bg-neutral-50 border-neutral-200 text-neutral-800',
                    partial: 'bg-neutral-50 border-neutral-200 text-neutral-800',
                  }
                  const colorClass = noteColor[note.note_type] ?? 'bg-neutral-50 border-neutral-200 text-neutral-800'
                  return (
                    <div key={note.id} className={`border rounded p-3 ${colorClass}`}>
                      <div className="flex items-center justify-between mb-1">
                        <p className="text-sm font-medium">{noteLabel[note.note_type] ?? note.note_type}</p>
                        <p className="text-xs opacity-70">{new Date(note.created_at).toLocaleDateString('en-PH')}</p>
                      </div>
                      {note.notes && <p className="text-sm opacity-90">{note.notes}</p>}
                      {note.items && note.items.length > 0 && (
                        <div className="mt-2 space-y-1">
                          {note.items.map((ni, idx) => (
                            <p key={idx} className="text-xs opacity-80">
                              {ni.item_description}: ordered {ni.quantity_ordered} → proposed {ni.negotiated_quantity ?? '—'}
                              {ni.vendor_item_notes ? ` (${ni.vendor_item_notes})` : ''}
                            </p>
                          ))}
                        </div>
                      )}
                    </div>
                  )
                })}
              </div>
            </CardBody>
          </Card>
        )}

        {/* ── Goods receipts link ────────────────────────────────────────────── */}
        {(po.status === 'in_transit' || po.status === 'partially_received' || po.status === 'fully_received' || po.status === 'closed') && (
          <Card>
            <CardBody>
              <Link
                to={`/procurement/goods-receipts?purchase_order_id=${po.id}`}
                className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900 font-medium underline underline-offset-2"
              >
                <PackageCheck className="w-4 h-4" />
                View Receipts ({po.items.reduce((sum, item) => sum + (item.quantity_received || 0), 0)} items received)
              </Link>
            </CardBody>
          </Card>
        )}

        {/* Document Chain */}
        <Card>
          <CardHeader>Document Chain</CardHeader>
          <CardBody>
            <ChainRecordTimeline documentType="purchase_order" documentId={po.id} />
          </CardBody>
        </Card>

        {/* Negotiation History */}
        {po.negotiation_round > 0 && (
          <Card>
            <CardHeader>Negotiation History</CardHeader>
            <CardBody>
              <NegotiationHistoryPanel poUlid={po.ulid} fulfillmentNotes={po.fulfillment_notes} />
            </CardBody>
          </Card>
        )}

        {/* Activity Timeline */}
        <Card>
          <CardHeader>Activity Timeline</CardHeader>
          <CardBody>
            <StatusTimeline auditableType="purchase_order" auditableId={po.id} />
          </CardBody>
        </Card>
      </div>
    </>
  )
}
