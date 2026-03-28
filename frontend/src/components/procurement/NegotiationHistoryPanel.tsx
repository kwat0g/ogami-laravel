import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

interface FulfillmentNote {
  id: number
  note_type: string
  notes: string | null
  items: Array<{
    po_item_id: number
    item_description: string
    quantity_ordered: number
    negotiated_quantity: number | null
    vendor_item_notes: string | null
  }> | null
  created_at: string
}

const NOTE_TYPE_STYLES: Record<string, { label: string; bg: string; icon: string }> = {
  change_requested: { label: 'Change Requested', bg: 'bg-amber-50 border-amber-200', icon: '📝' },
  change_accepted: { label: 'Change Accepted', bg: 'bg-green-50 border-green-200', icon: '✅' },
  change_rejected: { label: 'Change Rejected', bg: 'bg-red-50 border-red-200', icon: '❌' },
  in_transit: { label: 'In Transit', bg: 'bg-blue-50 border-blue-200', icon: '🚚' },
  delivered: { label: 'Delivered', bg: 'bg-emerald-50 border-emerald-200', icon: '📦' },
  acknowledged: { label: 'Acknowledged', bg: 'bg-indigo-50 border-indigo-200', icon: '👍' },
  partial: { label: 'Partial Delivery', bg: 'bg-orange-50 border-orange-200', icon: '📋' },
}

function formatDate(dateStr: string): string {
  try {
    return new Date(dateStr).toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}

interface Props {
  poUlid: string
  fulfillmentNotes?: FulfillmentNote[]
}

/**
 * Negotiation History Panel — shows the full conversation timeline
 * of vendor negotiations on a Purchase Order.
 *
 * Can receive fulfillment_notes from the parent PO detail page,
 * or fetch them independently via API.
 */
export default function NegotiationHistoryPanel({ poUlid, fulfillmentNotes }: Props) {
  const { data: fetchedNotes, isLoading } = useQuery({
    queryKey: ['po-fulfillment-notes', poUlid],
    queryFn: async () => {
      const res = await api.get<{ data: { fulfillment_notes: FulfillmentNote[] } }>(
        `/procurement/purchase-orders/${poUlid}`,
      )
      return res.data.data.fulfillment_notes ?? []
    },
    enabled: !fulfillmentNotes && !!poUlid,
    staleTime: 30_000,
  })

  const notes = fulfillmentNotes ?? fetchedNotes ?? []
  const negotiationNotes = notes.filter((n) =>
    ['change_requested', 'change_accepted', 'change_rejected', 'acknowledged', 'in_transit', 'delivered', 'partial'].includes(n.note_type),
  )

  if (isLoading && !fulfillmentNotes) return <SkeletonLoader rows={3} />

  if (negotiationNotes.length === 0) {
    return (
      <div className="text-xs text-neutral-400 py-3 text-center">
        No negotiation history yet.
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <h4 className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">
        Negotiation History ({negotiationNotes.length} entries)
      </h4>
      <div className="space-y-2">
        {negotiationNotes.map((note) => {
          const style = NOTE_TYPE_STYLES[note.note_type] ?? { label: note.note_type, bg: 'bg-neutral-50 border-neutral-200', icon: '📄' }
          return (
            <div key={note.id} className={`border rounded-lg p-3 ${style.bg}`}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-sm">{style.icon}</span>
                  <span className="text-xs font-semibold text-neutral-700">{style.label}</span>
                </div>
                <span className="text-[10px] text-neutral-400">{formatDate(note.created_at)}</span>
              </div>
              {note.notes && (
                <p className="text-xs text-neutral-600 mt-1.5">{note.notes}</p>
              )}
              {note.items && note.items.length > 0 && (
                <div className="mt-2 space-y-1">
                  {note.items.map((item, idx) => (
                    <div key={idx} className="text-[11px] text-neutral-500 flex items-center gap-2">
                      <span className="font-medium text-neutral-700">{item.item_description}</span>
                      {item.negotiated_quantity !== null && item.negotiated_quantity !== item.quantity_ordered && (
                        <span className="text-amber-600">
                          Qty: {item.quantity_ordered} → {item.negotiated_quantity}
                        </span>
                      )}
                      {item.vendor_item_notes && (
                        <span className="italic text-neutral-400">"{item.vendor_item_notes}"</span>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
