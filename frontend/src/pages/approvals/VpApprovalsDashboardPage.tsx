import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, AlertTriangle } from 'lucide-react'
import { useVpPendingPurchaseRequests, useVpPendingLoans, useVpPendingMrqs } from '@/hooks/useVpApprovals'
import { useVpApprovePurchaseRequest, useRejectPurchaseRequest } from '@/hooks/usePurchaseRequests'
import { useVpApproveLoan, useRejectLoan } from '@/hooks/useLoans'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

type TabId = 'purchase-requests' | 'loans' | 'mrq'

export default function VpApprovalsDashboardPage(): React.ReactElement {
  const [activeTab, setActiveTab] = useState<TabId>('purchase-requests')

  // Reject PR state
  const [rejectPrTarget, setRejectPrTarget] = useState<string | null>(null)
  const [rejectPrReason, setRejectPrReason] = useState('')

  // Reject Loan state
  const [rejectLoanTarget, setRejectLoanTarget] = useState<string | null>(null)
  const [rejectLoanRemarks, setRejectLoanRemarks] = useState('')

  // Reject MRQ state
  const [rejectMrqTarget, setRejectMrqTarget] = useState<string | null>(null)
  const [rejectMrqReason, setRejectMrqReason] = useState('')

  const prQuery   = useVpPendingPurchaseRequests()
  const loanQuery = useVpPendingLoans()
  const mrqQuery  = useVpPendingMrqs()

  const vpApprovePR   = useVpApprovePurchaseRequest()
  const rejectPR      = useRejectPurchaseRequest()
  const vpApproveLoan = useVpApproveLoan()
  const rejectLoan    = useRejectLoan()

  // MRQ mutations — operate directly on the API with the selected ulid
  const queryClient = useQueryClient()
  const vpApproveMRQ = useMutation({
    mutationFn: async ({ ulid, comments }: { ulid: string; comments?: string }) =>
      api.patch(`/inventory/requisitions/${ulid}/vp-approve`, { comments: comments ?? '' }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['vp-approvals'] })
      void queryClient.invalidateQueries({ queryKey: ['material-requisitions'] })
    },
  })
  const rejectMRQMutation = useMutation({
    mutationFn: async ({ ulid, reason }: { ulid: string; reason: string }) =>
      api.patch(`/inventory/requisitions/${ulid}/reject`, { reason }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['vp-approvals'] })
      void queryClient.invalidateQueries({ queryKey: ['material-requisitions'] })
    },
  })

  const prCount   = prQuery.data?.meta?.total   ?? 0
  const loanCount = loanQuery.data?.meta?.total ?? 0
  const mrqCount  = mrqQuery.data?.meta?.total  ?? 0

  // ── PR handlers ─────────────────────────────────────────────────────────────

  const handleApprovePR = async (ulid: string): Promise<void> => {
    try {
      await vpApprovePR.mutateAsync({ ulid, payload: { comments: '' } })
      toast.success('Purchase Request approved.')
    } catch {
      toast.error('Approval failed. Please try again.')
    }
  }

  const handleRejectPR = async (): Promise<void> => {
    if (!rejectPrTarget || rejectPrReason.length < 10) return
    try {
      await rejectPR.mutateAsync({
        ulid: rejectPrTarget,
        payload: { reason: rejectPrReason, stage: 'reviewed' },
      })
      toast.success('Purchase Request rejected.')
      setRejectPrTarget(null)
      setRejectPrReason('')
    } catch {
      toast.error('Rejection failed. Please try again.')
    }
  }

  // ── Loan handlers ────────────────────────────────────────────────────────────

  const handleApproveLoan = async (id: string): Promise<void> => {
    try {
      await vpApproveLoan.mutateAsync({ id })
      toast.success('Loan approved.')
    } catch {
      toast.error('Approval failed. Please try again.')
    }
  }

  const handleRejectLoan = async (): Promise<void> => {
    if (!rejectLoanTarget || rejectLoanRemarks.length < 5) return
    try {
      await rejectLoan.mutateAsync({ id: rejectLoanTarget, remarks: rejectLoanRemarks })
      toast.success('Loan rejected.')
      setRejectLoanTarget(null)
      setRejectLoanRemarks('')
    } catch {
      toast.error('Rejection failed. Please try again.')
    }
  }

  // ── MRQ handlers ─────────────────────────────────────────────────────────────

  const handleApproveMRQ = async (ulid: string): Promise<void> => {
    try {
      await vpApproveMRQ.mutateAsync({ ulid, comments: '' })
      toast.success('Material Requisition approved.')
    } catch {
      toast.error('Approval failed. Please try again.')
    }
  }

  const handleRejectMRQ = async (): Promise<void> => {
    if (!rejectMrqTarget || rejectMrqReason.length < 10) return
    try {
      await rejectMRQMutation.mutateAsync({ ulid: rejectMrqTarget, reason: rejectMrqReason })
      toast.success('Material Requisition rejected.')
      setRejectMrqTarget(null)
      setRejectMrqReason('')
    } catch {
      toast.error('Rejection failed. Please try again.')
    }
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-neutral-100 rounded flex items-center justify-center">
          <ClipboardCheck className="w-5 h-5 text-neutral-600" />
        </div>
        <div>
          <h1 className="text-lg font-semibold text-neutral-900 mb-6">Pending Approvals</h1>
          <p className="text-sm text-neutral-500">Items awaiting your final sign-off</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-neutral-100 rounded p-1 w-fit mb-6">
        {(
          [
            { id: 'purchase-requests', label: 'Purchase Requests', count: prCount },
            { id: 'loans',             label: 'Loans',             count: loanCount },
            { id: 'mrq',               label: 'Material Requisitions', count: mrqCount },
          ] as { id: TabId; label: string; count: number }[]
        ).map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`flex items-center gap-2 px-4 py-2 rounded text-sm font-medium transition-colors ${
              activeTab === tab.id
                ? 'bg-white text-neutral-900'
                : 'text-neutral-500 hover:text-neutral-700'
            }`}
          >
            {tab.label}
            {tab.count > 0 && (
              <span
                className={`px-1.5 py-0.5 rounded text-xs font-medium ${
                  activeTab === tab.id
                    ? 'bg-neutral-100 text-neutral-700'
                    : 'bg-neutral-200 text-neutral-600'
                }`}
              >
                {tab.count}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Purchase Requests Tab */}
      {activeTab === 'purchase-requests' && (
        <div>
          {prQuery.isLoading && <SkeletonLoader rows={5} />}
          {prQuery.isError && (
            <div className="flex items-center gap-2 text-neutral-700 text-sm">
              <AlertTriangle className="w-4 h-4" />
              Failed to load purchase requests.
            </div>
          )}
          {!prQuery.isLoading && !prQuery.isError && (
            <div className="bg-white border border-neutral-200 rounded overflow-hidden">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['PR Reference', 'Dept', 'Urgency', 'Total Est. Cost', 'Submitted By', 'Reviewed By', 'Actions'].map(
                      (h) => (
                        <th
                          key={h}
                          className="px-4 py-3 text-left text-xs font-semibold text-neutral-600"
                        >
                          {h}
                        </th>
                      ),
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {prQuery.data?.data?.length === 0 && (
                    <tr>
                      <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">
                        No purchase requests awaiting your approval.
                      </td>
                    </tr>
                  )}
                  {prQuery.data?.data?.map((pr) => (
                    <tr key={pr.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                      <td className="px-4 py-3 font-mono text-neutral-700 font-medium">
                        <Link
                          to={`/procurement/purchase-requests/${pr.ulid}`}
                          className="underline underline-offset-2"
                        >
                          {pr.pr_reference}
                        </Link>
                      </td>
                      <td className="px-4 py-3 text-neutral-600">#{pr.department_id}</td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                            pr.urgency === 'critical' ? 'bg-neutral-100 text-neutral-700' :
                            pr.urgency === 'urgent'   ? 'bg-neutral-100 text-neutral-700' :
                            'bg-neutral-100 text-neutral-500'
                          }`}
                        >
                          {pr.urgency}
                        </span>
                      </td>
                      <td className="px-4 py-3 font-medium text-neutral-800">
                        ₱{Number(pr.total_estimated_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 text-neutral-600">{pr.submitted_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-neutral-600">{pr.reviewed_by?.name ?? '—'}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => handleApprovePR(pr.ulid)}
                            disabled={vpApprovePR.isPending}
                            className="text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 disabled:bg-neutral-300 disabled:cursor-not-allowed text-white font-medium rounded transition-colors"
                          >
                            Approve
                          </button>
                          <button
                            onClick={() => {
                              setRejectPrTarget(pr.ulid)
                              setRejectPrReason('')
                            }}
                            className="text-xs px-3 py-1.5 border border-neutral-300 text-red-600 hover:bg-neutral-50 font-medium rounded transition-colors"
                          >
                            Reject
                          </button>
                          <Link
                            to={`/procurement/purchase-requests/${pr.ulid}`}
                            className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                          >
                            View
                          </Link>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Loans Tab */}
      {activeTab === 'loans' && (
        <div>
          {loanQuery.isLoading && <SkeletonLoader rows={5} />}
          {loanQuery.isError && (
            <div className="flex items-center gap-2 text-red-600 text-sm">
              <AlertTriangle className="w-4 h-4" />
              Failed to load loans.
            </div>
          )}
          {!loanQuery.isLoading && !loanQuery.isError && (
            <div className="bg-white border border-neutral-200 rounded overflow-hidden">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['Loan Reference', 'Employee', 'Type', 'Amount', 'Officer Reviewed', 'Actions'].map(
                      (h) => (
                        <th
                          key={h}
                          className="px-4 py-3 text-left text-xs font-semibold text-neutral-600"
                        >
                          {h}
                        </th>
                      ),
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {loanQuery.data?.data?.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-neutral-400 text-sm">
                        No loans awaiting your approval.
                      </td>
                    </tr>
                  )}
                  {loanQuery.data?.data?.map((loan) => (
                    <tr key={loan.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                      <td className="px-4 py-3 font-mono text-neutral-700 font-medium">
                        {loan.reference_no ?? `LOAN-${loan.id}`}
                      </td>
                      <td className="px-4 py-3 text-neutral-600">{loan.employee?.full_name ?? `#${loan.employee_id}`}</td>
                      <td className="px-4 py-3 text-neutral-600">{loan.loan_type?.name ?? '—'}</td>
                      <td className="px-4 py-3 font-medium text-neutral-800">
                        ₱{Number(loan.principal_php).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 text-neutral-600">
                        {loan.officer_reviewed_at
                          ? new Date(loan.officer_reviewed_at).toLocaleDateString('en-PH')
                          : '—'}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => handleApproveLoan(loan.ulid ?? String(loan.id))}
                            disabled={vpApproveLoan.isPending}
                            className="text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 disabled:bg-neutral-300 disabled:cursor-not-allowed text-white font-medium rounded transition-colors"
                          >
                            Approve
                          </button>
                          <button
                            onClick={() => {
                              setRejectLoanTarget(loan.ulid ?? String(loan.id))
                              setRejectLoanRemarks('')
                            }}
                            className="text-xs px-3 py-1.5 border border-neutral-300 text-red-600 hover:bg-neutral-50 font-medium rounded transition-colors"
                          >
                            Reject
                          </button>
                          <Link
                            to={`/hr/loans/${loan.ulid ?? loan.id}`}
                            className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                          >
                            View
                          </Link>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Material Requisitions Tab */}
      {activeTab === 'mrq' && (
        <div>
          {mrqQuery.isLoading && <SkeletonLoader rows={5} />}
          {mrqQuery.isError && (
            <div className="flex items-center gap-2 text-red-600 text-sm">
              <AlertTriangle className="w-4 h-4" />
              Failed to load material requisitions.
            </div>
          )}
          {!mrqQuery.isLoading && !mrqQuery.isError && (
            <div className="bg-white border border-neutral-200 rounded overflow-hidden">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['MR Reference', 'Department', 'Purpose', 'Reviewed By', 'Reviewed At', 'Actions'].map(
                      (h) => (
                        <th
                          key={h}
                          className="px-4 py-3 text-left text-xs font-semibold text-neutral-600"
                        >
                          {h}
                        </th>
                      ),
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {mrqQuery.data?.data?.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-neutral-400 text-sm">
                        No material requisitions awaiting your approval.
                      </td>
                    </tr>
                  )}
                  {mrqQuery.data?.data?.map((mrq) => (
                    <tr key={mrq.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                      <td className="px-4 py-3 font-mono text-neutral-700 font-medium">
                        <Link
                          to={`/inventory/requisitions/${mrq.ulid}`}
                          className="hover:underline"
                        >
                          {mrq.mr_reference}
                        </Link>
                      </td>
                      <td className="px-4 py-3 text-neutral-600">{mrq.department?.name ?? `#${mrq.department_id}`}</td>
                      <td className="px-4 py-3 text-neutral-600 max-w-xs truncate">{mrq.purpose}</td>
                      <td className="px-4 py-3 text-neutral-600">{mrq.reviewed_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-neutral-600">
                        {mrq.reviewed_at
                          ? new Date(mrq.reviewed_at).toLocaleDateString('en-PH')
                          : '—'}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => handleApproveMRQ(mrq.ulid)}
                            disabled={vpApproveMRQ.isPending}
                            className="text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 disabled:bg-neutral-300 disabled:cursor-not-allowed text-white font-medium rounded transition-colors"
                          >
                            Approve
                          </button>
                          <button
                            onClick={() => {
                              setRejectMrqTarget(mrq.ulid)
                              setRejectMrqReason('')
                            }}
                            className="text-xs px-3 py-1.5 border border-neutral-300 text-red-600 hover:bg-neutral-50 font-medium rounded transition-colors"
                          >
                            Reject
                          </button>
                          <Link
                            to={`/inventory/requisitions/${mrq.ulid}`}
                            className="px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 font-medium"
                          >
                            View
                          </Link>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Reject PR Modal */}
      {rejectPrTarget && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
            <h3 className="text-base font-medium text-neutral-700">Reject Purchase Request</h3>
            <textarea
              value={rejectPrReason}
              onChange={(e) => setRejectPrReason(e.target.value)}
              rows={3}
              placeholder="Reason for rejection (min. 10 characters)"
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
            />
            <div className="flex justify-end gap-3">
              <button
                onClick={() => { setRejectPrTarget(null); setRejectPrReason('') }}
                className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                disabled={rejectPrReason.length < 10 || rejectPR.isPending}
                onClick={handleRejectPR}
                className="text-sm px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed text-white font-medium rounded"
              >
                {rejectPR.isPending ? 'Rejecting…' : 'Confirm Reject'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Loan Modal */}
      {rejectLoanTarget && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
            <h3 className="text-base font-medium text-red-700">Reject Loan</h3>
            <textarea
              value={rejectLoanRemarks}
              onChange={(e) => setRejectLoanRemarks(e.target.value)}
              rows={3}
              placeholder="Remarks for rejection (min. 5 characters)"
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
            />
            <div className="flex justify-end gap-3">
              <button
                onClick={() => { setRejectLoanTarget(null); setRejectLoanRemarks('') }}
                className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                disabled={rejectLoanRemarks.length < 5 || rejectLoan.isPending}
                onClick={handleRejectLoan}
                className="text-sm px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed text-white font-medium rounded"
              >
                {rejectLoan.isPending ? 'Rejecting…' : 'Confirm Reject'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject MRQ Modal */}
      {rejectMrqTarget && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
            <h3 className="text-base font-medium text-red-700">Reject Material Requisition</h3>
            <textarea
              value={rejectMrqReason}
              onChange={(e) => setRejectMrqReason(e.target.value)}
              rows={3}
              placeholder="Reason for rejection (min. 10 characters)"
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
            />
            <div className="flex justify-end gap-3">
              <button
                onClick={() => { setRejectMrqTarget(null); setRejectMrqReason('') }}
                className="text-sm px-4 py-2 border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                disabled={rejectMrqReason.length < 10 || rejectMRQMutation.isPending}
                onClick={handleRejectMRQ}
                className="text-sm px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed text-white font-medium rounded"
              >
                {rejectMRQMutation.isPending ? 'Rejecting…' : 'Confirm Reject'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
