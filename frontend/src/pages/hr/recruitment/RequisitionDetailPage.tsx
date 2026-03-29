import { useParams, Link } from 'react-router-dom'
import { useRequisition, useRequisitionAction } from '@/hooks/useRecruitment'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useState } from 'react'
import { toast } from 'sonner'

export default function RequisitionDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: req, isLoading } = useRequisition(ulid ?? '')
  const action = useRequisitionAction(ulid ?? '')
  const [remarks, setRemarks] = useState('')

  if (isLoading) return <SkeletonLoader rows={8} />
  if (!req) return <p className="text-sm text-neutral-400 p-6">Requisition not found.</p>

  const canSubmit = req.status === 'draft' || req.status === 'rejected'
  const canApprove = req.status === 'pending_approval'
  const canCreatePosting = req.status === 'approved' || req.status === 'open'

  const handleAction = async (act: string, payload?: Record<string, unknown>) => {
    try {
      await action.mutateAsync({ action: act, payload })
      toast.success(`Requisition ${act} successfully`)
    } catch {
      toast.error(`Failed to ${act} requisition`)
    }
  }

  return (
    <div>
      <PageHeader
        title={req.position?.title ?? 'Requisition'}
        subtitle={`${req.requisition_number} - ${req.department?.name}`}
        backTo="/hr/recruitment?tab=requisitions"
        status={<StatusBadge status={req.status}>{req.status_label}</StatusBadge>}
        actions={
          <div className="flex items-center gap-2">
            {canSubmit && (
              <button onClick={() => handleAction('submit')} disabled={action.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 disabled:opacity-50 transition-colors">
                Submit for Approval
              </button>
            )}
            {canCreatePosting && (
              <Link to={`/hr/recruitment/postings/new?requisition=${ulid}`}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors">
                Create Job Posting
              </Link>
            )}
            {req.status === 'rejected' && (
              <Link to={`/hr/recruitment/requisitions/${ulid}/edit`}
                className="px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
                Edit & Resubmit
              </Link>
            )}
          </div>
        }
      />

      {/* Approval Banner */}
      {canApprove && (
        <Card className="mb-6 border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20">
          <CardBody>
            <p className="mb-3 text-sm font-medium text-amber-800 dark:text-amber-400">This requisition is awaiting your approval.</p>
            <textarea
              placeholder="Remarks (optional for approval, required for rejection)"
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              className="w-full mb-3 px-3 py-2 text-sm border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
              rows={2}
            />
            <div className="flex gap-3">
              <button onClick={() => handleAction('approve', { remarks })} disabled={action.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                Approve
              </button>
              <button onClick={() => { if (!remarks.trim()) { toast.error('Reason required for rejection'); return; } handleAction('reject', { reason: remarks }) }} disabled={action.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors">
                Reject
              </button>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Rejection banner */}
      {req.status === 'rejected' && req.rejection_reason && (
        <Card className="mb-6 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20">
          <CardBody>
            <p className="text-sm font-medium text-red-800 dark:text-red-400">Rejected: {req.rejection_reason}</p>
          </CardBody>
        </Card>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Details */}
        <Card className="lg:col-span-2">
          <CardHeader>Requisition Details</CardHeader>
          <CardBody>
            <div className="grid grid-cols-2 gap-y-4 gap-x-8">
              <div>
                <p className="text-xs text-neutral-500">Employment Type</p>
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{req.employment_type_label}</p>
              </div>
              <div>
                <p className="text-xs text-neutral-500">Headcount</p>
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{req.headcount} ({req.hired_count} hired)</p>
              </div>
              <div>
                <p className="text-xs text-neutral-500">Salary Range</p>
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                  {req.salary_range_min != null
                    ? `${(req.salary_range_min / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })} - ${(req.salary_range_max! / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}`
                    : 'Not specified'}
                </p>
              </div>
              <div>
                <p className="text-xs text-neutral-500">Target Start Date</p>
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{req.target_start_date ?? 'Not specified'}</p>
              </div>
              <div className="col-span-2">
                <p className="text-xs text-neutral-500">Reason</p>
                <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-line">{req.reason}</p>
              </div>
              {req.justification && (
                <div className="col-span-2">
                  <p className="text-xs text-neutral-500">Justification</p>
                  <p className="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-line">{req.justification}</p>
                </div>
              )}
              <div>
                <p className="text-xs text-neutral-500">Requested By</p>
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{req.requester?.name}</p>
              </div>
              {req.approver && (
                <div>
                  <p className="text-xs text-neutral-500">Approved By</p>
                  <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{req.approver.name}</p>
                </div>
              )}
            </div>
          </CardBody>
        </Card>

        {/* Approval History */}
        <Card>
          <CardHeader>Approval History</CardHeader>
          <CardBody className="p-0">
            <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {req.approvals && req.approvals.length > 0 ? (
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                req.approvals.map((a: any) => (
                  <div key={a.id} className="px-5 py-3">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium text-neutral-900 dark:text-neutral-100">{a.user.name}</span>
                      <StatusBadge status={a.action}>{a.action}</StatusBadge>
                    </div>
                    {a.remarks && <p className="mt-1 text-xs text-neutral-500">"{a.remarks}"</p>}
                    <p className="text-xs text-neutral-400">{a.acted_at ? new Date(a.acted_at).toLocaleString() : ''}</p>
                  </div>
                ))
              ) : (
                <p className="px-5 py-4 text-sm text-neutral-400">No approval history yet.</p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
