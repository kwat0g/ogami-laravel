import { useState } from 'react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import {
  useTeamOvertimeRequests,
  useApproveOvertimeRequest,
  useRejectOvertimeRequest,
} from '@/hooks/useOvertime'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { SodActionButton } from '@/components/ui/SodActionButton'
import type { OvertimeFilters } from '@/types/hr'

export default function TeamOvertimePage() {
  const { hasPermission } = useAuthStore()
  const canApprove = hasPermission('overtime.approve')

  const [filters, setFilters] = useState<OvertimeFilters>({ per_page: 25 })

  // Approve modal state
  const [approvingId, setApprovingId] = useState<number | null>(null)
  const [approvedMins, setApprovedMins] = useState<string>('')
  const [approveRemarks, setApproveRemarks] = useState<string>('')

  // Reject modal state
  const [rejectId, setRejectId] = useState<number | null>(null)
  const [rejectRemarks, setRejectRemarks] = useState<string>('')

  // Validation state
  const [touched, setTouched] = useState<Record<string, boolean>>({})

  const { data, isLoading, isError } = useTeamOvertimeRequests(filters)
  const approve = useApproveOvertimeRequest()
  const reject = useRejectOvertimeRequest()

  function openApprove(id: number, requestedMins: number) {
    setApprovingId(id)
    setApprovedMins(String(requestedMins))
    setApproveRemarks('')
    setTouched(prev => ({ ...prev, approve: false }))
  }

  // Validation
  const approvedMinsError = touched.approve && (!approvedMins || Number(approvedMins) < 1)
    ? 'Approved minutes must be at least 1.'
    : undefined
  const rejectRemarksError = touched.reject && !rejectRemarks.trim()
    ? 'Rejection reason is required.'
    : undefined

  async function submitApprove() {
    setTouched(prev => ({ ...prev, approve: true }))
    if (!approvingId || !approvedMins || Number(approvedMins) < 1) {
      toast.error('Please enter valid approved minutes.')
      return
    }
    try {
      await approve.mutateAsync({
        id: approvingId,
        approved_minutes: Number(approvedMins),
        remarks: approveRemarks || undefined,
      })
      toast.success('Overtime request approved successfully.')
      setApprovingId(null)
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to approve overtime request.'))
    }
  }

  async function submitReject() {
    setTouched(prev => ({ ...prev, reject: true }))
    if (!rejectId || !rejectRemarks.trim()) {
      toast.error('Please provide a rejection reason.')
      return
    }
    try {
      await reject.mutateAsync({ id: rejectId, remarks: rejectRemarks })
      toast.success('Overtime request rejected successfully.')
      setRejectId(null)
      setRejectRemarks('')
      setTouched(prev => ({ ...prev, reject: false }))
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to reject overtime request.'))
    }
  }

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError)   return <div className="text-neutral-600 text-sm mt-4">Failed to load overtime requests.</div>

  const rows = data?.data ?? []

  // Format minutes to hours and minutes
  const formatDuration = (mins: number) => {
    const hours = Math.floor(mins / 60)
    const minutes = mins % 60
    return `${hours}h ${minutes}m`
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">Team Overtime</h1>
      </div>
      <p className="text-sm text-neutral-500 mb-4">
        {data?.meta?.total ?? 0} records
        <span className="ml-2 text-xs text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded">
          Department Only
        </span>
      </p>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              status: (e.target.value as OvertimeFilters['status']) || undefined,
              page: 1,
            }))
          }
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none focus:border-neutral-400"
        >
          <option value="">All Statuses</option>
          {['pending', 'approved', 'rejected', 'cancelled'].map((s) => (
            <option key={s} value={s}>
              {s.charAt(0).toUpperCase() + s.slice(1)}
            </option>
          ))}
        </select>

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
              {['Employee', 'Date', 'Requested', 'Status', 'Actions'].map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr>
                <td colSpan={5} className="px-3 py-8 text-center text-neutral-400">
                  No overtime requests found.
                </td>
              </tr>
            )}
            {rows.map((row) => (
              <tr key={row.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-2 font-medium text-neutral-900">
                  {row.employee?.full_name ?? `#${row.employee_id}`}
                </td>
                <td className="px-3 py-2 text-neutral-600">{row.work_date}</td>
                <td className="px-3 py-2 text-neutral-600">{formatDuration(row.requested_minutes)}</td>
                <td className="px-3 py-2">
                  <StatusBadge status={row.status}>{row.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                </td>
                <td className="px-3 py-2 flex gap-2">
                  {canApprove && row.status === 'pending' && (
                    <>
                      <SodActionButton
                        initiatedById={row.created_by_id}
                        label="Approve"
                        onClick={() => openApprove(row.id, row.requested_minutes)}
                        isLoading={approve.isPending || reject.isPending}
                        variant="success"
                      />
                      <SodActionButton
                        initiatedById={row.created_by_id}
                        label="Reject"
                        onClick={() => { setRejectId(row.id); setRejectRemarks(''); setTouched(prev => ({ ...prev, reject: false })) }}
                        isLoading={approve.isPending || reject.isPending}
                        variant="danger"
                      />
                    </>
                  )}
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
          <div className="bg-white rounded p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-neutral-900 mb-4">Approve Overtime</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Approved Minutes</label>
                <input
                  type="number"
                  min={1}
                  value={approvedMins}
                  onChange={(e) => setApprovedMins(e.target.value)}
                  onBlur={() => setTouched(prev => ({ ...prev, approve: true }))}
                  className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                    approvedMinsError ? 'border-red-400' : 'border-neutral-300'
                  }`}
                />
                {approvedMinsError && <p className="mt-1 text-xs text-red-600">{approvedMinsError}</p>}
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
          <div className="bg-white rounded p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-neutral-900 mb-2">Reject Overtime</h3>
            <textarea
              value={rejectRemarks}
              onChange={(e) => setRejectRemarks(e.target.value)}
              onBlur={() => setTouched(prev => ({ ...prev, reject: true }))}
              placeholder="Enter rejection reason..."
              className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none mb-2 ${
                rejectRemarksError ? 'border-red-400' : 'border-neutral-300'
              }`}
              rows={3}
            />
            {rejectRemarksError && <p className="mb-2 text-xs text-red-600">{rejectRemarksError}</p>}
            <div className="flex justify-end gap-2">
              <button
                onClick={() => { setRejectId(null); setRejectRemarks(''); setTouched(prev => ({ ...prev, reject: false })) }}
                className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded"
              >
                Cancel
              </button>
              <button
                onClick={submitReject}
                disabled={!rejectRemarks.trim() || reject.isPending}
                className="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
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
