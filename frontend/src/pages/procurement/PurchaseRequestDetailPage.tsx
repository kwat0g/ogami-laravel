import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, AlertTriangle, CheckCircle2, XCircle, ShoppingCart, FileText } from 'lucide-react'
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
import StatusBadge from '@/components/ui/StatusBadge'
import PageHeader from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import type { PurchaseRequestStatus } from '@/types/procurement'

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
          isDone ? 'bg-neutral-100' : 'bg-neutral-50'
        }`}
      >
        {isDone ? (
          <CheckCircle2 className="w-4 h-4 text-neutral-600" />
        ) : (
          <div className="w-2 h-2 rounded-full bg-neutral-400" />
        )}
      </div>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium ${isDone ? 'text-neutral-800' : 'text-neutral-400'}`}>
          {label}
        </p>
        {isDone && actor && (
          <p className="text-xs text-neutral-500 mt-0.5">
            {actor.name}
            {timestamp && (
              <span className="ml-2 text-neutral-400">
                {new Date(timestamp).toLocaleDateString('en-PH')}
              </span>
            )}
          </p>
        )}
        {isDone && comments && (
          <p className="text-xs text-neutral-600 mt-1 bg-neutral-50 px-2 py-1 rounded italic">
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
      <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
        <div className="flex items-center gap-2 text-red-600">
          <XCircle className="w-5 h-5" />
          <h3 className="text-base font-semibold">Reject Purchase Request</h3>
        </div>
        <p className="text-sm text-neutral-600">
          Rejecting at <strong>{stage}</strong> stage. Provide a clear reason.
        </p>
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          placeholder="Reason for rejection (min. 10 characters)"
          className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-400 resize-none"
        />
        <div className="flex justify-end gap-3">
          <button
            onClick={onClose}
            className="text-sm px-4 py-2 bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            disabled={reason.length < 10 || isSubmitting}
            onClick={() => onConfirm(reason)}
            className="text-sm px-4 py-2 bg-white text-red-600 border border-red-300 hover:bg-red-50 font-medium rounded disabled:opacity-50 disabled:cursor-not-allowed"
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
      <div className="bg-white rounded max-w-md w-full p-6 space-y-4">
        <h3 className="text-base font-semibold text-neutral-900">{actionLabel}</h3>
        <textarea
          value={comments}
          onChange={(e) => setComments(e.target.value)}
          rows={3}
          placeholder="Optional comments for the next approver"
          className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
        />
        <div className="flex justify-end gap-3">
          <button
            onClick={onClose}
            className="text-sm px-4 py-2 bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            disabled={isSubmitting}
            onClick={() => onConfirm(comments)}
            className="text-sm px-4 py-2 bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed font-medium rounded"
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
  const canCreatePo  = hasPermission('procurement.purchase-order.create') && pr.status === 'approved'
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
    <div className="max-w-7xl mx-auto space-y-6">
      <PageHeader
        backTo="/procurement/purchase-requests"
        title={pr.pr_reference}
        subtitle={`Requested by ${pr.requested_by?.name} · ${new Date(pr.created_at).toLocaleDateString('en-PH')}`}
        icon={<FileText className="w-5 h-5" />}
        status={<StatusBadge status={pr.status}>{pr.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>}
        actions={
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
                className="text-sm px-4 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded"
              >
                {submitMutation.isPending ? 'Submitting…' : 'Submit for Approval'}
              </button>
            )}

            {canCreatePo && (
              <Link
                to={`/procurement/purchase-orders/new?pr_id=${pr.id}`}
                className="inline-flex items-center gap-1.5 text-sm px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
              >
                <ShoppingCart className="w-4 h-4" />
                Create PO
              </Link>
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
                className="text-sm px-3 py-2 bg-white text-red-600 border border-red-300 hover:bg-red-50 font-medium rounded transition-colors"
              >
                Reject
              </button>
            )}

            {canCancel && (
              <button
                onClick={handleCancel}
                disabled={cancelMutation.isPending}
                className="text-sm px-3 py-2 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 font-medium rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Cancel
              </button>
            )}
          </div>
        }
      />

      {/* Rejection notice */}
      {pr.status === 'rejected' && (
        <div className="flex items-start gap-3 bg-red-50 border border-red-200 rounded p-4">
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
          <Card>
            <CardHeader>Request Details</CardHeader>
            <CardBody>
              <InfoList>
                <InfoRow label="Justification" value={pr.justification} />
                {pr.notes && <InfoRow label="Notes" value={pr.notes} />}
                <InfoRow 
                  label="Urgency" 
                  value={
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                      pr.urgency === 'critical' ? 'bg-red-100 text-red-700' :
                      pr.urgency === 'urgent'   ? 'bg-orange-100 text-orange-700' :
                      'bg-neutral-100 text-neutral-500'
                    }`}>
                      {pr.urgency}
                    </span>
                  } 
                />
              </InfoList>
            </CardBody>
          </Card>

          {/* Line Items */}
          <Card>
            <CardHeader>Line Items</CardHeader>
            <CardBody className="p-0">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50">
                  <tr>
                    {['Description', 'UoM', 'Qty', 'Unit Cost', 'Total', 'Specs'].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {pr.items.map((item) => (
                    <tr key={item.id}>
                      <td className="px-4 py-3 text-neutral-800">{item.item_description}</td>
                      <td className="px-4 py-3 text-neutral-600">{item.unit_of_measure}</td>
                      <td className="px-4 py-3 text-neutral-700">{item.quantity}</td>
                      <td className="px-4 py-3 text-neutral-700">
                        ₱{item.estimated_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 font-medium text-neutral-800">
                        ₱{item.estimated_total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                      </td>
                      <td className="px-4 py-3 text-neutral-500 text-xs">{item.specifications ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-neutral-50 border-t border-neutral-200">
                  <tr>
                    <td colSpan={4} className="px-4 py-3 text-right text-sm font-semibold text-neutral-700">
                      Total Estimated Cost
                    </td>
                    <td className="px-4 py-3 font-bold text-neutral-900">
                      ₱{pr.total_estimated_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td />
                  </tr>
                </tfoot>
              </table>
            </CardBody>
          </Card>
        </div>

        {/* Approval Timeline — 1/3 */}
        <div className="space-y-4">
          <Card>
            <CardHeader>Approval Timeline</CardHeader>
            <CardBody>
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
            </CardBody>
          </Card>
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
