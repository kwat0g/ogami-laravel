import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PackageCheck, Plus, AlertTriangle } from 'lucide-react'
import { useGoodsReceipts } from '@/hooks/useGoodsReceipts'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useAuthStore } from '@/stores/authStore'
import type { GoodsReceiptStatus } from '@/types/procurement'

const statusBadge: Record<GoodsReceiptStatus, string> = {
  draft:     'bg-gray-100 text-gray-600',
  confirmed: 'bg-green-100 text-green-700',
}

export default function GoodsReceiptListPage(): React.ReactElement {
  const [statusFilter, setStatusFilter] = useState<GoodsReceiptStatus | ''>('')
  const [page, setPage] = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('procurement.goods-receipt.create')

  const { data, isLoading, isError } = useGoodsReceipts({
    ...(statusFilter ? { status: statusFilter } : {}),
    page,
    with_archived: withArchived || undefined,
  })

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
            <PackageCheck className="w-5 h-5 text-green-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Goods Receipts</h1>
            <p className="text-sm text-gray-500 mt-0.5">Track received deliveries from vendors</p>
          </div>
        </div>
        {canCreate && (
          <Link
            to="/procurement/goods-receipts/new"
            className="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-xl transition-colors"
          >
            <Plus className="w-4 h-4" />
            Record Receipt
          </Link>
        )}
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 mb-5">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value as GoodsReceiptStatus | ''); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"
        >
          <option value="">All Statuses</option>
          {(['draft', 'confirmed'] as GoodsReceiptStatus[]).map((s) => (
            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-green-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {/* Table */}
      {isLoading && <SkeletonLoader rows={6} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" />
          Failed to load goods receipts.
        </div>
      )}
      {!isLoading && !isError && (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['GR Number', 'PO Number', 'Received Date', 'Status', 'Confirmed By', ''].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data?.data?.length === 0 && (
                <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-gray-400 text-sm">
                    No goods receipts found.
                  </td>
                </tr>
              )}
              {data?.data?.map((gr) => (
                <tr key={gr.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-blue-700 font-medium">{gr.gr_reference}</td>
                  <td className="px-4 py-3 text-gray-600">{gr.purchase_order?.po_reference ?? `#${gr.purchase_order_id}`}</td>
                  <td className="px-4 py-3 text-gray-500">
                    {gr.received_date ? new Date(gr.received_date).toLocaleDateString('en-PH') : '—'}
                  </td>
                  <td className="px-4 py-3">
                    {gr.deleted_at && <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${statusBadge[gr.status]}`}>
                      {gr.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{gr.confirmed_by?.name ?? '—'}</td>
                  <td className="px-4 py-3">
                    <Link to={`/procurement/goods-receipts/${gr.ulid}`} className="text-xs text-blue-600 hover:text-blue-800 font-medium">
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {/* Pagination */}
          {data?.meta && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-sm text-gray-500">
              <span>Page {data.meta.current_page} of {data.meta.last_page}</span>
              <div className="flex gap-2">
                <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1 border rounded-lg disabled:opacity-40">Prev</button>
                <button disabled={page === data.meta.last_page} onClick={() => setPage(p => p + 1)} className="px-3 py-1 border rounded-lg disabled:opacity-40">Next</button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
