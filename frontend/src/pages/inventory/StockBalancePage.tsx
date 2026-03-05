import { useState } from 'react'
import { BarChart2, AlertTriangle, AlertCircle } from 'lucide-react'
import { useStockBalances, useWarehouseLocations } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function StockBalancePage(): React.ReactElement {
  const [search, setSearch]         = useState('')
  const [locationId, setLocationId] = useState<number | ''>('')
  const [lowStock, setLowStock]     = useState(false)
  const [page, setPage]             = useState(1)

  const { data: locations } = useWarehouseLocations({ is_active: true })
  const { data, isLoading, isError } = useStockBalances({
    search: search || undefined,
    location_id: locationId || undefined,
    low_stock: lowStock || undefined,
    page,
    per_page: 25,
  })

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
            <BarChart2 className="w-5 h-5 text-teal-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Stock Balances</h1>
            <p className="text-sm text-gray-500 mt-0.5">Current on-hand quantities per location</p>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <input
          type="text"
          placeholder="Search item code or name…"
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white w-52"
        />
        <select
          value={locationId}
          onChange={(e) => { setLocationId(e.target.value ? Number(e.target.value) : ''); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Locations</option>
          {(locations ?? []).map((l) => <option key={l.id} value={l.id}>{l.code} — {l.name}</option>)}
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input
            type="checkbox"
            checked={lowStock}
            onChange={(e) => { setLowStock(e.target.checked); setPage(1) }}
            className="rounded border-gray-300 text-red-500 focus:ring-red-400"
          />
          Low stock only
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={10} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load stock balances.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['Item Code', 'Name', 'Location', 'UOM', 'On Hand', 'Reorder Pt.', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-gray-400 text-sm">No stock records found.</td>
                  </tr>
                )}
                {data?.data?.map((bal) => {
                  const onHand      = parseFloat(bal.quantity_on_hand)
                  const reorderPt   = parseFloat(bal.item?.reorder_point ?? '0')
                  const isBelowReorder = onHand <= reorderPt

                  return (
                    <tr key={`${bal.item_id}-${bal.location_id}`} className={`hover:bg-gray-50 ${isBelowReorder ? 'bg-red-50/30' : ''}`}>
                      <td className="px-4 py-3 font-mono text-teal-700 font-medium">{bal.item?.item_code ?? `#${bal.item_id}`}</td>
                      <td className="px-4 py-3 text-gray-900">{bal.item?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{bal.location ? `${bal.location.code} — ${bal.location.name}` : `#${bal.location_id}`}</td>
                      <td className="px-4 py-3 text-gray-500">{bal.item?.unit_of_measure ?? '—'}</td>
                      <td className={`px-4 py-3 font-semibold tabular-nums ${isBelowReorder ? 'text-red-600' : 'text-gray-900'}`}>
                        {onHand.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                      </td>
                      <td className="px-4 py-3 text-gray-400 tabular-nums">
                        {reorderPt.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                      </td>
                      <td className="px-4 py-3">
                        {isBelowReorder && (
                          <div className="flex items-center gap-1 text-red-500 text-xs font-semibold">
                            <AlertCircle className="w-3.5 h-3.5" /> Low
                          </div>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {data && data.meta && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} records</span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => p + 1)}
                  disabled={page >= data.meta.last_page}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
