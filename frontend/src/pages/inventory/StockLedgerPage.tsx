import { useState } from 'react'
import { List, AlertTriangle } from 'lucide-react'
import { useStockLedger, useWarehouseLocations } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { StockLedger } from '@/types/inventory'

const TX_LABELS: Record<StockLedger['transaction_type'], string> = {
  goods_receipt:      'Goods Receipt',
  issue:              'Issue',
  transfer:           'Transfer',
  adjustment:         'Adjustment',
  return:             'Return',
  production_output:  'Production Output',
}

const txBadge: Record<StockLedger['transaction_type'], string> = {
  goods_receipt:      'bg-green-100 text-green-700',
  issue:              'bg-orange-100 text-orange-700',
  transfer:           'bg-blue-100 text-blue-700',
  adjustment:         'bg-purple-100 text-purple-700',
  return:             'bg-yellow-100 text-yellow-700',
  production_output:  'bg-teal-100 text-teal-700',
}

export default function StockLedgerPage(): React.ReactElement {
  const [locationId, setLocationId] = useState<number | ''>('')
  const [txType, setTxType]         = useState('')
  const [dateFrom, setDateFrom]     = useState('')
  const [dateTo, setDateTo]         = useState('')
  const [page, setPage]             = useState(1)

  const { data: locations } = useWarehouseLocations({ is_active: true })
  const { data, isLoading, isError } = useStockLedger({
    location_id:      locationId || undefined,
    transaction_type: txType || undefined,
    date_from:        dateFrom || undefined,
    date_to:          dateTo || undefined,
    page,
    per_page: 30,
  })

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
            <List className="w-5 h-5 text-teal-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Stock Ledger</h1>
            <p className="text-sm text-gray-500 mt-0.5">All stock movement history (append-only)</p>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <select
          value={locationId}
          onChange={(e) => { setLocationId(e.target.value ? Number(e.target.value) : ''); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Locations</option>
          {(locations ?? []).map((l) => <option key={l.id} value={l.id}>{l.code} — {l.name}</option>)}
        </select>
        <select
          value={txType}
          onChange={(e) => { setTxType(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Types</option>
          {(Object.keys(TX_LABELS) as StockLedger['transaction_type'][]).map((t) => (
            <option key={t} value={t}>{TX_LABELS[t]}</option>
          ))}
        </select>
        <input
          type="date"
          value={dateFrom}
          onChange={(e) => { setDateFrom(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        />
        <span className="text-gray-400 text-sm">to</span>
        <input
          type="date"
          value={dateTo}
          onChange={(e) => { setDateTo(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        />
      </div>

      {isLoading && <SkeletonLoader rows={12} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load stock ledger.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['Date/Time', 'Item', 'Location', 'Type', 'Qty', 'Balance After', 'Reference', 'By', 'Remarks'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={9} className="px-4 py-8 text-center text-gray-400 text-sm">No ledger entries found.</td>
                  </tr>
                )}
                {data?.data?.map((entry) => {
                  const qty = parseFloat(entry.quantity)
                  const isNeg = qty < 0

                  return (
                    <tr key={entry.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">
                        {new Date(entry.created_at).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' })}
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-mono text-teal-700 font-medium text-xs">{entry.item?.item_code ?? `#${entry.item_id}`}</div>
                        <div className="text-gray-500 text-xs truncate max-w-32">{entry.item?.name}</div>
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-500">{entry.location?.code ?? `#${entry.location_id}`}</td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap ${txBadge[entry.transaction_type]}`}>
                          {TX_LABELS[entry.transaction_type]}
                        </span>
                      </td>
                      <td className={`px-4 py-3 font-semibold tabular-nums text-right ${isNeg ? 'text-red-600' : 'text-green-600'}`}>
                        {isNeg ? '' : '+'}{qty.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                      </td>
                      <td className="px-4 py-3 font-semibold tabular-nums text-right text-gray-700">
                        {parseFloat(entry.balance_after).toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-400 font-mono">
                        {entry.reference_type ? `${entry.reference_type}#${entry.reference_id}` : '—'}
                      </td>
                      <td className="px-4 py-3 text-xs text-gray-500">{entry.created_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-xs text-gray-400 max-w-32 truncate">{entry.remarks ?? '—'}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} entries</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-gray-300 rounded-lg disabled:opacity-40 hover:bg-gray-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
