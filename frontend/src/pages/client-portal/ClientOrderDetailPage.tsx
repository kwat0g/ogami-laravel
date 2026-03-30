import { firstErrorMessage } from '@/lib/errorHandler'
import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { 
  ArrowLeft, 
  Clock, 
  CheckCircle, 
  XCircle, 
  MessageCircle, 
  Calendar,
  Package,
  FileText,
  User,
  History,
  AlertCircle,
  Trash2,
  AlertTriangle,
  Truck
} from 'lucide-react'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { StatusBadge } from '@/components/ui/StatusBadge'
import { useClientOrder, useRespondToNegotiation, useCancelClientOrder } from '@/hooks/useClientOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import { NEGOTIATION_REASONS, REJECTION_REASONS } from '@/types/client-order'

const STATUS_CONFIG: Record<string, {
  status: string
  icon: React.ReactNode
  label: string
  description: string
}> = {
  pending: {
    status: 'pending',
    icon: <Clock className="h-4 w-4" />,
    label: 'Pending Review',
    description: 'Your order is being reviewed by our sales team.'
  },
  negotiating: {
    status: 'in_progress',
    icon: <MessageCircle className="h-4 w-4" />,
    label: 'Under Negotiation',
    description: 'Sales has proposed changes. Please review and respond.'
  },
  client_responded: {
    status: 'in_progress',
    icon: <MessageCircle className="h-4 w-4" />,
    label: 'Awaiting Sales Review',
    description: 'Your counter-proposal has been submitted and is awaiting sales review.'
  },
  vp_pending: {
    status: 'in_progress',
    icon: <Clock className="h-4 w-4" />,
    label: 'Awaiting VP Approval',
    description: 'Your order value requires VP review. We\'ll notify you once it\'s approved.'
  },
  approved: {
    status: 'approved',
    icon: <CheckCircle className="h-4 w-4" />,
    label: 'Order Approved',
    description: 'Your order has been approved and is being processed for delivery.'
  },
  completed: {
    status: 'approved',
    icon: <CheckCircle className="h-4 w-4" />,
    label: 'Order Completed',
    description: 'Your order has been delivered successfully.'
  },
  rejected: {
    status: 'rejected',
    icon: <XCircle className="h-4 w-4" />,
    label: 'Order Rejected',
    description: 'Unfortunately, this order could not be fulfilled.'
  },
  cancelled: {
    status: 'cancelled',
    icon: <XCircle className="h-4 w-4" />,
    label: 'Order Cancelled',
    description: 'This order has been cancelled.'
  },
}

