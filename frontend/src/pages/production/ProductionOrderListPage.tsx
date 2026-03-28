import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus, RotateCcw, Trash2 } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import Pagination from '@/components/ui/Pagination'
import { useProductionOrders } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { DepartmentGuard } from '@/components/ui/guards'
import { ExportButton } from '@/components/ui/ExportButton'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveEmptyState from '@/components/ui/ArchiveEmptyState'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import api from '@/lib/api'
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
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])

  const { data, isLoading, isError, refetch } = useProductionOrders({
    status: status || undefined,
    page,
    per_page: 20,
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['production-orders', 'archived', debouncedSearch],
    queryFn: () => api.get('/production/orders-archived', { params: { search: debouncedSearch || undefined, per_page: 20 } }),
    enabled: isArchiveView,
  })

  const currentData = isArchiveView ? (archivedData?.data?.data ?? []) : (data?.data ?? [])
  const currentLoading = isArchiveView ? archivedLoading : isLoading
  const isSuperAdmin = useAuthStore(s => s.user?.roles?.some((r: { name: string }) => r.name === 'super_admin'))
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('production.orders.create')

  return (
    <div>
      <PageHeader
        title="Production Orders"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'po_reference', label: 'Reference' },
                { key: 'product_item.name', label: 'Product' },
                { key: 'qty_required', label: 'Required' },
                { key: 'qty_produced', label: 'Produced' },
                { key: 'status', label: 'Status' },
                { key: 'target_start_date', label: 'Start Date' },
                { key: 'target_end_date', label: 'End Date' },
              ]}
              filename="production-orders"
            />
            {/* Production orders are created from delivery schedules */}
          </div>
        }
      />

      <div className="mb-5 flex flex-wrap items-center gap-3">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search production orders..."
          className="w-64"
        />
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
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isArchiveView && <ArchiveViewBanner />}

      {currentLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load work orders.
        </div>
      )}

      {!currentLoading && !isError && (
        <>
          {currentData.length === 0 ? (
            <ArchiveEmptyState isArchiveView={isArchiveView} recordLabel="production orders" />
          ) : (
          <div className="bg-white border border-neutral-200 rounded overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {(isArchiveView
                    ? ['WO Reference', 'Product', 'Status', 'Archived On', '']
                    : ['WO Reference', 'Product', 'Qty Required', 'Progress', 'Target Start', 'Target End', 'Status']
                  ).map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {currentData.map((order: any) => (
                  <tr key={order.id} className={`even:bg-neutral-100 hover:bg-neutral-50 ${isArchiveView ? '' : 'cursor-pointer'}`} onClick={() => !isArchiveView && navigate(`/production/orders/${order.ulid}`)}>
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
          )}
          {!isArchiveView && data && data.meta.last_page > 1 && (
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
