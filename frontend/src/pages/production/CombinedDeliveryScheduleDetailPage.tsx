import { useState } from 'react'
import { useParams, useNavigate, Link, useSearchParams } from 'react-router-dom'
import { ArrowLeft, Truck, Package, Calendar, User, AlertCircle, CheckCircle, Clock, AlertTriangle, Send, Check } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { 
  useCombinedDeliverySchedule, 
  useDispatchCombinedSchedule,
  useMarkDelivered,
  useNotifyMissingItems 
} from '@/hooks/useCombinedDeliverySchedules'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import type { CombinedDeliverySchedule, ItemStatusSummary } from '@/types/production'

const STATUS_COLORS: Record<string, string> = {
  planning: 'bg-neutral-100 text-neutral-700',
  ready: 'bg-green-100 text-green-700',
  partially_ready: 'bg-amber-100 text-amber-700',
  dispatched: 'bg-blue-100 text-blue-700',
  delivered: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-red-100 text-red-700',
}

interface DispatchModalProps {
  isOpen: boolean
  onClose: () => void
  schedule: CombinedDeliverySchedule
}

function DispatchModal({ isOpen, onClose, schedule }: DispatchModalProps): JSX.Element | null {
  const [driverName, setDriverName] = useState('')
  const [deliveryNotes, setDeliveryNotes] = useState('')
  const dispatchMutation = useDispatchCombinedSchedule(schedule.ulid)

  if (!isOpen) return null

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await dispatchMutation.mutateAsync({ driver_name: driverName, delivery_notes: deliveryNotes })
      toast.success('Delivery dispatched successfully')
      onClose()
    } catch (error) {
      toast.error('Failed to dispatch delivery')
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-md shadow-xl">
        <div className="p-5 border-b border-neutral-100">
          <h2 className="text-lg font-semibold">Dispatch Delivery</h2>
          <p className="text-sm text-neutral-500">{schedule.cds_reference}</p>
        </div>
        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Driver Name</label>
            <input
              type="text"
              value={driverName}
              onChange={(e) => setDriverName(e.target.value)}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 outline-none text-sm"
              placeholder="e.g., John Smith"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Delivery Notes</label>
            <textarea
              value={deliveryNotes}
              onChange={(e) => setDeliveryNotes(e.target.value)}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 outline-none text-sm"
              rows={3}
              placeholder="Special instructions for delivery..."
            />
          </div>
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={dispatchMutation.isPending}
              className="flex-1 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm"
            >
              {dispatchMutation.isPending ? 'Dispatching...' : 'Dispatch'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

interface NotifyModalProps {
  isOpen: boolean
  onClose: () => void
  schedule: CombinedDeliverySchedule
}

function NotifyMissingModal({ isOpen, onClose, schedule }: NotifyModalProps): JSX.Element | null {
  const [expectedDate, setExpectedDate] = useState('')
  const [message, setMessage] = useState('')
  const [selectedItems, setSelectedItems] = useState<number[]>([])
  const notifyMutation = useNotifyMissingItems(schedule.ulid)

  if (!isOpen) return null

  const notReadyItems = schedule.item_status_summary?.filter(item => !item.is_ready) || []

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (selectedItems.length === 0) {
      toast.error('Please select at least one item')
      return
    }

    const missingItems = selectedItems.map(id => ({
      item_id: id,
      reason: 'Delayed in production'
    }))

    try {
      await notifyMutation.mutateAsync({
        missing_items: missingItems,
        expected_delivery_date: expectedDate,
        message
      })
      toast.success('Customer notified about delayed items')
      onClose()
    } catch (error) {
      toast.error('Failed to send notification')
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-md shadow-xl">
        <div className="p-5 border-b border-neutral-100">
          <h2 className="text-lg font-semibold">Notify Customer - Missing Items</h2>
          <p className="text-sm text-neutral-500">{schedule.cds_reference}</p>
        </div>
        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-2">Select Missing Items</label>
            <div className="space-y-2 max-h-40 overflow-y-auto">
              {notReadyItems.map(item => (
                <label key={item.delivery_schedule_id} className="flex items-center gap-2 p-2 bg-neutral-50 rounded">
                  <input
                    type="checkbox"
                    checked={selectedItems.includes(item.delivery_schedule_id)}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelectedItems([...selectedItems, item.delivery_schedule_id])
                      } else {
                        setSelectedItems(selectedItems.filter(id => id !== item.delivery_schedule_id))
                      }
                    }}
                    className="rounded border-neutral-300"
                  />
                  <span className="text-sm">{item.product_name} ({item.qty_ordered} pcs)</span>
                </label>
              ))}
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Expected Delivery Date</label>
            <input
              type="date"
              value={expectedDate}
              onChange={(e) => setExpectedDate(e.target.value)}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 outline-none text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Message to Customer</label>
            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 outline-none text-sm"
              rows={3}
              placeholder="Explain the delay..."
            />
          </div>
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={notifyMutation.isPending}
              className="flex-1 py-2.5 bg-amber-600 text-white font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50 text-sm"
            >
              {notifyMutation.isPending ? 'Sending...' : 'Send Notification'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

export default function CombinedDeliveryScheduleDetailPage(): JSX.Element {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { hasPermission } = useAuthStore()
  const [showDispatchModal, setShowDispatchModal] = useState(searchParams.get('action') === 'dispatch')
  const [showNotifyModal, setShowNotifyModal] = useState(searchParams.get('action') === 'notify')
  const [showMarkDeliveredModal, setShowMarkDeliveredModal] = useState(false)

  const { data: schedule, isLoading, isError } = useCombinedDeliverySchedule(ulid || null)
  const markDeliveredMutation = useMarkDelivered(ulid || '')

  const canManage = hasPermission('production.delivery-schedule.manage')

  if (isLoading) return <SkeletonLoader rows={5} />

  if (isError || !schedule) {
    return (
      <div className="text-center py-16">
        <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-neutral-900">Schedule not found</h3>
        <button
          onClick={() => navigate('/production/combined-delivery-schedules')}
          className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white rounded-lg"
        >
          <ArrowLeft className="w-4 h-4" /> Back
        </button>
      </div>
    )
  }

  const status = schedule.status

  const handleMarkDelivered = async () => {
    try {
      await markDeliveredMutation.mutateAsync({
        delivery_date: new Date().toISOString().split('T')[0],
      })
      toast.success('Delivery marked as delivered')
      setShowMarkDeliveredModal(false)
    } catch (error) {
      toast.error('Failed to mark as delivered')
    }
  }

  return (
    <div className="space-y-5 max-w-6xl mx-auto">
      {/* Back Button */}
      <button
        onClick={() => navigate('/production/combined-delivery-schedules')}
        className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Schedules
      </button>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-neutral-900">{schedule.cds_reference}</h1>
            <span className={`px-2 py-1 rounded text-xs font-medium capitalize ${STATUS_COLORS[status]}`}>
              {schedule.status_label || status.replace('_', ' ')}
            </span>
          </div>
          <p className="text-sm text-neutral-500 mt-1">
            Order: <span className="font-medium">{schedule.client_order?.order_reference}</span> | 
            Customer: <span className="font-medium">{schedule.customer?.name}</span>
          </p>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center gap-2">
          {canManage && schedule.can_dispatch && (
            <button
              onClick={() => setShowDispatchModal(true)}
              className="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded"
            >
              <Truck className="w-4 h-4" /> Dispatch
            </button>
          )}
          {canManage && schedule.status === 'partially_ready' && (
            <button
              onClick={() => setShowNotifyModal(true)}
              className="inline-flex items-center gap-1.5 border border-amber-300 text-amber-700 hover:bg-amber-50 text-sm font-medium px-4 py-2 rounded"
            >
              <AlertTriangle className="w-4 h-4" /> Notify Missing
            </button>
          )}
          {canManage && schedule.status === 'dispatched' && (
            <button
              onClick={() => setShowMarkDeliveredModal(true)}
              className="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded"
            >
              <CheckCircle className="w-4 h-4" /> Mark Delivered
            </button>
          )}
        </div>
      </div>

      {/* Status Banner */}
      <div className={`px-4 py-3 rounded-lg border ${STATUS_COLORS[status]}`}>
        <div className="flex items-center gap-2">
          <Truck className="h-5 w-5" />
          <span className="font-medium">
            {schedule.ready_items} of {schedule.total_items} items ready
          </span>
          <span className="opacity-70">({schedule.progress_percentage}% complete)</span>
        </div>
      </div>

      {/* Dispute Banner */}
      {schedule.has_dispute && (
        <div className="px-4 py-3 rounded-lg border border-red-200 bg-red-50 flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-600 mt-0.5 shrink-0" />
          <div>
            <p className="font-medium text-red-800">Delivery Dispute Reported</p>
            <p className="text-sm text-red-700 mt-0.5">
              The customer reported issues with this delivery. Please review and resolve.
            </p>
            {schedule.dispute_summary && (
              <p className="text-xs text-red-600 mt-1">
                {typeof schedule.dispute_summary === 'string'
                  ? schedule.dispute_summary
                  : JSON.stringify(schedule.dispute_summary)}
              </p>
            )}
          </div>
        </div>
      )}

      <div className="grid lg:grid-cols-3 gap-5">
        {/* Left Column - Items */}
        <div className="lg:col-span-2 space-y-5">
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <Package className="h-4 w-4 text-neutral-500" />
                Order Items
              </span>
            </CardHeader>
            <CardBody>
              <div className="divide-y divide-neutral-100">
                {schedule.item_schedules?.map((item: any) => {
                  const summary = schedule.item_status_summary?.find(
                    (s: ItemStatusSummary) => s.delivery_schedule_id === item.id
                  )
                  const isReady = summary?.is_ready
                  const isMissing = summary?.is_missing

                  return (
                    <div key={item.id} className="py-4 flex items-start justify-between">
                      <div>
                        <p className="font-medium text-neutral-900">{item.product_item?.name}</p>
                        <p className="text-xs text-neutral-500">{item.product_item?.item_code}</p>
                        <p className="text-sm text-neutral-600 mt-1">
                          Qty: {parseFloat(item.qty_ordered).toLocaleString('en-PH')}
                        </p>
                        {summary?.expected_delivery && (
                          <p className="text-xs text-amber-600 mt-1">
                            Expected: {new Date(summary.expected_delivery).toLocaleDateString('en-PH')}
                          </p>
                        )}
                      </div>
                      <div className="text-right">
                        <span className={`inline-flex px-2 py-1 rounded text-xs font-medium ${
                          isReady ? 'bg-green-100 text-green-700' :
                          isMissing ? 'bg-red-100 text-red-700' :
                          'bg-neutral-100 text-neutral-600'
                        }`}>
                          {isReady ? 'Ready' : isMissing ? 'Delayed' : 'In Progress'}
                        </span>
                        {item.production_orders?.length > 0 && (
                          <p className="text-xs text-neutral-400 mt-1">
                            {item.production_orders.length} Production Order(s)
                          </p>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Right Column - Details */}
        <div className="space-y-5">
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <Calendar className="h-4 w-4 text-neutral-500" />
                Delivery Details
              </span>
            </CardHeader>
            <CardBody className="space-y-3">
              <div>
                <p className="text-xs text-neutral-500 uppercase">Target Date</p>
                <p className="font-medium">{new Date(schedule.target_delivery_date).toLocaleDateString('en-PH')}</p>
              </div>
              {schedule.actual_delivery_date && (
                <div>
                  <p className="text-xs text-neutral-500 uppercase">Actual Delivery</p>
                  <p className="font-medium text-green-600">
                    {new Date(schedule.actual_delivery_date).toLocaleDateString('en-PH')}
                  </p>
                </div>
              )}
              {schedule.dispatched_at && (
                <div>
                  <p className="text-xs text-neutral-500 uppercase">Dispatched At</p>
                  <p className="text-sm">{new Date(schedule.dispatched_at).toLocaleString('en-PH')}</p>
                </div>
              )}
              <div>
                <p className="text-xs text-neutral-500 uppercase">Delivery Address</p>
                <p className="text-sm text-neutral-700">{schedule.delivery_address || 'Not specified'}</p>
              </div>
              {schedule.delivery_instructions && (
                <div>
                  <p className="text-xs text-neutral-500 uppercase">Instructions</p>
                  <p className="text-sm text-neutral-700">{schedule.delivery_instructions}</p>
                </div>
              )}
            </CardBody>
          </Card>

          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <User className="h-4 w-4 text-neutral-500" />
                Customer
              </span>
            </CardHeader>
            <CardBody>
              <p className="font-medium text-neutral-900">{schedule.customer?.name}</p>
              <p className="text-sm text-neutral-500">{schedule.customer?.email}</p>
              <p className="text-sm text-neutral-500">{schedule.customer?.phone}</p>
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Modals */}
      <DispatchModal
        isOpen={showDispatchModal}
        onClose={() => setShowDispatchModal(false)}
        schedule={schedule}
      />
      <NotifyMissingModal
        isOpen={showNotifyModal}
        onClose={() => setShowNotifyModal(false)}
        schedule={schedule}
      />
    </div>
  )
}
