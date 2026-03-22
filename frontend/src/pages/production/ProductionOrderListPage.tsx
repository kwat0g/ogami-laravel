import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useProductionOrders } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { DepartmentGuard } from '@/components/ui/guards'
import type { ProductionOrderStatus } from '@/types/production'

const statusBadge: Record<ProductionOrderStatus, string> = {
  draft:       'bg-neutral-100 text-neutral-600',
  released:    'bg-neutral-200 text-neutral-800',
  in_progress: 'bg-neutral-100 text-neutral-700',
  completed:   'bg-neutral-200 text-neutral-800',
  cancelled:   'bg-neutral-100 text-neutral-400',
}

export default function ProductionOrderListPage(): React.ReactElement {
  const navigate = useNavigate()
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
      <PageHeader
        title="Production Orders"
        actions={
          <DepartmentGuard module="production">
            {canCreate && (
              <Link
                to="/production/orders/new"
                className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="w-4 h-4" />
                New Order
              </Link>
            )}
          </DepartmentGuard>
        }
      />

      <div className="mb-5 flex items-center gap-3">
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Statuses</option>
          {(['draft', 'released', 'in_progress', 'completed', 'cancelled'] as ProductionOrderStatus[]).map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ')}</option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300" />
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
          <div className="bg-white border border-neutral-200 rounded overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['WO Reference', 'Product', 'Qty Required', 'Progress', 'Target Start', 'Target End', 'Status'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">No work orders found.</td>
                  </tr>
                )}
                {data?.data?.map((order) => (
                  <tr key={order.id} className="even:bg-neutral-100 hover:bg-neutral-50 cursor-pointer" onClick={() => navigate(`/production/orders/${order.ulid}`)}>
                    <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{order.po_reference}</td>
                    <td className="px-4 py-3">
                      <div className="text-xs font-mono text-neutral-400">{order.product_item?.item_code}</div>
                      <div className="text-sm text-neutral-800">{order.product_item?.name}</div>
                    </td>
                    <td className="px-4 py-3 tabular-nums font-semibold text-neutral-700">
                      {parseFloat(order.qty_required || '0').toLocaleString('en-PH', { maximumFractionDigits: 2 })}
                    </td>
                    <td className="px-4 py-3">
                      <div className="w-24">
                        <div className="flex items-center justify-between text-xs text-neutral-500 mb-1">
                          <span>{(order.progress_pct ?? 0).toFixed(0)}%</span>
                        </div>
                        <div className="h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-neutral-600 rounded-full transition-all"
                            style={{ width: `${Math.min(100, order.progress_pct ?? 0)}%` }}
                          />
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-neutral-500 text-xs">{order.target_start_date}</td>
                    <td className="px-4 py-3 text-neutral-500 text-xs">{order.target_end_date}</td>
                    <td className="px-4 py-3">
                      {order.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500 mr-1">Archived</span>}
                      {order.status === 'released' && order.mrq_pending ? (
                        <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                          Released — Pending MRQ
                        </span>
                      ) : (
                        <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${statusBadge[order.status]}`}>
                          {order.status?.replace('_', ' ') || 'Unknown'}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} orders</span>
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
