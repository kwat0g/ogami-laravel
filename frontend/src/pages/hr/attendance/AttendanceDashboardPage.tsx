import { useState } from 'react'
import { useAttendanceDashboard } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'
import PageHeader from '@/components/ui/PageHeader'
import SkeletonTable from '@/components/ui/SkeletonTable'
import StatusBadge from '@/components/ui/StatusBadge'
import SodActionButton from '@/components/ui/SodActionButton'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { useApproveOvertimeRequest, useRejectOvertimeRequest } from '@/hooks/useAttendance'
import { toast } from 'sonner'

export default function AttendanceDashboardPage() {
  const { hasPermission } = useAuthStore()
  const canApproveOT = hasPermission('overtime.approve')

  const { data, isLoading, isError, refetch } = useAttendanceDashboard()

  // OT approval state
  const [approvingId, setApprovingId]       = useState<number | null>(null)
  const [approvedMins, setApprovedMins]     = useState<string>('')
  const [rejectId, setRejectId]             = useState<number | null>(null)
  const [rejectRemarks, setRejectRemarks]   = useState<string>('')

  const approve = useApproveOvertimeRequest()
  const reject  = useRejectOvertimeRequest()

  async function submitApprove() {
    if (!approvingId) return
    try {
      await approve.mutateAsync({ id: approvingId, approved_minutes: Number(approvedMins) })
      toast.success('Overtime request approved.')
    } catch {
      toast.error('Failed to approve overtime request.')
    }
    setApprovingId(null)
    void refetch()
  }

  async function submitReject() {
    if (!rejectId) return
    try {
      await reject.mutateAsync({ id: rejectId, remarks: rejectRemarks })
      toast.success('Overtime request rejected.')
    } catch {
      toast.error('Failed to reject overtime request.')
    }
    setRejectId(null)
    void refetch()
  }

  if (isLoading) return (
    <div className="p-6 space-y-6">
      <PageHeader title="Attendance Dashboard" />
      <SkeletonTable rows={6} cols={5} />
    </div>
  )

  if (isError) return (
    <div className="p-6">
      <PageHeader title="Attendance Dashboard" />
      <p className="text-red-600 text-sm mt-4">Failed to load attendance dashboard.</p>
    </div>
  )

  const anomalies = data?.anomaly_feed ?? []
  const otQueue   = data?.ot_queue?.data ?? []
  const stats     = data?.period_stats

  return (
    <div className="p-6 space-y-6">
      <ExecutiveReadOnlyBanner />
      <PageHeader title="Attendance Dashboard" />

      {/* Period stats */}
      {stats && (
        <div className="grid grid-cols-3 gap-4">
          <div className="rounded-lg border border-neutral-200 p-4 bg-white">
            <p className="text-xs text-neutral-500 uppercase tracking-wide">Absences (14 days)</p>
            <p className="mt-1 text-2xl font-bold text-red-600">{stats.absent_count}</p>
          </div>
          <div className="rounded-lg border border-neutral-200 p-4 bg-white">
            <p className="text-xs text-neutral-500 uppercase tracking-wide">Tardy (14 days)</p>
            <p className="mt-1 text-2xl font-bold text-amber-600">{stats.tardy_count}</p>
          </div>
          <div className="rounded-lg border border-neutral-200 p-4 bg-white">
            <p className="text-xs text-neutral-500 uppercase tracking-wide">Total OT Minutes</p>
            <p className="mt-1 text-2xl font-bold text-neutral-900">{stats.total_overtime_minutes.toLocaleString()}</p>
          </div>
        </div>
      )}

      {/* Anomaly feed */}
      <section>
        <h2 className="text-sm font-semibold text-neutral-700 mb-2">Anomaly Feed (Last 14 Days)</h2>
        <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
          <table className="min-w-full divide-y divide-neutral-200 text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {['Date', 'Employee', 'Type', 'Minutes Late'].map(h => (
                  <th key={h} className="px-4 py-2 text-left text-xs font-medium text-neutral-500 uppercase">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {anomalies.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-neutral-400">No anomalies in the last 14 days.</td>
                </tr>
              ) : anomalies.map(a => (
                <tr key={a.id} className="hover:bg-neutral-50">
                  <td className="px-4 py-2 whitespace-nowrap">{a.log_date}</td>
                  <td className="px-4 py-2">{a.employee_name ?? `#${a.employee_id}`}</td>
                  <td className="px-4 py-2">
                    <StatusBadge label={a.type} autoVariant />
                  </td>
                  <td className="px-4 py-2">{a.minutes_late ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* OT approval queue */}
      {canApproveOT && (
        <section>
          <h2 className="text-sm font-semibold text-neutral-700 mb-2">
            Overtime Approval Queue
            {data?.ot_queue?.total ? <span className="ml-2 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">{data.ot_queue.total} pending</span> : null}
          </h2>
          <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
            <table className="min-w-full divide-y divide-neutral-200 text-sm">
              <thead className="bg-neutral-50">
                <tr>
                  {['Date', 'Employee', 'Requested (min)', 'Status', 'Actions'].map(h => (
                    <th key={h} className="px-4 py-2 text-left text-xs font-medium text-neutral-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {otQueue.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-6 text-center text-neutral-400">No pending OT requests.</td>
                  </tr>
                ) : otQueue.map(ot => (
                  <tr key={ot.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-2 whitespace-nowrap">{ot.date}</td>
                    <td className="px-4 py-2">{ot.employee_name ?? `#${ot.employee_id}`}</td>
                    <td className="px-4 py-2">{ot.requested_minutes}</td>
                    <td className="px-4 py-2"><StatusBadge label={ot.status} autoVariant /></td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-2">
                        <SodActionButton
                          initiatedById={(ot as { created_by_id: number }).created_by_id}
                          label="Approve"
                          onClick={() => { setApprovingId(ot.id); setApprovedMins(String(ot.requested_minutes)) }}
                        />
                        <button
                          onClick={() => setRejectId(ot.id)}
                          className="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200"
                        >
                          Reject
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* Approve modal */}
      {approvingId !== null && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-80 space-y-4">
            <h3 className="font-semibold text-neutral-900">Approve Overtime</h3>
            <label className="block text-sm">
              Approved Minutes
              <input
                type="number"
                value={approvedMins}
                onChange={e => setApprovedMins(e.target.value)}
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
              />
            </label>
            <div className="flex justify-end gap-2">
              <button onClick={() => setApprovingId(null)} className="text-sm px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button onClick={() => void submitApprove()} disabled={approve.isPending} className="text-sm px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
                {approve.isPending ? 'Approving…' : 'Confirm'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject modal */}
      {rejectId !== null && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg border border-neutral-200 p-6 w-80 space-y-4">
            <h3 className="font-semibold text-neutral-900">Reject Overtime</h3>
            <label className="block text-sm">
              Remarks (optional)
              <textarea
                value={rejectRemarks}
                onChange={e => setRejectRemarks(e.target.value)}
                rows={3}
                className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
              />
            </label>
            <div className="flex justify-end gap-2">
              <button onClick={() => setRejectId(null)} className="text-sm px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button onClick={() => void submitReject()} disabled={reject.isPending} className="text-sm px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50">
                {reject.isPending ? 'Rejecting…' : 'Reject'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
