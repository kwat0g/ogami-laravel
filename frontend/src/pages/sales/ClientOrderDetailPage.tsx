import { firstErrorMessage } from '@/lib/errorHandler'
import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, CheckCircle, XCircle, MessageCircle, Calendar, Package, AlertCircle, RotateCcw, ShieldCheck, Truck } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { ActionGuard } from '@/components/ui/ActionGuard'
import {
  useClientOrder,
  useApproveClientOrder,
  useRejectClientOrder,
  useNegotiateClientOrder,
  useSalesRespondToCounter,
  useVpApproveClientOrder
} from '@/hooks/useClientOrders'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusTimeline from '@/components/ui/StatusTimeline'
import { getClientOrderSteps, isRejectedStatus } from '@/lib/workflowSteps'
import { toast } from 'sonner'
import { NEGOTIATION_REASONS, REJECTION_REASONS } from '@/types/client-order'
import type { ClientOrder } from '@/types/client-order'

const STATUS_LABEL: Record<string, string> = {
  pending: 'Pending',
  negotiating: 'Awaiting Client Response',
  client_responded: 'Client Counter-Proposal',
  vp_pending: 'Awaiting VP Approval',
  approved: 'Approved',
  completed: 'Completed',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-700',
  negotiating: 'bg-blue-100 text-blue-700',
  client_responded: 'bg-purple-100 text-purple-700',
  vp_pending: 'bg-orange-100 text-orange-700',
  approved: 'bg-emerald-100 text-emerald-700',
  completed: 'bg-teal-100 text-teal-700',
  rejected: 'bg-red-100 text-red-700',
  cancelled: 'bg-neutral-100 text-neutral-500',
}

const STATUS_DESCRIPTION: Record<string, string> = {
  pending: 'Order is pending review',
  negotiating: 'Sales proposed changes, waiting for client response',
  client_responded: 'Client made a counter-proposal, awaiting sales decision',
  vp_pending: 'High-value order escalated — awaiting VP approval',
  approved: 'Order has been approved — delivery schedules and production orders created',
  completed: 'Order has been delivered and completed',
  rejected: 'Order was rejected',
  cancelled: 'Order was cancelled',
}

