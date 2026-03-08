import { useState } from 'react'
import {
  usePendingExecutiveOvertimeRequests,
  useExecutiveApproveOvertimeRequest,
  useExecutiveRejectOvertimeRequest,
} from '@/hooks/useOvertime'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import type { OvertimeFilters } from '@/types/hr'
import { PageHeader } from '@/components/ui/PageHeader'

export default function ExecutiveOvertimeApprovalPage() {
  const [filters, setFilters] = useState<OvertimeFilters>({ per_page: 25 })

  // Approve modal state
  const [approvingId, setApprovingId] = useState<number | null>(null)
  const [approvedMins, setApprovedMins] = useState<string>('')
  const [approveRemarks, setApproveRemarks] = useState<string>('')

  // Reject modal state
  const [rejectId, setRejectId] = useState<number | null>(null)
  const [rejectRemarks, setRejectRemarks] = useState<string>('')

  const { data, isLoading, isError } = usePendingExecutiveOvertimeRequests(filters)
  const approve = useExecutiveApproveOvertimeRequest()
  const reject  = useExecutiveRejectOvertimeRequest()

  function openApprove(id: number, requestedMins: number) {
    setApprovingId(id)
    setApprovedMins(String(requestedMins))
    setApproveRemarks('')
  }

  async function submitApprove() {
    if (!approvingId) return
    await approve.mutateAsync({
      id: approvingId,
      approved_minutes: Number(approvedMins),
      remarks: approveRemarks || undefined,
    })
    setApprovingId(null)
  }

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError) {
    return (
      <div className="text-neutral-600 text-sm mt-4">
        Failed to load pending executive overtime approvals.
      </div>
    )
  }

  const rows = data?.data ?? []

  const formatDuration = (mins: number) => {
    const hours = Math.floor(mins / 60)
    const minutes = mins % 60
    return `${hours}h ${minutes}m`
  }

  return (
    <div>
      <PageHeader title="Overtime Approvals" />

      {/* Info Card */}
      <div className="bg-neutral-50 border border-neutral-200 rounded p-4 mb-6">
        <h3 className="text-sm font-medium text-neutral-900 mb-1">Approval Workflow</h3>
        <p className="text-sm text-neutral-600">
          These are overtime requests filed by Department Managers that require Executive approval.
          Staff and Supervisor OT requests are handled in Team Management via the supervisor → manager chain.
        </p>
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <input
          type="date"
          value={filters.date_from ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value || undefined, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
          placeholder="From"
        />
        <input
          type="date"
          value={filters.date_to ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value || undefined, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
          placeholder="To"
        />
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {['Employee', 'Department', 'Date', 'Requested', 'Reason', 'Status', 'Actions'].map((h) => (
                <th
                  key={h}
                  className="px-4 py-3 text-left text-xs font-semibold text-neutral-600"
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">
                  No manager OT requests pending executive approval.
                </td>
              </tr>
            )}
            {rows.map((row) => (
              <tr key={row.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                <td className="px-4 py-3 font-medium text-neutral-900">
                  {row.employee?.full_name ?? `#${row.employee_id}`}
                </td>
                <td className="px-4 py-3 text-neutral-600">
                  {row.employee?.department?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-neutral-600">{row.work_date}</td>
                <td className="px-4 py-3 text-neutral-600">{formatDuration(row.requested_minutes)}</td>
                <td className="px-4 py-3 text-neutral-600 max-w-xs truncate">{row.reason}</td>
                <td className="px-4 py-3">
                  <StatusBadge status={row.status}>{row.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                </td>
                <td className="px-4 py-3 flex gap-2">
                  <button
                    onClick={() => openApprove(row.id, row.requested_minutes)}
                    disabled={approve.isPending}
                    className="px-2 py-1 text-xs bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Approve
                  </button>
                  <button
                    onClick={() => { setRejectId(row.id); setRejectRemarks('') }}
                    disabled={approve.isPending || reject.isPending}
                    className="px-2 py-1 text-xs bg-neutral-600 text-white rounded hover:bg-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed"
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

      {/* Approve Modal */}
      {approvingId && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded p-6 w-full max-w-md border border-neutral-200">
            <h3 className="text-base font-semibold text-neutral-900 mb-4">Approve Overtime</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Approved Minutes</label>
                <input
                  type="number"
                  min={1}
                  value={approvedMins}
                  onChange={(e) => setApprovedMins(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
                <textarea
                  value={approveRemarks}
                  onChange={(e) => setApproveRemarks(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                  rows={2}
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-6">
              <button
                onClick={() => setApprovingId(null)}
                className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded"
              >
                Cancel
              </button>
              <button
                onClick={submitApprove}
                disabled={!approvedMins || approve.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Approve
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {rejectId && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded p-6 w-full max-w-md border border-neutral-200">
            <h3 className="text-base font-semibold text-neutral-900 mb-2">Reject Overtime Request</h3>
            <textarea
              value={rejectRemarks}
              onChange={(e) => setRejectRemarks(e.target.value)}
              placeholder="Enter rejection reason..."
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none mb-4"
              rows={3}
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => { setRejectId(null); setRejectRemarks('') }}
                className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  if (!rejectId) return
                  reject.mutate(
                    { id: rejectId, remarks: rejectRemarks },
                    { onSuccess: () => { setRejectId(null); setRejectRemarks('') } },
                  )
                }}
                disabled={!rejectRemarks || reject.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
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
