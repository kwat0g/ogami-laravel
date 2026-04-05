import { firstErrorMessage } from '@/lib/errorHandler'
import { useState, useRef } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Package, CheckCircle, AlertTriangle, Truck, MessageSquare, Send, Camera } from 'lucide-react'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { useCombinedDeliverySchedule, useAcknowledgeReceipt } from '@/hooks/useDeliveryScheduleWorkflow'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import MultiPhotoUpload from '@/components/ui/MultiPhotoUpload'
import { toast } from 'sonner'
import type { ItemStatusSummary } from '@/types/production'

interface AcknowledgmentForm {
  received_qty: number
  condition: 'good' | 'damaged' | 'missing'
  notes: string
  photo_urls: string[]
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

  const itemSummaries = schedule.item_status_summary ?? []
  // Supports both DS (`items`) and CDS (`item_schedules`) payload shapes.
  const scheduleItems = ((schedule.item_schedules as Array<Record<string, unknown>> | undefined)
    ?? (schedule.items as Array<Record<string, unknown>> | undefined)
    ?? [])
  const existingAcknowledgments = Array.isArray(schedule.client_acknowledgment)
    ? schedule.client_acknowledgment
    : []
  const missingItemIds = new Set(
    itemSummaries
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      .filter((summary: any) => Boolean(summary?.is_missing))
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      .map((summary: any) => Number(summary?.delivery_schedule_item_id ?? summary?.delivery_schedule_id))
      .filter((id): id is number => Number.isFinite(id))
  )
  const actionableItemIds = scheduleItems
    .map((i) => Number(i.id))
    .filter((id): id is number => Number.isFinite(id) && !missingItemIds.has(id))
  const actionableSummaries = itemSummaries.filter(summary => !summary?.is_missing)
  const hasSummaryAck = actionableSummaries.length > 0
    && actionableSummaries.every((summary: ItemStatusSummary & { client_acknowledgment?: unknown }) => Boolean(summary.client_acknowledgment))
  const hasStoredAck = existingAcknowledgments.length > 0
  const hasAcknowledgment = hasSummaryAck || hasStoredAck

  // Acknowledgment is only allowed after delivery has been completed.
  const canAcknowledge = schedule.status === 'delivered' && !hasAcknowledgment
  const isAlreadyAcknowledged = hasAcknowledgment
  const deliveredDateRaw = schedule.actual_delivery_date || schedule.dispatched_at || null
  const deliveredDateLabel = deliveredDateRaw
    ? new Date(deliveredDateRaw).toLocaleDateString('en-PH')
    : 'Not available'

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const handleAcknowledgmentChange = (itemId: number, field: keyof AcknowledgmentForm, value: any) => {
    setAcknowledgments(prev => ({
      ...prev,
      [itemId]: {
        ...prev[itemId],
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        received_qty: prev[itemId]?.received_qty ?? parseFloat(String(scheduleItems.find((i) => Number(i.id) === itemId)?.qty_ordered ?? '0')),
        condition: prev[itemId]?.condition ?? 'good',
        notes: prev[itemId]?.notes ?? '',
        photo_urls: prev[itemId]?.photo_urls ?? [],
        [field]: value,
      }
    }))
  }

