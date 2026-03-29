import { useState, useCallback, useEffect } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import {
  useTeamOvertimeRequests,
  useApproveOvertimeRequest,
  useRejectOvertimeRequest,
  useBatchApproveOvertime,
  useBatchRejectOvertime,
} from '@/hooks/useOvertime'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { SodActionButton } from '@/components/ui/SodActionButton'
import { CheckSquare, XSquare } from 'lucide-react'
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

  // Batch selection
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [batchApproveOpen, setBatchApproveOpen] = useState(false)
  const [batchApproveMins, setBatchApproveMins] = useState('60')
  const [batchApproveRemarks, setBatchApproveRemarks] = useState('')
  const [batchRejectOpen, setBatchRejectOpen] = useState(false)
  const [batchRejectRemarks, setBatchRejectRemarks] = useState('')

  const { data, isLoading, isError } = useTeamOvertimeRequests(filters)
  const approve = useApproveOvertimeRequest()
  const reject = useRejectOvertimeRequest()
  const batchApproveOt = useBatchApproveOvertime()
  const batchRejectOt = useBatchRejectOvertime()

  // Clear selection on filter change
  useEffect(() => { setSelectedIds(new Set()) }, [filters])

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
    } catch (_err) {
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
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to reject overtime request.'))
    }
  }

  // Derive data (safe even when loading/error - defaults to empty arrays)
  const rows = data?.data ?? []
  const pendingRows = rows.filter((r: { status: string }) => r.status === 'pending')
  const allPendingSelected = pendingRows.length > 0 && pendingRows.every((r: { id: number }) => selectedIds.has(r.id))

  // All hooks must be called before any early return (Rules of Hooks)
  const toggleSelect = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id); else next.add(id)
      return next
    })
  }, [])

  const toggleSelectAll = useCallback(() => {
    if (allPendingSelected) setSelectedIds(new Set())
    else setSelectedIds(new Set(pendingRows.map((r: { id: number }) => r.id)))
  }, [allPendingSelected, pendingRows])

  const handleBatchApprove = useCallback(() => {
    if (selectedIds.size === 0 || !batchApproveMins) return
    batchApproveOt.mutate(
      { ids: Array.from(selectedIds), approved_minutes: Number(batchApproveMins), remarks: batchApproveRemarks || undefined },
      {
        onSuccess: (result) => {
          setSelectedIds(new Set()); setBatchApproveOpen(false)
          const ok = result.results.approved?.length ?? 0
          const fail = result.results.failed.length
          if (fail > 0) toast.warning(`${ok} approved, ${fail} failed.`)
          else toast.success(`${ok} OT request${ok !== 1 ? 's' : ''} approved.`)
        },
      },
    )
  }, [selectedIds, batchApproveMins, batchApproveRemarks, batchApproveOt])

  const handleBatchReject = useCallback(() => {
    if (selectedIds.size === 0 || !batchRejectRemarks.trim()) return
    batchRejectOt.mutate(
      { ids: Array.from(selectedIds), remarks: batchRejectRemarks.trim() },
      {
        onSuccess: (result) => {
          setSelectedIds(new Set()); setBatchRejectOpen(false); setBatchRejectRemarks('')
          const ok = result.results.rejected?.length ?? 0
          const fail = result.results.failed.length
          if (fail > 0) toast.warning(`${ok} rejected, ${fail} failed.`)
          else toast.success(`${ok} OT request${ok !== 1 ? 's' : ''} rejected.`)
        },
      },
    )
  }, [selectedIds, batchRejectRemarks, batchRejectOt])

  // Early returns AFTER all hooks (Rules of Hooks compliant)
  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError)   return <div className="text-neutral-600 text-sm mt-4">Failed to load overtime requests.</div>

  // Format minutes to hours and minutes
  const formatDuration = (mins: number) => {
    const hours = Math.floor(mins / 60)
    const minutes = mins % 60
    return `${hours}h ${minutes}m`
  }

  return (
    <div>
      <PageHeader title="Team Overtime" />
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

      {/* Batch Actions Bar */}
      {canApprove && selectedIds.size > 0 && (
        <div className="mb-4 bg-accent-soft border border-accent/20 rounded-lg p-3 flex items-center gap-3 flex-wrap">
          <span className="text-sm font-medium text-neutral-800">
            {selectedIds.size} request{selectedIds.size !== 1 ? 's' : ''} selected
          </span>
          <button onClick={() => setBatchApproveOpen(true)} disabled={batchApproveOt.isPending}
            className="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium px-3 py-1.5 rounded transition-colors">
            <CheckSquare className="h-4 w-4" /> Approve All
          </button>
          <button onClick={() => setBatchRejectOpen(true)} disabled={batchRejectOt.isPending}
            className="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium px-3 py-1.5 rounded transition-colors">
            <XSquare className="h-4 w-4" /> Reject All
          </button>
          <button onClick={() => setSelectedIds(new Set())} className="text-sm text-neutral-500 hover:text-neutral-700 underline ml-2">
            Clear selection
          </button>
        </div>
      )}

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              {canApprove && (
                <th className="px-3 py-2.5 text-left w-10">
                  <input type="checkbox" checked={allPendingSelected} onChange={toggleSelectAll}
                    disabled={pendingRows.length === 0} className="rounded border-neutral-300" />
                </th>
              )}
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
              <tr key={row.id} className={`hover:bg-neutral-50 transition-colors ${selectedIds.has(row.id) ? 'bg-accent-soft/50' : 'even:bg-neutral-100'}`}>
                {canApprove && (
                  <td className="px-3 py-2">
                    {row.status === 'pending' ? (
                      <input type="checkbox" checked={selectedIds.has(row.id)} onChange={() => toggleSelect(row.id)} className="rounded border-neutral-300" />
                    ) : <span className="block w-4" />}
                  </td>
                )}
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
      {/* Batch Approve Modal */}
      {batchApproveOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-semibold text-neutral-900 mb-2">
              Batch Approve {selectedIds.size} OT Request{selectedIds.size !== 1 ? 's' : ''}
            </h3>
            <p className="text-sm text-neutral-500 mb-4">All selected requests will be approved with the same minutes.</p>
            <div className="space-y-3 mb-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Approved Minutes</label>
                <input type="number" min={1} max={480} value={batchApproveMins}
                  onChange={(e) => setBatchApproveMins(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks (optional)</label>
                <textarea value={batchApproveRemarks} onChange={(e) => setBatchApproveRemarks(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" rows={2} />
              </div>
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setBatchApproveOpen(false)} className="px-4 py-2 text-sm text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button onClick={handleBatchApprove} disabled={!batchApproveMins || batchApproveOt.isPending}
                className="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
                {batchApproveOt.isPending ? 'Approving...' : 'Approve All'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Batch Reject Modal */}
      {batchRejectOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-semibold text-neutral-900 mb-2">
              Reject {selectedIds.size} OT Request{selectedIds.size !== 1 ? 's' : ''}
            </h3>
            <p className="text-sm text-neutral-500 mb-4">Provide a reason for rejecting these requests.</p>
            <textarea autoFocus value={batchRejectRemarks} onChange={(e) => setBatchRejectRemarks(e.target.value)}
              placeholder="Reason for rejection..." rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none mb-4" />
            <div className="flex justify-end gap-2">
              <button onClick={() => { setBatchRejectOpen(false); setBatchRejectRemarks('') }}
                className="px-4 py-2 text-sm text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
              <button onClick={handleBatchReject} disabled={!batchRejectRemarks.trim() || batchRejectOt.isPending}
                className="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50">
                {batchRejectOt.isPending ? 'Rejecting...' : 'Reject All'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
