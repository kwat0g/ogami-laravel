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
  draft:          'bg-gray-100 text-gray-600',
  submitted:      'bg-blue-100 text-blue-700',
  noted:          'bg-indigo-100 text-indigo-700',
  checked:        'bg-violet-100 text-violet-700',
  reviewed:       'bg-amber-100 text-amber-700',
  approved:       'bg-green-100 text-green-700',
  rejected:       'bg-red-100 text-red-700',
  cancelled:      'bg-gray-100 text-gray-400',
  converted_to_po: 'bg-teal-100 text-teal-700',
}

const urgencyBadgeClass: Record<PurchaseRequestUrgency, string> = {
  normal:   'bg-gray-100 text-gray-600',
  urgent:   'bg-orange-100 text-orange-700',
  critical: 'bg-red-100 text-red-700',
}

export default function PurchaseRequestListPage(): React.ReactElement {
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('procurement.purchase-request.create')

  const [filters, setFilters] = useState<PurchaseRequestFilters>({ per_page: 25 })

  const { data, isLoading, isError } = usePurchaseRequests(filters)

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
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Purchase Requests</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {data?.meta?.total ?? 0} records
          </p>
        </div>
        {canCreate && (
          <Link
            to="/procurement/purchase-requests/new"
            className="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            <Plus className="w-4 h-4" />
            New Request
          </Link>
        )}
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        <select
          className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
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
          className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
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
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              {['PR Reference', 'Department', 'Urgency', 'Total Est. Cost', 'Status', 'Submitted By', 'Date', ''].map(
                (h) => (
                  <th
                    key={h}
                    className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"
                  >
                    {h}
                  </th>
                ),
              )}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {data?.data?.length === 0 && (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-gray-400 text-sm">
                  No purchase requests found.
                </td>
              </tr>
            )}
            {data?.data?.map((pr) => (
              <tr key={pr.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 font-mono text-blue-700 font-medium">
                  {pr.pr_reference}
                </td>
                <td className="px-4 py-3 text-gray-700">
                  Dept #{pr.department_id}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${urgencyBadgeClass[pr.urgency]}`}
                  >
                    {pr.urgency}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-700 font-medium">
                  ₱{Number(pr.total_estimated_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass[pr.status]}`}
                  >
                    {pr.status.replace(/_/g, ' ')}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-600">
                  {pr.requested_by?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-gray-500">
                  {new Date(pr.created_at).toLocaleDateString('en-PH')}
                </td>
                <td className="px-4 py-3 text-right">
                  <Link
                    to={`/procurement/purchase-requests/${pr.ulid}`}
                    className="text-blue-600 hover:text-blue-800 text-xs font-medium"
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
          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-200">
            <span className="text-sm text-gray-500">
              Page {data.meta.current_page} of {data.meta.last_page}
            </span>
            <div className="flex gap-2">
              <button
                disabled={data.meta.current_page <= 1}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                className="text-sm px-3 py-1 rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="text-sm px-3 py-1 rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50"
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
