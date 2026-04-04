/**
 * M14 FIX: Leave request detail page.
 *
 * Supervisors need to view leave request details before approving.
 * Previously, this route was missing from the router.
 */
import { useParams, useNavigate, Link } from 'react-router-dom'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useLeaveRequest,
  useHeadApproveLeaveRequest,
  useManagerCheckLeaveRequest,
  useRejectLeaveRequest,
} from '@/hooks/useLeave'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { useState } from 'react'

export default function LeaveDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data: request, isLoading, isError } = useLeaveRequest(id ? Number(id) : null)
  const hasPermission = useAuthStore((s) => s.hasPermission)
  const headApprove = useHeadApproveLeaveRequest()
  const managerCheck = useManagerCheckLeaveRequest()
  const reject = useRejectLeaveRequest()
  const [remarks, setRemarks] = useState('')
  const [rejectionReason, setRejectionReason] = useState('')
  const [showRejectModal, setShowRejectModal] = useState(false)

  if (isLoading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-neutral-200 rounded w-1/3" />
          <div className="h-32 bg-neutral-200 rounded" />
        </div>
      </div>
    )
  }

  if (isError || !request) {
    return (
      <div className="p-6">
        <PageHeader title="Leave Request" />
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
          Leave request not found or could not be loaded.
        </div>
      </div>
    )
  }

  const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    head_approved: 'bg-blue-100 text-blue-800',
    manager_checked: 'bg-indigo-100 text-indigo-800',
    ga_processed: 'bg-purple-100 text-purple-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
  }

  const handleHeadApprove = async () => {
    try {
      await headApprove.mutateAsync({ id: request.id, remarks })
      toast.success('Leave request approved (Head).')
    } catch {
    }
  }

  const handleManagerCheck = async () => {
    try {
      await managerCheck.mutateAsync({ id: request.id, remarks })
      toast.success('Leave request checked (Manager).')
    } catch {
    }
  }

  const handleReject = async () => {
    try {
      await reject.mutateAsync({ id: request.id, reason: rejectionReason })
      toast.success('Leave request rejected.')
      setShowRejectModal(false)
    } catch {
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-4xl mx-auto">
      <PageHeader title={`Leave Request #${request.id}`}>
        <Link to="/hr/leave" className="text-sm text-blue-600 hover:underline">
          Back to List
        </Link>
      </PageHeader>

      {/* Status Badge */}
      <div className="flex items-center gap-3">
        <span className={`px-3 py-1 rounded-full text-xs font-medium ${statusColors[request.status] ?? 'bg-neutral-100 text-neutral-700'}`}>
          {request.status?.replace(/_/g, ' ').toUpperCase()}
        </span>
      </div>

      {/* Request Details */}
      <div className="bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-5 space-y-4">
        <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wide">Request Details</h3>
        <dl className="grid grid-cols-2 gap-4 text-sm">
          <div>
            <dt className="text-neutral-500">Employee</dt>
            <dd className="font-medium">{request.employee_name ?? `Employee #${request.employee_id}`}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">Leave Type</dt>
            <dd className="font-medium">{request.leave_type_name ?? request.leave_type_id}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">Start Date</dt>
            <dd className="font-medium">{request.start_date}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">End Date</dt>
            <dd className="font-medium">{request.end_date}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">Days</dt>
            <dd className="font-medium">{request.total_days ?? '-'}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">Reason</dt>
            <dd className="font-medium">{request.reason ?? '-'}</dd>
          </div>
        </dl>
      </div>

      {/* Approval Actions */}
      <div className="bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-5 space-y-4">
        <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wide">Actions</h3>

        <div className="space-y-2">
          <label className="block text-sm text-neutral-600">Remarks (optional)</label>
          <textarea
            className="w-full border rounded p-2 text-sm"
            rows={2}
            value={remarks}
            onChange={(e) => setRemarks(e.target.value)}
            placeholder="Add remarks..."
          />
        </div>

        <div className="flex gap-3">
          {request.status === 'pending' && hasPermission('hr.full_access') && (
            <button
              onClick={handleHeadApprove}
              disabled={headApprove.isPending}
              className="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {headApprove.isPending ? 'Approving...' : 'Head Approve'}
            </button>
          )}

          {request.status === 'head_approved' && hasPermission('hr.full_access') && (
            <button
              onClick={handleManagerCheck}
              disabled={managerCheck.isPending}
              className="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 disabled:opacity-50"
            >
              {managerCheck.isPending ? 'Checking...' : 'Manager Check'}
            </button>
          )}

          {!['approved', 'rejected'].includes(request.status) && hasPermission('hr.full_access') && (
            <button
              onClick={() => setShowRejectModal(true)}
              className="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700"
            >
              Reject
            </button>
          )}
        </div>
      </div>

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-neutral-800 rounded-lg p-6 max-w-md w-full shadow-xl">
            <h3 className="text-lg font-semibold mb-3">Reject Leave Request</h3>
            <textarea
              className="w-full border rounded p-2 text-sm mb-3"
              rows={3}
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              placeholder="Reason for rejection (required)..."
            />
            <div className="flex justify-end gap-3">
              <button
                onClick={() => setShowRejectModal(false)}
                className="px-4 py-2 text-sm border rounded hover:bg-neutral-100"
              >
                Cancel
              </button>
              <button
                onClick={handleReject}
                disabled={!rejectionReason.trim() || reject.isPending}
                className="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 disabled:opacity-50"
              >
                {reject.isPending ? 'Rejecting...' : 'Confirm Reject'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
