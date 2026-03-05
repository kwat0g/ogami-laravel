import { useState } from 'react'
import {
  usePendingExecutiveLeaveRequests,
  useExecutiveApproveLeaveRequest,
  useExecutiveRejectLeaveRequest,
} from '@/hooks/useLeave'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

import type { LeaveFilters } from '@/types/hr'

const YEARS = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

export default function ExecutiveLeaveApprovalPage() {
  const [filters, setFilters] = useState<LeaveFilters>({ per_page: 25 })
  const [rejectId, setRejectId] = useState<number | null>(null)
  const [remarks, setRemarks] = useState('')

  const { data, isLoading, isError } = usePendingExecutiveLeaveRequests(filters)
  const approve = useExecutiveApproveLeaveRequest()
  const reject = useExecutiveRejectLeaveRequest()

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Failed to load pending executive approvals.
      </div>
    )
  }

  const rows = data?.data ?? []

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Executive Approval</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {data?.meta?.total ?? 0} manager requests pending approval
            <span className="ml-2 text-xs text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full">
              Executive Only
            </span>
          </p>
        </div>
      </div>

      {/* Info Card */}
      <div className="bg-purple-50 border border-purple-200 rounded-xl p-4 mb-6">
        <h3 className="text-sm font-medium text-purple-900 mb-1">Approval Workflow</h3>
        <p className="text-sm text-purple-700">
          These are leave requests filed by Department Managers that require Executive approval.
          Staff and Supervisor requests are handled in Team Management.
        </p>
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.year ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              year: e.target.value ? Number(e.target.value) : undefined,
              page: 1,
            }))
          }
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-purple-500 outline-none"
        >
          <option value="">All Years</option>
          {YEARS.map((y) => (
            <option key={y} value={y}>
              {y}
            </option>
          ))}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              {[
                'Employee',
                'Department',
                'Leave Type',
                'From',
                'To',
                'Days',
                'Requester Role',
                'Actions',
              ].map((h) => (
                <th
                  key={h}
                  className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {rows.length === 0 && (
              <tr>
                <td colSpan={8} className="px-4 py-8 text-center text-gray-400">
                  No manager requests pending executive approval.
                </td>
              </tr>
            )}
            {rows.map((row) => (
              <tr key={row.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium text-gray-900">
                  {row.employee?.full_name ?? `#${row.employee_id}`}
                </td>
                <td className="px-4 py-3 text-gray-600">
                  {row.employee?.department?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-gray-600">
                  {row.leave_type?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-gray-600">{row.date_from}</td>
                <td className="px-4 py-3 text-gray-600">{row.date_to}</td>
                <td className="px-4 py-3 text-gray-600">{row.total_days}</td>
                <td className="px-4 py-3">
                  <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    Manager
                  </span>
                </td>
                <td className="px-4 py-3 flex gap-2">
                  <button
                    onClick={() => approve.mutate({ id: row.id })}
                    disabled={approve.isPending}
                    className="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                  >
                    Approve
                  </button>
                  <button
                    onClick={() => setRejectId(row.id)}
                    disabled={approve.isPending || reject.isPending}
                    className="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                  >
                    Reject
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {data?.meta && data.meta.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm text-gray-600">
          <span>
            Page {data.meta.current_page} of {data.meta.last_page}
          </span>
          <div className="flex gap-2">
            <button
              disabled={data.meta.current_page <= 1}
              onClick={() =>
                setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))
              }
              className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50"
            >
              Previous
            </button>
            <button
              disabled={data.meta.current_page >= data.meta.last_page}
              onClick={() =>
                setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))
              }
              className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {rejectId && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 mb-2">
              Reject Leave Request
            </h3>
            <textarea
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              placeholder="Enter rejection reason..."
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none mb-4"
              rows={3}
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setRejectId(null)
                  setRemarks('')
                }}
                className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  if (!rejectId) return
                  reject.mutate(
                    { id: rejectId, remarks },
                    { onSuccess: () => { setRejectId(null); setRemarks('') } },
                  )
                }}
                disabled={!remarks || reject.isPending}
                className="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
