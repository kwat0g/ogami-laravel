import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Package, CheckCircle, AlertTriangle, Truck, MessageSquare, Send } from 'lucide-react'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { useCombinedDeliverySchedule, useAcknowledgeReceipt } from '@/hooks/useCombinedDeliverySchedules'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import type { ItemStatusSummary } from '@/types/production'

interface AcknowledgmentForm {
  received_qty: number
  condition: 'good' | 'damaged' | 'missing'
  notes: string
}

export default function OrderReceiptPage(): JSX.Element {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { _user } = useAuthStore()
  const [acknowledgments, setAcknowledgments] = useState<Record<number, AcknowledgmentForm>>({})
  const [generalNotes, setGeneralNotes] = useState('')
  const [showSubmitConfirm, setShowSubmitConfirm] = useState(false)

  const { data: schedule, isLoading, isError } = useCombinedDeliverySchedule(ulid || null)
  const acknowledgeMutation = useAcknowledgeReceipt(ulid || '')

  if (isLoading) return <SkeletonLoader rows={5} />

  if (isError || !schedule) {
    return (
      <div className="text-center py-16">
        <AlertTriangle className="w-12 h-12 text-red-400 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-neutral-900">Delivery not found</h3>
        <button
          onClick={() => navigate('/client-portal/orders')}
          className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white rounded-lg"
        >
          <ArrowLeft className="w-4 h-4" /> Back to Orders
        </button>
      </div>
    )
  }

  // Only allow acknowledgment if status is dispatched or delivered
  const canAcknowledge = schedule.status === 'dispatched' || schedule.status === 'delivered'
  const isAlreadyAcknowledged = schedule.status === 'delivered'

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const handleAcknowledgmentChange = (itemId: number, field: keyof AcknowledgmentForm, value: any) => {
    setAcknowledgments(prev => ({
      ...prev,
      [itemId]: {
        ...prev[itemId],
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        received_qty: prev[itemId]?.received_qty ?? parseFloat(schedule.item_schedules?.find((i: any) => i.id === itemId)?.qty_ordered || '0'),
        condition: prev[itemId]?.condition ?? 'good',
        notes: prev[itemId]?.notes ?? '',
        [field]: value,
      }
    }))
  }

  const handleSubmit = async () => {
    // Validate all items have acknowledgment
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const itemIds = schedule.item_schedules?.map((i: any) => i.id) || []
    const missingItems = itemIds.filter(id => !acknowledgments[id])

    if (missingItems.length > 0) {
      toast.error('Please acknowledge receipt of all items')
      return
    }

    const payload = {
      item_acknowledgments: Object.entries(acknowledgments).map(([itemId, ack]) => ({
        item_id: parseInt(itemId),
        received_qty: ack.received_qty,
        condition: ack.condition,
        notes: ack.notes,
      })),
      general_notes: generalNotes,
    }

    try {
      await acknowledgeMutation.mutateAsync(payload)
      toast.success('Receipt acknowledgment submitted successfully')
      navigate('/client-portal/orders')
    } catch (_error) {
      toast.error('Failed to submit acknowledgment')
    }
  }

  return (
    <div className="space-y-5 max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate('/client-portal/orders')}
          className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Orders
        </button>
        {isAlreadyAcknowledged && (
          <span className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-full">
            <CheckCircle className="w-4 h-4" /> Acknowledged
          </span>
        )}
      </div>

      {/* Delivery Info */}
      <div className="text-center py-6">
        <div className="inline-flex items-center justify-center w-16 h-16 bg-blue-100 text-blue-600 rounded-full mb-4">
          <Truck className="w-8 h-8" />
        </div>
        <h1 className="text-2xl font-semibold text-neutral-900">Order Delivered</h1>
        <p className="text-neutral-500 mt-1">{schedule.cds_reference}</p>
        <p className="text-sm text-neutral-400">Delivered on {new Date(schedule.actual_delivery_date || schedule.dispatched_at || '').toLocaleDateString('en-PH')}</p>
      </div>

      {/* Instructions */}
      {canAcknowledge && !isAlreadyAcknowledged && (
        <div className="bg-blue-50 border border-blue-100 rounded-lg p-4">
          <p className="text-sm text-blue-700">
            <strong>Please verify your delivery:</strong> Check each item below and report any damages or missing quantities. 
            Your acknowledgment helps us maintain quality service.
          </p>
        </div>
      )}

      {/* Items List */}
      <Card>
        <CardHeader>
          <span className="flex items-center gap-2">
            <Package className="h-4 w-4 text-neutral-500" />
            Items Received
          </span>
        </CardHeader>
        <CardBody>
          <div className="space-y-4">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {schedule.item_schedules?.map((item: any) => {
              const summary = schedule.item_status_summary?.find(
                (s: ItemStatusSummary) => s.delivery_schedule_id === item.id
              )
              const isMissing = summary?.is_missing
              const ack = acknowledgments[item.id]

              return (
                <div key={item.id} className={`p-4 rounded-lg border ${isMissing ? 'bg-red-50 border-red-100' : 'bg-neutral-50 border-neutral-100'}`}>
                  <div className="flex items-start justify-between">
                    <div>
                      <p className="font-medium text-neutral-900">{item.product_item?.name}</p>
                      <p className="text-xs text-neutral-500">{item.product_item?.item_code}</p>
                      <p className="text-sm text-neutral-600 mt-1">
                        Ordered: {parseFloat(item.qty_ordered).toLocaleString('en-PH')} pcs
                      </p>
                      {isMissing && (
                        <p className="text-xs text-red-600 mt-1 font-medium">
                          <AlertTriangle className="w-3 h-3 inline mr-1" />
                          This item was delayed - will be delivered separately
                        </p>
                      )}
                    </div>
                    {isMissing ? (
                      <span className="px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded">
                        Delayed
                      </span>
                    ) : (
                      <span className="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded">
                        Delivered
                      </span>
                    )}
                  </div>

                  {/* Acknowledgment Form */}
                  {canAcknowledge && !isMissing && !isAlreadyAcknowledged && (
                    <div className="mt-4 pt-4 border-t border-neutral-200">
                      <p className="text-sm font-medium text-neutral-700 mb-3">How was this item received?</p>
                      <div className="grid sm:grid-cols-3 gap-4">
                        <div>
                          <label className="block text-xs text-neutral-500 mb-1">Quantity Received</label>
                          <input
                            type="number"
                            value={ack?.received_qty ?? parseFloat(item.qty_ordered)}
                            onChange={(e) => handleAcknowledgmentChange(item.id, 'received_qty', parseFloat(e.target.value))}
                            className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
                            min="0"
                            max={parseFloat(item.qty_ordered)}
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-neutral-500 mb-1">Condition</label>
                          <select
                            value={ack?.condition ?? 'good'}
                            onChange={(e) => handleAcknowledgmentChange(item.id, 'condition', e.target.value)}
                            className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
                          >
                            <option value="good">Good - Perfect condition</option>
                            <option value="damaged">Damaged - Has defects</option>
                            <option value="missing">Missing - Not received</option>
                          </select>
                        </div>
                        <div>
                          <label className="block text-xs text-neutral-500 mb-1">Notes (optional)</label>
                          <input
                            type="text"
                            value={ack?.notes ?? ''}
                            onChange={(e) => handleAcknowledgmentChange(item.id, 'notes', e.target.value)}
                            placeholder="Describe any issues..."
                            className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm"
                          />
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Show previous acknowledgment */}
                  {isAlreadyAcknowledged && summary?.client_acknowledgment && (
                    <div className="mt-4 pt-4 border-t border-neutral-200">
                      <p className="text-sm font-medium text-neutral-700">Your Acknowledgment:</p>
                      <div className="mt-2 text-sm text-neutral-600">
                        <p>Received: {summary.client_acknowledgment.received_qty} pcs</p>
                        <p>Condition: {summary.client_acknowledgment.condition}</p>
                        {summary.client_acknowledgment.notes && (
                          <p>Notes: {summary.client_acknowledgment.notes}</p>
                        )}
                        <p className="text-xs text-neutral-400 mt-1">
                          Acknowledged on {new Date(summary.client_acknowledgment.acknowledged_at).toLocaleDateString('en-PH')}
                        </p>
                      </div>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </CardBody>
      </Card>

      {/* General Notes */}
      {canAcknowledge && !isAlreadyAcknowledged && (
        <Card>
          <CardHeader>
            <span className="flex items-center gap-2">
              <MessageSquare className="h-4 w-4 text-neutral-500" />
              Additional Comments
            </span>
          </CardHeader>
          <CardBody>
            <textarea
              value={generalNotes}
              onChange={(e) => setGeneralNotes(e.target.value)}
              placeholder="Any additional feedback about this delivery..."
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm min-h-[100px]"
            />
          </CardBody>
        </Card>
      )}

      {/* Submit Button */}
      {canAcknowledge && !isAlreadyAcknowledged && (
        <div className="flex justify-center">
          <button
            onClick={() => setShowSubmitConfirm(true)}
            className="inline-flex items-center gap-2 px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors"
          >
            <CheckCircle className="w-5 h-5" />
            Confirm Receipt Acknowledgment
          </button>
        </div>
      )}

      {/* Submit Confirmation Modal */}
      {showSubmitConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl w-full max-w-md shadow-xl p-6">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Confirm Submission</h2>
            <p className="text-sm text-neutral-600 mb-4">
              Once submitted, you won't be able to modify your acknowledgment. Please verify all items are correct.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowSubmitConfirm(false)}
                className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50"
              >
                Review Again
              </button>
              <button
                onClick={handleSubmit}
                disabled={acknowledgeMutation.isPending}
                className="flex-1 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {acknowledgeMutation.isPending ? (
                  <>
                    <span className="animate-spin">⟳</span>
                    Submitting...
                  </>
                ) : (
                  <>
                    <Send className="w-4 h-4" />
                    Submit
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
