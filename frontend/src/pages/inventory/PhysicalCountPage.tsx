import { useState } from 'react'
import { ClipboardCheck, Save } from 'lucide-react'
import { toast } from 'sonner'
import { useItems, useWarehouseLocations, useStockBalances, useStockAdjust } from '@/hooks/useInventory'

interface CountItem {
  item_id: number
  item_code: string
  item_name: string
  location_id: number
  location_name: string
  system_qty: number
  counted_qty: string
  variance: number
}

export default function PhysicalCountPage(): React.ReactElement {
  const [locationId, setLocationId] = useState<number | ''>('')
  const { data: locations } = useWarehouseLocations({ is_active: true })
  const { data: itemsData } = useItems({ is_active: true, per_page: 500 })
  const { data: balancesData } = useStockBalances(locationId ? { location_id: locationId, per_page: 500 } : {})
  const adjustMutation = useStockAdjust()

  const items = itemsData?.data ?? []
  const balances = balancesData?.data ?? []

  const [countItems, setCountItems] = useState<CountItem[]>([])
  const [isInitialized, setIsInitialized] = useState(false)

  const initializeCount = () => {
    if (!locationId) {
      toast.error('Please select a warehouse location first.')
      return
    }
    const loc = (locations ?? []).find((l) => l.id === locationId)
    const rows: CountItem[] = items.map((item) => {
      const balance = balances.find((b) => b.item_id === item.id)
      return {
        item_id: item.id,
        item_code: item.item_code,
        item_name: item.name,
        location_id: Number(locationId),
        location_name: loc?.name ?? '',
        system_qty: Number(balance?.quantity ?? 0),
        counted_qty: '',
        variance: 0,
      }
    })
    setCountItems(rows)
    setIsInitialized(true)
  }

  const updateCount = (idx: number, value: string) => {
    setCountItems((prev) => {
      const next = [...prev]
      next[idx] = { ...next[idx], counted_qty: value, variance: value ? Number(value) - next[idx].system_qty : 0 }
      return next
    })
  }

  const [posting, setPosting] = useState(false)

  const postAdjustments = async () => {
    const adjustments = countItems.filter((c) => c.counted_qty !== '' && c.variance !== 0)
    if (adjustments.length === 0) {
      toast.info('No variances to post.')
      return
    }
    setPosting(true)
    let success = 0
    for (const adj of adjustments) {
      try {
        await adjustMutation.mutateAsync({
          item_id: adj.item_id,
          location_id: adj.location_id,
          adjusted_qty: Number(adj.counted_qty),
          remarks: `Physical inventory count. System: ${adj.system_qty}, Counted: ${adj.counted_qty}, Variance: ${adj.variance > 0 ? '+' : ''}${adj.variance}`,
        })
        success++
      } catch {
        toast.error(`Failed to adjust ${adj.item_code}`)
      }
    }
    setPosting(false)
    toast.success(`Posted ${success} adjustments successfully.`)
    setIsInitialized(false)
    setCountItems([])
  }

  const totalVariance = countItems.filter((c) => c.counted_qty !== '').reduce((s, c) => s + Math.abs(c.variance), 0)
  const countedCount = countItems.filter((c) => c.counted_qty !== '').length

  return (
    <div className="max-w-6xl mx-auto">
      <h1 className="text-2xl font-bold text-neutral-900 flex items-center gap-2 mb-6">
        <ClipboardCheck className="w-6 h-6 text-orange-600" /> Physical Inventory Count
      </h1>

      {/* Setup */}
      {!isInitialized && (
        <div className="bg-white border border-neutral-200 rounded-lg p-6 max-w-md">
          <h2 className="font-semibold text-neutral-700 mb-4">Start Count Session</h2>
          <div className="mb-4">
            <label className="block text-xs font-medium text-neutral-500 mb-1">Warehouse Location</label>
            <select value={locationId} onChange={(e) => setLocationId(e.target.value ? Number(e.target.value) : '')}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm">
              <option value="">Select location…</option>
              {(locations ?? []).map((l) => (
                <option key={l.id} value={l.id}>{l.code} — {l.name}</option>
              ))}
            </select>
          </div>
          <button onClick={initializeCount}
            className="bg-orange-600 hover:bg-orange-700 text-white font-medium py-2.5 px-6 rounded transition-colors text-sm">
            Initialize Count Sheet
          </button>
        </div>
      )}

      {/* Count Sheet */}
      {isInitialized && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="text-sm text-neutral-500">
              {countedCount} of {countItems.length} items counted · {totalVariance > 0 ? `${totalVariance} total variance units` : 'No variances'}
            </div>
            <div className="flex gap-2">
              <button onClick={() => { setIsInitialized(false); setCountItems([]) }}
                className="text-sm text-neutral-500 hover:text-neutral-700 px-3 py-2 border border-neutral-300 rounded">
                Cancel
              </button>
              <button onClick={postAdjustments} disabled={posting || countedCount === 0}
                className="flex items-center gap-1.5 bg-orange-600 hover:bg-orange-700 text-white font-medium py-2 px-4 rounded transition-colors text-sm disabled:opacity-50">
                <Save className="w-4 h-4" /> {posting ? 'Posting…' : 'Post Adjustments'}
              </button>
            </div>
          </div>

          <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-500">Code</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-neutral-500">Item</th>
                  <th className="px-3 py-2 text-right text-xs font-medium text-neutral-500">System Qty</th>
                  <th className="px-3 py-2 text-center text-xs font-medium text-neutral-500 w-32">Counted</th>
                  <th className="px-3 py-2 text-right text-xs font-medium text-neutral-500">Variance</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {countItems.map((item, i) => (
                  <tr key={item.item_id} className={`hover:bg-neutral-50 transition-colors ${item.variance !== 0 ? 'bg-amber-50' : ''}`}>
                    <td className="px-3 py-2 font-mono text-xs text-neutral-500">{item.item_code}</td>
                    <td className="px-3 py-2 text-neutral-800">{item.item_name}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{item.system_qty}</td>
                    <td className="px-3 py-2 text-center">
                      <input type="number" min="0" step="1" value={item.counted_qty}
                        onChange={(e) => updateCount(i, e.target.value)}
                        className="w-24 border border-neutral-300 rounded px-2 py-1 text-sm text-center tabular-nums"
                        placeholder="—" />
                    </td>
                    <td className={`px-3 py-2 text-right tabular-nums font-medium ${
                      item.variance > 0 ? 'text-emerald-600' : item.variance < 0 ? 'text-red-600' : 'text-neutral-400'
                    }`}>
                      {item.counted_qty !== '' ? (item.variance > 0 ? `+${item.variance}` : item.variance) : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
