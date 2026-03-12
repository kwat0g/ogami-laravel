import { useState } from 'react'
import {
  useTeamLeaveRequests,
  useGaProcessLeaveRequest,
  useVpNoteLeaveRequest,
  useRejectLeaveRequest,
} from '@/hooks/useLeave'
import type { GaProcessPayload } from '@/hooks/useLeave'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { parseApiError } from '@/lib/errorHandler'
import { toast } from 'sonner'
import type { LeaveFilters } from '@/types/hr'
import { PageHeader } from '@/components/ui/PageHeader'

const YEARS = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

export default function ExecutiveLeaveApprovalPage() {
  const { hasPermission } = useAuthStore()
  const canGaProcess = hasPermission('leaves.ga_process')
  const canVpNote = hasPermission('leaves.vp_note')

  const [filters, setFilters] = useState<LeaveFilters>({ per_page: 25, status: 'manager_checked' })
  const [processId, setProcessId] = useState<number | null>(null)
  const [actionTaken, setActionTaken] = useState<GaProcessPayload['action_taken']>('approved_with_pay')
  const [remarks, setRemarks] = useState('')
  const [vpNoteId, setVpNoteId] = useState<number | null>(null)
  const [vpRemarks, setVpRemarks] = useState('')

  const { data, isLoading, isError } = useTeamLeaveRequests(filters)
  const gaProcess = useGaProcessLeaveRequest()
  const vpNote = useVpNoteLeaveRequest()
  const reject = useRejectLeaveRequest()

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError) {
    return (
      <div className="text-neutral-600 text-sm mt-4">
        Failed to load leave requests for GA processing.
      </div>
    )
  }

  const rows = data?.data ?? []

  return (
    <div>
      <PageHeader title="Leave Approvals" />

      {/* Info Card */}
      <div className="bg-neutral-50 border border-neutral-200 rounded p-4 mb-6">
        <h3 className="text-sm font-medium text-neutral-900 mb-1">Leave Approvals (AD-084-00)</h3>
        <p className="text-sm text-neutral-600">
          {canGaProcess && 'Step 4 (GA): Review manager-checked requests. Set action taken (approved with pay, without pay, or disapproved).'}
          {canGaProcess && canVpNote && ' '}
          {canVpNote && 'Step 5 (VP): Final notation for GA-processed requests. Approves and triggers balance deduction.'}
        </p>
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.status ?? 'manager_checked'}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              status: (e.target.value || undefined) as LeaveFilters['status'],
              page: 1,
            }))
          }
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          {canGaProcess && <option value="manager_checked">Pending GA Process</option>}
          {canVpNote && <option value="ga_processed">Pending VP Notation</option>}
          <option value="">All</option>
        </select>

        <select
          value={filters.year ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              year: e.target.value ? Number(e.target.value) : undefined,
              page: 1,
            }))
          }
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
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
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {[
                'Employee',
                'Department',
                'Leave Type',
                'From',
                'To',
                'Days',
                'Status',
                'Actions',
              ].map((h) => (
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
                <td colSpan={8} className="px-4 py-8 text-center text-neutral-400">
                  No leave requests pending GA processing.
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
                <td className="px-4 py-3 text-neutral-600">
                  {row.leave_type?.name ?? '—'}
                </td>
                <td className="px-4 py-3 text-neutral-600">{row.date_from}</td>
                <td className="px-4 py-3 text-neutral-600">{row.date_to}</td>
                <td className="px-4 py-3 text-neutral-600">{row.total_days}</td>
                <td className="px-4 py-3">
                  <StatusBadge status={row.status}>{row.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                </td>
                <td className="px-4 py-3 flex gap-2">
                  {canGaProcess && row.status === 'manager_checked' && (
                    <>
                      <button
                        onClick={() => setProcessId(row.id)}
                        disabled={gaProcess.isPending}
                        className="px-2 py-1 text-xs bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        Process
                      </button>
                      <button
                        onClick={() => {
                          reject.mutate(
                            { id: row.id, remarks: '' },
                            {
                              onSuccess: () => toast.success('Request rejected.'),
                              onError: (err) => toast.error(parseApiError(err).message),
                            }
                          )
                        }}
                        disabled={reject.isPending}
                        className="px-2 py-1 text-xs bg-neutral-600 text-white rounded hover:bg-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        Reject
                      </button>
                    </>
                  )}
                  {canVpNote && row.status === 'ga_processed' && (
                    <>
                      <button
                        onClick={() => { setVpNoteId(row.id); setVpRemarks('') }}
                        disabled={vpNote.isPending}
                        className="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        VP Approve
                      </button>
                      <button
                        onClick={() => {
                          reject.mutate(
                            { id: row.id, remarks: '' },
                            {
                              onSuccess: () => toast.success('Request rejected by VP.'),
                              onError: (err) => toast.error(parseApiError(err).message),
                            }
                          )
                        }}
                        disabled={reject.isPending}
                        className="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        Reject
                      </button>
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
          <span>
            Page {data.meta.current_page} of {data.meta.last_page}
          </span>
          <div className="flex gap-2">
            <button
              disabled={data.meta.current_page <= 1}
              onClick={() =>
                setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))
              }
              className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50"
            >
              Previous
            </button>
            <button
              disabled={data.meta.current_page >= data.meta.last_page}
              onClick={() =>
                setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))
              }
              className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {/* GA Process Modal */}
      {processId && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded p-6 w-full max-w-md border border-neutral-200">
            <h3 className="text-base font-semibold text-neutral-900 mb-4">
              Process Leave Request (GA Officer)
            </h3>

            <div className="mb-4">
              <label className="block text-sm font-medium text-neutral-700 mb-1">Action Taken</label>
              <select
                value={actionTaken}
                onChange={(e) => setActionTaken(e.target.value as GaProcessPayload['action_taken'])}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
              >
                <option value="approved_with_pay">Approved With Pay</option>
                <option value="approved_without_pay">Approved Without Pay</option>
                <option value="disapproved">Disapproved</option>
              </select>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
              <textarea
                value={remarks}
                onChange={(e) => setRemarks(e.target.value)}
                placeholder="Enter remarks..."
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                rows={3}
              />
            </div>

            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setProcessId(null)
                  setRemarks('')
                  setActionTaken('approved_with_pay')
                }}
                className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  if (!processId) return
                  gaProcess.mutate(
                    { id: processId, action_taken: actionTaken, remarks },
                    {
                      onSuccess: () => {
                        toast.success('Leave request processed.')
                        setProcessId(null)
                        setRemarks('')
                        setActionTaken('approved_with_pay')
                      },
                      onError: (err) => toast.error(parseApiError(err).message),
                    }
                  )
                }}
                disabled={gaProcess.isPending}
                className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {gaProcess.isPending ? 'Processing…' : 'Submit'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* VP Note Confirmation Modal */}
      {vpNoteId && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded p-6 w-full max-w-md border border-neutral-200">
            <h3 className="text-base font-semibold text-neutral-900 mb-4">
              VP Final Notation (Step 5)
            </h3>
            <p className="text-sm text-neutral-600 mb-4">
              This is the final approval step. Approving will deduct the employee's leave balance
              (for approved-with-pay requests).
            </p>

            <div className="mb-4">
              <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
              <textarea
                value={vpRemarks}
                onChange={(e) => setVpRemarks(e.target.value)}
                placeholder="Enter VP remarks..."
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                rows={3}
              />
            </div>

            <div className="flex justify-end gap-2">
              <button
                onClick={() => { setVpNoteId(null); setVpRemarks('') }}
                className="px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-100 rounded"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  if (!vpNoteId) return
                  vpNote.mutate(
                    { id: vpNoteId, remarks: vpRemarks || undefined },
                    {
                      onSuccess: () => {
                        toast.success('Leave request approved by VP.')
                        setVpNoteId(null)
                        setVpRemarks('')
                      },
                      onError: (err) => toast.error(parseApiError(err).message),
                    }
                  )
                }}
                disabled={vpNote.isPending}
                className="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {vpNote.isPending ? 'Approving…' : 'Approve (VP)'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
