import { useState } from 'react'
import { toast } from 'sonner'
import { PackagePlus, ShieldAlert } from 'lucide-react'
import {
  useItems,
  useWarehouseLocations,
  useStockAdjust,
  useStockLedger,
} from '@/hooks/useInventory'
import { useAuthStore } from '@/stores/authStore'

export default function StockAdjustmentsPage(): React.ReactElement {
  const canAdjust = useAuthStore(s => s.hasPermission('inventory.adjustments.create'))
  const [itemId, setItemId] = useState<number | ''>('')
  const [locationId, setLocationId] = useState<number | ''>('')
  const [adjustedQty, setAdjustedQty] = useState('')
  const [remarks, setRemarks] = useState('')

  const { data: itemsData } = useItems({ is_active: true, per_page: 500 })
  const { data: locations } = useWarehouseLocations({ is_active: true })
  const { data: ledgerData } = useStockLedger({ transaction_type: 'adjustment', per_page: 20 })
  const adjustMutation = useStockAdjust()

  const items = itemsData?.data ?? []
  const recentAdjustments = ledgerData?.data ?? []

  if (!canAdjust) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-neutral-500">
        <ShieldAlert className="w-10 h-10 mb-3 text-neutral-400" />
        <p className="text-sm font-medium">You do not have permission to create stock adjustments.</p>
      </div>
    )
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!itemId || !locationId || !adjustedQty || remarks.length < 10) {
      toast.error('Please fill all fields. Remarks must be at least 10 characters.')
      return
    }
    adjustMutation.mutate(
      { item_id: Number(itemId), location_id: Number(locationId), adjusted_qty: Number(adjustedQty), remarks },
      {
        onSuccess: () => {
          toast.success('Stock adjustment recorded successfully.')
          setItemId('')
          setLocationId('')
          setAdjustedQty('')
          setRemarks('')
        },
        onError: () => toast.error('Adjustment failed. Check the form and try again.'),
      },
    )
  }

  return (
    <div className="max-w-5xl mx-auto">
      <h1 className="text-2xl font-bold text-neutral-900 flex items-center gap-2 mb-6">
        <PackagePlus className="w-6 h-6 text-teal-600" />
        Stock Adjustments
      </h1>

      <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {/* Form */}
        <form onSubmit={handleSubmit} className="lg:col-span-2 bg-white border border-neutral-200 rounded-lg p-5 space-y-4 h-fit">
          <h2 className="font-semibold text-neutral-700 text-sm uppercase tracking-wide">New Adjustment</h2>

          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Item</label>
            <select value={itemId} onChange={(e) => setItemId(e.target.value ? Number(e.target.value) : '')}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" required>
              <option value="">Select item…</option>
              {items.map((i) => (
                <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Warehouse Location</label>
            <select value={locationId} onChange={(e) => setLocationId(e.target.value ? Number(e.target.value) : '')}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" required>
              <option value="">Select location…</option>
              {(locations ?? []).map((l) => (
                <option key={l.id} value={l.id}>{l.code} — {l.name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Adjusted Quantity</label>
            <input type="number" min="0" step="0.01" value={adjustedQty}
              onChange={(e) => setAdjustedQty(e.target.value)}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" required
              placeholder="New absolute quantity" />
            <p className="text-xs text-neutral-400 mt-1">Enter the new physical count (absolute, not delta)</p>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">Remarks / Reason</label>
            <textarea value={remarks} onChange={(e) => setRemarks(e.target.value)}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm" rows={3} required
              minLength={10} placeholder="Reason for adjustment (min 10 chars)" />
          </div>

          <button type="submit" disabled={adjustMutation.isPending}
            className="w-full bg-teal-600 hover:bg-teal-700 text-white font-medium py-2.5 rounded transition-colors text-sm disabled:opacity-50">
            {adjustMutation.isPending ? 'Submitting…' : 'Record Adjustment'}
          </button>
        </form>

        {/* Recent adjustments */}
        <div className="lg:col-span-3 bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <h2 className="font-semibold text-neutral-700 text-sm uppercase tracking-wide">Recent Adjustments</h2>
          </div>
          {recentAdjustments.length === 0 ? (
            <p className="text-sm text-neutral-500 p-6">No adjustment entries yet.</p>
          ) : (
            <table className="w-full text-sm">
              <thead className="border-b border-neutral-200 bg-neutral-50">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-500">Date</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-500">Item</th>
                  <th className="px-3 py-2 text-right text-xs font-medium text-neutral-500">Qty</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-500">Remarks</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {recentAdjustments.map((entry) => (
                  <tr key={entry.id} className="hover:bg-neutral-50 transition-colors">
                    <td className="px-3 py-2 text-neutral-600 whitespace-nowrap">{entry.created_at?.split('T')[0] ?? '—'}</td>
                    <td className="px-3 py-2 text-neutral-800">{entry.item?.name ?? `Item #${entry.item_id}`}</td>
                    <td className={`px-3 py-2 text-right tabular-nums font-medium ${Number(entry.quantity) >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                      {Number(entry.quantity) >= 0 ? '+' : ''}{entry.quantity}
                    </td>
                    <td className="px-3 py-2 text-neutral-500 max-w-[200px] truncate">{entry.remarks ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  )
}