function formatCurrency(centavos: number) {
  return '₱' + (centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function StatusBadge({ status }: { status: string }) {
  return (
    <span className={`px-2.5 py-1 rounded text-xs font-medium ${STATUS_COLORS[status] ?? 'bg-neutral-100 text-neutral-600'}`}>
      {STATUS_LABEL[status] ?? status}
    </span>
  )
}

// Component to display negotiation history/proposal
function NegotiationPanel({ order }: { order: ClientOrder }) {
  if (!order.last_proposal) return null

  const isClientTurn = order.negotiation_turn === 'client'
  const isSalesTurn = order.negotiation_turn === 'sales'
  const proposal = order.last_proposal

  return (
    <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
      <div className="flex items-start justify-between mb-3">
        <div>
          <h3 className="font-medium text-purple-900 flex items-center gap-2">
            <RotateCcw className="h-4 w-4" />
            Negotiation Round {order.negotiation_round}
          </h3>
          <p className="text-sm text-purple-700 mt-1">
            Last proposal by: <span className="font-medium capitalize">{proposal.by}</span>
          </p>
        </div>
        {isClientTurn && (
          <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded font-medium">
            Awaiting Client
          </span>
        )}
        {isSalesTurn && (
          <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded font-medium">
            Awaiting Your Response
          </span>
        )}
      </div>

      {proposal.changes && (
        <div className="space-y-2 text-sm">
          {proposal.changes.delivery_date && (
            <div className="flex items-center gap-2">
              <Calendar className="h-4 w-4 text-purple-500" />
              <span className="text-purple-800">
                Proposed delivery: {new Date(proposal.changes.delivery_date).toLocaleDateString('en-PH', {
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric'
                })}
              </span>
            </div>
          )}
          {proposal.notes && (
            <div className="mt-2 p-2 bg-white/60 rounded text-purple-800">
              <span className="font-medium">Note:</span> {proposal.notes}
            </div>
          )}
        </div>
      )}

      {proposal.proposed_at && (
        <p className="text-xs text-purple-500 mt-3">
          Proposed on {new Date(proposal.proposed_at).toLocaleString('en-PH')}
        </p>
      )}
    </div>
  )
}

export default function ClientOrderDetailPage(): JSX.Element {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const orderUlid = ulid || ''

  const { data: order, isLoading } = useClientOrder(orderUlid)
  const approveMutation = useApproveClientOrder()
  const rejectMutation = useRejectClientOrder()
  const negotiateMutation = useNegotiateClientOrder()
  const salesRespondMutation = useSalesRespondToCounter()
  const vpApproveMutation = useVpApproveClientOrder()

  // Stock preview: fetch stock balances for all items in this order
  const itemIds = order?.items?.map((i: { item_master_id: number }) => i.item_master_id).filter(Boolean) ?? []
  const { data: stockData } = useQuery({
    queryKey: ['stock-balances-preview', itemIds],
    queryFn: async () => {
      if (itemIds.length === 0) return {}
      const res = await api.get('/inventory/stock-balances', { params: { item_ids: itemIds.join(','), per_page: 100 } })
      const balances: Record<number, number> = {}
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      for (const b of (res.data?.data ?? [])) {
        const itemId = b.item_id ?? b.item_master_id
        if (itemId) balances[itemId] = (balances[itemId] ?? 0) + parseFloat(b.quantity_on_hand ?? b.balance ?? '0')
      }
      return balances
    },
    enabled: itemIds.length > 0,
    staleTime: 30_000,
  })
  const stockMap: Record<number, number> = stockData ?? {}

  // Modal states
  const [showApproveModal, setShowApproveModal] = useState(false)
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [showNegotiateModal, setShowNegotiateModal] = useState(false)
  const [showSalesResponseModal, setShowSalesResponseModal] = useState(false)
  const [showVpApproveModal, setShowVpApproveModal] = useState(false)
  const [vpApproveNotes, setVpApproveNotes] = useState('')

  // Form states
  const [approveNotes, setApproveNotes] = useState('')
  const [rejectReason, setRejectReason] = useState('')
  const [rejectNotes, setRejectNotes] = useState('')
  const [negotiateReason, setNegotiateReason] = useState('')
  const [negotiateNotes, setNegotiateNotes] = useState('')
  const [proposedDeliveryDate, setProposedDeliveryDate] = useState('')

  // Sales response to counter
  const [salesResponse, setSalesResponse] = useState<'accept' | 'counter' | 'reject'>('accept')
  const [counterDeliveryDate, setCounterDeliveryDate] = useState('')
  const [counterNotes, setCounterNotes] = useState('')

  const handleApprove = async () => {
    try {
      await approveMutation.mutateAsync({ orderUlid, notes: approveNotes })
      toast.success('Order approved successfully')
      setShowApproveModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to approve order'))
    }
  }

  const handleReject = async () => {
    if (!rejectReason) {
      toast.error('Please select a rejection reason')
      return
    }
    try {
      await rejectMutation.mutateAsync({ orderUlid, reason: rejectReason, notes: rejectNotes })
      toast.success('Order rejected')
      setShowRejectModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to reject order'))
    }
  }

  const handleNegotiate = async () => {
    if (!negotiateReason) {
      toast.error('Please select a negotiation reason')
      return
    }
    try {
      await negotiateMutation.mutateAsync({
        orderUlid,
        reason: negotiateReason,
        proposedChanges: proposedDeliveryDate ? { deliveryDate: proposedDeliveryDate } : undefined,
        notes: negotiateNotes,
      })
      toast.success('Negotiation proposal sent to client')
      setShowNegotiateModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to send negotiation'))
    }
  }

  const handleSalesResponse = async () => {
    try {
      await salesRespondMutation.mutateAsync({
        orderUlid,
        response: salesResponse,
        counterProposals: salesResponse === 'counter' && counterDeliveryDate
          ? { deliveryDate: counterDeliveryDate, notes: counterNotes }
          : salesResponse === 'reject'
          ? { reason: 'negotiation_failed' }
          : undefined,
        notes: counterNotes,
      })
      toast.success(`Response sent: ${salesResponse}`)
      setShowSalesResponseModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to send response'))
    }
  }

  if (isLoading) {
    return <SkeletonLoader rows={5} />
  }

  if (!order) {
    return (
      <div className="text-center py-16 text-neutral-400">
        <p>Order not found</p>
        <button
          onClick={() => navigate('/sales/client-orders')}
          className="mt-4 text-neutral-600 hover:text-neutral-900 text-sm underline"
        >
          Back to Orders
        </button>
      </div>
    )
  }

  const handleVpApprove = async () => {
    try {
      await vpApproveMutation.mutateAsync({ orderUlid, notes: vpApproveNotes })
      toast.success('Order VP-approved successfully')
      setShowVpApproveModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to VP-approve order'))
    }
  }

  // Determine available actions based on status
  const canApprove = order.status === 'pending' || order.status === 'client_responded'
  const canReject = order.status === 'pending' || order.status === 'negotiating' || order.status === 'client_responded'
  const canNegotiate = order.status === 'pending'
  const canRespondToCounter = order.status === 'client_responded'
  const canVpApprove = order.status === 'vp_pending'

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate('/sales/client-orders')}
            className="p-2 hover:bg-neutral-100 rounded-lg border border-neutral-200"
          >
            <ArrowLeft className="h-5 w-5 text-neutral-600" />
          </button>
          <PageHeader 
            title={`Order ${order.order_reference}`}
          />
        </div>
        
        <div className="flex items-center gap-2">
          {canRespondToCounter && (
            <ActionGuard permission="sales.order_negotiate">
              <button
                onClick={() => setShowSalesResponseModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-purple-100 text-purple-800 text-sm rounded hover:bg-purple-200 border border-purple-300"
              >
                <MessageCircle className="h-4 w-4" />
                Respond to Counter
              </button>
            </ActionGuard>
          )}
          {canNegotiate && (
            <ActionGuard permission="sales.order_negotiate">
              <button
                onClick={() => setShowNegotiateModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-100 text-neutral-800 text-sm rounded hover:bg-neutral-200 border border-neutral-300"
              >
                <MessageCircle className="h-4 w-4" />
                Negotiate
              </button>
            </ActionGuard>
          )}
          {canReject && (
            <ActionGuard permission="sales.order_reject">
              <button
                onClick={() => setShowRejectModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-100 text-neutral-800 text-sm rounded hover:bg-neutral-200 border border-neutral-300"
              >
                <XCircle className="h-4 w-4" />
                Reject
              </button>
            </ActionGuard>
          )}
          {canApprove && (
            <ActionGuard permission="sales.order_approve">
              <button
                onClick={() => setShowApproveModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
              >
                <CheckCircle className="h-4 w-4" />
                Approve
              </button>
            </ActionGuard>
          )}
          {canVpApprove && (
            <ActionGuard permission="sales.order_vp_approve">
              <button
                onClick={() => setShowVpApproveModal(true)}
                className="flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm rounded hover:bg-orange-700"
              >
                <ShieldCheck className="h-4 w-4" />
                VP Approve
              </button>
            </ActionGuard>
          )}
        </div>
      </div>

      <p className="text-sm text-neutral-500">
        Submitted by <span className="font-medium text-neutral-700">{order.customer?.name}</span> on {' '}
        {new Date(order.created_at).toLocaleDateString('en-PH', {
          month: 'long',
          day: 'numeric',
          year: 'numeric'
        })}
      </p>

      {/* Workflow Timeline */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4">
        <StatusTimeline
          steps={getClientOrderSteps(order)}
          currentStatus={order.status}
          direction="horizontal"
          isRejected={isRejectedStatus(order.status)}
        />
      </div>

      {/* Status Alert */}
      <div className="flex items-start gap-3 p-4 bg-neutral-50 border border-neutral-200 rounded-lg">
        <AlertCircle className="h-5 w-5 text-neutral-500 mt-0.5" />
        <div>
          <div className="flex items-center gap-2">
            <span className="text-sm text-neutral-500">Status:</span>
            <StatusBadge status={order.status} />
          </div>
          <p className="text-sm text-neutral-600 mt-1">
            {STATUS_DESCRIPTION[order.status]}
          </p>
        </div>
      </div>

      {/* Negotiation Panel */}
      {(order.status === 'negotiating' || order.status === 'client_responded') && (
        <NegotiationPanel order={order} />
      )}

      {/* Order Info */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white rounded border border-neutral-200 p-4">
          <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Client</p>
          <p className="font-semibold text-neutral-900 mt-1">{order.customer?.name}</p>
          <p className="text-sm text-neutral-500">{order.customer?.email}</p>
        </div>
        <div className="bg-white rounded border border-neutral-200 p-4">
          <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Status</p>
          <div className="mt-1">
            <StatusBadge status={order.status} />
          </div>
          {order.negotiation_round > 0 && (
            <p className="text-xs text-neutral-500 mt-1">
              Round {order.negotiation_round}
            </p>
          )}
        </div>
        <div className="bg-white rounded border border-neutral-200 p-4">
          <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Total Amount</p>
          <p className="text-lg font-semibold text-neutral-900 mt-1 font-mono">
            {formatCurrency(order.total_amount_centavos)}
          </p>
        </div>
      </div>

      {/* Items Table */}
      <div className="bg-white rounded border border-neutral-200 overflow-hidden">
        <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
          <h2 className="font-medium text-neutral-900 flex items-center gap-2">
            <Package className="h-4 w-4 text-neutral-500" />
            Order Items
          </h2>
        </div>
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Item</th>
              <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Quantity</th>
              <th className="text-right px-3 py-2.5 font-medium text-neutral-600">In Stock</th>
              <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Unit Price</th>
              <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Total</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {order.items.map((item) => (
              <tr key={item.id} className="hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-3">
                  <p className="font-medium text-neutral-900">{item.item_description}</p>
                  {item.line_notes && (
                    <p className="text-xs text-neutral-500 mt-0.5">Note: {item.line_notes}</p>
                  )}
                  {(item.negotiated_quantity || item.negotiated_price_centavos) && (
                    <p className="text-xs text-purple-600 mt-0.5">
                      Negotiated: {item.negotiated_quantity ? `Qty: ${item.negotiated_quantity}` : ''}
                      {item.negotiated_price_centavos ? ` Price: ${formatCurrency(item.negotiated_price_centavos)}` : ''}
                    </p>
                  )}
                </td>
                <td className="px-3 py-3 text-right text-neutral-700">
                  {item.quantity} {item.unit_of_measure}
                </td>
                <td className="px-3 py-3 text-right">
                  {item.item_master_id && stockMap[item.item_master_id] !== undefined ? (
                    <span className={`font-mono text-sm ${
                      stockMap[item.item_master_id] >= parseFloat(item.quantity)
                        ? 'text-green-700'
                        : stockMap[item.item_master_id] > 0
                          ? 'text-amber-600'
                          : 'text-red-600'
                    }`}>
                      {stockMap[item.item_master_id].toLocaleString('en-PH', { maximumFractionDigits: 2 })}
                      {stockMap[item.item_master_id] >= parseFloat(item.quantity)
                        ? ' (sufficient)'
                        : stockMap[item.item_master_id] > 0
                          ? ' (partial)'
                          : ' (none)'}
                    </span>
                  ) : (
                    <span className="text-neutral-400 text-xs">--</span>
                  )}
                </td>
                <td className="px-3 py-3 text-right font-mono text-neutral-700">
                  {formatCurrency(item.unit_price_centavos)}
                </td>
                <td className="px-3 py-3 text-right font-mono font-medium text-neutral-900">
                  {formatCurrency(item.line_total_centavos)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Delivery Info */}
      <div className="bg-white rounded border border-neutral-200 overflow-hidden">
        <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
          <h2 className="font-medium text-neutral-900 flex items-center gap-2">
            <Calendar className="h-4 w-4 text-neutral-500" />
            Delivery Information
          </h2>
        </div>
        <div className="p-4 grid grid-cols-2 gap-6">
          <div>
            <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Requested Delivery Date</p>
            <p className="text-neutral-900 mt-1">
              {order.requested_delivery_date 
                ? new Date(order.requested_delivery_date).toLocaleDateString('en-PH', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                  })
                : 'Not specified'
              }
            </p>
          </div>
          {order.agreed_delivery_date && (
            <div>
              <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Agreed Delivery Date</p>
              <p className="text-emerald-700 font-medium mt-1">
                {new Date(order.agreed_delivery_date).toLocaleDateString('en-PH', {
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric'
                })}
              </p>
            </div>
          )}
        </div>
        {order.client_notes && (
          <div className="px-4 py-3 border-t border-neutral-200 bg-neutral-50/50">
            <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide">Client Notes</p>
            <p className="text-sm text-neutral-700 mt-1">{order.client_notes}</p>
          </div>
        )}
      </div>

      {/* Delivery Schedules — shown after approval */}
      {(order.status === 'approved' || order.status === 'completed') && order.deliverySchedules && order.deliverySchedules.length > 0 && (
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <h2 className="font-medium text-neutral-900 flex items-center gap-2">
              <Truck className="h-4 w-4 text-neutral-500" />
              Delivery Schedules
            </h2>
          </div>
          <div className="p-4 space-y-3">
            <p className="text-sm text-neutral-500">
              {order.deliverySchedules.length} delivery schedule{order.deliverySchedules.length !== 1 ? 's' : ''} created for this order.
              View details in <a href="/production/combined-delivery-schedules" className="underline text-neutral-700 hover:text-neutral-900">Combined Delivery Schedules</a> or <a href="/production/orders" className="underline text-neutral-700 hover:text-neutral-900">Production Orders</a>.
            </p>
            <div className="grid gap-2">
              {order.deliverySchedules.map((ds) => {
                const sched = ds.deliverySchedule
                if (!sched) return null
                const schedStatusColors: Record<string, string> = {
                  open: 'bg-amber-100 text-amber-700',
                  ready: 'bg-emerald-100 text-emerald-700',
                  dispatched: 'bg-blue-100 text-blue-700',
                  delivered: 'bg-teal-100 text-teal-700',
                  closed: 'bg-neutral-100 text-neutral-500',
                }
                return (
                  <a
                    key={ds.id}
                    href={`/production/delivery-schedules/${sched.ulid}`}
                    className="flex items-center justify-between p-3 bg-neutral-50 border border-neutral-100 rounded-lg hover:bg-neutral-100 transition-colors"
                  >
                    <div>
                      <span className="text-sm font-mono text-neutral-700">{sched.ds_reference}</span>
                      <p className="text-xs text-neutral-500">
                        Target: {sched.target_delivery_date
                          ? new Date(sched.target_delivery_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
                          : 'TBD'}
                      </p>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${schedStatusColors[sched.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                      {sched.status}
                    </span>
                  </a>
                )
              })}
            </div>
          </div>
        </div>
      )}

      {/* Post-approval next steps when no delivery schedules yet */}
      {order.status === 'approved' && (!order.deliverySchedules || order.deliverySchedules.length === 0) && (
        <div className="flex items-start gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
          <CheckCircle className="h-5 w-5 text-emerald-600 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-emerald-900">Order approved</p>
            <p className="text-sm text-emerald-700 mt-1">
              Delivery schedules and production orders have been created. View them in{' '}
              <a href="/production/combined-delivery-schedules" className="underline font-medium">Combined Delivery Schedules</a>{' '}
              or <a href="/production/orders" className="underline font-medium">Production Orders</a>.
            </p>
          </div>
        </div>
      )}

      {/* Approve Modal */}
      {showApproveModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
            <h2 className="text-lg font-semibold text-neutral-900">Approve Order</h2>
            <p className="text-sm text-neutral-500 mt-2">
              This will create a delivery schedule for {order.customer?.name}.
            </p>
            <textarea
              value={approveNotes}
              onChange={(e) => setApproveNotes(e.target.value)}
              placeholder="Internal notes (optional)"
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 mt-4 text-sm focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex gap-3 mt-4">
              <button
                onClick={() => setShowApproveModal(false)}
                className="flex-1 py-2 border border-neutral-300 rounded text-sm hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                onClick={handleApprove}
                disabled={approveMutation.isPending}
                className="flex-1 py-2 bg-neutral-900 text-white rounded text-sm hover:bg-neutral-800 disabled:opacity-50"
              >
                {approveMutation.isPending ? 'Approving...' : 'Approve Order'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
            <h2 className="text-lg font-semibold text-neutral-900">Reject Order</h2>
            <div className="space-y-4 mt-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Reason</label>
                <select
                  value={rejectReason}
                  onChange={(e) => setRejectReason(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">Select reason...</option>
                  {Object.entries(REJECTION_REASONS).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                  ))}
                </select>
              </div>
              <textarea
                value={rejectNotes}
                onChange={(e) => setRejectNotes(e.target.value)}
                placeholder="Additional notes for client (optional)"
                rows={3}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              />
            </div>
            <div className="flex gap-3 mt-4">
              <button
                onClick={() => setShowRejectModal(false)}
                className="flex-1 py-2 border border-neutral-300 rounded text-sm hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                onClick={handleReject}
                disabled={rejectMutation.isPending}
                className="flex-1 py-2 bg-neutral-900 text-white rounded text-sm hover:bg-neutral-800 disabled:opacity-50"
              >
                {rejectMutation.isPending ? 'Rejecting...' : 'Reject Order'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Negotiate Modal */}
      {showNegotiateModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
            <h2 className="text-lg font-semibold text-neutral-900">Negotiate Order</h2>
            <p className="text-sm text-neutral-500 mt-2">
              Propose changes to the client.
            </p>
            <div className="space-y-4 mt-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Reason</label>
                <select
                  value={negotiateReason}
                  onChange={(e) => setNegotiateReason(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                >
                  <option value="">Select reason...</option>
                  {Object.entries(NEGOTIATION_REASONS).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Proposed Delivery Date</label>
                <input
                  type="date"
                  value={proposedDeliveryDate}
                  onChange={(e) => setProposedDeliveryDate(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                />
              </div>
              <textarea
                value={negotiateNotes}
                onChange={(e) => setNegotiateNotes(e.target.value)}
                placeholder="Message to client (optional)"
                rows={3}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              />
            </div>
            <div className="flex gap-3 mt-4">
              <button
                onClick={() => setShowNegotiateModal(false)}
                className="flex-1 py-2 border border-neutral-300 rounded text-sm hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                onClick={handleNegotiate}
                disabled={negotiateMutation.isPending}
                className="flex-1 py-2 bg-neutral-900 text-white rounded text-sm hover:bg-neutral-800 disabled:opacity-50"
              >
                {negotiateMutation.isPending ? 'Sending...' : 'Send Proposal'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* VP Approve Modal */}
      {showVpApproveModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
            <h2 className="text-lg font-semibold text-neutral-900">VP Approve Order</h2>
            <p className="text-sm text-neutral-500 mt-2">
              This is a high-value order requiring VP approval. Approving will create a delivery schedule.
            </p>
            <div className="mt-4 p-3 bg-orange-50 border border-orange-100 rounded text-sm text-orange-700">
              <span className="font-medium">Total: </span>
              {formatCurrency(order.total_amount_centavos)}
            </div>
            <textarea
              value={vpApproveNotes}
              onChange={(e) => setVpApproveNotes(e.target.value)}
              placeholder="VP approval notes (optional)"
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 mt-4 text-sm focus:ring-1 focus:ring-neutral-400"
            />
            <div className="flex gap-3 mt-4">
              <button
                onClick={() => setShowVpApproveModal(false)}
                className="flex-1 py-2 border border-neutral-300 rounded text-sm hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                onClick={handleVpApprove}
                disabled={vpApproveMutation.isPending}
                className="flex-1 py-2 bg-orange-600 text-white rounded text-sm hover:bg-orange-700 disabled:opacity-50"
              >
                {vpApproveMutation.isPending ? 'Approving...' : 'VP Approve Order'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Sales Response to Counter Modal */}
      {showSalesResponseModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
            <h2 className="text-lg font-semibold text-neutral-900">Respond to Client Counter-Proposal</h2>
            <div className="space-y-4 mt-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-2">Your Response</label>
                <div className="space-y-2">
                  <label className="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-neutral-50">
                    <input
                      type="radio"
                      value="accept"
                      checked={salesResponse === 'accept'}
                      onChange={(e) => setSalesResponse(e.target.value as 'accept' | 'counter' | 'reject')}
                      className="text-neutral-900"
                    />
                    <div>
                      <p className="font-medium text-sm">Accept Counter</p>
                      <p className="text-xs text-neutral-500">Approve with client's proposed terms</p>
                    </div>
                  </label>
                  <label className="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-neutral-50">
                    <input
                      type="radio"
                      value="counter"
                      checked={salesResponse === 'counter'}
                      onChange={(e) => setSalesResponse(e.target.value as 'accept' | 'counter' | 'reject')}
                      className="text-neutral-900"
                    />
                    <div>
                      <p className="font-medium text-sm">Counter Back</p>
                      <p className="text-xs text-neutral-500">Make another proposal to the client</p>
                    </div>
                  </label>
                  <label className="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-neutral-50">
                    <input
                      type="radio"
                      value="reject"
                      checked={salesResponse === 'reject'}
                      onChange={(e) => setSalesResponse(e.target.value as 'accept' | 'counter' | 'reject')}
                      className="text-neutral-900"
                    />
                    <div>
                      <p className="font-medium text-sm">Reject & End</p>
                      <p className="text-xs text-neutral-500">Reject the order and end negotiation</p>
                    </div>
                  </label>
                </div>
              </div>
              
              {salesResponse === 'counter' && (
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Your Counter Proposal</label>
                  <input
                    type="date"
                    value={counterDeliveryDate}
                    onChange={(e) => setCounterDeliveryDate(e.target.value)}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 mb-2"
                  />
                  <textarea
                    value={counterNotes}
                    onChange={(e) => setCounterNotes(e.target.value)}
                    placeholder="Notes for client"
                    rows={2}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                  />
                </div>
              )}
              
              {(salesResponse === 'accept' || salesResponse === 'reject') && (
                <textarea
                  value={counterNotes}
                  onChange={(e) => setCounterNotes(e.target.value)}
                  placeholder="Notes (optional)"
                  rows={2}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
                />
              )}
            </div>
            <div className="flex gap-3 mt-4">
              <button
                onClick={() => setShowSalesResponseModal(false)}
                className="flex-1 py-2 border border-neutral-300 rounded text-sm hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                onClick={handleSalesResponse}
                disabled={salesRespondMutation.isPending}
                className="flex-1 py-2 bg-neutral-900 text-white rounded text-sm hover:bg-neutral-800 disabled:opacity-50"
              >
                {salesRespondMutation.isPending ? 'Sending...' : 'Send Response'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
