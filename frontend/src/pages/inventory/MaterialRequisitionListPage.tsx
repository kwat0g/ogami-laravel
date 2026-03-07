import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ClipboardList, Plus, AlertTriangle } from 'lucide-react'
import { useMaterialRequisitions } from '@/hooks/useInventory'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { MaterialRequisitionStatus } from '@/types/inventory'

const statusBadge: Record<MaterialRequisitionStatus, string> = {
  draft:      'bg-gray-100 text-gray-600',
  submitted:  'bg-blue-100 text-blue-700',
  noted:      'bg-indigo-100 text-indigo-700',
  checked:    'bg-purple-100 text-purple-700',
  reviewed:   'bg-orange-100 text-orange-700',
  approved:   'bg-green-100 text-green-700',
  rejected:   'bg-red-100 text-red-700',
  cancelled:  'bg-gray-100 text-gray-400',
  fulfilled:  'bg-teal-100 text-teal-700',
}

const ALL_STATUSES: MaterialRequisitionStatus[] = [
  'draft', 'submitted', 'noted', 'checked', 'reviewed', 'approved', 'rejected', 'cancelled', 'fulfilled',
]

export default function MaterialRequisitionListPage(): React.ReactElement {
  const [status, setStatus] = useState<MaterialRequisitionStatus | ''>('')
  const [page, setPage]     = useState(1)
  const [withArchived, setWithArchived] = useState(false)
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('inventory.mrq.create')

  const { data, isLoading, isError } = useMaterialRequisitions({
    status: status || undefined,
    page,
    per_page: 20,
    with_archived: withArchived || undefined,
  })

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
            <ClipboardList className="w-5 h-5 text-teal-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Material Requisitions</h1>
            <p className="text-sm text-gray-500 mt-0.5">Request and track material issuances</p>
          </div>
        </div>
        {canCreate && (
          <Link
            to="/inventory/requisitions/new"
            className="flex items-center gap-2 px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl transition-colors"
          >
            <Plus className="w-4 h-4" /> New Requisition
          </Link>
        )}
      </div>

      {/* Filter */}
      <div className="mb-5">
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value as MaterialRequisitionStatus | ''); setPage(1) }}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
        >
          <option value="">All Statuses</option>
          {ALL_STATUSES.map((s) => (
            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-gray-300 text-teal-600" />
          <span>Show Archived</span>
        </label>
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load requisitions.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['MR Reference', 'Department', 'Purpose', 'Status', 'Requested By', 'Date', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-gray-400 text-sm">No requisitions found.</td>
                  </tr>
                )}
                {data?.data?.map((mrq) => (
                  <tr key={mrq.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono text-teal-700 font-medium">{mrq.mr_reference}</td>
                    <td className="px-4 py-3 text-gray-600">{mrq.department?.name ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-500 max-w-xs truncate">{mrq.purpose}</td>
                    <td className="px-4 py-3">
                      {mrq.deleted_at && <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 mr-1">Archived</span>}
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${statusBadge[mrq.status]}`}>
                        {mrq.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-500">{mrq.requested_by?.name ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-400 text-xs">
                      {mrq.created_at ? new Date(mrq.created_at).toLocaleDateString('en-PH') : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/inventory/requisitions/${mrq.ulid}`} className="text-xs text-teal-600 hover:text-teal-800 font-medium">
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
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} requisitions</span>
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
