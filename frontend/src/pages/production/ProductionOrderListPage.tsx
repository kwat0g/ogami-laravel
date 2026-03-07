import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Factory, AlertTriangle, Plus } from 'lucide-react'
import { useProductionOrders } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { ProductionOrderStatus } from '@/types/production'

const statusBadge: Record<ProductionOrderStatus, string> = {
  draft:       'bg-gray-100 text-gray-600',
  released:    'bg-blue-100 text-blue-700',
  in_progress: 'bg-amber-100 text-amber-700',
  completed:   'bg-green-100 text-green-700',
  cancelled:   'bg-gray-100 text-gray-400',
}

export default function ProductionOrderListPage(): React.ReactElement {
  const [status, setStatus] = useState('')
  const [page, setPage]     = useState(1)
  const [withArchived, setWithArchived] = useState(false)

  const { data, isLoading, isError } = useProductionOrders({
    status: status || undefined,
    page,
    per_page: 20,
    with_archived: withArchived || undefined,
  })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('production.orders.create')

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center">
            <Factory className="w-5 h-5 text-violet-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Work Orders</h1>
            <p className="text-sm text-gray-500 mt-0.5">Production orders and manufacturing runs</p>
          </div>
        </div>
        {canCreate && (
          <Link
            to="/production/orders/new"
            className="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            New Order
          </Link>
        )}
      </div>

      <div className="mb-5">
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
        >
          <option value="">All Statuses</option>
          {(['draft', 'released', 'in_progress', 'completed', 'cancelled'] as ProductionOrderStatus[]).map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ')}</option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-violet-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load work orders.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['WO Reference', 'Product', 'Qty Required', 'Progress', 'Target Start', 'Target End', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-4 py-8 text-center text-gray-400 text-sm">No work orders found.</td>
                  </tr>
                )}
                {data?.data?.map((order) => (
                  <tr key={order.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-violet-700 font-medium">{order.po_reference}</td>
                    <td className="px-4 py-3">
                      <div className="text-xs font-mono text-gray-400">{order.product_item?.item_code}</div>
                      <div className="text-sm text-gray-800">{order.product_item?.name}</div>
                    </td>
                    <td className="px-4 py-3 tabular-nums font-semibold text-gray-700">
                      {parseFloat(order.qty_required).toLocaleString('en-PH', { maximumFractionDigits: 2 })}
                    </td>
                    <td className="px-4 py-3">
                      <div className="w-24">
                        <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                          <span>{order.progress_pct.toFixed(0)}%</span>
                        </div>
                        <div className="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-violet-500 rounded-full transition-all"
                            style={{ width: `${Math.min(100, order.progress_pct)}%` }}
                          />
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-gray-500 text-xs">{order.target_start_date}</td>
                    <td className="px-4 py-3 text-gray-500 text-xs">{order.target_end_date}</td>
                    <td className="px-4 py-3">
                      {order.deleted_at && <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${statusBadge[order.status]}`}>
                        {order.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/production/orders/${order.ulid}`} className="text-xs text-violet-600 hover:text-violet-800 font-medium">
                        View →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} orders</span>
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
