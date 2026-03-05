import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, AlertTriangle, CheckCircle2, Send, XCircle } from 'lucide-react'
import {
  usePurchaseOrder,
  useSendPurchaseOrder,
  useCancelPurchaseOrder,
} from '@/hooks/usePurchaseOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { PurchaseOrderStatus } from '@/types/procurement'

const statusBadgeClass: Record<PurchaseOrderStatus, string> = {
  draft:              'bg-gray-100 text-gray-600',
  sent:               'bg-blue-100 text-blue-700',
  partially_received: 'bg-amber-100 text-amber-700',
  fully_received:     'bg-green-100 text-green-700',
  closed:             'bg-teal-100 text-teal-700',
  cancelled:          'bg-red-100 text-red-400',
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
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-2">Cancel Purchase Order</h2>
        <p className="text-sm text-gray-500 mb-4">
          This will cancel the PO. Please provide a reason.
        </p>
        <textarea
          className="w-full border rounded-lg p-2 text-sm resize-none h-24 focus:outline-none focus:ring-2 focus:ring-red-300"
          placeholder="Cancellation reason (min. 10 characters)"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
        />
        <div className="flex gap-2 mt-4 justify-end">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 rounded-lg text-sm text-gray-600 border border-gray-300 hover:bg-gray-50"
          >
            Close
          </button>
          <button
            type="button"
            disabled={reason.trim().length < 10 || loading}
            onClick={() => onConfirm(reason.trim())}
            className="px-4 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-50"
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
        <div className="flex items-center gap-3 text-red-600 bg-red-50 rounded-xl p-4">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <p className="text-sm">Failed to load Purchase Order. It may have been deleted or you lack access.</p>
        </div>
      </div>
    )
  }

  const canSend   = po.status === 'draft'
  const canCancel = po.status === 'draft' || po.status === 'sent'

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
              className="p-2 rounded-lg hover:bg-gray-100 text-gray-500"
              aria-label="Back"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <h1 className="text-xl font-semibold text-gray-800">{po.po_reference}</h1>
              <p className="text-sm text-gray-500 mt-0.5">Purchase Order</p>
            </div>
          </div>

          <span
            className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
              statusBadgeClass[po.status]
            }`}
          >
            {statusLabel[po.status]}
          </span>
        </div>

        {/* ── Order info card ────────────────────────────────────────────────── */}
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h2 className="text-sm font-semibold text-gray-700 mb-4">Order Details</h2>
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div>
              <dt className="text-gray-400 text-xs uppercase tracking-wide">Vendor</dt>
              <dd className="text-gray-800 font-medium mt-0.5">{po.vendor?.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-gray-400 text-xs uppercase tracking-wide">PO Date</dt>
              <dd className="text-gray-800 mt-0.5">
                {new Date(po.po_date).toLocaleDateString('en-PH')}
              </dd>
            </div>
            <div>
              <dt className="text-gray-400 text-xs uppercase tracking-wide">Delivery Date</dt>
              <dd className="text-gray-800 mt-0.5">
                {new Date(po.delivery_date).toLocaleDateString('en-PH')}
              </dd>
            </div>
            <div>
              <dt className="text-gray-400 text-xs uppercase tracking-wide">Payment Terms</dt>
              <dd className="text-gray-800 mt-0.5">{po.payment_terms}</dd>
            </div>
            {po.delivery_address && (
              <div className="sm:col-span-2">
                <dt className="text-gray-400 text-xs uppercase tracking-wide">Delivery Address</dt>
                <dd className="text-gray-800 mt-0.5">{po.delivery_address}</dd>
              </div>
            )}
            {po.purchase_request && (
              <div>
                <dt className="text-gray-400 text-xs uppercase tracking-wide">Source PR</dt>
                <dd className="mt-0.5">
                  <Link
                    to={`/procurement/purchase-requests/${po.purchase_request.ulid}`}
                    className="text-indigo-600 hover:underline font-medium"
                  >
                    {po.purchase_request.pr_reference}
                  </Link>
                </dd>
              </div>
            )}
            <div>
              <dt className="text-gray-400 text-xs uppercase tracking-wide">Created By</dt>
              <dd className="text-gray-800 mt-0.5">{po.created_by?.name ?? '—'}</dd>
            </div>
            {po.sent_at && (
              <div>
                <dt className="text-gray-400 text-xs uppercase tracking-wide">Sent At</dt>
                <dd className="text-gray-800 mt-0.5">
                  {new Date(po.sent_at).toLocaleDateString('en-PH')}
                </dd>
              </div>
            )}
            {po.notes && (
              <div className="sm:col-span-2">
                <dt className="text-gray-400 text-xs uppercase tracking-wide">Notes</dt>
                <dd className="text-gray-600 italic mt-0.5">{po.notes}</dd>
              </div>
            )}
            {po.cancellation_reason && (
              <div className="sm:col-span-2">
                <dt className="text-gray-400 text-xs uppercase tracking-wide">Cancellation Reason</dt>
                <dd className="text-red-600 mt-0.5">{po.cancellation_reason}</dd>
              </div>
            )}
          </dl>
        </div>

        {/* ── Line items ────────────────────────────────────────────────────── */}
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="text-sm font-semibold text-gray-700">Line Items</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 text-left">
                <tr>
                  <th className="px-4 py-3 font-medium text-gray-500">#</th>
                  <th className="px-4 py-3 font-medium text-gray-500">Description</th>
                  <th className="px-4 py-3 font-medium text-gray-500 text-right">Qty Ordered</th>
                  <th className="px-4 py-3 font-medium text-gray-500">UOM</th>
                  <th className="px-4 py-3 font-medium text-gray-500 text-right">Unit Cost</th>
                  <th className="px-4 py-3 font-medium text-gray-500 text-right">Total</th>
                  <th className="px-4 py-3 font-medium text-gray-500 text-right">Received</th>
                  <th className="px-4 py-3 font-medium text-gray-500 text-right">Pending</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {po.items.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-gray-400">{item.line_order}</td>
                    <td className="px-4 py-3 text-gray-800">{item.item_description}</td>
                    <td className="px-4 py-3 text-right text-gray-800">{item.quantity_ordered}</td>
                    <td className="px-4 py-3 text-gray-600">{item.unit_of_measure}</td>
                    <td className="px-4 py-3 text-right text-gray-800">
                      ₱{item.agreed_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="px-4 py-3 text-right text-gray-800 font-medium">
                      ₱{item.total_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="px-4 py-3 text-right text-gray-600">{item.quantity_received}</td>
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
              <tfoot className="bg-gray-50">
                <tr>
                  <td colSpan={5} className="px-4 py-3 text-right text-sm font-semibold text-gray-600">
                    Grand Total
                  </td>
                  <td className="px-4 py-3 text-right text-sm font-bold text-gray-800">
                    ₱{po.total_po_amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                  </td>
                  <td colSpan={2} />
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {/* ── Status timeline ────────────────────────────────────────────────── */}
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h2 className="text-sm font-semibold text-gray-700 mb-4">Timeline</h2>
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 bg-green-100">
                <CheckCircle2 className="w-4 h-4 text-green-600" />
              </div>
              <div>
                <p className="text-sm font-medium text-gray-800">Created</p>
                <p className="text-xs text-gray-500 mt-0.5">
                  {po.created_by?.name ?? '—'}
                  <span className="ml-2 text-gray-400">
                    {new Date(po.created_at).toLocaleDateString('en-PH')}
                  </span>
                </p>
              </div>
            </div>

            <div className="flex items-start gap-3">
              <div
                className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 ${
                  po.sent_at ? 'bg-green-100' : 'bg-gray-100'
                }`}
              >
                {po.sent_at ? (
                  <CheckCircle2 className="w-4 h-4 text-green-600" />
                ) : (
                  <div className="w-2 h-2 rounded-full bg-gray-400" />
                )}
              </div>
              <div>
                <p
                  className={`text-sm font-medium ${
                    po.sent_at ? 'text-gray-800' : 'text-gray-400'
                  }`}
                >
                  Sent to Vendor
                </p>
                {po.sent_at && (
                  <p className="text-xs text-gray-500 mt-0.5">
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
                    <p className="text-xs text-gray-500 mt-0.5">"{po.cancellation_reason}"</p>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* ── Goods receipts link ────────────────────────────────────────────── */}
        <Link
          to={`/procurement/goods-receipts?purchase_order_id=${po.id}`}
          className="block rounded-xl border border-gray-200 bg-white p-4 hover:bg-gray-50 transition-colors text-sm text-indigo-600 font-medium"
        >
          View Goods Receipts for this PO →
        </Link>

        {/* ── Actions ───────────────────────────────────────────────────────── */}
        {(canSend || canCancel) && (
          <div className="flex items-center gap-3">
            {canSend && (
              <button
                type="button"
                onClick={handleSend}
                disabled={sendMutation.isPending}
                className="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
              >
                <Send className="w-4 h-4" />
                {sendMutation.isPending ? 'Sending…' : 'Send to Vendor'}
              </button>
            )}
            {canCancel && (
              <button
                type="button"
                onClick={() => setShowCancelModal(true)}
                className="flex items-center gap-2 px-5 py-2.5 rounded-lg border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50"
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
