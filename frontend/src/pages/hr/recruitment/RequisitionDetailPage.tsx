import { useParams, Link } from 'react-router-dom'
import { useRequisition, useRequisitionAction } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import { useState } from 'react'

export default function RequisitionDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const { data: req, isLoading } = useRequisition(ulid ?? '')
  const action = useRequisitionAction(ulid ?? '')
  const [remarks, setRemarks] = useState('')
  const [reason, setReason] = useState('')

  if (isLoading || !req) return <div className="p-6">Loading...</div>

  const canSubmit = req.status === 'draft' || req.status === 'rejected'
  const canApprove = req.status === 'pending_approval'
  const canCreatePosting = req.status === 'approved' || req.status === 'open'

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-500">{req.requisition_number}</p>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            {req.position?.title ?? 'Requisition'}
          </h1>
          <p className="text-sm text-gray-500">{req.department?.name}</p>
        </div>
        <StatusBadge status={req.status} label={req.status_label} />
      </div>

      {/* Approval Banner */}
      {canApprove && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
          <p className="mb-3 text-sm font-medium text-amber-800">This requisition is awaiting your approval.</p>
          <div className="mb-3">
            <textarea
              placeholder="Remarks (optional for approval, required for rejection)"
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
              rows={2}
            />
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => action.mutate({ action: 'approve', payload: { remarks } })}
              disabled={action.isPending}
              className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-50"
            >
              Approve
            </button>
            <button
              onClick={() => {
                if (!remarks.trim()) { setReason('Reason is required for rejection'); return }
                action.mutate({ action: 'reject', payload: { reason: remarks } })
              }}
              disabled={action.isPending}
              className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50"
            >
              Reject
            </button>
          </div>
          {reason && <p className="mt-2 text-xs text-red-600">{reason}</p>}
        </div>
      )}

      {/* Rejection reason */}
      {req.status === 'rejected' && req.rejection_reason && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-4">
          <p className="text-sm font-medium text-red-800">Rejected: {req.rejection_reason}</p>
        </div>
      )}

      {/* Details */}
      <div className="grid grid-cols-2 gap-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div>
          <p className="text-xs text-gray-500">Employment Type</p>
          <p className="text-sm font-medium">{req.employment_type_label}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Headcount</p>
          <p className="text-sm font-medium">{req.headcount} ({req.hired_count} hired)</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Salary Range</p>
          <p className="text-sm font-medium">
            {req.salary_range_min != null ? `${(req.salary_range_min / 100).toLocaleString()} - ${(req.salary_range_max! / 100).toLocaleString()}` : 'Not specified'}
          </p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Target Start Date</p>
          <p className="text-sm font-medium">{req.target_start_date ?? 'Not specified'}</p>
        </div>
        <div className="col-span-2">
          <p className="text-xs text-gray-500">Reason</p>
          <p className="text-sm">{req.reason}</p>
        </div>
        {req.justification && (
          <div className="col-span-2">
            <p className="text-xs text-gray-500">Justification</p>
            <p className="text-sm">{req.justification}</p>
          </div>
        )}
        <div>
          <p className="text-xs text-gray-500">Requested By</p>
          <p className="text-sm font-medium">{req.requester?.name}</p>
        </div>
        {req.approver && (
          <div>
            <p className="text-xs text-gray-500">Approved By</p>
            <p className="text-sm font-medium">{req.approver.name}</p>
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="flex gap-3">
        {canSubmit && (
          <button
            onClick={() => action.mutate({ action: 'submit' })}
            disabled={action.isPending}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
          >
            Submit for Approval
          </button>
        )}
        {canCreatePosting && (
          <Link
            to={`/hr/recruitment/postings/new?requisition=${ulid}`}
            className="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500"
          >
            Create Job Posting
          </Link>
        )}
        {req.status === 'rejected' && (
          <Link
            to={`/hr/recruitment/requisitions/${ulid}/edit`}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          >
            Edit & Resubmit
          </Link>
        )}
      </div>

      {/* Approval History */}
      {req.approvals && req.approvals.length > 0 && (
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
          <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Approval History</h3>
          <div className="space-y-2">
            {req.approvals.map((a) => (
              <div key={a.id} className="flex items-center justify-between text-sm">
                <div>
                  <span className="font-medium">{a.user.name}</span> - <span className="capitalize">{a.action}</span>
                  {a.remarks && <span className="ml-2 text-gray-500">"{a.remarks}"</span>}
                </div>
                <span className="text-xs text-gray-400">{a.acted_at ? new Date(a.acted_at).toLocaleString() : ''}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