  const handleSubmit = async () => {
    // Build acknowledgments for every actionable item, falling back to default values
    // so users only need to edit rows with issues.
    const itemMap = new Map(
      scheduleItems
        .map((item) => [Number(item.id), item] as const)
        .filter(([id]) => Number.isFinite(id))
    )

    const normalizedAcknowledgments = actionableItemIds.map((itemId) => {
      const item = itemMap.get(itemId)
      const qtyOrdered = parseFloat(String(item?.qty_ordered ?? '0'))
      const ack = acknowledgments[itemId]

      return {
        item_id: itemId,
        received_qty: ack?.received_qty ?? qtyOrdered,
        condition: ack?.condition ?? 'good',
        notes: ack?.notes ?? '',
        photo_urls: ack?.photo_urls ?? [],
      }
    })

    const invalidAcknowledgment = normalizedAcknowledgments.find((ack) => {
      if (!Number.isFinite(ack.received_qty) || ack.received_qty < 0) {
        return true
      }

      const item = itemMap.get(ack.item_id)
      const qtyOrdered = parseFloat(String(item?.qty_ordered ?? '0'))

      if (!Number.isFinite(qtyOrdered) || qtyOrdered < 0) {
        return true
      }

      if (ack.received_qty > qtyOrdered) {
        return true
      }

      if (ack.condition === 'missing' && ack.received_qty >= qtyOrdered) {
        return true
      }

      if (ack.condition === 'good' && ack.received_qty !== qtyOrdered) {
        return true
      }

      if (ack.condition === 'damaged' && ack.received_qty >= qtyOrdered) {
        return true
      }

      if ((ack.condition === 'damaged' || ack.condition === 'missing') && (ack.photo_urls?.length ?? 0) < 1) {
        return true
      }

      return false
    })

    if (invalidAcknowledgment) {
      const invalidItem = itemMap.get(invalidAcknowledgment.item_id)
      const invalidItemName = (invalidItem?.product_item as { name?: string } | undefined)?.name ?? 'one item'
      toast.error(`Invalid acknowledgment for ${invalidItemName}. Good must be full qty, Damaged/Missing must reduce qty, and photo evidence is required for Damaged/Missing.`)
      return
    }

    const payload = {
      item_acknowledgments: normalizedAcknowledgments,
      general_notes: generalNotes,
    }

    try {
      await acknowledgeMutation.mutateAsync(payload)
      toast.success('Receipt acknowledgment submitted successfully')
      navigate('/client-portal/orders')
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to submit acknowledgment'))
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
        <p className="text-neutral-500 mt-1">{schedule.cds_reference ?? schedule.ds_reference}</p>
        <p className="text-sm text-neutral-400">Delivered on {deliveredDateLabel}</p>
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
            {scheduleItems.map((item) => {
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              const summary = schedule.item_status_summary?.find((s: any) => (s.delivery_schedule_item_id ?? s.delivery_schedule_id) === item.id)
              const isMissing = summary?.is_missing
              const ack = acknowledgments[item.id]

              return (
                <div key={item.id} className={`p-4 rounded-lg border ${isMissing ? 'bg-red-50 border-red-100' : 'bg-neutral-50 border-neutral-100'}`}>
                  <div className="flex items-start justify-between">
                    <div>
                      <p className="font-medium text-neutral-900">{(item.product_item as { name?: string } | undefined)?.name ?? 'Item'}</p>
                      <p className="text-xs text-neutral-500">{(item.product_item as { item_code?: string } | undefined)?.item_code}</p>
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
                            onChange={(e) => {
                              const condition = e.target.value as AcknowledgmentForm['condition']
                              const qtyOrdered = parseFloat(String(item.qty_ordered ?? '0'))
                              handleAcknowledgmentChange(item.id, 'condition', condition)

                              // Keep qty aligned with obvious condition choices to reduce user mistakes.
                              if (condition === 'missing') {
                                const currentQty = acknowledgments[item.id]?.received_qty
                                if (currentQty === undefined || currentQty >= qtyOrdered) {
                                  handleAcknowledgmentChange(item.id, 'received_qty', Math.max(0, qtyOrdered - 1))
                                }
                              } else if (condition === 'good') {
                                handleAcknowledgmentChange(item.id, 'received_qty', qtyOrdered)
                              } else if (condition === 'damaged') {
                                const currentQty = acknowledgments[item.id]?.received_qty
                                if (currentQty === undefined || currentQty >= qtyOrdered) {
                                  handleAcknowledgmentChange(item.id, 'received_qty', Math.max(0, qtyOrdered - 1))
                                }
                              }
                            }}
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

                      {(ack?.condition === 'damaged' || ack?.condition === 'missing') && (
                        <p className="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                          For {ack.condition === 'damaged' ? 'Damaged' : 'Missing'}, reduce Quantity Received to accepted items only and upload at least one photo evidence.
                        </p>
                      )}

                      {/* Photo upload -- shown for damaged/missing items */}
                      {ack?.condition && ack.condition !== 'good' && (
                        <div className="mt-3">
                          <MultiPhotoUpload
                            photos={ack.photo_urls || []}
                            onChange={(photos) => handleAcknowledgmentChange(item.id, 'photo_urls', photos)}
                            maxPhotos={3}
                            label="Photo Evidence (required for damaged/missing)"
                          />
                        </div>
                      )}
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
