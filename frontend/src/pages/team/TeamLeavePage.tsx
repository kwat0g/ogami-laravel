import { useState } from 'react'
import { useAuthStore } from '@/stores/authStore'
import {
  useTeamLeaveRequests,
  useHeadApproveLeaveRequest,
  useManagerCheckLeaveRequest,
  useRejectLeaveRequest,
} from '@/hooks/useLeave'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { parseApiError } from '@/lib/errorHandler'
import { toast } from 'sonner'
import type { LeaveFilters, LeaveRequest } from '@/types/hr'

const YEARS = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

export default function TeamLeavePage() {
  const { hasPermission } = useAuthStore()
  const canHeadApprove = hasPermission('leaves.head_approve')
  const canManagerCheck = hasPermission('leaves.manager_check')

  const [filters, setFilters] = useState<LeaveFilters>({ per_page: 25 })
  const [rejectId, setRejectId] = useState<number | null>(null)
  const [remarks, setRemarks] = useState('')

  const { data, isLoading, isError } = useTeamLeaveRequests(filters)
  const headApprove = useHeadApproveLeaveRequest()
  const managerCheck = useManagerCheckLeaveRequest()
  const reject = useRejectLeaveRequest()

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError)   return <div className="text-neutral-700 text-sm mt-4">Failed to load team leave requests.</div>

  const rows = data?.data ?? []

  // Get action buttons based on request status and user role
  const getActionButtons = (row: LeaveRequest) => {
    const buttons = []

    // Step 2: Dept Head approves submitted requests
    if (canHeadApprove && row.status === 'submitted') {
      buttons.push(
        <button
          key="head-approve"
          onClick={() => headApprove.mutate({ id: row.id }, {
            onSuccess: () => toast.success('Request approved by dept head.'),
            onError: (err) => toast.error(parseApiError(err).message),
          })}
          disabled={headApprove.isPending}
          className="px-2.5 py-1 text-xs font-medium bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Approve
        </button>
      )
      buttons.push(
        <button
          key="head-reject"
          onClick={() => setRejectId(row.id)}
          disabled={headApprove.isPending || reject.isPending}
          className="px-2.5 py-1 text-xs font-medium border border-neutral-300 text-neutral-600 rounded hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Reject
        </button>
      )
    }

    // Step 3: Plant Manager checks head-approved requests
    if (canManagerCheck && row.status === 'head_approved') {
      buttons.push(
        <button
          key="manager-check"
          onClick={() => managerCheck.mutate({ id: row.id }, {
            onSuccess: () => toast.success('Request checked by plant manager.'),
            onError: (err) => toast.error(parseApiError(err).message),
          })}
          disabled={managerCheck.isPending}
          className="px-2.5 py-1 text-xs font-medium bg-neutral-800 text-white rounded-md hover:bg-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Check
        </button>
      )
      buttons.push(
        <button
          key="manager-reject"
          onClick={() => setRejectId(row.id)}
          disabled={managerCheck.isPending || reject.isPending}
          className="px-2.5 py-1 text-xs font-medium border border-neutral-300 text-neutral-600 rounded hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Reject
        </button>
      )
    }

    return buttons
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">Team Leave Requests</h1>
      </div>
      <p className="text-sm text-neutral-500 mb-4">
        {data?.meta?.total ?? 0} records
        <span className="ml-2 text-xs text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded">
          Department Only
        </span>
      </p>

      {/* Workflow Info */}
      <div className="bg-neutral-50 border border-neutral-200 rounded p-4 mb-6">
        <h3 className="text-sm font-medium text-neutral-900 mb-2">Approval Workflow (AD-084-00)</h3>
        <ul className="text-sm text-neutral-700 space-y-1">
          <li>• Step 2: Dept Head approves submitted requests</li>
          <li>• Step 3: Plant Manager checks head-approved requests</li>
          <li>• Step 4: GA Officer processes manager-checked requests</li>
          <li>• Step 5: VP notes GA-processed requests (final approval)</li>
        </ul>
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.status ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value as LeaveFilters['status'] || undefined, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none focus:border-neutral-400"
        >
          <option value="">All Statuses</option>
          {['submitted', 'head_approved', 'manager_checked', 'ga_processed', 'approved', 'rejected', 'cancelled'].map((s) => (
            <option key={s} value={s}>{s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>
          ))}
        </select>

        <select
          value={filters.year ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, year: e.target.value ? Number(e.target.value) : undefined, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Years</option>
          {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {['Employee', 'Leave Type', 'From', 'To', 'Days', 'Reason', 'Status', 'Actions'].map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr><td colSpan={8} className="px-3 py-8 text-center text-neutral-400">No leave requests found.</td></tr>
            )}
            {rows.map((row) => (
              <tr key={row.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-2 font-medium text-neutral-900">{row.employee?.full_name ?? `#${row.employee_id}`}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.leave_type?.name ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.date_from}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.date_to}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.total_days}</td>
                  <td className="px-3 py-2 text-neutral-600">
                    <div className="w-40 truncate text-xs text-neutral-600" title={row.reason || undefined}>
                      {row.reason || '—'}
                    </div>
                  </td>
                <td className="px-3 py-2"><StatusBadge status={row.status}>{row.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge></td>
                <td className="px-3 py-2 flex gap-2">
                  {getActionButtons(row)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {data?.meta && data.meta.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm text-neutral-600">
          <span>Page {data.meta.current_page} of {data.meta.last_page}</span>
          <div className="flex gap-2">
            <button
              disabled={data.meta.current_page <= 1}
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
              className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50"
            >
              Previous
            </button>
            <button
              disabled={data.meta.current_page >= data.meta.last_page}
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
              className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {rejectId && (
        <div className="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded w-full max-w-md border border-neutral-200">
            <div className="px-6 pt-5 pb-4">
              <h3 className="text-[15px] font-semibold text-neutral-900 mb-1">Reject Leave Request</h3>
              <p className="text-sm text-neutral-500 mb-4">Please provide a reason for rejection. The employee will be notified.</p>
              <textarea
                value={remarks}
                onChange={(e) => setRemarks(e.target.value)}
                placeholder="Enter rejection reason…"
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none"
                rows={3}
                autoFocus
              />
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 border-t border-neutral-100 bg-neutral-50">
              <button
                onClick={() => { setRejectId(null); setRemarks('') }}
                className="px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  if (!rejectId) return
                  reject.mutate(
                    { id: rejectId, remarks },
                    {
                      onSuccess: () => { toast.success('Leave request rejected.'); setRejectId(null); setRemarks('') },
                      onError: (err) => toast.error(parseApiError(err).message),
                    }
                  )
                }}
                disabled={!remarks.trim() || reject.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {reject.isPending ? 'Rejecting…' : 'Reject'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
