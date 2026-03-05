import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, ClipboardList, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import {
  useMaterialRequisition,
  useSubmitMRQ,
  useNoteMRQ,
  useCheckMRQ,
  useReviewMRQ,
  useVpApproveMRQ,
  useRejectMRQ,
  useCancelMRQ,
  useFulfillMRQ,
} from '@/hooks/useInventory'
import { usePermission } from '@/hooks/usePermission'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { MaterialRequisitionStatus } from '@/types/inventory'

const statusBadge: Record<MaterialRequisitionStatus, string> = {
  draft:      'bg-gray-100 text-gray-600',
  submitted:  'bg-blue-100 text-blue-700',
  noted:      'bg-indigo-100 text-indigo-700',
  checked:    'bg-purple-100 text-purple-700',
  reviewed:   'bg-orange-100 text-orange-700',
  approved:   'bg-green-100 text-green-700',
  rejected:   'bg-red-100 text-red-700',
  cancelled:  'bg-gray-100 text-gray-400',
  fulfilled:  'bg-teal-100 text-teal-700',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-4 py-2 border-b border-gray-100 last:border-0">
      <dt className="text-sm text-gray-500 w-36 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-gray-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

export default function MaterialRequisitionDetailPage(): React.ReactElement {
  const { ulid }   = useParams<{ ulid: string }>()
  const navigate   = useNavigate()
  const [comments, setComments]   = useState('')
  const [reason, setReason]       = useState('')
  const [activeAction, setAction] = useState<string | null>(null)

  const { data: mrq, isLoading, isError } = useMaterialRequisition(ulid ?? null)

  const canNote       = usePermission('inventory.mrq.note')
  const canCheck      = usePermission('inventory.mrq.check')
  const canReview     = usePermission('inventory.mrq.review')
  const canVpApprove  = usePermission('inventory.mrq.vp_approve')
  const canFulfill    = usePermission('inventory.mrq.fulfill')

  const submitMut   = useSubmitMRQ(ulid ?? '')
  const noteMut     = useNoteMRQ(ulid ?? '')
  const checkMut    = useCheckMRQ(ulid ?? '')
  const reviewMut   = useReviewMRQ(ulid ?? '')
  const vpMut       = useVpApproveMRQ(ulid ?? '')
  const rejectMut   = useRejectMRQ(ulid ?? '')
  const cancelMut   = useCancelMRQ(ulid ?? '')
  const fulfillMut  = useFulfillMRQ(ulid ?? '')

  const handleAction = async (action: string) => {
    try {
      switch (action) {
        case 'submit':  await submitMut.mutateAsync();                   break
        case 'note':    await noteMut.mutateAsync({ comments });         break
        case 'check':   await checkMut.mutateAsync({ comments });        break
        case 'review':  await reviewMut.mutateAsync({ comments });       break
        case 'approve': await vpMut.mutateAsync({ comments });           break
        case 'reject':  await rejectMut.mutateAsync({ reason });         break
        case 'cancel':  await cancelMut.mutateAsync();                   break
        case 'fulfill': await fulfillMut.mutateAsync();                  break
      }
      toast.success(`Requisition ${action}d successfully.`)
      setAction(null)
      setComments('')
      setReason('')
    } catch {
      toast.error(`Failed to ${action} requisition.`)
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !mrq) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load requisition.
    </div>
  )

  const status = mrq.status

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <button onClick={() => navigate('/inventory/requisitions')} className="p-2 hover:bg-gray-100 rounded-lg">
          <ArrowLeft className="w-4 h-4 text-gray-500" />
        </button>
        <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
          <ClipboardList className="w-5 h-5 text-teal-600" />
        </div>
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-gray-900 font-mono">{mrq.mr_reference}</h1>
            <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold capitalize ${statusBadge[status]}`}>
              {status}
            </span>
          </div>
          <p className="text-sm text-gray-500 mt-0.5">{mrq.department?.name}</p>
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-gray-200 rounded-xl p-6 mb-5">
        <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Request Information</h2>
        <dl>
          <InfoRow label="Purpose"       value={mrq.purpose} />
          <InfoRow label="Requested By"  value={mrq.requested_by?.name} />
          <InfoRow label="Department"    value={mrq.department?.name} />
          <InfoRow label="Created"       value={mrq.created_at ? new Date(mrq.created_at).toLocaleString('en-PH') : '—'} />
          {mrq.noted_at && <InfoRow label="Noted By" value={`${mrq.noted_by?.name} — ${mrq.noted_comments ?? ''}`} />}
          {mrq.checked_at && <InfoRow label="Checked By" value={`${mrq.checked_by?.name} — ${mrq.checked_comments ?? ''}`} />}
          {mrq.reviewed_at && <InfoRow label="Reviewed By" value={`${mrq.reviewed_by?.name} — ${mrq.reviewed_comments ?? ''}`} />}
          {mrq.vp_approved_at && <InfoRow label="VP Approved" value={`${mrq.vp_approved_by?.name} — ${mrq.vp_comments ?? ''}`} />}
          {mrq.rejected_at && <InfoRow label="Rejected By" value={`${mrq.rejected_by?.name} — ${mrq.rejection_reason ?? ''}`} />}
          {mrq.fulfilled_at && <InfoRow label="Fulfilled By" value={`${mrq.fulfilled_by?.name} on ${new Date(mrq.fulfilled_at).toLocaleDateString('en-PH')}`} />}
        </dl>
      </div>

      {/* Items */}
      <div className="bg-white border border-gray-200 rounded-xl p-6 mb-5">
        <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Items Requested</h2>
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50">
            <tr>
              {['#', 'Item Code', 'Name', 'UOM', 'Qty Requested', 'Qty Issued', 'Remarks'].map((h) => (
                <th key={h} className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(mrq.items ?? []).map((line) => (
              <tr key={line.id}>
                <td className="px-3 py-2 text-gray-400 text-xs">{line.line_order}</td>
                <td className="px-3 py-2 font-mono text-teal-700 font-medium text-xs">{line.item?.item_code ?? `#${line.item_id}`}</td>
                <td className="px-3 py-2 text-gray-800">{line.item?.name ?? '—'}</td>
                <td className="px-3 py-2 text-gray-400 text-xs">{line.item?.unit_of_measure}</td>
                <td className="px-3 py-2 tabular-nums font-semibold">{parseFloat(line.qty_requested).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                <td className="px-3 py-2 tabular-nums text-green-700">
                  {line.qty_issued !== null ? parseFloat(line.qty_issued).toLocaleString('en-PH', { maximumFractionDigits: 4 }) : '—'}
                </td>
                <td className="px-3 py-2 text-gray-400 text-xs">{line.remarks ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Actions */}
      <div className="bg-white border border-gray-200 rounded-xl p-6">
        <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Actions</h2>

        {/* Comment / reason textarea */}
        {(activeAction === 'note' || activeAction === 'check' || activeAction === 'review' || activeAction === 'approve') && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Comments (optional)</label>
            <textarea
              value={comments}
              onChange={(e) => setComments(e.target.value)}
              rows={2}
              className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500"
            />
          </div>
        )}

        {activeAction === 'reject' && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Rejection Reason *</label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={2}
              className="w-full text-sm border border-red-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-400"
              placeholder="Provide a reason for rejection…"
            />
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {status === 'draft' && (
            <button
              onClick={() => activeAction === 'submit' ? handleAction('submit') : setAction('submit')}
              className="px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            >
              Submit for Approval
            </button>
          )}
          {status === 'submitted' && canNote && (
            <button
              onClick={() => activeAction === 'note' ? handleAction('note') : setAction('note')}
              className="px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors"
            >
              {activeAction === 'note' ? 'Confirm Note' : 'Note'}
            </button>
          )}
          {status === 'noted' && canCheck && (
            <button
              onClick={() => activeAction === 'check' ? handleAction('check') : setAction('check')}
              className="px-4 py-2 text-sm font-medium bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors"
            >
              {activeAction === 'check' ? 'Confirm Check' : 'Check'}
            </button>
          )}
          {status === 'checked' && canReview && (
            <button
              onClick={() => activeAction === 'review' ? handleAction('review') : setAction('review')}
              className="px-4 py-2 text-sm font-medium bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors"
            >
              {activeAction === 'review' ? 'Confirm Review' : 'Review'}
            </button>
          )}
          {status === 'reviewed' && canVpApprove && (
            <button
              onClick={() => activeAction === 'approve' ? handleAction('approve') : setAction('approve')}
              className="px-4 py-2 text-sm font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
            >
              {activeAction === 'approve' ? 'Confirm Approve' : 'VP Approve'}
            </button>
          )}
          {status === 'approved' && canFulfill && (
            <button
              onClick={() => handleAction('fulfill')}
              disabled={fulfillMut.isPending}
              className="px-4 py-2 text-sm font-medium bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition-colors disabled:opacity-50"
            >
              Fulfill (Issue Stock)
            </button>
          )}
          {['submitted', 'noted', 'checked', 'reviewed'].includes(status) && (
            <button
              onClick={() => activeAction === 'reject' ? handleAction('reject') : setAction('reject')}
              disabled={rejectMut.isPending}
              className="px-4 py-2 text-sm font-medium border border-red-300 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
            >
              {activeAction === 'reject' ? 'Confirm Rejection' : 'Reject'}
            </button>
          )}
          {mrq.is_cancellable && (
            <button
              onClick={() => handleAction('cancel')}
              disabled={cancelMut.isPending}
              className="px-4 py-2 text-sm font-medium border border-gray-300 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"
            >
              Cancel
            </button>
          )}
          {activeAction && (
            <button
              onClick={() => { setAction(null); setComments(''); setReason('') }}
              className="px-4 py-2 text-sm font-medium border border-gray-300 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors"
            >
              Cancel Action
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
