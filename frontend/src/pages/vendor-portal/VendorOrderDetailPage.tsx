import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Truck, CheckCircle, AlertCircle } from 'lucide-react'
import { useVendorOrder, useMarkInTransit, useMarkDelivered } from '@/hooks/useVendorPortal'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'

export default function VendorOrderDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()

  const { data: order, isLoading, isError } = useVendorOrder(ulid ?? '')
  const markInTransit = useMarkInTransit()
  const markDelivered = useMarkDelivered()
  const { hasPermission } = useAuthStore()

  const [inTransitNotes, setInTransitNotes] = useState('')
  const [deliveryNotes, setDeliveryNotes] = useState('')
  const [deliveryQtys, setDeliveryQtys] = useState<Record<number, number>>({})

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading order…</p>
  if (isError || !order) return <p className="text-sm text-red-500 mt-4">Order not found.</p>

  const canFulfill = ['sent', 'partially_received'].includes(order.status) && hasPermission('vendor_portal.update_fulfillment')

  function handleMarkInTransit() {
    if (!ulid) return
    markInTransit.mutate(
      { ulid, notes: inTransitNotes },
      {
        onSuccess: () => {
          toast.success('In-transit notification sent.')
          setInTransitNotes('')
        },
        onError: () => toast.error('Failed to send notification.'),
      }
    )
  }

  function handleMarkDelivered() {
    if (!ulid || !order) return
    const items = order.items
      .filter((item) => {
        const qty = deliveryQtys[item.id]
        return qty !== undefined && qty > 0
      })
      .map((item) => ({ po_item_id: item.id, qty_delivered: deliveryQtys[item.id] }))

    if (items.length === 0) {
      toast.error('Please enter at least one delivery quantity.')
      return
    }

    markDelivered.mutate(
      { ulid, items, notes: deliveryNotes },
      {
        onSuccess: () => {
          toast.success('Delivery confirmed. A goods receipt draft has been created.')
          setDeliveryQtys({})
          setDeliveryNotes('')
        },
        onError: () => toast.error('Failed to confirm delivery.'),
      }
    )
  }

  return (
    <div className="max-w-4xl">
      <div className="flex items-center gap-2 mb-1">
        <button
          onClick={() => navigate('/vendor-portal/orders')}
          className="text-xs text-neutral-400 hover:text-neutral-700"
        >
          ← Orders
        </button>
      </div>
      <h1 className="text-2xl font-bold text-neutral-900 mb-1">{order.po_reference}</h1>
      <p className="text-sm text-neutral-500 mb-6">
        Delivery by <strong>{order.delivery_date}</strong> · {order.payment_terms}
      </p>

      {/* Items Table */}
      <div className="bg-white border border-neutral-200 rounded-lg mb-6 overflow-hidden">
        <div className="px-4 py-3 border-b border-neutral-200">
          <h2 className="text-sm font-semibold text-neutral-800">Order Items</h2>
        </div>
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs">Description</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Ordered</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Received</th>
              <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Pending</th>
              {canFulfill && (
                <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs">Deliver Qty</th>
              )}
            </tr>
          </thead>
          <tbody>
            {order.items.map((item) => (
              <tr key={item.id} className="border-b border-neutral-100 last:border-0">
                <td className="px-4 py-3 text-neutral-800">
                  {item.item_description}
                  <span className="text-xs text-neutral-400 ml-1">({item.unit_of_measure})</span>
                </td>
                <td className="px-4 py-3 text-right text-neutral-600">{item.quantity_ordered}</td>
                <td className="px-4 py-3 text-right text-neutral-600">{item.quantity_received}</td>
                <td className="px-4 py-3 text-right font-medium text-neutral-800">{item.quantity_pending}</td>
                {canFulfill && (
                  <td className="px-4 py-3 text-right">
                    <input
                      type="number"
                      min={0}
                      max={Number(item.quantity_pending)}
                      step="0.001"
                      value={deliveryQtys[item.id] ?? ''}
                      onChange={(e) =>
                        setDeliveryQtys((prev) => ({ ...prev, [item.id]: Number(e.target.value) }))
                      }
                      className="w-24 text-right text-sm border border-neutral-300 rounded-md px-2 py-1"
                      placeholder="0"
                    />
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {canFulfill && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          {/* Mark In Transit */}
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
            <button
              onClick={handleMarkInTransit}
              disabled={markInTransit.isPending}
              className="w-full text-sm bg-neutral-900 text-white rounded-md px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
            >
              {markInTransit.isPending ? 'Sending…' : 'Mark as In-Transit'}
            </button>
          </div>

          {/* Mark Delivered */}
          <div className="bg-white border border-neutral-200 rounded-lg p-4">
            <div className="flex items-center gap-2 mb-3">
              <CheckCircle className="w-4 h-4 text-neutral-600" />
              <h3 className="text-sm font-semibold text-neutral-800">Confirm Delivery</h3>
            </div>
            <textarea
              value={deliveryNotes}
              onChange={(e) => setDeliveryNotes(e.target.value)}
              rows={2}
              placeholder="Optional notes (delivery reference, condition, etc.)"
              className="w-full text-sm border border-neutral-300 rounded-md px-3 py-2 resize-none mb-3"
            />
            <button
              onClick={handleMarkDelivered}
              disabled={markDelivered.isPending}
              className="w-full text-sm bg-neutral-900 text-white rounded-md px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
            >
              {markDelivered.isPending ? 'Confirming…' : 'Confirm Delivery'}
            </button>
          </div>
        </div>
      )}

      {/* Fulfillment History */}
      {(order.fulfillment_notes?.length ?? 0) > 0 && (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <div className="px-4 py-3 border-b border-neutral-200">
            <h2 className="text-sm font-semibold text-neutral-800">Fulfillment History</h2>
          </div>
          <ul className="divide-y divide-neutral-100">
            {order.fulfillment_notes?.map((note) => (
              <li key={note.id} className="px-4 py-3">
                <div className="flex items-center gap-2">
                  {note.note_type === 'in_transit' && <Truck className="w-3.5 h-3.5 text-neutral-500" />}
                  {note.note_type === 'delivered' && <CheckCircle className="w-3.5 h-3.5 text-neutral-500" />}
                  {note.note_type === 'partial' && <AlertCircle className="w-3.5 h-3.5 text-yellow-500" />}
                  <span className="text-xs font-medium text-neutral-700 capitalize">{note.note_type.replace('_', ' ')}</span>
                  <span className="text-xs text-neutral-400">{new Date(note.created_at).toLocaleString()}</span>
                </div>
                {note.notes && <p className="text-xs text-neutral-600 mt-1 ml-5">{note.notes}</p>}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
