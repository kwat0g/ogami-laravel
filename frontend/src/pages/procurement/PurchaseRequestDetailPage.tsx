import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, AlertTriangle, CheckCircle2, XCircle } from 'lucide-react'
import {
  usePurchaseRequest,
  useSubmitPurchaseRequest,
  useNotePurchaseRequest,
  useCheckPurchaseRequest,
  useReviewPurchaseRequest,
  useVpApprovePurchaseRequest,
  useRejectPurchaseRequest,
  useCancelPurchaseRequest,
} from '@/hooks/usePurchaseRequests'
import { useAuthStore } from '@/stores/authStore'
import { SodActionButton } from '@/components/ui/SodActionButton'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { PurchaseRequestStatus } from '@/types/procurement'

const statusBadgeClass: Record<PurchaseRequestStatus, string> = {
  draft:          'bg-gray-100 text-gray-600',
  submitted:      'bg-blue-100 text-blue-700',
  noted:          'bg-indigo-100 text-indigo-700',
  checked:        'bg-violet-100 text-violet-700',
  reviewed:       'bg-amber-100 text-amber-700',
  approved:       'bg-green-100 text-green-700',
  rejected:       'bg-red-100 text-red-700',
  cancelled:      'bg-gray-100 text-gray-400',
  converted_to_po: 'bg-teal-100 text-teal-700',
}

// ── Approval stage component ──────────────────────────────────────────────────

function ApprovalStage({
  label,
  actor,
  timestamp,
  comments,
  isDone,
}: {
  label: string
  actor: { id: number; name: string } | null | undefined
  timestamp: string | null | undefined
  comments: string | null | undefined
  isDone: boolean
}): React.ReactElement {
  return (
    <div className="flex items-start gap-3">
      <div
        className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 ${
          isDone ? 'bg-green-100' : 'bg-gray-100'
        }`}
      >
        {isDone ? (
          <CheckCircle2 className="w-4 h-4 text-green-600" />
        ) : (
          <div className="w-2 h-2 rounded-full bg-gray-400" />
        )}
      </div>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium ${isDone ? 'text-gray-800' : 'text-gray-400'}`}>
          {label}
        </p>
        {isDone && actor && (
          <p className="text-xs text-gray-500 mt-0.5">
            {actor.name}
            {timestamp && (
              <span className="ml-2 text-gray-400">
                {new Date(timestamp).toLocaleDateString('en-PH')}
              </span>
            )}
          </p>
        )}
        {isDone && comments && (
          <p className="text-xs text-gray-600 mt-1 bg-gray-50 px-2 py-1 rounded italic">
            "{comments}"
          </p>
        )}
      </div>
    </div>
  )
}

// ── Reject modal ──────────────────────────────────────────────────────────────

