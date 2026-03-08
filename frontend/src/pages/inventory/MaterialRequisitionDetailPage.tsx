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
  useWarehouseLocations,
} from '@/hooks/useInventory'
import { usePermission } from '@/hooks/usePermission'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { MaterialRequisitionStatus } from '@/types/inventory'

const statusBadge: Record<MaterialRequisitionStatus, string> = {
  draft:      'bg-neutral-100 text-neutral-600',
  submitted:  'bg-neutral-100 text-neutral-700',
  noted:      'bg-neutral-100 text-neutral-700',
  checked:    'bg-neutral-100 text-neutral-700',
  reviewed:   'bg-neutral-100 text-neutral-700',
  approved:   'bg-neutral-200 text-neutral-800',
  rejected:   'bg-neutral-100 text-neutral-400',
  cancelled:  'bg-neutral-100 text-neutral-400',
  fulfilled:  'bg-neutral-200 text-neutral-800',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-4 py-2 border-b border-neutral-100 last:border-0">
      <dt className="text-sm text-neutral-500 w-36 flex-shrink-0">{label}</dt>
      <dd className="text-sm text-neutral-900 font-medium">{value ?? '—'}</dd>
    </div>
  )
}

export default function MaterialRequisitionDetailPage(): React.ReactElement {
  const { ulid }   = useParams<{ ulid: string }>()
  const navigate   = useNavigate()
  const [comments, setComments]   = useState('')
  const [reason, setReason]       = useState('')
  const [activeAction, setAction] = useState<string | null>(null)
  const [locationId, setLocationId] = useState<number | ''>('')

  const { data: locationsData } = useWarehouseLocations({})

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
        case 'fulfill': await fulfillMut.mutateAsync({ location_id: locationId as number }); break
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
        <button onClick={() => navigate('/inventory/requisitions')} className="p-2 hover:bg-neutral-100 rounded">
          <ArrowLeft className="w-4 h-4 text-neutral-500" />
        </button>
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-neutral-900 font-mono">{mrq.mr_reference}</h1>
            <span className={`inline-flex px-2.5 py-1 rounded text-xs font-medium capitalize ${statusBadge[status]}`}>
              {status}
            </span>
          </div>
          <p className="text-sm text-neutral-500 mt-0.5">{mrq.department?.name}</p>
        </div>
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Request Information</h2>
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
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Items Requested</h2>
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50">
            <tr>
              {['#', 'Item Code', 'Name', 'UOM', 'Qty Requested', 'Qty Issued', 'Remarks'].map((h) => (
                <th key={h} className="px-3 py-2 text-left text-xs font-medium text-neutral-600">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {(mrq.items ?? []).map((line) => (
              <tr key={line.id}>
                <td className="px-3 py-2 text-neutral-400 text-xs">{line.line_order}</td>
                <td className="px-3 py-2 font-mono text-neutral-900 font-medium text-xs">{line.item?.item_code ?? `#${line.item_id}`}</td>
                <td className="px-3 py-2 text-neutral-800">{line.item?.name ?? '—'}</td>
                <td className="px-3 py-2 text-neutral-400 text-xs">{line.item?.unit_of_measure}</td>
                <td className="px-3 py-2 tabular-nums font-medium">{parseFloat(line.qty_requested).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                <td className="px-3 py-2 tabular-nums text-neutral-700">
                  {line.qty_issued !== null ? parseFloat(line.qty_issued).toLocaleString('en-PH', { maximumFractionDigits: 4 }) : '—'}
                </td>
                <td className="px-3 py-2 text-neutral-400 text-xs">{line.remarks ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Actions */}
      <div className="bg-white border border-neutral-200 rounded p-6">
        <h2 className="text-sm font-medium text-neutral-700 mb-4">Actions</h2>

        {/* Comment / reason textarea */}
        {(activeAction === 'note' || activeAction === 'check' || activeAction === 'review' || activeAction === 'approve') && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-neutral-700 mb-1">Comments (optional)</label>
            <textarea
              value={comments}
              onChange={(e) => setComments(e.target.value)}
              rows={2}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            />
          </div>
        )}

        {activeAction === 'reject' && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-neutral-700 mb-1">Rejection Reason *</label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={2}
              className="w-full text-sm border border-red-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-400"
              placeholder="Provide a reason for rejection…"
            />
          </div>
        )}

        {activeAction === 'fulfill' && (
          <div className="mb-4">
            <label className="block text-sm font-medium text-neutral-700 mb-1">Issue From Location *</label>
            <select
              value={locationId}
              onChange={(e) => setLocationId(e.target.value === '' ? '' : Number(e.target.value))}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            >
              <option value="">Select warehouse location…</option>
              {(locationsData ?? []).map((loc) => (
                <option key={loc.id} value={loc.id}>{loc.name}</option>
              ))}
            </select>
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {status === 'draft' && (
            <button
              onClick={() => activeAction === 'submit' ? handleAction('submit') : setAction('submit')}
              className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded"
            >
              Submit for Approval
            </button>
          )}
          {status === 'submitted' && canNote && (
            <button
              onClick={() => activeAction === 'note' ? handleAction('note') : setAction('note')}
              className="px-4 py-2 text-sm font-medium bg-neutral-800 hover:bg-neutral-700 text-white rounded"
            >
              {activeAction === 'note' ? 'Confirm Note' : 'Note'}
            </button>
          )}
          {status === 'noted' && canCheck && (
            <button
              onClick={() => activeAction === 'check' ? handleAction('check') : setAction('check')}
              className="px-4 py-2 text-sm font-medium bg-neutral-800 hover:bg-neutral-700 text-white rounded"
            >
              {activeAction === 'check' ? 'Confirm Check' : 'Check'}
            </button>
          )}
          {status === 'checked' && canReview && (
            <button
              onClick={() => activeAction === 'review' ? handleAction('review') : setAction('review')}
              className="px-4 py-2 text-sm font-medium bg-neutral-800 hover:bg-neutral-700 text-white rounded"
            >
              {activeAction === 'review' ? 'Confirm Review' : 'Review'}
            </button>
          )}
          {status === 'reviewed' && canVpApprove && (
            <button
              onClick={() => activeAction === 'approve' ? handleAction('approve') : setAction('approve')}
              className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded"
            >
              {activeAction === 'approve' ? 'Confirm Approve' : 'VP Approve'}
            </button>
          )}
          {status === 'approved' && canFulfill && (
            <button
              onClick={() => activeAction === 'fulfill' ? handleAction('fulfill') : setAction('fulfill')}
              disabled={fulfillMut.isPending || (activeAction === 'fulfill' && !locationId)}
              className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50"
            >
              {activeAction === 'fulfill' ? 'Confirm Fulfill' : 'Fulfill (Issue Stock)'}
            </button>
          )}
          {['submitted', 'noted', 'checked', 'reviewed'].includes(status) && (
            <button
              onClick={() => activeAction === 'reject' ? handleAction('reject') : setAction('reject')}
              disabled={rejectMut.isPending}
              className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-600 hover:bg-neutral-50 rounded"
            >
              {activeAction === 'reject' ? 'Confirm Rejection' : 'Reject'}
            </button>
          )}
          {mrq.is_cancellable && (
            <button
              onClick={() => handleAction('cancel')}
              disabled={cancelMut.isPending}
              className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-500 hover:bg-neutral-50 rounded"
            >
              Cancel
            </button>
          )}
          {activeAction && (
            <button
              onClick={() => { setAction(null); setComments(''); setReason('') }}
              className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-600 hover:bg-neutral-50 rounded"
            >
              Cancel Action
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
