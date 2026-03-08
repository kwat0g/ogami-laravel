import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle, Plus } from 'lucide-react'
import { usePurchaseRequests } from '@/hooks/usePurchaseRequests'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type {
  PurchaseRequestFilters,
  PurchaseRequestStatus,
  PurchaseRequestUrgency,
} from '@/types/procurement'

const STATUSES: PurchaseRequestStatus[] = [
  'draft', 'submitted', 'noted', 'checked', 'reviewed',
  'approved', 'rejected', 'cancelled', 'converted_to_po',
]

const URGENCIES: PurchaseRequestUrgency[] = ['normal', 'urgent', 'critical']

const statusBadgeClass: Record<PurchaseRequestStatus, string> = {
  draft:          'bg-neutral-100 text-neutral-600',
  submitted:      'bg-neutral-100 text-neutral-700',
  noted:          'bg-neutral-100 text-neutral-700',
  checked:        'bg-neutral-100 text-neutral-700',
  reviewed:       'bg-neutral-100 text-neutral-700',
  approved:       'bg-neutral-200 text-neutral-800',
  rejected:       'bg-neutral-100 text-neutral-400',
  cancelled:      'bg-neutral-100 text-neutral-400',
  converted_to_po: 'bg-neutral-200 text-neutral-800',
}

const urgencyBadgeClass: Record<PurchaseRequestUrgency, string> = {
  normal:   'bg-neutral-100 text-neutral-600',
  urgent:   'bg-orange-100 text-orange-700',
  critical: 'bg-red-100 text-red-700',
}

export default function PurchaseRequestListPage(): React.ReactElement {
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('procurement.purchase-request.create')

  const [filters, setFilters] = useState<PurchaseRequestFilters>({ per_page: 25 })
  const [withArchived, setWithArchived] = useState(false)

  const { data, isLoading, isError } = usePurchaseRequests({ ...filters, with_archived: withArchived || undefined })

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="flex items-center gap-2 text-red-600 text-sm mt-4">
        <AlertTriangle className="w-4 h-4" />
        Failed to load purchase requests. Please try again.
      </div>
    )
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">Purchase Requests</h1>
        {canCreate && (
          <Link
            to="/procurement/purchase-requests/new"
            className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
          >
            <Plus className="w-4 h-4" />
            New Request
          </Link>
        )}
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <select
          className="text-sm border border-neutral-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
          value={filters.status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              status: (e.target.value as PurchaseRequestStatus) || undefined,
              page: 1,
            }))
          }
        >
          <option value="">All Statuses</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replace(/_/g, ' ')}
            </option>
          ))}
        </select>

        <select
          className="text-sm border border-neutral-300 rounded px-3 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
          value={filters.urgency ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              urgency: (e.target.value as PurchaseRequestUrgency) || undefined,
              page: 1,
            }))
          }
        >
          <option value="">All Urgencies</option>
          {URGENCIES.map((u) => (
            <option key={u} value={u}>
              {u.charAt(0).toUpperCase() + u.slice(1)}
            </option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input type="checkbox" checked={withArchived} onChange={(e) => setWithArchived(e.target.checked)} className="rounded border-neutral-300" />
          <span>Show Archived</span>
        </label>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {['PR Reference', 'Department', 'Urgency', 'Total Est. Cost', 'Status', 'Submitted By', 'Date', ''].map(
                (h) => (
                  <th
                    key={h}
                    className="px-4 py-3 text-left text-xs font-medium text-neutral-600"
                  >
                    {h}
                  </th>
                ),
              )}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {data?.data?.length === 0 && (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-neutral-400 text-sm">
                  No purchase requests found.
                </td>
              </tr>
            )}
            {data?.data?.map((pr) => (
              <tr key={pr.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-4 py-3 font-mono text-neutral-900 font-medium">
                  {pr.pr_reference}
                </td>
                <td className="px-4 py-3 text-neutral-700">
                  Dept #{pr.department_id}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${urgencyBadgeClass[pr.urgency]}`}
                  >
                    {pr.urgency}
                  </span>
                </td>
                <td className="px-4 py-3 text-neutral-700 font-medium">
                  ₱{Number(pr.total_estimated_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </td>
                <td className="px-4 py-3">
                  {pr.deleted_at && <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500 mr-1">Archived</span>}
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusBadgeClass[pr.status]}`}
                  >
                    {pr.status.replace(/_/g, ' ')}
                  </span>
                </td>
                <td className="px-4 py-3 text-neutral-600">
                  {pr.requested_by?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-neutral-500">
                  {new Date(pr.created_at).toLocaleDateString('en-PH')}
                </td>
                <td className="px-4 py-3 text-right">
                  <Link
                    to={`/procurement/purchase-requests/${pr.ulid}`}
                    className="text-neutral-700 hover:text-neutral-900 text-xs font-medium"
                  >
                    View
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {/* Pagination */}
        {data?.meta && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-neutral-200">
            <span className="text-sm text-neutral-500">
              Page {data.meta.current_page} of {data.meta.last_page}
            </span>
            <div className="flex gap-2">
              <button
                disabled={data.meta.current_page <= 1}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                className="text-sm px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 hover:bg-neutral-50"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="text-sm px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 hover:bg-neutral-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
