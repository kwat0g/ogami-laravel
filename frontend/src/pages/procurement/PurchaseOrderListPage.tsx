import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ShoppingCart, Plus, AlertTriangle } from 'lucide-react'
import { usePurchaseOrders } from '@/hooks/usePurchaseOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useAuthStore } from '@/stores/authStore'
import type { PurchaseOrderStatus } from '@/types/procurement'

const statusBadge: Record<PurchaseOrderStatus, string> = {
  draft:              'bg-gray-100 text-gray-600',
  sent:               'bg-purple-100 text-purple-700',
  partially_received: 'bg-yellow-100 text-yellow-700',
  fully_received:     'bg-green-100 text-green-700',
  closed:             'bg-blue-100 text-blue-700',
  cancelled:          'bg-red-100 text-red-700',
}

export default function PurchaseOrderListPage(): React.ReactElement {
  const [statusFilter, setStatusFilter] = useState<PurchaseOrderStatus | ''>('')
  const [page, setPage] = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('procurement.purchase-order.create')

  const { data, isLoading, isError } = usePurchaseOrders({
    ...(statusFilter ? { status: statusFilter } : {}),
    page,
    with_archived: withArchived || undefined,
  })

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
            <ShoppingCart className="w-5 h-5 text-purple-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Purchase Orders</h1>
            <p className="text-sm text-gray-500 mt-0.5">Manage vendor purchase orders</p>
          </div>
        </div>
        {canCreate && (
          <Link
            to="/procurement/purchase-orders/new"
            className="flex items-center gap-2 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-xl transition-colors"
          >
            <Plus className="w-4 h-4" />
            Create PO
          </Link>
        )}
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 mb-5">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value as PurchaseOrderStatus | ''); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white"
        >
          <option value="">All Statuses</option>
          {(['draft', 'sent', 'partially_received', 'fully_received', 'closed', 'cancelled'] as PurchaseOrderStatus[]).map((s) => (
            <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase())}</option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-purple-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {/* Table */}
      {isLoading && <SkeletonLoader rows={6} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" />
          Failed to load purchase orders.
        </div>
      )}
      {!isLoading && !isError && (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['PO Number', 'Vendor', 'PR Reference', 'Total Amount', 'Status', 'Created', ''].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data?.data?.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-gray-400 text-sm">
                    No purchase orders found.
                  </td>
                </tr>
              )}
              {data?.data?.map((po) => (
                <tr key={po.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono text-blue-700 font-medium">{po.po_reference}</td>
                  <td className="px-4 py-3 text-gray-700">{po.vendor?.name ?? `#${po.vendor_id}`}</td>
                  <td className="px-4 py-3 text-gray-600 font-mono text-xs">{po.purchase_request?.pr_reference ?? '—'}</td>
                  <td className="px-4 py-3 font-medium text-gray-800">
                    ₱{Number(po.total_po_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3">
                    {po.deleted_at && <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${statusBadge[po.status] ?? 'bg-gray-100 text-gray-600'}`}>
                      {po.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{new Date(po.created_at).toLocaleDateString('en-PH')}</td>
                  <td className="px-4 py-3">
                    <Link to={`/procurement/purchase-orders/${po.ulid}`} className="text-xs text-blue-600 hover:text-blue-800 font-medium">
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
