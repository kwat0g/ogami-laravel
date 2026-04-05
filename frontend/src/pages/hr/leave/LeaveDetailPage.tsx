import { useParams, Link } from 'react-router-dom'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useLeaveRequest,
  useHeadApproveLeaveRequest,
  useManagerApproveLeaveRequest,
  useHrApproveLeaveRequest,
  useVpApproveLeaveRequest,
  useRejectLeaveRequest,
} from '@/hooks/useLeave'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { toast } from 'sonner'
import { useState } from 'react'
import StatusBadge from '@/components/ui/StatusBadge'

export default function LeaveDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { data: request, isLoading, isError } = useLeaveRequest(id ? Number(id) : null)
  const hasPermission = useAuthStore((s) => s.hasPermission)
  const headApprove = useHeadApproveLeaveRequest()
  const managerApprove = useManagerApproveLeaveRequest()
  const hrApprove = useHrApproveLeaveRequest()
  const vpApprove = useVpApproveLeaveRequest()
  const reject = useRejectLeaveRequest()
  const [remarks, setRemarks] = useState('')
  const [rejectionReason, setRejectionReason] = useState('')
  const [showRejectModal, setShowRejectModal] = useState(false)

  if (isLoading) {
    return <div className="p-6">Loading leave request...</div>
  }

  if (isError || !request) {
    return <div className="p-6 text-red-700">Leave request not found or could not be loaded.</div>
  }

  const canHeadApprove = hasPermission(PERMISSIONS.leaves.head_approve) && request.requester_type === 'staff' && request.status === 'submitted'
  const canManagerApprove = hasPermission(PERMISSIONS.leaves.manager_approve) && request.requester_type === 'head_officer' && request.status === 'submitted'
  const canHrApprove = hasPermission(PERMISSIONS.leaves.hr_approve) && (
    (request.requester_type === 'staff' && request.status === 'head_approved')
    || (request.requester_type === 'head_officer' && request.status === 'manager_approved')
    || (request.requester_type === 'dept_manager' && request.status === 'submitted')
  )
  const canVpApprove = hasPermission(PERMISSIONS.leaves.vp_approve) && (
    (request.requester_type === 'dept_manager' && request.status === 'hr_approved')
    || (request.requester_type === 'hr_manager' && request.status === 'submitted')
  )
  const canReject = !['approved', 'rejected', 'cancelled'].includes(request.status)

  const handleApprove = async () => {
    try {
      if (canHeadApprove) {
        await headApprove.mutateAsync({ id: request.id, remarks })
        toast.success('Leave request approved by department head.')
      } else if (canManagerApprove) {
        await managerApprove.mutateAsync({ id: request.id, remarks })
        toast.success('Leave request approved by department manager.')
      } else if (canHrApprove) {
        await hrApprove.mutateAsync({ id: request.id, remarks })
        toast.success('Leave request approved by HR.')
      } else if (canVpApprove) {
        await vpApprove.mutateAsync({ id: request.id, remarks })
        toast.success('Leave request approved by VP.')
      }
    } catch {
      // handled by global error path
    }
  }

  const handleReject = async () => {
    try {
      await reject.mutateAsync({ id: request.id, remarks: rejectionReason })
      toast.success('Leave request rejected.')
      setShowRejectModal(false)
      setRejectionReason('')
    } catch {
      // handled by global error path
    }
  }

  const approveLabel = canHeadApprove
    ? 'Head Approve'
    : canManagerApprove
      ? 'Manager Approve'
      : canHrApprove
        ? 'HR Approve'
        : 'VP Approve'

  return (
    <div className="p-6 space-y-6 max-w-4xl mx-auto">
      <PageHeader title={`Leave Request #${request.id}`}>
        <Link to="/hr/leave" className="text-sm text-blue-600 hover:underline">
          Back to List
        </Link>
      </PageHeader>

      <div className="flex items-center gap-3">
        <StatusBadge status={request.status}>{request.status.replace(/_/g, ' ').toUpperCase()}</StatusBadge>
        <span className="text-sm text-neutral-500">Workflow: {request.requester_type.replace(/_/g, ' ')}</span>
      </div>

      <div className="bg-white rounded-lg border border-neutral-200 p-5 space-y-4">
        <h3 className="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Request Details</h3>
        <dl className="grid grid-cols-2 gap-4 text-sm">
          <div>
            <dt className="text-neutral-500">Employee</dt>
            <dd className="font-medium">{request.employee?.full_name ?? `Employee #${request.employee_id}`}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">Leave Type</dt>
            <dd className="font-medium">{request.leave_type?.name ?? request.leave_type_id}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">Start Date</dt>
            <dd className="font-medium">{request.date_from}</dd>
          </div>
          <div>
            <dt className="text-neutral-500">End Date</dt>
            <dd className="font-medium">{request.date_to}</dd>
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

      <div className="bg-white rounded-lg border border-neutral-200 p-5 space-y-4">
        <h3 className="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Actions</h3>

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
          {(canHeadApprove || canManagerApprove || canHrApprove || canVpApprove) && (
            <button
              onClick={handleApprove}
              disabled={headApprove.isPending || managerApprove.isPending || hrApprove.isPending || vpApprove.isPending}
              className="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {approveLabel}
            </button>
          )}

          {canReject && (
            <button
              onClick={() => setShowRejectModal(true)}
              className="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700"
            >
              Reject
            </button>
          )}
        </div>
      </div>

      {showRejectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white rounded-lg p-6 max-w-md w-full shadow-xl">
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
