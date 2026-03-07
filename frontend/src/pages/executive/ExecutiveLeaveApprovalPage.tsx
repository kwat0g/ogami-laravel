import { useState } from 'react'
import {
  useTeamLeaveRequests,
  useGaProcessLeaveRequest,
  useRejectLeaveRequest,
} from '@/hooks/useLeave'
import type { GaProcessPayload } from '@/hooks/useLeave'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { parseApiError } from '@/lib/errorHandler'
import { toast } from 'sonner'
import type { LeaveFilters } from '@/types/hr'

const YEARS = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

export default function ExecutiveLeaveApprovalPage() {
  const [filters, setFilters] = useState<LeaveFilters>({ per_page: 25, status: 'manager_checked' })
  const [processId, setProcessId] = useState<number | null>(null)
  const [actionTaken, setActionTaken] = useState<GaProcessPayload['action_taken']>('approved_with_pay')
  const [remarks, setRemarks] = useState('')

  const { data, isLoading, isError } = useTeamLeaveRequests(filters)
  const gaProcess = useGaProcessLeaveRequest()
  const reject = useRejectLeaveRequest()

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Failed to load leave requests for GA processing.
      </div>
    )
  }

  const rows = data?.data ?? []

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">GA Leave Processing</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {data?.meta?.total ?? 0} requests pending GA processing
            <span className="ml-2 text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
              GA Officer
            </span>
          </p>
        </div>
      </div>

      {/* Info Card */}
      <div className="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6">
        <h3 className="text-sm font-medium text-indigo-900 mb-1">GA Processing (Step 4 — AD-084-00)</h3>
        <p className="text-sm text-indigo-700">
          Review leave requests that have been checked by the Plant Manager. Set the action taken
          (approved with pay, without pay, or disapproved) before forwarding to VP for final notation.
        </p>
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.status ?? 'manager_checked'}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              status: (e.target.value || 'manager_checked') as LeaveFilters['status'],
              page: 1,
            }))
          }
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
        >
          <option value="manager_checked">Pending GA Process</option>
          <option value="ga_processed">Pending VP Notation</option>
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
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
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
                'Status',
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
                  No leave requests pending GA processing.
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
                  <StatusBadge label={row.status} />
                </td>
                <td className="px-4 py-3 flex gap-2">
                  {row.status === 'manager_checked' && (
                    <>
                      <button
                        onClick={() => setProcessId(row.id)}
                        disabled={gaProcess.isPending}
                        className="px-2 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
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
                        className="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
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

      {/* GA Process Modal */}
      {processId && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              Process Leave Request (GA Officer)
            </h3>

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Action Taken</label>
              <select
                value={actionTaken}
                onChange={(e) => setActionTaken(e.target.value as GaProcessPayload['action_taken'])}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
              >
                <option value="approved_with_pay">Approved With Pay</option>
                <option value="approved_without_pay">Approved Without Pay</option>
                <option value="disapproved">Disapproved</option>
              </select>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Remarks (optional)</label>
              <textarea
                value={remarks}
                onChange={(e) => setRemarks(e.target.value)}
                placeholder="Enter remarks..."
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
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
                className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg"
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
                className="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
              >
                {gaProcess.isPending ? 'Processing…' : 'Submit'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

