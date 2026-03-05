import { useState } from 'react'
import { useLeaveRequests, useLeaveBalances, useCancelLeaveRequest } from '@/hooks/useLeave'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import FileLeaveModal from '@/components/modals/FileLeaveModal'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { parseApiError } from '@/lib/errorHandler'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

const YEARS = Array.from({ length: 3 }, (_, i) => new Date().getFullYear() - i)

export default function MyLeavesPage() {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined

  const [year, setYear] = useState(new Date().getFullYear())
  const [page, setPage] = useState(1)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [cancelTarget, setCancelTarget] = useState<number | null>(null)

  const { data: leavesData, isLoading: leavesLoading, refetch: refetchLeaves } = useLeaveRequests({
    employee_id: employeeId,
    year,
    per_page: 20,
    page,
  })
  const { data: balancesData, isLoading: balLoading, refetch: refetchBalances } = useLeaveBalances(
    employeeId != null ? { employee_id: employeeId, year } : {}
  )
  const cancel = useCancelLeaveRequest()

  // The API returns a paginated list of employees; we only want the first (our own record)
  const myBalances = balancesData?.data?.[0]?.balances ?? []

  if (!employeeId) {
    return <div className="text-gray-500 text-sm mt-4">No employee profile linked to your account.</div>
  }

  const handleSuccess = () => {
    refetchLeaves()
    refetchBalances()
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Leaves</h1>
          <p className="text-sm text-gray-500 mt-0.5">Your leave requests and available credits</p>
        </div>
        <button
          onClick={() => setIsModalOpen(true)}
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-2"
        >
          <Plus className="h-4 w-4" />
          File Leave
        </button>
      </div>

      {/* Year selector */}
      <div className="flex gap-3 mb-5">
        {YEARS.map((y) => (
          <button key={y} onClick={() => { setYear(y); setPage(1) }}
            className={`px-4 py-1.5 text-sm rounded-full border transition-colors
              ${year === y ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'}`}>
            {y}
          </button>
        ))}
      </div>

      {/* Balance cards */}
      {balLoading ? <SkeletonLoader rows={1} /> : (
        <div className="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-6">
          {myBalances
            .filter((b) => b.leave_type_code !== 'LWOP')
            .map((b) => {
              const total = b.opening_balance + b.accrued + b.adjusted
              const usedPct = total > 0 ? Math.min(100, (b.used / total) * 100) : 0
              const isEmpty = b.balance <= 0
              return (
                <div
                  key={b.leave_type_id}
                  className={`bg-white border rounded-lg px-3 py-2.5 ${isEmpty ? 'border-red-200 bg-red-50/40' : 'border-gray-200'}`}
                >
                  <p className="text-[11px] text-gray-500 mb-0.5 truncate leading-tight">{b.leave_type_name}</p>
                  <div className="flex items-baseline gap-0.5">
                    <span className={`text-lg font-bold leading-none ${isEmpty ? 'text-red-600' : 'text-blue-700'}`}>
                      {b.balance}
                    </span>
                    <span className="text-xs text-gray-400">/{total}</span>
                  </div>
                  <p className="text-[10px] text-gray-400 mt-0.5">{b.used} used{isEmpty ? ' · ' : ''}{isEmpty && <span className="text-red-500 font-medium">No balance</span>}</p>
                  <div className="mt-1.5 bg-gray-100 rounded-full h-1">
                    <div
                      className={`h-1 rounded-full ${isEmpty ? 'bg-red-400' : 'bg-blue-500'}`}
                      style={{ width: `${usedPct}%` }}
                    />
                  </div>
                </div>
              )
            })}
          {myBalances.length === 0 && (
            <div className="col-span-7 text-sm text-gray-400">No leave balances set up for {year}.</div>
          )}
        </div>
      )}

      {/* Leave history */}
      <h2 className="text-base font-semibold text-gray-900 mb-3">Leave History</h2>
      {leavesLoading ? <SkeletonLoader rows={6} /> : (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['Leave Type', 'From', 'To', 'Days', 'Status', 'Filed', 'Action'].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(leavesData?.data ?? []).length === 0 && (
                <tr><td colSpan={7} className="px-3 py-8 text-center text-gray-400">No leave requests for {year}.</td></tr>
              )}
              {(leavesData?.data ?? []).map((row) => (
                <tr key={row.id} className="even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                  <td className="px-3 py-2 text-gray-700">{row.leave_type?.name ?? '—'}</td>
                  <td className="px-3 py-2 text-gray-600">{row.date_from}</td>
                  <td className="px-3 py-2 text-gray-600">{row.date_to}</td>
                  <td className="px-3 py-2 text-gray-600">{row.total_days}</td>
                  <td className="px-3 py-2">
                    <StatusBadge label={row.status} />
                    {row.status === 'rejected' && row.reviewer_remarks && (
                      <p className="text-[11px] text-red-500 mt-0.5 leading-tight max-w-[180px]" title={row.reviewer_remarks}>
                        {row.reviewer_remarks.length > 60
                          ? row.reviewer_remarks.slice(0, 60) + '…'
                          : row.reviewer_remarks}
                      </p>
                    )}
                  </td>
                  <td className="px-3 py-2 text-gray-400">{row.created_at?.slice(0, 10) ?? '—'}</td>
                  <td className="px-3 py-2">
                    {['submitted', 'draft'].includes(row.status) && (
                      <button
                        onClick={() => setCancelTarget(row.id)}
                        className="text-xs text-red-500 hover:text-red-700 hover:underline"
                      >
                        Cancel
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {(leavesData?.meta?.last_page ?? 1) > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
          <span>Page {leavesData?.meta?.current_page} of {leavesData?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
              className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40">Prev</button>
            <button disabled={page >= (leavesData?.meta?.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}
              className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40">Next</button>
          </div>
        </div>
      )}

      {/* Cancel confirmation */}
      <ConfirmDialog
        open={cancelTarget !== null}
        onClose={() => setCancelTarget(null)}
        onConfirm={() => {
          if (cancelTarget === null) return
          cancel.mutate(cancelTarget, {
            onSuccess: () => { toast.success('Leave request cancelled.'); setCancelTarget(null) },
            onError: (err) => { toast.error(parseApiError(err).message); setCancelTarget(null) },
          })
        }}
        title="Cancel leave request?"
        description="This leave request will be withdrawn. Your balance won't be deducted."
        confirmLabel="Yes, cancel it"
        loading={cancel.isPending}
      />

      {/* File Leave Modal */}
      <FileLeaveModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={handleSuccess}
        balances={myBalances}
      />
    </div>
  )
}
