import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, AlertTriangle, CheckCircle2, Send, XCircle, PackageCheck } from 'lucide-react'
import {
  usePurchaseOrder,
  useSendPurchaseOrder,
  useCancelPurchaseOrder,
} from '@/hooks/usePurchaseOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { PurchaseOrderStatus } from '@/types/procurement'

const statusBadgeClass: Record<PurchaseOrderStatus, string> = {
  draft:              'bg-neutral-100 text-neutral-600',
  sent:               'bg-neutral-200 text-neutral-800',
  partially_received: 'bg-neutral-100 text-neutral-700',
  fully_received:     'bg-neutral-200 text-neutral-800',
  closed:             'bg-neutral-100 text-neutral-500',
  cancelled:          'bg-neutral-100 text-neutral-400',
}

const statusLabel: Record<PurchaseOrderStatus, string> = {
  draft:              'Draft',
  sent:               'Sent to Vendor',
  partially_received: 'Partially Received',
  fully_received:     'Fully Received',
  closed:             'Closed',
  cancelled:          'Cancelled',
}

// ── Cancel modal ─────────────────────────────────────────────────────────────

function CancelModal({
  onConfirm,
  onClose,
  loading,
}: {
  onConfirm: (reason: string) => void
  onClose: () => void
  loading: boolean
}): React.ReactElement {
  const [reason, setReason] = useState('')
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded w-full max-w-md max-h-[90vh] overflow-y-auto mx-4 p-4 sm:p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-2">Cancel Purchase Order</h2>
        <p className="text-sm text-neutral-500 mb-4">
          This will cancel the PO. Please provide a reason.
        </p>
        <textarea
          className="w-full border border-neutral-300 rounded p-2 text-sm resize-none h-24 focus:outline-none focus:ring-1 focus:ring-neutral-400"
          placeholder="Cancellation reason (min. 10 characters)"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
        />
        <div className="flex flex-col-reverse sm:flex-row gap-2 sm:gap-2 mt-4 justify-end">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 rounded text-sm text-neutral-600 border border-neutral-300 hover:bg-neutral-50"
          >
            Close
          </button>
          <button
            type="button"
            disabled={reason.trim().length < 10 || loading}
            onClick={() => onConfirm(reason.trim())}
            className="px-4 py-2 rounded text-sm font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-50"
          >
            {loading ? 'Cancelling…' : 'Cancel PO'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────

export default function PurchaseOrderDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate  = useNavigate()

  const { data: po, isLoading, isError } = usePurchaseOrder(ulid ?? null)

  const sendMutation   = useSendPurchaseOrder()
  const cancelMutation = useCancelPurchaseOrder()

  const [showCancelModal, setShowCancelModal] = useState(false)

  // ── handlers ────────────────────────────────────────────────────────────────

  function handleSend(): void {
    if (!po) return
    sendMutation.mutate(po.ulid, {
      onSuccess: () => toast.success('Purchase Order sent to vendor.'),
      onError:   () => toast.error('Failed to send PO. Please try again.'),
    })
  }

  function handleCancel(reason: string): void {
    if (!po) return
    cancelMutation.mutate(
      { ulid: po.ulid, reason },
      {
        onSuccess: () => {
          toast.success('Purchase Order cancelled.')
          setShowCancelModal(false)
        },
        onError: () => toast.error('Failed to cancel PO.'),
      },
    )
  }

  // ── render states ────────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="max-w-4xl mx-auto p-6 space-y-4">
        <SkeletonLoader />
      </div>
    )
  }

  if (isError || !po) {
    return (
      <div className="max-w-4xl mx-auto p-6">
        <div className="flex items-center gap-3 text-red-600 bg-red-50 rounded p-4">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <p className="text-sm">Failed to load Purchase Order. It may have been deleted or you lack access.</p>
        </div>
      </div>
    )
  }

  const canSend    = po.status === 'draft'
  const canCancel  = po.status === 'draft' || po.status === 'sent'
  const canReceive = po.status === 'sent' || po.status === 'partially_received'

  return (
    <>
      {showCancelModal && (
        <CancelModal
          onConfirm={handleCancel}
          onClose={() => setShowCancelModal(false)}
          loading={cancelMutation.isPending}
        />
      )}

      <div className="max-w-4xl mx-auto p-6 space-y-6">

        {/* ── Header ────────────────────────────────────────────────────────── */}
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => navigate(-1)}
              className="p-2 rounded hover:bg-neutral-100 text-neutral-500"
              aria-label="Back"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-neutral-900">{po.po_reference}</h1>
              <p className="text-sm text-neutral-500 mt-0.5">Purchase Order</p>
            </div>
          </div>

          <span
            className={`inline-flex items-center px-3 py-1 rounded text-xs font-medium ${
              statusBadgeClass[po.status]
            }`}
          >
            {statusLabel[po.status]}
          </span>
        </div>

        {/* ── Order info card ────────────────────────────────────────────────── */}
        <div className="bg-white rounded border border-neutral-200 p-6">
          <h2 className="text-sm font-medium text-neutral-700 mb-4">Order Details</h2>
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div>
              <dt className="text-neutral-400 text-xs">Vendor</dt>
              <dd className="text-neutral-800 font-medium mt-0.5">{po.vendor?.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-neutral-400 text-xs">PO Date</dt>
              <dd className="text-neutral-800 mt-0.5">
                {new Date(po.po_date).toLocaleDateString('en-PH')}
              </dd>
            </div>
            <div>
              <dt className="text-neutral-400 text-xs">Delivery Date</dt>
              <dd className="text-neutral-800 mt-0.5">
                {new Date(po.delivery_date).toLocaleDateString('en-PH')}
              </dd>
            </div>
            <div>
              <dt className="text-neutral-400 text-xs">Payment Terms</dt>
              <dd className="text-neutral-800 mt-0.5">{po.payment_terms}</dd>
            </div>
            {po.delivery_address && (
              <div className="sm:col-span-2">
                <dt className="text-neutral-400 text-xs">Delivery Address</dt>
                <dd className="text-neutral-800 mt-0.5">{po.delivery_address}</dd>
              </div>
            )}
            {po.purchase_request && (
              <div>
                <dt className="text-neutral-400 text-xs">Source PR</dt>
                <dd className="mt-0.5">
                  <Link
                    to={`/procurement/purchase-requests/${po.purchase_request.ulid}`}
                    className="text-neutral-700 hover:text-neutral-900 font-medium"
                  >
                    {po.purchase_request.pr_reference}
                  </Link>
                </dd>
              </div>
            )}
            <div>
              <dt className="text-neutral-400 text-xs">Created By</dt>
              <dd className="text-neutral-800 mt-0.5">{po.created_by?.name ?? '—'}</dd>
            </div>
            {po.sent_at && (
              <div>
                <dt className="text-neutral-400 text-xs">Sent At</dt>
                <dd className="text-neutral-800 mt-0.5">
                  {new Date(po.sent_at).toLocaleDateString('en-PH')}
                </dd>
              </div>
            )}
            {po.notes && (
              <div className="sm:col-span-2">
                <dt className="text-neutral-400 text-xs">Notes</dt>
                <dd className="text-neutral-600 italic mt-0.5">{po.notes}</dd>
              </div>
            )}
            {po.cancellation_reason && (
              <div className="sm:col-span-2">
                <dt className="text-neutral-400 text-xs">Cancellation Reason</dt>
                <dd className="text-red-600 mt-0.5">{po.cancellation_reason}</dd>
              </div>
            )}
          </dl>
        </div>

        {/* ── Line items ────────────────────────────────────────────────────── */}
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-neutral-100">
            <h2 className="text-sm font-medium text-neutral-700">Line Items</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 text-left">
                <tr>
                  <th className="px-4 py-3 font-medium text-neutral-600">#</th>
                  <th className="px-4 py-3 font-medium text-neutral-600">Description</th>
                  <th className="px-4 py-3 font-medium text-neutral-600 text-right">Qty Ordered</th>
                  <th className="px-4 py-3 font-medium text-neutral-600">UOM</th>
                  <th className="px-4 py-3 font-medium text-neutral-600 text-right">Unit Cost</th>
                  <th className="px-4 py-3 font-medium text-neutral-600 text-right">Total</th>
                  <th className="px-4 py-3 font-medium text-neutral-600 text-right">Received</th>
                  <th className="px-4 py-3 font-medium text-neutral-600 text-right">Pending</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {po.items.map((item) => (
                  <tr key={item.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-3 text-neutral-400">{item.line_order}</td>
                    <td className="px-4 py-3 text-neutral-800">{item.item_description}</td>
                    <td className="px-4 py-3 text-right text-neutral-800">{item.quantity_ordered}</td>
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
                          item.quantity_pending > 0 ? 'text-amber-600' : 'text-green-600'
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
                  <td colSpan={5} className="px-4 py-3 text-right text-sm font-semibold text-neutral-600">
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
        </div>

        {/* ── Status timeline ────────────────────────────────────────────────── */}
        <div className="bg-white rounded border border-neutral-200 p-6">
          <h2 className="text-sm font-medium text-neutral-700 mb-4">Timeline</h2>
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
        </div>

        {/* ── Goods receipts ────────────────────────────────────────────── */}
        <div className="flex items-center justify-between rounded border border-neutral-200 bg-white p-4">
          <Link
            to={`/procurement/goods-receipts?purchase_order_id=${po.id}`}
            className="text-sm text-neutral-700 font-medium hover:text-neutral-900"
          >
            View Goods Receipts for this PO →
          </Link>
          {canReceive && (
            <Link
              to={`/procurement/goods-receipts/new?po_ulid=${po.ulid}`}
              className="flex items-center gap-2 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded"
            >
              <PackageCheck className="w-4 h-4" />
              Receive Goods
            </Link>
          )}
        </div>

        {/* ── Actions ───────────────────────────────────────────────────────── */}
        {(canSend || canCancel) && (
          <div className="flex items-center gap-3">
            {canSend && (
              <button
                type="button"
                onClick={handleSend}
                disabled={sendMutation.isPending}
                className="flex items-center gap-2 px-5 py-2.5 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50"
              >
                <Send className="w-4 h-4" />
                {sendMutation.isPending ? 'Sending…' : 'Send to Vendor'}
              </button>
            )}
            {canCancel && (
              <button
                type="button"
                onClick={() => setShowCancelModal(true)}
                className="flex items-center gap-2 px-5 py-2.5 rounded border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50"
              >
                <XCircle className="w-4 h-4" />
                Cancel PO
              </button>
            )}
          </div>
        )}
      </div>
    </>
  )
}
