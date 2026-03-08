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
    return <div className="text-neutral-500 text-sm mt-4">No employee profile linked to your account.</div>
  }

  const handleSuccess = () => {
    refetchLeaves()
    refetchBalances()
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">My Leaves</h1>
        <button
          onClick={() => setIsModalOpen(true)}
          className="bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors inline-flex items-center gap-2"
        >
          <Plus className="h-4 w-4" />
          File Leave
        </button>
      </div>

      {/* Year selector */}
      <div className="flex gap-3 mb-5">
        {YEARS.map((y) => (
          <button key={y} onClick={() => { setYear(y); setPage(1) }}
            className={`px-4 py-1.5 text-sm rounded border transition-colors
              ${year === y ? 'bg-neutral-900 text-white border-neutral-900' : 'border-neutral-300 text-neutral-600 hover:bg-neutral-50'}`}>
            {y}
          </button>
        ))}
      </div>

      {/* Balance cards */}
      {balLoading ? <SkeletonLoader rows={1} /> : (
        <div className="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-6">
          {myBalances
            .filter((b) => b.leave_type_code !== 'OTH')
            .map((b) => {
              const total = b.opening_balance + b.accrued + b.adjusted
              const usedPct = total > 0 ? Math.min(100, (b.used / total) * 100) : 0
              const isEmpty = b.balance <= 0
              return (
                <div
                  key={b.leave_type_id}
                  className={`bg-white border border-neutral-200 rounded px-3 py-2.5 ${isEmpty ? 'border-neutral-200' : 'border-neutral-200'}`}
                >
                  <p className="text-[11px] text-neutral-500 mb-0.5 truncate leading-tight">{b.leave_type_name}</p>
                  <div className="flex items-baseline gap-0.5">
                    <span className={`text-lg font-bold leading-none ${isEmpty ? 'text-neutral-700' : 'text-neutral-700'}`}>
                      {b.balance}
                    </span>
                    <span className="text-xs text-neutral-400">/{total}</span>
                  </div>
                  <p className="text-[10px] text-neutral-400 mt-0.5">{b.used} used{isEmpty ? ' · ' : ''}{isEmpty && <span className="text-neutral-600 font-medium">No balance</span>}</p>
                  <div className="mt-1.5 bg-neutral-100 rounded-full h-1">
                    <div
                      className={`h-1 rounded-full ${isEmpty ? 'bg-neutral-500' : 'bg-neutral-600'}`}
                      style={{ width: `${usedPct}%` }}
                    />
                  </div>
                </div>
              )
            })}
          {myBalances.length === 0 && (
            <div className="col-span-7 text-sm text-neutral-400">No leave balances set up for {year}.</div>
          )}
        </div>
      )}

      {/* Leave history */}
      <h2 className="text-base font-semibold text-neutral-900 mb-3">Leave History</h2>
      {leavesLoading ? <SkeletonLoader rows={6} /> : (
        <div className="bg-white border border-neutral-200 rounded overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Leave Type', 'From', 'To', 'Days', 'Status', 'Filed', 'Action'].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(leavesData?.data ?? []).length === 0 && (
                <tr><td colSpan={7} className="px-3 py-8 text-center text-neutral-400">No leave requests for {year}.</td></tr>
              )}
              {(leavesData?.data ?? []).map((row) => (
                <tr key={row.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                  <td className="px-3 py-2 text-neutral-700">{row.leave_type?.name ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.date_from}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.date_to}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.total_days}</td>
                  <td className="px-3 py-2">
                    <StatusBadge label={row.status} />
                    {row.status === 'rejected' && row.reviewer_remarks && (
                      <p className="text-[11px] text-neutral-600 mt-0.5 leading-tight max-w-[180px]" title={row.reviewer_remarks}>
                        {row.reviewer_remarks.length > 60
                          ? row.reviewer_remarks.slice(0, 60) + '…'
                          : row.reviewer_remarks}
                      </p>
                    )}
                  </td>
                  <td className="px-3 py-2 text-neutral-400">{row.created_at?.slice(0, 10) ?? '—'}</td>
                  <td className="px-3 py-2">
                    {['submitted', 'draft'].includes(row.status) && (
                      <button
                        onClick={() => setCancelTarget(row.id)}
                        className="text-xs text-neutral-600 hover:text-neutral-900 hover:underline"
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
        <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
          <span>Page {leavesData?.meta?.current_page} of {leavesData?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40">Prev</button>
            <button disabled={page >= (leavesData?.meta?.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40">Next</button>
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