export default function ClientOrderDetailPage(): JSX.Element {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const orderUlid = ulid || ''

  const { data: order, isLoading } = useClientOrder(orderUlid)
  const respondMutation = useRespondToNegotiation()
  const cancelMutation = useCancelClientOrder()

  const [showCancelModal, setShowCancelModal] = useState(false)
  const [showResponseModal, setShowResponseModal] = useState(false)

  const handleCancel = async () => {
    try {
      await cancelMutation.mutateAsync(orderUlid)
      toast.success('Order cancelled successfully')
      setShowCancelModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to cancel order'))
    }
  }
  const [responseType, setResponseType] = useState<'accept' | 'counter' | 'cancel'>('accept')
  const [counterDate, setCounterDate] = useState('')

  const handleRespond = async () => {
    try {
      await respondMutation.mutateAsync({
        orderUlid,
        response: responseType,
        counterProposals: responseType === 'counter' ? { deliveryDate: counterDate } : undefined,
      })

      toast.success('Response submitted successfully')
      setShowResponseModal(false)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to submit response'))
    }
  }

  const formatPrice = (centavos: number) => {
    return `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  }

  if (isLoading) {
    return <SkeletonLoader rows={5} />
  }

  if (!order) {
    return (
      <div className="text-center py-16">
        <div className="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <Package className="h-8 w-8 text-neutral-400" />
        </div>
        <h3 className="text-base font-medium text-neutral-900 mb-2">Order not found</h3>
        <p className="text-sm text-neutral-500 mb-6">The order you&apos;re looking for doesn&apos;t exist or you don&apos;t have access.</p>
        <button
          onClick={() => navigate('/client-portal/orders')}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Orders
        </button>
      </div>
    )
  }

  const status = STATUS_CONFIG[order.status]

  return (
    <div className="space-y-5 max-w-5xl mx-auto">
      {/* Back Button */}
      <button
        onClick={() => navigate('/client-portal/orders')}
        className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900 font-medium"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to Orders
      </button>

      {/* Header Card */}
      <Card>
        <div className="p-5">
          <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div className="flex items-start gap-4">
              <div className="w-12 h-12 bg-neutral-100 rounded-lg flex items-center justify-center">
                <Package className="h-6 w-6 text-neutral-600" />
              </div>
              <div>
                <h1 className="text-lg font-semibold text-neutral-900">{order.order_reference}</h1>
                <p className="text-sm text-neutral-500 mt-0.5">
                  Placed on {new Date(order.created_at).toLocaleDateString('en-PH', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                  })}
                </p>
                <div className="flex items-center gap-4 mt-2 text-sm text-neutral-500">
                  <span className="flex items-center gap-1">
                    <Package className="h-3.5 w-3.5" />
                    {order.items.length} item{order.items.length !== 1 ? 's' : ''}
                  </span>
                  {order.submittedBy && (
                    <span className="flex items-center gap-1">
                      <User className="h-3.5 w-3.5" />
                      By {order.submittedBy.name}
                    </span>
                  )}
                </div>
              </div>
            </div>
            
            <div className="flex items-center gap-2 self-start">
              <StatusBadge status={status.status} className="flex items-center gap-1.5">
                {status.icon}
                {status.label}
              </StatusBadge>
              
          {/* Cancel button for pending/negotiating/client_responded orders */}
          {(order.status === 'pending' || order.status === 'negotiating' || order.status === 'client_responded') && (
            <button
              onClick={() => setShowCancelModal(true)}
              disabled={cancelMutation.isPending}
              className="flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors border border-red-200 hover:border-red-300 disabled:opacity-50"
            >
              <Trash2 className="h-4 w-4" />
              {cancelMutation.isPending ? 'Cancelling...' : 'Cancel'}
            </button>
          )}
            </div>
          </div>
        </div>

        {/* Status Banner */}
        <div className={`px-5 py-3 border-t border-neutral-100 bg-neutral-50/50`}>
          <div className="flex items-start gap-3">
            <AlertCircle className="h-4 w-4 mt-0.5 text-neutral-500" />
            <div className="text-sm">
              <p className="text-neutral-700">{status.description}</p>
              {order.status === 'negotiating' && order.negotiation_reason && (
                <p className="text-neutral-500 mt-0.5">
                  Reason: {NEGOTIATION_REASONS[order.negotiation_reason as keyof typeof NEGOTIATION_REASONS]}
                </p>
              )}
              {order.status === 'rejected' && order.rejection_reason && (
                <p className="text-neutral-500 mt-0.5">
                  Reason: {REJECTION_REASONS[order.rejection_reason as keyof typeof REJECTION_REASONS] || order.rejection_reason}
                </p>
              )}
            </div>
          </div>
        </div>
      </Card>

      <div className="grid lg:grid-cols-3 gap-5">
        {/* Left Column */}
        <div className="lg:col-span-2 space-y-5">
          {/* Items Card */}
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <Package className="h-4 w-4 text-neutral-500" />
                Order Items
              </span>
            </CardHeader>
            <div className="divide-y divide-neutral-100">
              {order.items.map((item) => (
                <div key={item.id} className="p-4 flex justify-between items-start">
                  <div className="flex-1">
                    <p className="font-medium text-neutral-900 text-sm">{item.item_description}</p>
                    <div className="flex items-center gap-3 mt-1 text-xs text-neutral-500">
                      <span>{item.quantity} {item.unit_of_measure}</span>
                      <span>•</span>
                      <span>{formatPrice(item.unit_price_centavos)} each</span>
                    </div>
                    {item.line_notes && (
                      <p className="text-xs text-neutral-600 mt-2 bg-neutral-50 px-3 py-2 rounded-lg border border-neutral-100">
                        Note: {item.line_notes}
                      </p>
                    )}
                    {item.negotiated_quantity && (
                      <div className="mt-2 p-2 bg-blue-50 border border-blue-100 rounded-lg text-xs">
                        <span className="text-blue-700 font-medium">Proposed change:</span>
                        <span className="text-blue-600 ml-2">
                          Quantity: {item.negotiated_quantity} {item.unit_of_measure}
                        </span>
                      </div>
                    )}
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-neutral-900 text-sm">{formatPrice(item.line_total_centavos)}</p>
                  </div>
                </div>
              ))}
            </div>
            <div className="p-4 border-t border-neutral-100 bg-neutral-50/50">
              <div className="flex justify-between items-center">
                <span className="text-sm text-neutral-600">Order Total</span>
                <span className="text-lg font-semibold text-neutral-900">{formatPrice(order.total_amount_centavos)}</span>
              </div>
            </div>
          </Card>

          {/* Notes Card */}
          {order.client_notes && (
            <Card>
              <CardHeader>
                <span className="flex items-center gap-2">
                  <FileText className="h-4 w-4 text-neutral-500" />
                  Your Notes
                </span>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-neutral-700">{order.client_notes}</p>
              </CardBody>
            </Card>
          )}

          {/* Activity Log */}
          {order.activities && order.activities.length > 0 && (
            <Card>
              <CardHeader>
                <span className="flex items-center gap-2">
                  <History className="h-4 w-4 text-neutral-500" />
                  Order History
                </span>
              </CardHeader>
              <CardBody>
                <div className="space-y-4">
                  {order.activities.map((activity, index) => (
                    <div key={activity.id} className="flex gap-4">
                      <div className="relative">
                        <div className="w-8 h-8 bg-neutral-100 rounded-full flex items-center justify-center">
                          <History className="h-3.5 w-3.5 text-neutral-500" />
                        </div>
                        {index < order.activities.length - 1 && (
                          <div className="absolute top-8 left-1/2 -translate-x-1/2 w-px h-full bg-neutral-200" />
                        )}
                      </div>
                      <div className="flex-1 pb-4">
                        <p className="font-medium text-neutral-900 text-sm capitalize">
                          {activity.action.replace(/_/g, ' ')}
                        </p>
                        <p className="text-xs text-neutral-500">
                          {new Date(activity.created_at).toLocaleString('en-PH', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit'
                          })}
                        </p>
                        {activity.comment && (
                          <p className="text-xs text-neutral-600 mt-2 bg-neutral-50 p-2.5 rounded-lg border border-neutral-100">
                            {activity.comment}
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          )}
        </div>

        {/* Right Column - Delivery Info */}
        <div className="space-y-5">
          {/* Delivery Card */}
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <Calendar className="h-4 w-4 text-neutral-500" />
                Delivery Information
              </span>
            </CardHeader>
            <CardBody className="space-y-4">
              <div>
                <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Requested Date</p>
                <p className="font-medium text-neutral-900 text-sm">
                  {order.requested_delivery_date 
                    ? new Date(order.requested_delivery_date).toLocaleDateString('en-PH', { 
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric' 
                      })
                    : 'Not specified'}
                </p>
              </div>
              
              {order.agreed_delivery_date && (
                <div className="p-3 bg-green-50 border border-green-100 rounded-lg">
                  <p className="text-xs text-green-700 font-medium mb-0.5">Confirmed Delivery</p>
                  <p className="text-sm text-green-800">
                    {new Date(order.agreed_delivery_date).toLocaleDateString('en-PH', { 
                      month: 'long', 
                      day: 'numeric', 
                      year: 'numeric' 
                    })}
                  </p>
                </div>
              )}

              {order.negotiation_notes && (
                <div>
                  <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Negotiation Notes</p>
                  <p className="text-xs text-neutral-700 bg-neutral-50 p-3 rounded-lg border border-neutral-100">
                    {order.negotiation_notes}
                  </p>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Action Card */}
          {order.status === 'negotiating' && (
            <div className="bg-blue-50 rounded-xl border border-blue-100 p-4">
              <h3 className="font-medium text-blue-900 mb-1 flex items-center gap-2 text-sm">
                <MessageCircle className="h-4 w-4" />
                Action Required
              </h3>
              <p className="text-xs text-blue-700 mb-4">
                Our sales team has proposed changes to your order. Please review and respond.
              </p>
              <button
                onClick={() => setShowResponseModal(true)}
                className="w-full py-2.5 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
              >
                Respond to Proposal
              </button>
            </div>
          )}

          {/* Delivery Tracking — shown after approval */}
          {(order.status === 'approved' || order.status === 'completed') && order.deliverySchedules && order.deliverySchedules.length > 0 && (
            <Card>
              <CardHeader>
                <span className="flex items-center gap-2">
                  <Truck className="h-4 w-4 text-neutral-500" />
                  Delivery Tracking
                </span>
              </CardHeader>
              <CardBody className="space-y-3">
                <p className="text-xs text-neutral-500">
                  Your order is being prepared for delivery. Track the status of each item below.
                </p>
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
                    <div key={ds.id} className="p-3 bg-neutral-50 border border-neutral-100 rounded-lg">
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-xs font-mono text-neutral-600">{sched.ds_reference}</span>
                        <span className={`px-2 py-0.5 rounded text-xs font-medium ${schedStatusColors[sched.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                          {sched.status}
                        </span>
                      </div>
                      <p className="text-xs text-neutral-500">
                        Target: {sched.target_delivery_date
                          ? new Date(sched.target_delivery_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
                          : 'TBD'}
                      </p>
                    </div>
                  )
                })}
                <p className="text-xs text-neutral-400 pt-1">
                  You will be notified when your delivery is dispatched.
                </p>
              </CardBody>
            </Card>
          )}

          {/* Post-approval next steps when no delivery schedules visible yet */}
          {order.status === 'approved' && (!order.deliverySchedules || order.deliverySchedules.length === 0) && (
            <div className="bg-emerald-50 rounded-xl border border-emerald-100 p-4">
              <h3 className="font-medium text-emerald-900 mb-1 flex items-center gap-2 text-sm">
                <CheckCircle className="h-4 w-4" />
                Order Approved
              </h3>
              <p className="text-xs text-emerald-700">
                Your order has been approved and is now being processed. Delivery schedules are being created and you will be notified once items are ready for dispatch.
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Cancel Confirmation Modal */}
      {showCancelModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
            <div className="p-6">
              <div className="flex items-start gap-4">
                <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                  <AlertTriangle className="h-6 w-6 text-red-600" />
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-neutral-900">Cancel Order?</h3>
                  <p className="text-sm text-neutral-500 mt-1">
                    Are you sure you want to cancel order <span className="font-medium text-neutral-700">{order.order_reference}</span>?
                  </p>
                </div>
              </div>

              <div className="mt-4 p-4 bg-neutral-50 rounded-lg border border-neutral-100">
                <div className="flex justify-between text-sm">
                  <span className="text-neutral-500">Order Total:</span>
                  <span className="font-semibold text-neutral-900">
                    {formatPrice(order.total_amount_centavos)}
                  </span>
                </div>
                <div className="flex justify-between text-sm mt-2">
                  <span className="text-neutral-500">Items:</span>
                  <span className="font-medium text-neutral-900">{order.items.length}</span>
                </div>
              </div>

              <div className="mt-2 text-sm text-neutral-500">
                <p>This action cannot be undone. The order will be permanently cancelled.</p>
              </div>
            </div>

            <div className="p-4 border-t border-neutral-100 flex gap-3">
              <button
                onClick={() => setShowCancelModal(false)}
                disabled={cancelMutation.isPending}
                className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
              >
                Keep Order
              </button>
              <button
                onClick={handleCancel}
                disabled={cancelMutation.isPending}
                className="flex-1 py-2.5 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm flex items-center justify-center gap-2"
              >
                {cancelMutation.isPending ? (
                  <>
                    <span className="animate-spin">⟳</span>
                    Cancelling...
                  </>
                ) : (
                  <>
                    <Trash2 className="h-4 w-4" />
                    Yes, Cancel Order
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Response Modal */}
      {showResponseModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
            <div className="p-5 border-b border-neutral-100">
              <h2 className="text-base font-semibold text-neutral-900">Respond to Proposal</h2>
              <p className="text-sm text-neutral-500 mt-0.5">Choose how you&apos;d like to proceed with this order</p>
            </div>
            
            <div className="p-5 space-y-3">
              <label className="flex items-start gap-3 p-4 border border-green-200 bg-green-50 rounded-xl cursor-pointer hover:border-green-300 transition-colors">
                <input
                  type="radio"
                  name="response"
                  value="accept"
                  checked={responseType === 'accept'}
                  onChange={(e) => setResponseType(e.target.value as 'accept')}
                  className="mt-0.5"
                />
                <div>
                  <p className="font-medium text-green-800 text-sm">Accept Proposal</p>
                  <p className="text-xs text-green-600">Agree to all proposed changes and proceed</p>
                </div>
              </label>
              
              <label className="flex items-start gap-3 p-4 border border-blue-200 bg-blue-50 rounded-xl cursor-pointer hover:border-blue-300 transition-colors">
                <input
                  type="radio"
                  name="response"
                  value="counter"
                  checked={responseType === 'counter'}
                  onChange={(e) => setResponseType(e.target.value as 'counter')}
                  className="mt-0.5"
                />
                <div className="flex-1">
                  <p className="font-medium text-blue-800 text-sm">Counter Propose</p>
                  <p className="text-xs text-blue-600">Suggest different delivery date</p>
                </div>
              </label>
              
              <label className="flex items-start gap-3 p-4 border border-red-200 bg-red-50 rounded-xl cursor-pointer hover:border-red-300 transition-colors">
                <input
                  type="radio"
                  name="response"
                  value="cancel"
                  checked={responseType === 'cancel'}
                  onChange={(e) => setResponseType(e.target.value as 'cancel')}
                  className="mt-0.5"
                />
                <div>
                  <p className="font-medium text-red-800 text-sm">Cancel Order</p>
                  <p className="text-xs text-red-600">Withdraw this order completely</p>
                </div>
              </label>

              {responseType === 'counter' && (
                <div className="mt-4 p-3 bg-neutral-50 rounded-lg border border-neutral-100">
                  <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                    Proposed Delivery Date
                  </label>
                  <input
                    type="date"
                    value={counterDate}
                    onChange={(e) => setCounterDate(e.target.value)}
                    min={new Date().toISOString().split('T')[0]}
                    className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
                  />
                </div>
              )}
            </div>

            <div className="p-5 border-t border-neutral-100 flex gap-3">
              <button
                onClick={() => setShowResponseModal(false)}
                className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
              >
                Cancel
              </button>
              <button
                onClick={handleRespond}
                disabled={respondMutation.isPending || (responseType === 'counter' && !counterDate)}
                className="flex-1 py-2.5 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
              >
                {respondMutation.isPending ? 'Submitting...' : 'Submit Response'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
