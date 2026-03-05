import { useState } from 'react'
import { useOvertimeRequests, useCancelOvertimeRequest } from '@/hooks/useOvertime'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import FileOvertimeModal from '@/components/modals/FileOvertimeModal'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { parseApiError } from '@/lib/errorHandler'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

export default function MyOTPage() {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined

  const currentYear = new Date().getFullYear()
  const currentMonth = new Date().getMonth() + 1
  
  const [year, setYear] = useState(currentYear)
  const [month, setMonth] = useState(currentMonth)
  const [page, setPage] = useState(1)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [cancelTarget, setCancelTarget] = useState<number | null>(null)

  const dateFrom = `${year}-${String(month).padStart(2, '0')}-01`
  const dateTo = new Date(year, month, 0).toISOString().slice(0, 10)

  const { data: otData, isLoading, refetch } = useOvertimeRequests({
    employee_id: employeeId,
    date_from: dateFrom,
    date_to: dateTo,
    per_page: 10,
    page,
  })
  const cancel = useCancelOvertimeRequest()

  if (!employeeId) {
    return <div className="text-gray-500 text-sm mt-4">No employee profile linked to your account.</div>
  }

  const formatDuration = (minutes: number | null) => {
    if (minutes === null) return '—'
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60
    if (mins === 0) return `${hours}h`
    return `${hours}h ${mins}m`
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Overtime</h1>
          <p className="text-sm text-gray-500 mt-0.5">Your overtime requests and approvals</p>
        </div>
        <button
          onClick={() => setIsModalOpen(true)}
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-2"
        >
          <Plus className="h-4 w-4" />
          File OT Request
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-5">
        <select
          value={year}
          onChange={(e) => { setYear(Number(e.target.value)); setPage(1) }}
          className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          {[currentYear, currentYear - 1, currentYear - 2].map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>
        <select
          value={month}
          onChange={(e) => { setMonth(Number(e.target.value)); setPage(1) }}
          className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          {MONTHS.map((m, i) => (
            <option key={i + 1} value={i + 1}>{m}</option>
          ))}
        </select>
      </div>

      {/* OT History */}
      {isLoading ? <SkeletonLoader rows={6} /> : (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['Date', 'Requested', 'Approved', 'Reason', 'Status', 'Filed', 'Action'].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(otData?.data ?? []).length === 0 && (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">No overtime requests for {MONTHS[month - 1]} {year}.</td></tr>
              )}
              {(otData?.data ?? []).map((row) => (
                <tr key={row.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-700 font-medium">{row.work_date}</td>
                  <td className="px-4 py-3 text-gray-600">{formatDuration(row.requested_minutes)}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {row.status === 'approved' ? (
                      <span className="text-green-600 font-medium">{formatDuration(row.approved_minutes)}</span>
                    ) : (
                      formatDuration(row.approved_minutes)
                    )}
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    <div className="w-40 truncate text-sm" title={row.reason || undefined}>{row.reason || '—'}</div>
                  </td>
                  <td className="px-4 py-3"><StatusBadge label={row.status} /></td>
                  <td className="px-4 py-3 text-gray-400">{row.created_at?.slice(0, 10) ?? '—'}</td>
                  <td className="px-4 py-3">
                    {['pending', 'pending_executive'].includes(row.status) && (
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
      {(otData?.meta?.last_page ?? 1) > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
          <span>Page {otData?.meta?.current_page} of {otData?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
              className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40">Prev</button>
            <button disabled={page >= (otData?.meta?.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}
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
            onSuccess: () => { toast.success('Overtime request cancelled.'); setCancelTarget(null) },
            onError: (err) => { toast.error(parseApiError(err).message); setCancelTarget(null) },
          })
        }}
        title="Cancel overtime request?"
        description="This overtime request will be withdrawn and removed from the approval queue."
        confirmLabel="Yes, cancel it"
        loading={cancel.isPending}
      />

      {/* File OT Modal */}
      <FileOvertimeModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={refetch}
      />
    </div>
  )
}
