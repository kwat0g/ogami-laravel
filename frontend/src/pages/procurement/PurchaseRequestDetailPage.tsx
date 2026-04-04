import { useState } from 'react'
import { useParams, useNavigate, useLocation, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, XCircle, FileText, Pencil } from 'lucide-react'
import {
  usePurchaseRequest,
  useSubmitPurchaseRequest,
  useReviewPurchaseRequest,
  useBudgetCheckPurchaseRequest,
  useVpApprovePurchaseRequest,
  useRejectPurchaseRequest,
  useCancelPurchaseRequest,
  useReturnPurchaseRequest,
} from '@/hooks/usePurchaseRequests'
import { useAuthStore } from '@/stores/authStore'
import ChainRecordTimeline from '@/components/ui/ChainRecordTimeline'
import { SodActionButton } from '@/components/ui/SodActionButton'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import PageHeader from '@/components/ui/PageHeader'
import { ExportPdfButton } from '@/components/ui/ExportPdfButton'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import StatusTimeline from '@/components/ui/StatusTimeline'
import { getPurchaseRequestSteps, isRejectedStatus } from '@/lib/workflowSteps'

// ── Workflow Stage Component ──────────────────────────────────────────────────

function WorkflowStage({
  number,
  label,
  actor,
  timestamp,
  comments,
  isDone,
  isCurrent,
}: {
  number: number
  label: string
  actor: { id: number; name: string } | null | undefined
  timestamp: string | null | undefined
  comments: string | null | undefined
  isDone: boolean
  isCurrent: boolean
}): React.ReactElement {
  return (
    <div className="flex items-start gap-3">
      <div
        className={`mt-0.5 w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-sm font-medium ${
          isDone 
            ? 'bg-green-100 text-green-700' 
            : isCurrent 
              ? 'bg-blue-100 text-blue-700 border-2 border-blue-500'
              : 'bg-neutral-100 text-neutral-400'
        }`}
      >
        {isDone ? (
          <CheckCircle2 className="w-4 h-4" />
        ) : (
          number
        )}
      </div>
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-medium ${isDone || isCurrent ? 'text-neutral-800' : 'text-neutral-400'}`}>
          {label}
        </p>
        {(isDone || isCurrent) && actor && (
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

// ── Reject/Return Modal ───────────────────────────────────────────────────────

function ReasonModal({
  title,
  confirmLabel,
  onConfirm,
  onClose,
  isSubmitting,
}: {
  title: string
  confirmLabel: string
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
          <h3 className="text-base font-semibold">{title}</h3>
        </div>
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          placeholder="Provide a clear reason (min. 10 characters)"
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
            {isSubmitting ? 'Processing…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Comments Modal (for approval actions) ─────────────────────────────────────

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

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function PurchaseRequestDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const backTo = (location.state as { from?: string } | null)?.from ?? '/procurement/purchase-requests'
  const { user, hasPermission } = useAuthStore()

  const { data: pr, isLoading, isError } = usePurchaseRequest(ulid ?? null)

  const submitMutation      = useSubmitPurchaseRequest()
  const reviewMutation      = useReviewPurchaseRequest()
  const budgetCheckMutation = useBudgetCheckPurchaseRequest()
  const vpMutation          = useVpApprovePurchaseRequest()
  const rejectMutation      = useRejectPurchaseRequest()
  const cancelMutation      = useCancelPurchaseRequest()
  const returnMutation      = useReturnPurchaseRequest()

  const [pendingAction, setPendingAction] = useState<
    null | 'review' | 'budget-check' | 'vp-approve' | 'reject' | 'return'
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

  // ── Permission checks ──────────────────────────────────────────────────────
  const isSuperAdmin = user?.roles?.includes('super_admin') ?? false
  const canSelfReviewInDemo = Boolean(
    import.meta.env.DEV
    && user?.roles?.includes('manager')
    && user?.primary_department_code === 'PURCH'
    && user?.id === pr.submitted_by_id,
  )

  // New simplified workflow permissions
  const isOwner        = user?.id === pr.requested_by_id
  const canEdit        = (isSuperAdmin || hasPermission('procurement.purchase-request.create') || hasPermission('procurement.purchase-request.create-dept')) &&
                          ['draft', 'returned'].includes(pr.status) && isOwner
  const canSubmit      = (isSuperAdmin || hasPermission('procurement.purchase-request.create') || hasPermission('procurement.purchase-request.create-dept')) &&
                          pr.status === 'draft' && isOwner
  // Each action mirrors the backend policy exactly — permission + specific status only.
  // VP has procurement.purchase-request.review for viewing, NOT for the review action.
  const isVp           = user?.roles?.includes('vice_president') ?? false
  const canReview      = (isSuperAdmin || (hasPermission('procurement.purchase-request.review') && !isVp)) &&
                          pr.status === 'pending_review'
  const canBudgetCheck = (isSuperAdmin || hasPermission('procurement.purchase-request.budget-check')) &&
                          pr.status === 'reviewed'
  const canVpApprove   = (isSuperAdmin || hasPermission('approvals.vp.approve')) &&
                          pr.status === 'budget_verified'
  const canCancel      = pr.status === 'draft' && (isSuperAdmin || isOwner)
  // Return: Purchasing can return at pending_review, Accounting at reviewed (mirrors policy)
  const canReturn      = (hasPermission('procurement.purchase-request.review') && !isVp && pr.status === 'pending_review') ||
                          (hasPermission('procurement.purchase-request.budget-check') && pr.status === 'reviewed') ||
                          (isSuperAdmin && ['pending_review', 'reviewed'].includes(pr.status))
  // Reject: scoped to the stage the user is responsible for (mirrors policy)
  const canReject      = (hasPermission('procurement.purchase-request.review') && !isVp && pr.status === 'pending_review') ||
                          (hasPermission('procurement.purchase-request.budget-check') && pr.status === 'reviewed') ||
                          (hasPermission('approvals.vp.approve') && pr.status === 'budget_verified') ||
                          (isSuperAdmin && ['pending_review', 'reviewed', 'budget_verified'].includes(pr.status))

  const handleAction = async (
    action: 'review' | 'budget-check' | 'vp-approve',
    comments: string,
  ): Promise<void> => {
    const payload = { ulid: pr.ulid, payload: { comments } }
    try {
      if (action === 'review')       await reviewMutation.mutateAsync(payload)
      if (action === 'budget-check') await budgetCheckMutation.mutateAsync(payload)
      if (action === 'vp-approve')   await vpMutation.mutateAsync(payload)
      toast.success('Action completed successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Action failed. Please try again.'))
    } finally {
      setPendingAction(null)
    }
  }

  const handleReturn = async (reason: string): Promise<void> => {
    try {
      await returnMutation.mutateAsync({
        ulid: pr.ulid,
        payload: { reason },
      })
      toast.success('Purchase Request returned for revision.')
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Return failed. Please try again.'))
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
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Rejection failed. Please try again.'))
    } finally {
      setPendingAction(null)
    }
  }

  const handleCancel = async (): Promise<void> => {
    try {
      await cancelMutation.mutateAsync(pr.ulid)
      toast.success('Purchase Request cancelled.')
      navigate(backTo)
    } catch (err) {
      const message = firstErrorMessage(err)
    }
  }

  // Helper to check if a stage is current
  const isCurrentStage = (stageStatus: string): boolean => pr.status === stageStatus

  return (
    <div className="max-w-7xl mx-auto space-y-6">

      <PageHeader
        backTo={backTo}
        title={pr.pr_reference}
        subtitle={`Requested by ${pr.requested_by?.name} · ${new Date(pr.created_at).toLocaleDateString('en-PH')}`}
        icon={<FileText className="w-5 h-5" />}
        status={<StatusBadge status={pr.status}>{pr.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>}
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            {/* PDF Export — visible only when approved */}
            {['approved', 'converted_to_po'].includes(pr.status) && (
              <ExportPdfButton href={`/api/v1/procurement/purchase-requests/${pr.ulid}/pdf`} />
            )}

            {canEdit && (
              <Link
                to={`/procurement/purchase-requests/${pr.ulid}/edit`}
                className="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 font-medium rounded transition-colors"
              >
                <Pencil className="w-4 h-4" />
                Edit
              </Link>
            )}

            {canSubmit && (
              <button
                onClick={async () => {
                  try {
                    await submitMutation.mutateAsync(pr.ulid)
                    toast.success('Purchase Request submitted for review.')
                  } catch (err) {
                    toast.error(firstErrorMessage(err, 'Submit failed. Please try again.'))
                  }
                }}
                disabled={submitMutation.isPending}
                className="text-sm px-4 py-2 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded"
              >
                {submitMutation.isPending ? 'Submitting…' : 'Submit for Review'}
              </button>
            )}

            {canReview && (
              <SodActionButton
                initiatedById={canSelfReviewInDemo ? null : pr.submitted_by_id}
                label="Review & Approve"
                onClick={() => setPendingAction('review')}
                isLoading={reviewMutation.isPending}
                variant="primary"
              />
            )}

            {canBudgetCheck && (
              <SodActionButton
                initiatedById={pr.reviewed_by_id}
                label="Verify Budget"
                onClick={() => setPendingAction('budget-check')}
                isLoading={budgetCheckMutation.isPending}
                variant="primary"
              />
            )}

            {canVpApprove && (
              <SodActionButton
                initiatedById={pr.budget_checked_by_id}
                label="Final Approve (VP)"
                onClick={() => setPendingAction('vp-approve')}
                isLoading={vpMutation.isPending}
                variant="primary"
              />
            )}

            {canReturn && (
              <button
                onClick={() => setPendingAction('return')}
                className="text-sm px-3 py-2 bg-white text-amber-600 border border-amber-300 hover:bg-amber-50 font-medium rounded transition-colors"
              >
                Return for Revision
              </button>
            )}

            {canReject && (
              <button
                onClick={() => setPendingAction('reject')}
                className="text-sm px-3 py-2 bg-white text-red-600 border border-red-300 hover:bg-red-50 font-medium rounded transition-colors"
              >
                Reject
              </button>
            )}

            {canCancel && (
              <ConfirmDialog
                title="Cancel Purchase Request?"
                description="This will cancel the PR and cannot be undone."
                onConfirm={handleCancel}
              >
                <button
                  disabled={cancelMutation.isPending}
                  className="text-sm px-3 py-2 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 font-medium rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Cancel
                </button>
              </ConfirmDialog>
            )}
          </div>
        }
      />

      {/* Status notices */}
      {pr.status === 'returned' && (
        <div className="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded p-4">
          <AlertTriangle className="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
          <div>
            <p className="text-sm font-semibold text-amber-700">
              Returned for Revision
              {pr.returned_by && ` by ${pr.returned_by.name}`}
            </p>
            {pr.return_reason && (
              <p className="text-sm text-amber-600 mt-1">{pr.return_reason}</p>
            )}
          </div>
        </div>
      )}

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

      {/* Workflow Progress */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-5">
        <StatusTimeline
          steps={getPurchaseRequestSteps(pr)}
          currentStatus={pr.status}
          direction="horizontal"
          isRejected={isRejectedStatus(pr.status)}
        />
      </div>

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
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['Description', 'UoM', 'Qty', 'Unit Cost', 'Total', 'Specs'].map((h) => (
                      <th key={h} className="text-left px-4 py-3 font-medium text-neutral-600">
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
            <CardHeader>Approval Workflow</CardHeader>
            <CardBody>
              <div className="space-y-4">
                <WorkflowStage
                  number={1}
                  label="Submitted for Review"
                  actor={pr.submitted_by}
                  timestamp={pr.submitted_at}
                  comments={null}
                  isDone={!!pr.submitted_at}
                  isCurrent={isCurrentStage('pending_review')}
                />
                <WorkflowStage
                  number={2}
                  label={
                    pr.reviewed_comments === 'Auto-reviewed (Purchasing Manager)'
                      ? 'Purchasing Review (Auto — Manager)'
                      : 'Purchasing Review'
                  }
                  actor={pr.reviewed_by}
                  timestamp={pr.reviewed_at}
                  comments={pr.reviewed_comments === 'Auto-reviewed (Purchasing Manager)' ? null : pr.reviewed_comments}
                  isDone={!!pr.reviewed_at}
                  isCurrent={isCurrentStage('reviewed')}
                />
                <WorkflowStage
                  number={3}
                  label="Budget Verification"
                  actor={pr.budget_checked_by}
                  timestamp={pr.budget_checked_at}
                  comments={pr.budget_checked_comments}
                  isDone={!!pr.budget_checked_at}
                  isCurrent={isCurrentStage('budget_verified')}
                />
                <WorkflowStage
                  number={4}
                  label="VP Final Approval"
                  actor={pr.vp_approved_by}
                  timestamp={pr.vp_approved_at}
                  comments={pr.vp_comments}
                  isDone={!!pr.vp_approved_at}
                  isCurrent={isCurrentStage('approved')}
                />
                {pr.converted_to_po_id && (
                  <WorkflowStage
                    number={5}
                    label="Converted to PO"
                    actor={null}
                    timestamp={pr.converted_at}
                    comments={null}
                    isDone={true}
                    isCurrent={false}
                  />
                )}
              </div>
            </CardBody>
          </Card>

          {/* Current Status Help */}
          <Card>
            <CardHeader>Current Status</CardHeader>
            <CardBody>
              <p className="text-sm text-neutral-600">
                {pr.status === 'draft' && 'This PR is in draft. Submit it to start the approval workflow.'}
                {pr.status === 'pending_review' && (
                  user?.id === pr.submitted_by_id
                    ? canSelfReviewInDemo
                      ? 'You submitted this PR. Demo mode allows Purchasing Manager self-review in local development. Click "Review & Approve" to continue.'
                      : 'You submitted this PR. Due to Segregation of Duties, a different Purchasing Officer must perform the review. Ask a colleague with the Purchasing Officer role to review and approve it.'
                    : 'This PR is awaiting review by the Purchasing Department. Click "Review & Approve" to proceed.'
                )}
                {pr.status === 'reviewed' && (
                  user?.id === pr.reviewed_by_id
                    ? 'You reviewed this PR. Due to Segregation of Duties, a different Accounting Officer must verify the budget.'
                    : 'This PR has been reviewed. An Accounting Officer must now verify the budget.'
                )}
                {pr.status === 'budget_verified' && (
                  user?.id === pr.budget_checked_by_id
                    ? 'You verified the budget. Due to Segregation of Duties, the VP must give final approval.'
                    : 'Budget has been verified. The VP must now give final approval.'
                )}
                {pr.status === 'approved' && 'This PR has been approved.'}
                {pr.status === 'converted_to_po' && 'This PR has been converted to a Purchase Order.'}
                {pr.status === 'returned' && 'This PR has been returned for revision. Update and resubmit.'}
                {pr.status === 'rejected' && 'This PR has been rejected and cannot be processed further.'}
                {pr.status === 'cancelled' && 'This PR has been cancelled.'}
              </p>
            </CardBody>
          </Card>

          {/* Document Chain */}
          <Card>
            <CardHeader>Document Chain</CardHeader>
            <CardBody>
              <ChainRecordTimeline documentType="purchase_request" documentId={pr.id} />
            </CardBody>
          </Card>

          {/* Activity Timeline */}
          <Card>
            <CardHeader>Activity Timeline</CardHeader>
            <CardBody>
              <StatusTimeline auditableType="purchase_request" auditableId={pr.id} />
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Modals */}
      {pendingAction && !['reject', 'return'].includes(pendingAction) && (
        <CommentsModal
          actionLabel={
            pendingAction === 'review'       ? 'Review Purchase Request' :
            pendingAction === 'budget-check' ? 'Budget Check Purchase Request' :
                                               'Final Approve Purchase Request (VP)'
          }
          onConfirm={(comments) => handleAction(pendingAction as 'review' | 'budget-check' | 'vp-approve', comments)}
          onClose={() => setPendingAction(null)}
          isSubmitting={
            reviewMutation.isPending || budgetCheckMutation.isPending || vpMutation.isPending
          }
        />
      )}

      {pendingAction === 'return' && (
        <ReasonModal
          title="Return for Revision"
          confirmLabel="Return"
          onConfirm={handleReturn}
          onClose={() => setPendingAction(null)}
          isSubmitting={returnMutation.isPending}
        />
      )}

      {pendingAction === 'reject' && (
        <ReasonModal
          title="Reject Purchase Request"
          confirmLabel="Reject"
          onConfirm={handleReject}
          onClose={() => setPendingAction(null)}
          isSubmitting={rejectMutation.isPending}
        />
      )}
    </div>
  )
}
