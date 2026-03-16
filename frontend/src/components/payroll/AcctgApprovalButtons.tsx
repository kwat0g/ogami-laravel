import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { XCircle, ThumbsUp, RotateCcw } from 'lucide-react'
import { useAcctgApprove } from '@/hooks/usePayroll'
import { useAuth } from '@/hooks/useAuth'

interface AcctgApprovalButtonsProps {
  runId: string
  initiatedById: number | null
}

const CHECKLIST_ITEMS = [
  'I have reviewed the GL journal entry and confirmed account codes are correct.',
  'I have verified the cash requirement against available fund balance.',
  'I approve the disbursement to proceed to payroll bank processing.',
]

export default function AcctgApprovalButtons({ runId, initiatedById }: AcctgApprovalButtonsProps) {
  const navigate = useNavigate()
  const acctgApprove = useAcctgApprove(runId)
  const { user } = useAuth()

  const [showReturnModal, setShowReturnModal] = useState(false)
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [showApproveModal, setShowApproveModal] = useState(false)
  const [comments, setComments] = useState('')
  const [returnReason, setReturnReason] = useState('')
  const [rejectReason, setRejectReason] = useState('')
  const [checked, setChecked] = useState<Record<number, boolean>>({})

  const allChecked = CHECKLIST_ITEMS.every((_, i) => !!checked[i])
  const isInitiator = user?.id === initiatedById

  // SoD check: user cannot approve a run they initiated
  if (isInitiator) {
    return (
      <div className="text-sm text-amber-600 bg-amber-50 px-4 py-2 rounded">
        You initiated this payroll run. A different user must approve.
      </div>
    )
  }

  async function handleApprove() {
    if (!allChecked) return
    try {
      await acctgApprove.mutateAsync({
        action: 'APPROVED',
        comments: comments || undefined,
        checkboxes_checked: CHECKLIST_ITEMS.filter((_, i) => checked[i]),
      })
      toast.success('Accounting approval recorded. Forwarded to VP for final approval.')
      navigate(`/payroll/runs/${runId}/vp-review`)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'Approval failed.')
    }
  }

  async function handleReturn() {
    if (!returnReason.trim()) {
      toast.error('Please provide a reason for returning.')
      return
    }
    try {
      await acctgApprove.mutateAsync({
        action: 'RETURNED',
        return_comments: returnReason,
      })
      toast.success('Payroll run returned to HR Manager for rework.')
      navigate('/payroll/runs')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'Return failed.')
    }
  }

  async function handleReject() {
    if (!rejectReason.trim()) {
      toast.error('Please provide a reason for rejection.')
      return
    }
    try {
      await acctgApprove.mutateAsync({
        action: 'REJECTED',
        rejection_reason: rejectReason,
      })
      toast.success('Payroll run permanently rejected.')
      navigate('/payroll/runs')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? 'Rejection failed.')
    }
  }

  return (
    <>
      {/* Action Buttons */}
      <div className="flex items-center gap-2">
        <button
          onClick={() => setShowReturnModal(true)}
          disabled={acctgApprove.isPending}
          className="flex items-center gap-2 px-4 py-2 border border-amber-300 text-amber-700 hover:bg-amber-50 text-sm font-medium rounded transition-colors"
        >
          <RotateCcw className="h-4 w-4" />
          Return for Rework
        </button>
        <button
          onClick={() => setShowRejectModal(true)}
          disabled={acctgApprove.isPending}
          className="flex items-center gap-2 px-4 py-2 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium rounded transition-colors"
        >
          <XCircle className="h-4 w-4" />
          Reject
        </button>
        <button
          onClick={() => setShowApproveModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded transition-colors"
        >
          <ThumbsUp className="h-4 w-4" />
          Approve
        </button>
      </div>

      {/* Return for Rework Modal */}
      {showReturnModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="bg-white rounded-lg shadow-lg w-full max-w-md">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-neutral-900 mb-2">Return for Rework</h3>
              <p className="text-sm text-neutral-600 mb-4">
                Return this payroll to the HR Manager for corrections. The payroll can be resubmitted after fixes.
              </p>
              <label className="block text-sm font-medium text-neutral-700 mb-2">
                Reason for Return <span className="text-red-500">*</span>
              </label>
              <textarea
                value={returnReason}
                onChange={(e) => setReturnReason(e.target.value)}
                placeholder="Explain what needs to be corrected..."
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none"
                rows={4}
              />
              <p className="text-xs text-neutral-500 mt-2">
                This note will be visible to the HR Manager so they know what to fix.
              </p>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 bg-neutral-50 rounded-b-lg">
              <button
                onClick={() => { setShowReturnModal(false); setReturnReason('') }}
                className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-100"
              >
                Cancel
              </button>
              <button
                onClick={handleReturn}
                disabled={!returnReason.trim() || acctgApprove.isPending}
                className="px-4 py-2 text-sm bg-amber-600 text-white rounded hover:bg-amber-700 disabled:opacity-50"
              >
                {acctgApprove.isPending ? 'Returning...' : 'Confirm Return'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="bg-white rounded-lg shadow-lg w-full max-w-md">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-red-800 mb-2">Permanently Reject Payroll</h3>
              <p className="text-sm text-red-600 mb-4">
                This action cannot be undone. The payroll run will be permanently rejected and a new run must be started from Step 1.
              </p>
              <label className="block text-sm font-medium text-red-800 mb-2">
                Reason for Rejection <span className="text-red-500">*</span>
              </label>
              <textarea
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder="Explain why the payroll is being rejected..."
                className="w-full border border-red-300 rounded px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"
                rows={4}
              />
              <p className="text-xs text-red-600 mt-2">
                This note will be visible to the HR Manager.
              </p>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 bg-red-50 rounded-b-lg">
              <button
                onClick={() => { setShowRejectModal(false); setRejectReason('') }}
                className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-white"
              >
                Cancel
              </button>
              <button
                onClick={handleReject}
                disabled={!rejectReason.trim() || acctgApprove.isPending}
                className="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
              >
                {acctgApprove.isPending ? 'Rejecting...' : 'Confirm Rejection'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Approve Modal */}
      {showApproveModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="bg-white rounded-lg shadow-lg w-full max-w-md">
            <div className="p-6">
              <h3 className="text-lg font-semibold text-neutral-900 mb-2">Accounting Manager Approval</h3>
              <p className="text-sm text-neutral-600 mb-4">
                Please confirm the following before approving:
              </p>
              <div className="space-y-3 mb-4">
                {CHECKLIST_ITEMS.map((item, i) => (
                  <label key={i} className="flex items-start gap-3 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={!!checked[i]}
                      onChange={() => setChecked((prev) => ({ ...prev, [i]: !prev[i] }))}
                      className="accent-neutral-900 mt-0.5"
                    />
                    <span className="text-sm text-neutral-700">{item}</span>
                  </label>
                ))}
              </div>
              <div className="mb-2">
                <label className="text-sm text-neutral-600">Comments (optional)</label>
                <textarea
                  value={comments}
                  onChange={(e) => setComments(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm mt-1 resize-none focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none"
                  rows={2}
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 bg-neutral-50 rounded-b-lg">
              <button
                onClick={() => { setShowApproveModal(false); setComments('') }}
                className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-white"
              >
                Cancel
              </button>
              <button
                onClick={handleApprove}
                disabled={!allChecked || acctgApprove.isPending}
                className="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
              >
                {acctgApprove.isPending ? 'Processing...' : 'Confirm Approval'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
