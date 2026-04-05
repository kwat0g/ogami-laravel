import { useState, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, Plus } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import SearchInput from '@/components/ui/SearchInput'
import { useDeliverySchedules } from '@/hooks/useProduction'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { DeliveryScheduleStatus } from '@/types/production'
import type { DeliverySchedule } from '@/types/production'

const statusBadge: Record<string, string> = {
  open:             'bg-neutral-100 text-neutral-700',
  planning:         'bg-neutral-100 text-neutral-600',
  in_production:    'bg-blue-100 text-blue-700',
  partially_ready:  'bg-amber-100 text-amber-700',
  ready:            'bg-green-100 text-green-700',
  dispatched:       'bg-purple-100 text-purple-700',
  delivered:        'bg-emerald-100 text-emerald-700',
  cancelled:        'bg-neutral-100 text-neutral-400',
}

export default function DeliveryScheduleListPage(): React.ReactElement {
  const navigate = useNavigate()
  const [status, setStatus] = useState('')
  const [type, setType] = useState('')
  const [page, setPage] = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
    setPage(1)
  }, [])

  const { data, isLoading, isError } = useDeliverySchedules({
    status: status || undefined,
    type: type || undefined,
    page,
    per_page: 20,
    with_archived: undefined,
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['delivery-schedules', 'archived', debouncedSearch],
    queryFn: () => api.get('/production/delivery-schedules', { params: { with_archived: true, search: debouncedSearch || undefined, per_page: 20 } }),
    enabled: isArchiveView,
  })
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission(PERMISSIONS.production.delivery_schedule.manage)
  const _canCreateWO = hasPermission(PERMISSIONS.production.orders.create)

  return (
    <div>
      <PageHeader
        title="Delivery Schedules"
        actions={
          <div className="flex items-center gap-2">
            {canCreate && (
              <Link
                to="/production/delivery-schedules/new"
                className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="w-4 h-4" />
                New Schedule
              </Link>
            )}
          </div>
        }
      />

      <div className="flex flex-wrap gap-3 mb-5 items-center">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search schedules..."
          className="w-64"
        />
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Statuses</option>
          {['open', 'in_production', 'ready', 'dispatched', 'delivered', 'cancelled'].map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ')}</option>
          ))}
        </select>
        <select
          value={type}
          onChange={(e) => { setType(e.target.value); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Types</option>
          <option value="local">Local</option>
          <option value="export">Export</option>
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load delivery schedules.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-neutral-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['DS Reference', 'Customer', 'Items', 'Target Date', 'Type', 'Progress', 'Status'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">No delivery schedules found.</td>
                  </tr>
                )}
          {data?.data?.map((ds: DeliverySchedule) => (
            <tr
              key={ds.id}
              onClick={() => navigate(`/production/delivery-schedules/${ds.ulid}`)}
              className="even:bg-neutral-100 hover:bg-neutral-50 cursor-pointer"
            >
              <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{ds.ds_reference}</td>
              <td className="px-4 py-3 text-neutral-600">{ds.customer?.name ?? '—'}</td>
              <td className="px-4 py-3">
                {ds.total_items > 0 ? (
                  <span className="text-sm text-neutral-700 font-medium">{ds.total_items} item{ds.total_items !== 1 ? 's' : ''}</span>
                ) : ds.product_item ? (
                  <div>
                    <div className="text-xs font-mono text-neutral-400">{ds.product_item?.item_code}</div>
                    <div className="text-sm text-neutral-800">{ds.product_item?.name}</div>
                  </div>
                ) : (
                  <span className="text-neutral-400">—</span>
                )}
              </td>
              <td className="px-4 py-3 text-neutral-500">
                {new Date(ds.target_delivery_date).toLocaleDateString('en-PH')}
              </td>
              <td className="px-4 py-3">
                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${ds.type === 'export' ? 'bg-neutral-100 text-neutral-700' : 'bg-neutral-100 text-neutral-600'}`}>
                  {ds.type}
                </span>
              </td>
              <td className="px-4 py-3">
                {ds.total_items > 0 ? (
                  <span className="text-xs text-neutral-500">{ds.ready_items}/{ds.total_items} ready</span>
                ) : '—'}
              </td>
          <td className="px-4 py-3">
            <div className="flex items-center gap-2">
              {ds.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Archived</span>}
              <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${statusBadge[ds.status]}`}>
                {ds.status?.replace('_', ' ') || 'Unknown'}
              </span>
            </div>
          </td>
            </tr>
          ))}
              </tbody>
            </table>
          </div>
          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} schedules</span>
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
