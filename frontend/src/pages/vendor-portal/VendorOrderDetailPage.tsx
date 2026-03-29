import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Truck, CheckCircle, AlertCircle, X, ThumbsUp, MessageSquare, ChevronDown, ChevronUp, MapPin, Calendar, FileText, CreditCard } from 'lucide-react'
import { useVendorOrder, useMarkInTransit, useMarkDelivered, useAcknowledgePO, useProposeChanges } from '@/hooks/useVendorPortal'
import type { ProposeChangesItem, ProposeChangesPayload } from '@/hooks/useVendorPortal'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import ConfirmDialog from '@/components/ui/ConfirmDialog'

interface DeliveryModalProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: (qtys: Record<number, number>, notes: string, deliveryDate: string) => void
  items: Array<{
    id: number
    item_description: string
    unit_of_measure: string
    quantity_ordered: string | number
    quantity_pending: string | number
    agreed_unit_cost?: number
  }>
  isPending: boolean
}

function formatDateInput(date: Date): string {
  return date.toISOString().split('T')[0]
}

function DeliveryModal({ isOpen, onClose, onConfirm, items, isPending }: DeliveryModalProps): React.ReactElement | null {
  const [qtys, setQtys] = useState<Record<number, number>>({})
  const [notes, setNotes] = useState('')
  const [deliveryDate, setDeliveryDate] = useState(formatDateInput(new Date()))
  const [_errors, _setErrors] = useState<Record<number, string>>({})

  if (!isOpen) return null

  // Calculate totals and validation
  const summary = items.map((item) => {
    const ordered = Number(item.quantity_ordered)
    const pending = Number(item.quantity_pending)
    const deliveredRaw = qtys[item.id]
    const delivered = deliveredRaw === undefined ? 0 : deliveredRaw
    const remaining = Math.max(0, pending - delivered)
    const isPartial = delivered > 0 && delivered < pending
    const isFull = delivered >= pending
    const lineTotal = (item.agreed_unit_cost || 0) * delivered
    
    // Validation
    let error = ''
    if (deliveredRaw !== undefined) {
      if (deliveredRaw < 0) {
        error = 'Cannot be negative'
      } else if (deliveredRaw > ordered) {
        error = `Max: ${ordered}`
      }
    }
    
    return {
      ...item,
      ordered,
      pending,
      delivered,
      deliveredRaw,
      remaining,
      isPartial,
      isFull,
      lineTotal,
      error,
    }
  })

  const totalDelivered = summary.reduce((sum, item) => sum + item.lineTotal, 0)
  const totalOrdered = summary.reduce((sum, item) => sum + (item.agreed_unit_cost || 0) * item.ordered, 0)
  const hasPartialDelivery = summary.some((item) => item.isPartial)
  const allItemsFull = summary.every((item) => item.isFull || item.delivered === 0)
  const hasAnyDelivery = summary.some((item) => item.delivered > 0)
  const hasErrors = summary.some((item) => item.error && item.delivered > 0)

  const handleQtyChange = (itemId: number, value: string) => {
    if (value === '') {
      setQtys((prev) => {
        const newQtys = { ...prev }
        delete newQtys[itemId]
        return newQtys
      })
    } else {
      setQtys((prev) => ({ ...prev, [itemId]: Number(value) }))
    }
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!hasAnyDelivery) {
      toast.error('Please enter at least one delivery quantity.')
      return
    }
    
    if (hasErrors) {
      toast.error('Please fix the validation errors before submitting.')
      return
    }
    
    onConfirm(qtys, notes, deliveryDate)
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200">
          <h2 className="text-lg font-semibold text-neutral-900">Confirm Delivery</h2>
          <button
            type="button"
            onClick={onClose}
            className="p-1 rounded-full hover:bg-neutral-100"
          >
            <X className="w-5 h-5 text-neutral-500" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {/* Delivery Date */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-2">
              Delivery Date <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              value={deliveryDate}
              onChange={(e) => setDeliveryDate(e.target.value)}
              max={formatDateInput(new Date())}
              className="w-full text-sm border border-neutral-300 rounded-md px-3 py-2"
              required
            />
            <p className="text-xs text-neutral-500 mt-1">
              Date when goods were actually delivered to the warehouse.
            </p>
          </div>

          {/* Items Table */}
          <div>
            <h3 className="text-sm font-medium text-neutral-700 mb-3">Enter Delivered Quantities</h3>
            <table className="w-full text-sm border border-neutral-200 rounded-xl overflow-hidden">
              <thead className="bg-neutral-50">
                <tr>
                  <th className="text-left px-4 py-2 font-medium text-neutral-600">Item</th>
                  <th className="text-right px-4 py-2 font-medium text-neutral-600">Ordered</th>
                  <th className="text-right px-4 py-2 font-medium text-neutral-600">Pending</th>
                  <th className="text-right px-4 py-2 font-medium text-neutral-600">Deliver Qty</th>
                  <th className="text-right px-4 py-2 font-medium text-neutral-600">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {summary.map((item) => (
                  <tr key={item.id} className={item.deliveredRaw !== undefined && item.deliveredRaw > 0 ? 'bg-green-50/50' : ''}>
                    <td className="px-4 py-3">
                      <span className="text-neutral-800">{item.item_description}</span>
                      <span className="text-xs text-neutral-400 ml-1">({item.unit_of_measure})</span>
                    </td>
                    <td className="px-4 py-3 text-right text-neutral-600">{item.ordered}</td>
                    <td className="px-4 py-3 text-right font-medium text-neutral-800">{item.pending}</td>
                    <td className="px-4 py-3 text-right">
                      <input
                        type="number"
                        min={0}
                        max={item.ordered}
                        step="0.001"
                        value={qtys[item.id] === undefined ? '' : qtys[item.id]}
                        onChange={(e) => handleQtyChange(item.id, e.target.value)}
                        className={`w-24 text-right text-sm border rounded-md px-2 py-1 ${
                          item.error 
                            ? 'border-red-400 bg-red-50' 
                            : item.deliveredRaw !== undefined && item.deliveredRaw > 0
                              ? item.isPartial 
                                ? 'border-amber-400 bg-amber-50' 
                                : item.isFull 
                                  ? 'border-green-400 bg-green-50' 
                                  : 'border-neutral-300'
                              : 'border-neutral-300'
                        }`}
                        placeholder="0"
                      />
                      {item.error && (
                        <p className="text-xs text-red-500 mt-1">{item.error}</p>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {item.deliveredRaw === undefined || item.deliveredRaw === 0 ? (
                        <span className="text-xs text-neutral-400">—</span>
                      ) : item.isFull ? (
                        <span className="text-xs font-medium text-green-600">✓ Full</span>
                      ) : (
                        <span className="text-xs font-medium text-amber-600">Partial</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Summary Panel */}
          {hasAnyDelivery && (
            <div className="bg-neutral-50 border border-neutral-200 rounded-lg p-4">
              <h4 className="text-sm font-medium text-neutral-700 mb-3">Delivery Summary</h4>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-neutral-500">Total Ordered:</span>
                  <span className="ml-2 font-medium">₱{totalOrdered.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                </div>
                <div>
                  <span className="text-neutral-500">This Delivery:</span>
                  <span className="ml-2 font-bold text-neutral-900">₱{totalDelivered.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                </div>
                <div>
                  <span className="text-neutral-500">Remaining:</span>
                  <span className="ml-2 font-medium">₱{(totalOrdered - totalDelivered).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                </div>
                <div>
                  <span className="text-neutral-500">Delivery Type:</span>
                  {allItemsFull && hasAnyDelivery ? (
                    <span className="ml-2 text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded font-medium">Full Delivery</span>
                  ) : hasPartialDelivery ? (
                    <span className="ml-2 text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">Partial Delivery</span>
                  ) : (
                    <span className="ml-2 text-xs text-neutral-400">—</span>
                  )}
                </div>
              </div>
              {hasPartialDelivery && (
                <p className="text-xs text-amber-600 mt-3">
                  ⚠️ This is a partial delivery. The remaining quantities will stay on the PO for future delivery.
                </p>
              )}
            </div>
          )}

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-2">
              Delivery Notes (Optional)
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              placeholder="Delivery reference, condition notes, etc."
              className="w-full text-sm border border-neutral-300 rounded-md px-3 py-2 resize-none"
            />
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t border-neutral-200">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-neutral-700 hover:text-neutral-900"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isPending || hasErrors}
              className="px-4 py-2 text-sm font-medium bg-neutral-900 text-white rounded-md hover:bg-neutral-800 disabled:opacity-50"
            >
              {isPending ? 'Confirming…' : hasErrors ? 'Fix Errors to Continue' : 'Confirm Delivery'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

export default function VendorOrderDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()

  const { data: order, isLoading, isError } = useVendorOrder(ulid ?? '')
  const markInTransit = useMarkInTransit()
  const markDelivered = useMarkDelivered()
  const acknowledgePO = useAcknowledgePO()
  const proposeChanges = useProposeChanges()
  const { hasPermission } = useAuthStore()

  const [inTransitNotes, setInTransitNotes] = useState('')
  const [showDeliveryModal, setShowDeliveryModal] = useState(false)
  const [showProposeForm, setShowProposeForm] = useState(false)
  const [proposeRemarks, setProposeRemarks] = useState('')
  const [proposeQtys, setProposeQtys] = useState<Record<number, number>>({})
  const [proposePrices, setProposePrices] = useState<Record<number, number>>({})
  const [proposeDeliveryDate, setProposeDeliveryDate] = useState('')

  // These effects must be before any early returns (Rules of Hooks)
  useEffect(() => {
    if (markDelivered.isSuccess && markDelivered.data) {
      const splitPo = markDelivered.data.data?.split_po

      if (splitPo) {
        toast.success(
          `Partial delivery confirmed. Split PO ${splitPo.reference} created for remaining quantities.`,
          { duration: 5000 }
        )
      } else {
        toast.success('Full delivery confirmed. A goods receipt draft has been created.')
      }
      setShowDeliveryModal(false)
      markDelivered.reset()
    }
  }, [markDelivered.isSuccess, markDelivered.data])

  useEffect(() => {
    if (markDelivered.isError && markDelivered.error) {
      toast.error(firstErrorMessage(markDelivered.error))
      markDelivered.reset()
    }
  }, [markDelivered.isError, markDelivered.error])

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading order…</p>
  if (isError || !order) return <p className="text-sm text-red-500 mt-4">Order not found.</p>

  const canFulfill = ['sent', 'acknowledged', 'in_transit'].includes(order.status) && hasPermission('vendor_portal.update_fulfillment')
  const canAcknowledgeOrPropose = order.status === 'sent'
  const canMarkInTransit = order.status === 'acknowledged'
  const canMarkDelivered = order.status === 'in_transit'
  const isDeliveredAwaitingGR = order.status === 'delivered'

  function handleAcknowledge() {
    if (!ulid) return
    acknowledgePO.mutate(
      { ulid },
      {
        onSuccess: () => toast.success('PO acknowledged. You can now mark it as in-transit when ready to ship.'),
        onError: (err) => toast.error(firstErrorMessage(err) ?? 'Failed to acknowledge PO.'),
      }
    )
  }

  function handleProposeChanges() {
    if (!ulid || !order) return
    if (!proposeRemarks.trim()) {
      toast.error('Please explain the reason for the proposed changes.')
      return
    }
    // Validate proposed quantities are strictly less than ordered
    for (const item of order.items) {
      const proposedQty = proposeQtys[item.id]
      if (proposedQty !== undefined && proposedQty >= Number(item.quantity_ordered)) {
        toast.error(`Proposed quantity for "${item.item_description}" must be less than the ordered quantity (${item.quantity_ordered}).`)
        return
      }
    }

    const items: ProposeChangesItem[] = order.items
      .filter((item) => proposeQtys[item.id] !== undefined || proposePrices[item.id] !== undefined)
      .map((item) => ({
        po_item_id: item.id,
        ...(proposeQtys[item.id] !== undefined ? { negotiated_quantity: proposeQtys[item.id] } : {}),
        ...(proposePrices[item.id] !== undefined ? { negotiated_unit_price: Math.round(proposePrices[item.id] * 100) } : {}),
      }))
    const hasPoLevelChanges = proposeDeliveryDate.trim() !== ''
    if (items.length === 0 && !hasPoLevelChanges) {
      toast.error('Please enter at least one proposed change.')
      return
    }
    const payload: ProposeChangesPayload = {
      ulid,
      remarks: proposeRemarks,
      items,
      ...(proposeDeliveryDate ? { proposed_delivery_date: proposeDeliveryDate } : {}),
    }
    proposeChanges.mutate(payload, {
      onSuccess: () => {
        toast.success('Proposed changes submitted. The buyer will review and respond.')
        setShowProposeForm(false)
        setProposeRemarks('')
        setProposeQtys({})
        setProposePrices({})
        setProposeDeliveryDate('')
      },
      onError: (err) => toast.error(firstErrorMessage(err) ?? 'Failed to submit proposal.'),
    })
  }

  function handleMarkInTransit() {
    if (!ulid) return
    markInTransit.mutate(
      { ulid, notes: inTransitNotes },
      {
        onSuccess: () => {
          toast.success('In-transit notification sent.')
          setInTransitNotes('')
        },
        onError: (err) => toast.error(firstErrorMessage(err)),
      }
    )
  }

  function handleMarkDelivered(qtys: Record<number, number>, notes: string, deliveryDate: string) {
    if (!ulid || !order) return
    const items = order.items
      .filter((item) => {
        const qty = qtys[item.id]
        return qty !== undefined && qty > 0
      })
      .map((item) => ({ po_item_id: item.id, qty_delivered: qtys[item.id] }))

    if (items.length === 0) {
      toast.error('Please enter at least one delivery quantity.')
      return
    }

    markDelivered.mutate({ ulid, items, notes, delivery_date: deliveryDate })
  }

  return (
    <div className="max-w-4xl">
      <div className="flex items-center gap-2 mb-1">
        <button
          onClick={() => navigate('/vendor-portal/orders')}
          className="text-xs text-neutral-400 hover:text-neutral-700"
        >
          ← Purchase Orders
        </button>
      </div>
      <div className="flex items-start justify-between mb-4">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900">{order.po_reference}</h1>
          <div className="flex items-center gap-2 mt-1">
            <span className="px-2 py-0.5 bg-neutral-100 rounded text-neutral-700 text-sm font-medium capitalize">
              {order.status.replace(/_/g, ' ')}
            </span>
            {order.po_type === 'split' && (
              <span className="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium">
                Split PO
              </span>
            )}
          </div>
        </div>
        <div className="text-right">
          <p className="text-xs text-neutral-500">Total Amount</p>
          <p className="text-lg font-semibold text-neutral-900">
            ₱{Number(order.total_po_amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
          </p>
        </div>
      </div>

      {/* PO Details Card */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-6 grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div className="flex items-start gap-2">
          <Calendar className="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-neutral-500">PO Date</p>
            <p className="font-medium text-neutral-800">
              {order.po_date ? new Date(order.po_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'}
            </p>
          </div>
        </div>
        <div className="flex items-start gap-2">
          <Calendar className="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-neutral-500">Required Delivery Date</p>
            <p className={`font-medium ${order.delivery_date && new Date(order.delivery_date) < new Date() && !['delivered','fully_received','closed'].includes(order.status) ? 'text-red-600' : 'text-neutral-800'}`}>
              {order.delivery_date
                ? new Date(order.delivery_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                : 'Not specified'}
              {order.delivery_date && new Date(order.delivery_date) < new Date() && !['delivered','fully_received','closed'].includes(order.status) && (
                <span className="ml-1 text-xs font-normal">(overdue)</span>
              )}
            </p>
          </div>
        </div>
        <div className="flex items-start gap-2">
          <CreditCard className="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-neutral-500">Payment Terms</p>
            <p className="font-medium text-neutral-800">{order.payment_terms || '—'}</p>
          </div>
        </div>
        <div className="flex items-start gap-2">
          <MapPin className="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
          <div>
            <p className="text-xs text-neutral-500">Delivery Address</p>
            <p className="font-medium text-neutral-800">{order.delivery_address || '—'}</p>
          </div>
        </div>
        {order.notes && (
          <div className="col-span-2 flex items-start gap-2">
            <FileText className="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
            <div>
              <p className="text-xs text-neutral-500">Notes / Instructions</p>
              <p className="text-neutral-700">{order.notes}</p>
            </div>
          </div>
        )}
        {order.proposed_delivery_date && (
          <div className="col-span-2 flex items-start gap-2 pt-2 border-t border-neutral-100">
            <Calendar className="w-4 h-4 text-amber-500 mt-0.5 shrink-0" />
            <div>
              <p className="text-xs text-amber-600 font-medium">Your Proposed Delivery Date (pending buyer review)</p>
              <p className="font-medium text-amber-700">
                {new Date(order.proposed_delivery_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })}
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Workflow Progress */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-6">
        <h3 className="text-xs font-medium text-neutral-500 mb-3 uppercase tracking-wide">Order Progress</h3>
        <div className="flex items-center">
          {/* Step 1: PO Sent */}
          <div className="flex items-center">
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium ${
              ['sent', 'negotiating', 'acknowledged', 'in_transit', 'partially_received', 'fully_received', 'closed'].includes(order.status)
                ? 'bg-green-100 text-green-700'
                : 'bg-neutral-100 text-neutral-400'
            }`}>
              1
            </div>
            <div className="ml-2 mr-4">
              <p className={`text-xs font-medium ${
                ['sent', 'negotiating', 'acknowledged', 'in_transit', 'partially_received', 'fully_received', 'closed'].includes(order.status)
                  ? 'text-green-700'
                  : 'text-neutral-400'
              }`}>PO Sent</p>
            </div>
          </div>
          
          {/* Connector */}
          <div className={`flex-1 h-0.5 mx-2 ${
            ['acknowledged', 'in_transit', 'partially_received', 'fully_received', 'closed'].includes(order.status)
              ? 'bg-green-500'
              : 'bg-neutral-200'
          }`} />

          {/* Step 2: Acknowledged / In Transit */}
          <div className="flex items-center">
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium ${
              ['in_transit', 'partially_received', 'fully_received', 'closed'].includes(order.status)
                ? 'bg-green-100 text-green-700'
                : ['sent', 'negotiating', 'acknowledged'].includes(order.status)
                  ? 'bg-blue-100 text-blue-700 border-2 border-blue-500'
                  : 'bg-neutral-100 text-neutral-400'
            }`}>
              2
            </div>
            <div className="ml-2 mr-4">
              <p className={`text-xs font-medium ${
                ['in_transit', 'partially_received', 'fully_received', 'closed'].includes(order.status)
                  ? 'text-green-700'
                  : ['sent', 'negotiating', 'acknowledged'].includes(order.status)
                    ? 'text-blue-700'
                    : 'text-neutral-400'
              }`}>
                {order.status === 'negotiating' ? 'Negotiating' : order.status === 'acknowledged' ? 'Acknowledged' : 'In Transit'}
              </p>
            </div>
          </div>
          
          {/* Connector */}
          <div className={`flex-1 h-0.5 mx-2 ${
            ['delivered', 'partially_received', 'fully_received', 'closed'].includes(order.status)
              ? 'bg-green-500'
              : 'bg-neutral-200'
          }`} />

          {/* Step 3: Delivered */}
          <div className="flex items-center">
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium ${
              ['delivered', 'partially_received', 'fully_received', 'closed'].includes(order.status)
                ? 'bg-green-100 text-green-700'
                : order.status === 'in_transit'
                  ? 'bg-blue-100 text-blue-700 border-2 border-blue-500'
                  : 'bg-neutral-100 text-neutral-400'
            }`}>
              3
            </div>
            <div className="ml-2 mr-4">
              <p className={`text-xs font-medium ${
                ['delivered', 'partially_received', 'fully_received', 'closed'].includes(order.status)
                  ? 'text-green-700'
                  : order.status === 'in_transit'
                    ? 'text-blue-700'
                    : 'text-neutral-400'
              }`}>Delivered</p>
            </div>
          </div>

          {/* Connector */}
          <div className={`flex-1 h-0.5 mx-2 ${
            ['fully_received', 'closed'].includes(order.status)
              ? 'bg-green-500'
              : order.status === 'partially_received'
                ? 'bg-amber-500'
                : 'bg-neutral-200'
          }`} />

          {/* Step 4: GR Confirmation */}
          <div className="flex items-center">
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium ${
              ['fully_received', 'closed'].includes(order.status)
                ? 'bg-green-100 text-green-700'
                : order.status === 'partially_received'
                  ? 'bg-amber-100 text-amber-700 border-2 border-amber-500'
                  : order.status === 'delivered'
                    ? 'bg-blue-100 text-blue-700 border-2 border-blue-500'
                    : 'bg-neutral-100 text-neutral-400'
            }`}>
              4
            </div>
            <div className="ml-2">
              <p className={`text-xs font-medium ${
                ['fully_received', 'closed'].includes(order.status)
                  ? 'text-green-700'
                  : order.status === 'partially_received'
                    ? 'text-amber-700'
                    : order.status === 'delivered'
                      ? 'text-blue-700'
                      : 'text-neutral-400'
              }`}>
                {order.status === 'partially_received' ? 'Partially Received' : 'GR Confirmed'}
              </p>
            </div>
          </div>
        </div>

        {/* Current Action Hint */}
        {order.status === 'sent' && (
          <p className="mt-3 text-xs text-blue-700 bg-blue-50 rounded px-3 py-2">
            <strong>Next:</strong> Acknowledge this PO to confirm you can fulfil it, or propose changes if needed.
          </p>
        )}
        {order.status === 'acknowledged' && (
          <p className="mt-3 text-xs text-blue-700 bg-blue-50 rounded px-3 py-2">
            <strong>Next:</strong> Click "Mark as In-Transit" when goods have been dispatched.
          </p>
        )}
        {order.status === 'in_transit' && (
          <p className="mt-3 text-xs text-blue-700 bg-blue-50 rounded px-3 py-2">
            <strong>Next:</strong> Click "Confirm Delivery" to report delivered quantities and create a goods receipt.
          </p>
        )}
        {isDeliveredAwaitingGR && (
          <p className="mt-3 text-xs text-green-700 bg-green-50 rounded px-3 py-2">
            <strong>Delivered.</strong> A goods receipt draft has been created. Awaiting warehouse confirmation.
          </p>
        )}
        {order.status === 'partially_received' && (
          <p className="mt-3 text-xs text-amber-700 bg-amber-50 rounded px-3 py-2">
            <strong>Status:</strong> Warehouse confirmed partial receipt. Check Split POs above for remaining quantities.
          </p>
        )}
        {order.status === 'fully_received' && (
          <p className="mt-3 text-xs text-green-700 bg-green-50 rounded px-3 py-2">
            <strong>Complete:</strong> All goods received. Awaiting payment per terms ({order.payment_terms}).
          </p>
        )}
      </div>

      {/* Parent PO Link */}
      {order.parent_po && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
          <p className="text-sm text-amber-800">
            <span className="font-medium">Split from:</span>{' '}
            <button
              onClick={() => navigate(`/vendor-portal/orders/${order.parent_po!.ulid}`)}
              className="text-amber-700 hover:text-amber-900 underline font-medium"
            >
              {order.parent_po.po_reference}
            </button>
          </p>
        </div>
      )}

      {/* Child POs (Split POs) */}
      {order.child_pos && order.child_pos.length > 0 && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <h3 className="text-sm font-medium text-blue-900 mb-2">Remaining Quantities (Split POs)</h3>
          <p className="text-xs text-blue-700 mb-3">
            This PO was partially delivered. The remaining items have been moved to new POs:
          </p>
          <div className="space-y-2">
            {order.child_pos.map((child) => (
              <div key={child.ulid} className="flex items-center justify-between bg-white rounded p-2">
                <div>
                  <button
                    onClick={() => navigate(`/vendor-portal/orders/${child.ulid}`)}
                    className="text-sm font-medium text-blue-700 hover:text-blue-900 underline"
                  >
                    {child.po_reference}
                  </button>
                  <span className="ml-2 text-xs px-2 py-0.5 bg-neutral-100 text-neutral-600 rounded capitalize">
                    {child.status.replace(/_/g, ' ')}
                  </span>
                </div>
                <span className="text-sm font-medium text-neutral-700">
                  ₱{Number(child.total_po_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Items Table */}
      <div className="bg-white border border-neutral-200 rounded-lg mb-6 overflow-hidden">
        <div className="px-4 py-3 border-b border-neutral-200">
          <h2 className="text-sm font-semibold text-neutral-800">Order Items</h2>
        </div>
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs">Description</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Unit Price</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Ordered</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Received</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Pending</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Line Total</th>
            </tr>
          </thead>
          <tbody>
            {order.items.map((item) => (
              <tr key={item.id} className="border-b border-neutral-100 last:border-0">
                <td className="px-4 py-3 text-neutral-800">
                  {item.item_description}
                  <span className="text-xs text-neutral-400 ml-1">({item.unit_of_measure})</span>
                </td>
                <td className="px-4 py-3 text-right text-neutral-600">
                  ₱{item.agreed_unit_cost ? Number(item.agreed_unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—'}
                </td>
                <td className="px-4 py-3 text-right text-neutral-600">{item.quantity_ordered}</td>
                <td className="px-4 py-3 text-right text-neutral-600">{item.quantity_received}</td>
                <td className="px-4 py-3 text-right font-medium text-neutral-800">{item.quantity_pending}</td>
                <td className="px-4 py-3 text-right text-neutral-600">
                  ₱{item.agreed_unit_cost ? (Number(item.agreed_unit_cost) * Number(item.quantity_ordered)).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* ── Acknowledge / Propose Changes (when status = sent) ───────────── */}
      {canAcknowledgeOrPropose && (
        <div className="mb-6 space-y-3">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p className="text-sm font-semibold text-blue-800 mb-1">Action Required: Review this Purchase Order</p>
            <p className="text-sm text-blue-700">
              You can fulfil this order as-is, or propose changes if you have a stock shortage or cannot meet the delivery date.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Acknowledge */}
            <ConfirmDialog
              title="Acknowledge Purchase Order?"
              description="By acknowledging, you confirm that you can fulfil this order on the agreed terms. The buyer will be notified and you can then mark it as in-transit."
              confirmLabel="Acknowledge"
              onConfirm={handleAcknowledge}
            >
              <button
                disabled={acknowledgePO.isPending}
                className="flex items-center justify-center gap-2 w-full text-sm bg-green-600 text-white rounded-lg px-4 py-3 hover:bg-green-700 disabled:opacity-50 font-medium"
              >
                <ThumbsUp className="w-4 h-4" />
                {acknowledgePO.isPending ? 'Acknowledging…' : 'Acknowledge PO'}
              </button>
            </ConfirmDialog>

            {/* Propose Changes toggle */}
            <button
              onClick={() => setShowProposeForm((v) => !v)}
              className="flex items-center justify-center gap-2 w-full text-sm bg-white border border-amber-300 text-amber-700 rounded-lg px-4 py-3 hover:bg-amber-50 font-medium"
            >
              <MessageSquare className="w-4 h-4" />
              Propose Changes
              {showProposeForm ? <ChevronUp className="w-3 h-3 ml-auto" /> : <ChevronDown className="w-3 h-3 ml-auto" />}
            </button>
          </div>

          {/* Propose Changes form */}
          {showProposeForm && (
            <div className="bg-white border border-amber-200 rounded-lg p-4 space-y-3">
              <p className="text-sm font-medium text-neutral-800">Propose Changes</p>

              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">
                  Reason / Remarks <span className="text-red-500">*</span>
                </label>
                <textarea
                  rows={3}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  placeholder="Explain what you cannot fulfil and why (stock shortage, lead time issue, etc.)"
                  value={proposeRemarks}
                  onChange={(e) => setProposeRemarks(e.target.value)}
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-neutral-600 mb-1">
                  Proposed Delivery Date
                </label>
                <input
                  type="date"
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  value={proposeDeliveryDate}
                  onChange={(e) => setProposeDeliveryDate(e.target.value)}
                />
                <p className="text-xs text-neutral-400 mt-1">Only if you cannot meet the original delivery date.</p>
              </div>

              <div>
                <p className="text-xs font-medium text-neutral-600 mb-2">Item Changes (leave blank if no change)</p>
                <div className="space-y-2">
                  {order.items.map((item) => (
                    <div key={item.id} className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-2">
                      <span className="text-sm text-neutral-700 truncate">{item.item_description}</span>
                      <span className="text-xs text-neutral-400 shrink-0 whitespace-nowrap">Ordered: {item.quantity_ordered} {item.unit_of_measure}</span>
                      <input
                        type="number"
                        min="0.001"
                        step="0.001"
                        max={Number(item.quantity_ordered) - 0.001}
                        className="w-24 text-sm border border-neutral-300 rounded px-2 py-1 text-right focus:outline-none focus:ring-1 focus:ring-neutral-400"
                        placeholder="Reduced qty"
                        value={proposeQtys[item.id] ?? ''}
                        onChange={(e) => {
                          const val = parseFloat(e.target.value)
                          setProposeQtys((prev) => ({ ...prev, [item.id]: isNaN(val) ? undefined as unknown as number : val }))
                        }}
                      />
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        className="w-28 text-sm border border-neutral-300 rounded px-2 py-1 text-right focus:outline-none focus:ring-1 focus:ring-neutral-400"
                        placeholder="New price ₱"
                        value={proposePrices[item.id] ?? ''}
                        onChange={(e) => {
                          const val = parseFloat(e.target.value)
                          setProposePrices((prev) => ({ ...prev, [item.id]: isNaN(val) ? undefined as unknown as number : val }))
                        }}
                      />
                    </div>
                  ))}
                </div>
              </div>

              <button
                onClick={handleProposeChanges}
                disabled={proposeChanges.isPending}
                className="w-full text-sm bg-amber-600 text-white rounded px-4 py-2 hover:bg-amber-700 disabled:opacity-50 font-medium"
              >
                {proposeChanges.isPending ? 'Submitting…' : 'Submit Proposed Changes'}
              </button>
            </div>
          )}
        </div>
      )}

      {canFulfill && (
        <div className={`grid grid-cols-1 ${canMarkInTransit && canMarkDelivered ? 'md:grid-cols-2' : ''} gap-4 mb-6`}>
          {/* Mark In Transit - only shown when status = acknowledged */}
          {canMarkInTransit && (
          <div className="bg-white border border-neutral-200 rounded-lg p-4">
            <div className="flex items-center gap-2 mb-3">
              <Truck className="w-4 h-4 text-neutral-700" />
              <h3 className="text-sm font-semibold text-neutral-800">Notify In-Transit</h3>
            </div>
            <textarea
              value={inTransitNotes}
              onChange={(e) => setInTransitNotes(e.target.value)}
              rows={2}
              placeholder="Optional notes (tracking number, courier, etc.)"
              className="w-full text-sm border border-neutral-300 rounded-md px-3 py-2 resize-none mb-3"
            />
            <ConfirmDialog
              title="Mark as In-Transit?"
              description="This will notify the buyer that the order is now in transit. Continue?"
              confirmLabel="Confirm"
              onConfirm={handleMarkInTransit}
            >
              <button
                disabled={markInTransit.isPending}
                className="w-full text-sm bg-neutral-900 text-white rounded-md px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
              >
                {markInTransit.isPending ? 'Sending…' : 'Mark as In-Transit'}
              </button>
            </ConfirmDialog>
          </div>
          )}

          {/* Mark Delivered */}
          {canMarkDelivered && (
          <div className="bg-white border border-neutral-200 rounded-lg p-4">
            <div className="flex items-center gap-2 mb-3">
              <CheckCircle className="w-4 h-4 text-neutral-600" />
              <h3 className="text-sm font-semibold text-neutral-800">Confirm Delivery</h3>
            </div>
            <p className="text-sm text-neutral-500 mb-3">
              Click to open the delivery form and enter quantities delivered.
            </p>
            <button
              onClick={() => setShowDeliveryModal(true)}
              disabled={markDelivered.isPending}
              className="w-full text-sm bg-neutral-900 text-white rounded-md px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
            >
              {markDelivered.isPending ? 'Confirming…' : 'Confirm Delivery'}
            </button>
          </div>
          )}
        </div>
      )}

      {/* Fulfillment History */}
      {(order.fulfillment_notes?.length ?? 0) > 0 && (
        <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden mb-6">
          <div className="px-4 py-3 border-b border-neutral-200">
            <h2 className="text-sm font-semibold text-neutral-800">Fulfillment History</h2>
          </div>
          <ul className="divide-y divide-neutral-100">
            {order.fulfillment_notes?.map((note) => (
              <li key={note.id} className="px-4 py-3">
                <div className="flex items-start gap-3">
                  <div className="mt-0.5">
                    {note.note_type === 'in_transit' && (
                      <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <Truck className="w-4 h-4 text-blue-600" />
                      </div>
                    )}
                    {(note.note_type === 'delivered') && (
                      <div className="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                        <CheckCircle className="w-4 h-4 text-green-600" />
                      </div>
                    )}
                    {note.note_type === 'partial' && (
                      <div className="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                        <AlertCircle className="w-4 h-4 text-amber-600" />
                      </div>
                    )}
                    {note.note_type === 'acknowledged' && (
                      <div className="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                        <ThumbsUp className="w-4 h-4 text-green-600" />
                      </div>
                    )}
                    {note.note_type === 'change_requested' && (
                      <div className="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                        <MessageSquare className="w-4 h-4 text-amber-600" />
                      </div>
                    )}
                    {(note.note_type === 'change_accepted' || note.note_type === 'change_rejected') && (
                      <div className={`w-8 h-8 rounded-full flex items-center justify-center ${note.note_type === 'change_accepted' ? 'bg-green-100' : 'bg-red-100'}`}>
                        <AlertCircle className={`w-4 h-4 ${note.note_type === 'change_accepted' ? 'text-green-600' : 'text-red-600'}`} />
                      </div>
                    )}
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-sm font-medium text-neutral-800 capitalize">
                        {note.note_type === 'in_transit' && 'Marked as In-Transit'}
                        {note.note_type === 'delivered' && 'Full Delivery Confirmed'}
                        {note.note_type === 'partial' && 'Partial Delivery Confirmed'}
                        {note.note_type === 'acknowledged' && 'PO Acknowledged'}
                        {note.note_type === 'change_requested' && 'Changes Proposed'}
                        {note.note_type === 'change_accepted' && 'Proposed Changes Accepted'}
                        {note.note_type === 'change_rejected' && 'Proposed Changes Rejected'}
                      </span>
                      <span className="text-xs text-neutral-400">· {new Date(note.created_at).toLocaleString()}</span>
                    </div>
                    {note.delivery_date && (
                      <p className="text-xs text-neutral-600 mb-1">
                        <span className="font-medium">Delivery Date:</span> {new Date(note.delivery_date).toLocaleDateString()}
                      </p>
                    )}
                    {note.items && note.items.length > 0 && (
                      <div className="text-xs text-neutral-600 mb-1">
                        <span className="font-medium">Items Delivered:</span>
                        <ul className="mt-1 ml-2 space-y-0.5">
                          {note.items.map((item: { po_item_id: number; qty_delivered: number }, idx: number) => {
                            const poItem = order.items.find((i) => i.id === item.po_item_id)
                            return (
                              <li key={idx}>
                                {poItem?.item_description || `Item #${item.po_item_id}`}: {item.qty_delivered} {poItem?.unit_of_measure || 'units'}
                              </li>
                            )
                          })}
                        </ul>
                      </div>
                    )}
                    {note.notes && <p className="text-xs text-neutral-500 italic mt-1">"{note.notes}"</p>}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Next Steps Info */}
      {(order.status === 'partially_received' || order.status === 'fully_received') && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <h3 className="text-sm font-medium text-green-900 mb-2">✓ Delivery Complete</h3>
          <p className="text-sm text-green-800 mb-2">
            The warehouse has confirmed receipt of the goods. Your delivery obligation for this PO is complete.
          </p>
          {order.status === 'partially_received' && order.child_pos && order.child_pos.length > 0 && (
            <p className="text-sm text-green-800">
              Please check the <strong>Split POs</strong> section above for any remaining quantities that need to be delivered.
            </p>
          )}
          {order.status === 'fully_received' && (
            <p className="text-sm text-green-800">
              This PO has been fully received. You will be invoiced according to the payment terms.
            </p>
          )}
        </div>
      )}

      {/* Delivery Modal */}
      <DeliveryModal
        isOpen={showDeliveryModal}
        onClose={() => setShowDeliveryModal(false)}
        onConfirm={handleMarkDelivered}
        items={order.items}
        isPending={markDelivered.isPending}
      />
    </div>
  )
}
