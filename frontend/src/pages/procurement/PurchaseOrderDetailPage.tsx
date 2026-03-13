import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, Send, XCircle, PackageCheck } from 'lucide-react'
import {
  usePurchaseOrder,
  useSendPurchaseOrder,
  useCancelPurchaseOrder,
} from '@/hooks/usePurchaseOrders'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import type { PurchaseOrderStatus } from '@/types/procurement'

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
            className="px-4 py-2 rounded text-sm font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
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
  const _navigate  = useNavigate()
  const { hasPermission } = useAuthStore()

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

  const canSend    = po.status === 'draft' && hasPermission('procurement.purchase-order.manage')
  const canCancel  = (po.status === 'draft' || po.status === 'sent') && hasPermission('procurement.purchase-order.manage')
  const canReceive = (po.status === 'sent' || po.status === 'partially_received') && hasPermission('procurement.goods-receipt.create')

  const actions = (
    <div className="flex items-center gap-3">
      {canReceive && (
        <Link
          to={`/procurement/goods-receipts/new?po_ulid=${po.ulid}`}
          className="flex items-center gap-2 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded"
        >
          <PackageCheck className="w-4 h-4" />
          Receive Goods
        </Link>
      )}
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
      {canCancel && (
        <button
          type="button"
          onClick={() => setShowCancelModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-white border border-red-300 text-red-600 text-sm font-medium rounded hover:bg-red-50"
        >
          <XCircle className="w-4 h-4" />
          Cancel PO
        </button>
      )}
    </div>
  )

  return (
    <>
      {showCancelModal && (
        <CancelModal
          onConfirm={handleCancel}
          onClose={() => setShowCancelModal(false)}
          loading={cancelMutation.isPending}
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

        {/* ── Goods receipts link ────────────────────────────────────────────── */}
        {(po.status === 'partially_received' || po.status === 'fully_received' || po.status === 'closed') && (
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
      </div>
    </>
  )
}
