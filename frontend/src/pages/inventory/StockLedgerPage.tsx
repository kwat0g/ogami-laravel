import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useStockLedger, useWarehouseLocations } from '@/hooks/useInventory'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import type { StockLedger } from '@/types/inventory'

function resolveReferenceRoute(referenceType: string | null, ulid: string): string {
  switch (referenceType) {
    case 'goods_receipts':        return `/procurement/goods-receipts/${ulid}`
    case 'material_requisitions': return `/inventory/requisitions/${ulid}`
    case 'production_orders':     return `/production/orders/${ulid}`
    default:                      return '#'
  }
}

const TX_LABELS: Record<StockLedger['transaction_type'], string> = {
  goods_receipt:      'Goods Receipt',
  issue:              'Issue',
  transfer:           'Transfer',
  adjustment:         'Adjustment',
  return:             'Return',
  production_output:  'Production Output',
}

const txBadge: Record<StockLedger['transaction_type'], string> = {
  goods_receipt:      'bg-neutral-100 text-neutral-700',
  issue:              'bg-neutral-100 text-neutral-700',
  transfer:           'bg-neutral-100 text-neutral-700',
  adjustment:         'bg-neutral-100 text-neutral-700',
  return:             'bg-neutral-100 text-neutral-700',
  production_output:  'bg-neutral-100 text-neutral-700',
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
      <PageHeader title="Item Movements" backTo="/inventory/stock" />
      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <select
          value={locationId}
          onChange={(e) => { setLocationId(e.target.value ? Number(e.target.value) : ''); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Locations</option>
          {(locations ?? []).map((l) => <option key={l.id} value={l.id}>{l.code} — {l.name}</option>)}
        </select>
        <select
          value={txType}
          onChange={(e) => { setTxType(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
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
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        />
        <span className="text-neutral-400 text-sm">to</span>
        <input
          type="date"
          value={dateTo}
          onChange={(e) => { setDateTo(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        />
      </div>

      {isLoading && <SkeletonLoader rows={12} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load item movements.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <Card>
            <CardHeader>Item Movement History</CardHeader>
            <CardBody className="p-0">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['Date/Time', 'Item', 'Location', 'Type', 'Qty', 'Balance After', 'Reference', 'By', 'Remarks'].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {data?.data?.length === 0 && (
                    <tr>
                      <td colSpan={9} className="px-4 py-8 text-center text-neutral-400 text-sm">No item movements found.</td>
                    </tr>
                  )}
                  {data?.data?.map((entry) => {
                    const qty = parseFloat(entry.quantity)
                    const isNeg = qty < 0

                    return (
                      <tr key={entry.id} className="hover:bg-neutral-50/50 transition-colors">
                        <td className="px-4 py-3 text-neutral-500 whitespace-nowrap text-xs">
                          {new Date(entry.created_at).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' })}
                        </td>
                        <td className="px-4 py-3">
                          <div className="font-mono text-neutral-900 font-medium text-xs">{entry.item?.item_code ?? `#${entry.item_id}`}</div>
                          <div className="text-neutral-500 text-xs truncate max-w-32">{entry.item?.name}</div>
                        </td>
                        <td className="px-4 py-3 text-xs text-neutral-500">{entry.location?.code ?? `#${entry.location_id}`}</td>
                        <td className="px-4 py-3">
                          <StatusBadge className={`whitespace-nowrap ${txBadge[entry.transaction_type]}`}>
                            {TX_LABELS[entry.transaction_type]}
                          </StatusBadge>
                        </td>
                        <td className={`px-4 py-3 font-semibold tabular-nums text-right ${isNeg ? 'text-red-600' : 'text-neutral-900'}`}>
                          {isNeg ? '' : '+'}{qty.toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                        </td>
                        <td className="px-4 py-3 font-semibold tabular-nums text-right text-neutral-700">
                          {parseFloat(entry.balance_after).toLocaleString('en-PH', { maximumFractionDigits: 4 })}
                        </td>
                        <td className="px-4 py-3 text-xs font-mono">
                          {entry.reference_label && entry.reference_ulid
                            ? (
                              <Link
                                to={resolveReferenceRoute(entry.reference_type, entry.reference_ulid)}
                                className="text-neutral-700 hover:text-neutral-900 underline underline-offset-2"
                              >
                                {entry.reference_label}
                              </Link>
                            )
                            : <span className="text-neutral-400">{entry.reference_label ?? '—'}</span>
                          }
                        </td>
                        <td className="px-4 py-3 text-xs text-neutral-500">{entry.created_by?.name ?? '—'}</td>
                        <td className="px-4 py-3 text-xs text-neutral-400 max-w-32 truncate">{entry.remarks ?? '—'}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </CardBody>
          </Card>

          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} entries</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