function RejectModal({
  stage,
  onConfirm,
  onClose,
  isSubmitting,
}: {
  stage: string
  onConfirm: (reason: string) => void
  onClose: () => void
  isSubmitting: boolean
}): React.ReactElement {
  const [reason, setReason] = useState('')
  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div className="flex items-center gap-2 text-red-600">
          <XCircle className="w-5 h-5" />
          <h3 className="text-base font-semibold">Reject Purchase Request</h3>
        </div>
        <p className="text-sm text-gray-600">
          Rejecting at <strong>{stage}</strong> stage. Provide a clear reason.
        </p>
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          placeholder="Reason for rejection (min. 10 characters)"
          className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"
        />
        <div className="flex justify-end gap-3">
          <button
            onClick={onClose}
            className="text-sm px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            disabled={reason.length < 10 || isSubmitting}
            onClick={() => onConfirm(reason)}
            className="text-sm px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-300 text-white font-medium rounded-lg"
          >
            {isSubmitting ? 'Rejecting…' : 'Confirm Reject'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Comments modal (for approval actions) ─────────────────────────────────────

function CommentsModal({
  actionLabel,
  onConfirm,
  onClose,
  isSubmitting,
}: {
  actionLabel: string
  onConfirm: (comments: string) => void
  onClose: () => void
  isSubmitting: boolean
}): React.ReactElement {
  const [comments, setComments] = useState('')
  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
        <h3 className="text-base font-semibold text-gray-900">{actionLabel}</h3>
        <textarea
          value={comments}
          onChange={(e) => setComments(e.target.value)}
          rows={3}
          placeholder="Optional comments for the next approver"
          className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
        />
        <div className="flex justify-end gap-3">
          <button
            onClick={onClose}
            className="text-sm px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            disabled={isSubmitting}
            onClick={() => onConfirm(comments)}
            className="text-sm px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg"
          >
            {isSubmitting ? 'Processing…' : 'Confirm'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function PurchaseRequestDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { user, hasPermission } = useAuthStore()

  const { data: pr, isLoading, isError } = usePurchaseRequest(ulid ?? null)

  const submitMutation  = useSubmitPurchaseRequest()
  const noteMutation    = useNotePurchaseRequest()
  const checkMutation   = useCheckPurchaseRequest()
  const reviewMutation  = useReviewPurchaseRequest()
  const vpMutation      = useVpApprovePurchaseRequest()
  const rejectMutation  = useRejectPurchaseRequest()
  const cancelMutation  = useCancelPurchaseRequest()

  const [pendingAction, setPendingAction] = useState<
    null | 'note' | 'check' | 'review' | 'vp-approve' | 'reject'
  >(null)

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError || !pr) {
    return (
      <div className="flex items-center gap-2 text-red-600 text-sm mt-4">
        <AlertTriangle className="w-4 h-4" />
        Failed to load purchase request.
      </div>
    )
  }

  // ── Permission checks ────────────────────────────────────────────────────
  const canSubmit  = hasPermission('procurement.purchase-request.create') && pr.status === 'draft'
  const canNote    = hasPermission('procurement.purchase-request.note')   && pr.status === 'submitted'
  const canCheck   = hasPermission('procurement.purchase-request.check')  && pr.status === 'noted'
  const canReview  = hasPermission('procurement.purchase-request.review') && pr.status === 'checked'
  const canVpApprove = hasPermission('approvals.vp.approve')              && pr.status === 'reviewed'
  const isOwner    = user?.id === pr.requested_by_id
  const canCancel  = isOwner && pr.isCancellable

  const handleAction = async (
    action: 'note' | 'check' | 'review' | 'vp-approve',
    comments: string,
  ): Promise<void> => {
    const payload = { ulid: pr.ulid, payload: { comments } }
    try {
      if (action === 'note')       await noteMutation.mutateAsync(payload)
      if (action === 'check')      await checkMutation.mutateAsync(payload)
      if (action === 'review')     await reviewMutation.mutateAsync(payload)
      if (action === 'vp-approve') await vpMutation.mutateAsync(payload)
      toast.success('Action completed successfully.')
    } catch {
      toast.error('Action failed. Please try again.')
    } finally {
      setPendingAction(null)
    }
  }

  const handleReject = async (reason: string): Promise<void> => {
    try {
      await rejectMutation.mutateAsync({
        ulid: pr.ulid,
        payload: { reason, stage: pr.status },
      })
      toast.success('Purchase Request rejected.')
    } catch {
      toast.error('Rejection failed. Please try again.')
    } finally {
      setPendingAction(null)
    }
  }

  const handleCancel = async (): Promise<void> => {
    if (!confirm('Cancel this purchase request?')) return
    try {
      await cancelMutation.mutateAsync(pr.ulid)
      toast.success('Purchase Request cancelled.')
      navigate('/procurement/purchase-requests')
    } catch {
      toast.error('Cancel failed. Please try again.')
    }
  }

  return (
    <div className="max-w-4xl space-y-6">
      {/* Back + Header */}
      <div>
        <Link
          to="/procurement/purchase-requests"
          className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-3"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Purchase Requests
        </Link>
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-gray-900 font-mono">{pr.pr_reference}</h1>
              <span
                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass[pr.status]}`}
              >
                {pr.status.replace(/_/g, ' ')}
              </span>
              <span
                className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                  pr.urgency === 'critical' ? 'bg-red-100 text-red-700' :
                  pr.urgency === 'urgent'   ? 'bg-orange-100 text-orange-700' :
                  'bg-gray-100 text-gray-500'
                }`}
              >
                {pr.urgency}
              </span>
            </div>
            <p className="text-sm text-gray-500 mt-1">
              Requested by <strong>{pr.requested_by?.name}</strong> ·{' '}
              {new Date(pr.created_at).toLocaleDateString('en-PH')}
            </p>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center gap-2">
            {canSubmit && (
              <button
                onClick={async () => {
                  try {
                    await submitMutation.mutateAsync(pr.ulid)
                    toast.success('Purchase Request submitted for approval.')
                  } catch {
                    toast.error('Submit failed. Please try again.')
                  }
                }}
                disabled={submitMutation.isPending}
                className="text-sm px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg"
              >
                {submitMutation.isPending ? 'Submitting…' : 'Submit for Approval'}
              </button>
            )}

            {canNote && (
              <SodActionButton
                initiatedById={pr.submitted_by_id}
                label="Note (Acknowledge)"
                onClick={() => setPendingAction('note')}
                isLoading={noteMutation.isPending}
                variant="primary"
              />
            )}

            {canCheck && (
              <SodActionButton
                initiatedById={pr.noted_by_id}
                label="Check (Verify)"
                onClick={() => setPendingAction('check')}
                isLoading={checkMutation.isPending}
                variant="primary"
              />
            )}

            {canReview && (
              <SodActionButton
                initiatedById={pr.checked_by_id}
                label="Review"
                onClick={() => setPendingAction('review')}
                isLoading={reviewMutation.isPending}
                variant="primary"
              />
            )}

            {canVpApprove && (
              <SodActionButton
                initiatedById={pr.reviewed_by_id}
                label="Final Approve"
                onClick={() => setPendingAction('vp-approve')}
                isLoading={vpMutation.isPending}
                variant="primary"
              />
            )}

            {(canNote || canCheck || canReview || canVpApprove) && (
              <button
                onClick={() => setPendingAction('reject')}
                className="text-sm px-3 py-2 border border-red-300 text-red-600 hover:bg-red-50 font-medium rounded-lg transition-colors"
              >
                Reject
              </button>
            )}

            {canCancel && (
              <button
                onClick={handleCancel}
                disabled={cancelMutation.isPending}
                className="text-sm px-3 py-2 border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium rounded-lg transition-colors"
              >
                Cancel
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Rejection notice */}
      {pr.status === 'rejected' && (
        <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl p-4">
          <XCircle className="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
          <div>
            <p className="text-sm font-semibold text-red-700">
              Rejected at {pr.rejection_stage} stage
              {pr.rejected_by && ` by ${pr.rejected_by.name}`}
            </p>
            {pr.rejection_reason && (
              <p className="text-sm text-red-600 mt-1">{pr.rejection_reason}</p>
            )}
          </div>
        </div>
      )}

      <div className="grid grid-cols-3 gap-6">
        {/* Main content — 2/3 */}
        <div className="col-span-2 space-y-6">
          {/* Details */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
            <h2 className="text-base font-semibold text-gray-800">Request Details</h2>
            <div>
              <p className="text-xs text-gray-500 uppercase tracking-wide">Justification</p>
              <p className="text-sm text-gray-800 mt-1">{pr.justification}</p>
            </div>
            {pr.notes && (
              <div>
                <p className="text-xs text-gray-500 uppercase tracking-wide">Notes</p>
                <p className="text-sm text-gray-700 mt-1">{pr.notes}</p>
              </div>
            )}
          </div>

          {/* Line Items */}
          <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-base font-semibold text-gray-800">Line Items</h2>
            </div>
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50">
                <tr>
                  {['Description', 'UoM', 'Qty', 'Unit Cost', 'Total', 'Specs'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {pr.items.map((item) => (
                  <tr key={item.id}>
                    <td className="px-4 py-3 text-gray-800">{item.item_description}</td>
                    <td className="px-4 py-3 text-gray-600">{item.unit_of_measure}</td>
                    <td className="px-4 py-3 text-gray-700">{item.quantity}</td>
                    <td className="px-4 py-3 text-gray-700">
                      ₱{item.estimated_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="px-4 py-3 font-medium text-gray-800">
                      ₱{item.estimated_total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="px-4 py-3 text-gray-500 text-xs">{item.specifications ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-50 border-t border-gray-200">
                <tr>
                  <td colSpan={4} className="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                    Total Estimated Cost
                  </td>
                  <td className="px-4 py-3 font-bold text-gray-900">
                    ₱{pr.total_estimated_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                  </td>
                  <td />
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {/* Approval Timeline — 1/3 */}
        <div className="space-y-4">
          <div className="bg-white border border-gray-200 rounded-xl p-6">
            <h2 className="text-base font-semibold text-gray-800 mb-4">Approval Timeline</h2>
            <div className="space-y-4">
              <ApprovalStage
                label="1. Submitted by Staff"
                actor={pr.submitted_by}
                timestamp={pr.submitted_at}
                comments={null}
                isDone={!!pr.submitted_at}
              />
              <ApprovalStage
                label="2. Noted by Head"
                actor={pr.noted_by}
                timestamp={pr.noted_at}
                comments={pr.noted_comments}
                isDone={!!pr.noted_at}
              />
              <ApprovalStage
                label="3. Checked by Manager"
                actor={pr.checked_by}
                timestamp={pr.checked_at}
                comments={pr.checked_comments}
                isDone={!!pr.checked_at}
              />
              <ApprovalStage
                label="4. Reviewed by Officer"
                actor={pr.reviewed_by}
                timestamp={pr.reviewed_at}
                comments={pr.reviewed_comments}
                isDone={!!pr.reviewed_at}
              />
              <ApprovalStage
                label="5. Approved by VP"
                actor={pr.vp_approved_by}
                timestamp={pr.vp_approved_at}
                comments={pr.vp_comments}
                isDone={!!pr.vp_approved_at}
              />
              {pr.converted_to_po_id && (
                <ApprovalStage
                  label="6. Converted to PO"
                  actor={null}
                  timestamp={pr.converted_at}
                  comments={null}
                  isDone={true}
                />
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Modals */}
      {pendingAction && pendingAction !== 'reject' && (
        <CommentsModal
          actionLabel={
            pendingAction === 'note'       ? 'Note (Acknowledge) Purchase Request' :
            pendingAction === 'check'      ? 'Check (Verify) Purchase Request' :
            pendingAction === 'review'     ? 'Review Purchase Request' :
                                             'Final Approve Purchase Request'
          }
          onConfirm={(comments) => handleAction(pendingAction as 'note' | 'check' | 'review' | 'vp-approve', comments)}
          onClose={() => setPendingAction(null)}
          isSubmitting={
            noteMutation.isPending || checkMutation.isPending ||
            reviewMutation.isPending || vpMutation.isPending
          }
        />
      )}

      {pendingAction === 'reject' && (
        <RejectModal
          stage={pr.status}
          onConfirm={handleReject}
          onClose={() => setPendingAction(null)}
          isSubmitting={rejectMutation.isPending}
        />
      )}
    </div>
  )
}
