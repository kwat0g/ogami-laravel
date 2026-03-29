import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { AlertTriangle, AlertCircle, ShoppingCart } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { toast } from 'sonner'
import api, { isHandledApiError } from '@/lib/api'
import StatusTimeline from '@/components/ui/StatusTimeline'
import { getMaterialRequisitionSteps, isRejectedStatus } from '@/lib/workflowSteps'
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
  useStockBalances,
} from '@/hooks/useInventory'
import { useConvertMrqToPr } from '@/hooks/usePurchaseRequests'
import { usePermission } from '@/hooks/usePermission'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'

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
  const [stockWarnings, setStockWarnings] = useState<string[]>([])
  const [showStockWarning, setShowStockWarning] = useState(false)
  const [stockOverrideReason, setStockOverrideReason] = useState('')

  const { data: locationsData } = useWarehouseLocations({})

  // Fetch stock at the selected location so staff can preview availability
  // even before they open the fulfill panel.
  const { data: stockData } = useStockBalances(
    locationId ? { location_id: locationId as number, per_page: 200 } : {}
  )
  const stockMap = new Map<number, number>(
    (stockData?.data ?? []).map((sb) => [sb.item_id, parseFloat(sb.quantity_on_hand)])
  )

  const { data: mrq, isLoading, isError } = useMaterialRequisition(ulid ?? null)

  const canNote           = usePermission('inventory.mrq.note')
  const canCheck          = usePermission('inventory.mrq.check')
  const canReview         = usePermission('inventory.mrq.review')
  const canVpApprove      = usePermission('inventory.mrq.vp_approve')
  const canFulfill        = usePermission('inventory.mrq.fulfill')
  const canCreate         = usePermission('inventory.mrq.create')
  const canCreatePr       = usePermission('procurement.purchase-request.create')
  
  const submitMut      = useSubmitMRQ(ulid ?? '')
  const noteMut        = useNoteMRQ(ulid ?? '')
  const checkMut       = useCheckMRQ(ulid ?? '')
  const reviewMut      = useReviewMRQ(ulid ?? '')
  const vpMut          = useVpApproveMRQ(ulid ?? '')
  const rejectMut      = useRejectMRQ(ulid ?? '')
  const cancelMut      = useCancelMRQ(ulid ?? '')
  const fulfillMut     = useFulfillMRQ(ulid ?? '')
  const convertToPrMut = useConvertMrqToPr()

  const handleSubmitWithStockCheck = async () => {
    if (!mrq) return
    try {
      // Fetch all stock balances (no location filter) to aggregate total qty per item
      const res = await api.get<{ data: { item_id: number; quantity_on_hand: string }[] }>(
        '/inventory/stock-balances',
        { params: { per_page: 500 } }
      )
      const totals = new Map<number, number>()
      for (const sb of res.data.data) {
        const prev = totals.get(sb.item_id) ?? 0
        totals.set(sb.item_id, prev + parseFloat(sb.quantity_on_hand))
      }
      const short = (mrq.items ?? []).filter(
        (line) => (totals.get(line.item_id) ?? 0) < parseFloat(line.qty_requested)
      ).map((line) => line.item?.name ?? `Item #${line.item_id}`)

      if (short.length > 0) {
        setStockWarnings(short)
        setShowStockWarning(true)
        return // pause — wait for user confirmation
      }
    } catch {
      // Stock check failed — don't block the submit, just proceed
    }
    handleAction('submit')
  }

  const handleAction = async (action: string) => {
    try {
      switch (action) {
        case 'submit':  await submitMut.mutateAsync(stockOverrideReason ? { stock_override_reason: stockOverrideReason } : undefined); break
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
      setStockOverrideReason('')
    } catch (_err) {
      if (isHandledApiError(err)) return
      const msg = (err as { message?: string })?.message
      toast.error(msg ?? `Failed to ${action} requisition.`)
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !mrq) return (
    <div className="flex items-center gap-2 text-red-600 text-sm">
      <AlertTriangle className="w-4 h-4" /> Failed to load requisition.
    </div>
  )

  const status = mrq.status

  // Check for stock availability when fulfilling
  const getStockShortages = (): string[] => {
    if (!mrq || !locationId) return []
    return (mrq.items ?? []).filter(
      (line) => (stockMap.get(line.item_id) ?? 0) < parseFloat(line.qty_requested)
    ).map((line) => line.item?.name ?? `Item #${line.item_id}`)
  }

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        title={mrq.mr_reference}
        subtitle={mrq.department?.name}
        backTo="/inventory/requisitions"
        status={
          <span className={`inline-flex px-2.5 py-1 rounded text-xs font-medium capitalize ${statusBadge[status]}`}>
            {status}
          </span>
        }
      />

      {/* Workflow Timeline */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-5">
        <StatusTimeline
          steps={getMaterialRequisitionSteps(mrq)}
          currentStatus={mrq.status}
          direction="horizontal"
          isRejected={isRejectedStatus(mrq.status)}
        />
      </div>

      {/* Details */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <h2 className="text-sm font-medium text-neutral-700 mb-3">Request Information</h2>
        <dl>
          <InfoRow 
            label="Purpose" 
            value={
              mrq.production_order ? (
                <span>
                  {mrq.purpose?.replace(mrq.production_order.po_reference, '')}
                  <Link 
                    to={`/production/orders/${mrq.production_order.ulid}`}
                    className="underline underline-offset-2 text-neutral-700 hover:text-neutral-900 font-medium"
                  >
                    {mrq.production_order.po_reference}
                  </Link>
                </span>
              ) : mrq.purpose
            } 
          />
          <InfoRow label="Requested By"  value={mrq.requested_by?.name} />
          <InfoRow label="Department"    value={mrq.department?.name} />
          <InfoRow label="Created"       value={mrq.created_at ? new Date(mrq.created_at).toLocaleString('en-PH') : '—'} />
          {mrq.noted_at && <InfoRow label="Noted By" value={`${mrq.noted_by?.name} — ${mrq.noted_comments ?? ''}`} />}
          {mrq.checked_at && <InfoRow label="Checked By" value={`${mrq.checked_by?.name} — ${mrq.checked_comments ?? ''}`} />}
          {mrq.reviewed_at && <InfoRow label="Reviewed By" value={`${mrq.reviewed_by?.name} — ${mrq.reviewed_comments ?? ''}`} />}
          {mrq.vp_approved_at && <InfoRow label="VP Approved" value={`${mrq.vp_approved_by?.name} — ${mrq.vp_comments ?? ''}`} />}
          {mrq.rejected_at && <InfoRow label="Rejected By" value={`${mrq.rejected_by?.name} — ${mrq.rejection_reason ?? ''}`} />}
          {mrq.fulfilled_at && <InfoRow label="Fulfilled By" value={`${mrq.fulfilled_by?.name} on ${new Date(mrq.fulfilled_at).toLocaleDateString('en-PH')}`} />}
          {mrq.remarks && (
            <InfoRow
              label="Notes"
              value={
                <span className={mrq.remarks.startsWith('[Stock override]') ? 'text-amber-700 font-medium' : undefined}>
                  {mrq.remarks}
                </span>
              }
            />
          )}
        </dl>
      </div>

      {/* Items */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-medium text-neutral-700">Items Requested</h2>
          {/* Stock preview picker — visible at any stage so approvers can spot shortages early */}
          {!['cancelled', 'rejected', 'fulfilled'].includes(status) && (
            <div className="flex items-center gap-2">
              <label className="text-xs text-neutral-500 whitespace-nowrap">Check stock at:</label>
              <select
                value={locationId}
                onChange={(e) => setLocationId(e.target.value === '' ? '' : Number(e.target.value))}
                className="text-xs border border-neutral-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-neutral-400"
              >
                <option value="">Select location…</option>
                {(locationsData ?? []).map((loc) => (
                  <option key={loc.id} value={loc.id}>{loc.name}</option>
                ))}
              </select>
            </div>
          )}
        </div>
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50">
            <tr>
              {['#', 'Item Code', 'Name', 'UOM', 'Qty Requested', 'Qty Issued',
                ...(locationId ? ['Available'] : []),
                'Remarks'].map((h) => (
                <th key={h} className="px-3 py-2 text-left text-xs font-medium text-neutral-600">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {(mrq.items ?? []).map((line) => {
              const available = locationId
                ? (stockMap.get(line.item_id) ?? 0)
                : null
              const isShort = available !== null && available < parseFloat(line.qty_requested)
              return (
                <tr key={line.id} className={isShort ? 'bg-red-50' : ''}>
                  <td className="px-3 py-2 text-neutral-400 text-xs">{line.line_order}</td>
                  <td className="px-3 py-2 font-mono text-neutral-900 font-medium text-xs">{line.item?.item_code ?? `#${line.item_id}`}</td>
                  <td className="px-3 py-2 text-neutral-800">{line.item?.name ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-400 text-xs">{line.item?.unit_of_measure}</td>
                  <td className="px-3 py-2 tabular-nums font-medium">{parseFloat(line.qty_requested).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
                  <td className="px-3 py-2 tabular-nums text-neutral-700">
                    {line.qty_issued !== null ? parseFloat(line.qty_issued).toLocaleString('en-PH', { maximumFractionDigits: 4 }) : '—'}
                  </td>
                  {locationId ? (
                    <td className={`px-3 py-2 tabular-nums font-semibold ${isShort ? 'text-red-600' : 'text-green-700'}`}>
                      {available !== null ? available.toLocaleString('en-PH', { maximumFractionDigits: 4 }) : '—'}
                      {isShort && <span className="ml-1 text-xs font-normal">(short)</span>}
                    </td>
                  ) : null}
                  <td className="px-3 py-2 text-neutral-400 text-xs">{line.remarks ?? '—'}</td>
                </tr>
              )
            })}
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
            {!reason.trim() && (
              <p className="text-xs text-red-500 mt-1">Reason is required to reject.</p>
            )}
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
            {!locationId && (
              <p className="text-xs text-red-500 mt-1">Location is required to fulfill.</p>
            )}
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {status === 'draft' && canCreate && (
            <ConfirmDialog
              title="Submit for Approval?"
              description="This will submit the requisition for review and approval."
              confirmLabel="Submit"
              onConfirm={handleSubmitWithStockCheck}
            >
              <button
                disabled={submitMut.isPending}
                className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {submitMut.isPending ? 'Checking…' : 'Submit for Approval'}
              </button>
            </ConfirmDialog>
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
            <ConfirmDialog
              title="Approve Requisition?"
              description="This will approve the requisition for fulfillment. Stock will be reserved."
              confirmLabel="Approve"
              onConfirm={() => handleAction('approve')}
            >
              <button
                className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded"
              >
                VP Approve
              </button>
            </ConfirmDialog>
          )}
          {status === 'approved' && canFulfill && (
            <>
              {activeAction === 'fulfill' ? (
                <ConfirmDialog
                  title="Fulfill Requisition?"
                  description={
                    <div className="space-y-2">
                      <p>You are about to issue stock from the selected location.</p>
                      {getStockShortages().length > 0 && (
                        <div className="bg-red-50 border border-red-200 rounded p-2 text-sm text-red-700">
                          <div className="flex items-start gap-2">
                            <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                            <div>
                              <p className="font-medium">Stock shortage detected:</p>
                              <p>{getStockShortages().join(', ')}</p>
                            </div>
                          </div>
                        </div>
                      )}
                      <p className="text-amber-600 text-xs">This will deduct inventory immediately.</p>
                    </div>
                  }
                  confirmLabel="Confirm Fulfill"
                  onConfirm={() => handleAction('fulfill')}
                >
                  <button
                    disabled={fulfillMut.isPending || !locationId}
                    className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {fulfillMut.isPending ? 'Processing…' : 'Confirm Fulfill'}
                  </button>
                </ConfirmDialog>
              ) : (
                <button
                  onClick={() => setAction('fulfill')}
                  className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded"
                >
                  Fulfill (Issue Stock)
                </button>
              )}
            </>
          )}
          {/* Convert to PR — visible when approved, not yet converted, and user has PR create permission */}
          {status === 'approved' && !mrq.converted_to_pr && canCreatePr && (
            <ConfirmDialog
              title="Convert to Purchase Request?"
              description="This will create a draft Purchase Request with all items from this requisition pre-filled. You'll be redirected to the new PR to add pricing and vendor details."
              confirmLabel="Convert to PR"
              onConfirm={async () => {
                try {
                  const pr = await convertToPrMut.mutateAsync({ mrqUlid: mrq.ulid })
                  toast.success('Purchase Request created from MRQ.')
                  navigate(`/procurement/purchase-requests/${pr.ulid}`)
                } catch (_err) {
                  if (isHandledApiError(err)) return
                  toast.error((err as { message?: string })?.message ?? 'Failed to convert to PR.')
                }
              }}
            >
              <button
                disabled={convertToPrMut.isPending}
                className="flex items-center gap-2 px-4 py-2 text-sm font-medium bg-blue-700 hover:bg-blue-800 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <ShoppingCart className="w-4 h-4" />
                {convertToPrMut.isPending ? 'Converting…' : 'Convert to PR'}
              </button>
            </ConfirmDialog>
          )}
          {status === 'approved' && mrq.converted_to_pr && (
            <div className="flex items-center gap-1.5 px-4 py-2 text-sm text-neutral-500 border border-neutral-200 rounded bg-neutral-50">
              <ShoppingCart className="w-4 h-4" />
              Already converted to PR
            </div>
          )}
          {['submitted', 'noted', 'checked', 'reviewed'].includes(status) && (
            <>
              {activeAction === 'reject' ? (
                <ConfirmDestructiveDialog
                  title="Reject Requisition?"
                  description="This will reject the requisition. The requester will be notified."
                  confirmWord="REJECT"
                  confirmLabel="Confirm Rejection"
                  onConfirm={() => handleAction('reject')}
                >
                  <button
                    disabled={rejectMut.isPending || !reason.trim()}
                    className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-600 hover:bg-neutral-50 rounded disabled:opacity-50"
                  >
                    {rejectMut.isPending ? 'Processing…' : 'Confirm Rejection'}
                  </button>
                </ConfirmDestructiveDialog>
              ) : (
                <button
                  onClick={() => setAction('reject')}
                  className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-600 hover:bg-neutral-50 rounded"
                >
                  Reject
                </button>
              )}
            </>
          )}
          {mrq.is_cancellable && (
            <ConfirmDestructiveDialog
              title="Cancel Requisition?"
              description="This will cancel the requisition. This action cannot be undone."
              confirmWord="CANCEL"
              confirmLabel="Confirm Cancel"
              onConfirm={() => handleAction('cancel')}
            >
              <button
                disabled={cancelMut.isPending}
                className="px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-500 hover:bg-neutral-50 rounded disabled:opacity-50"
              >
                Cancel
              </button>
            </ConfirmDestructiveDialog>
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

      {/* Stock warning — requires reason before allowing override submit */}
      {showStockWarning && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => { setShowStockWarning(false); setStockOverrideReason('') }} />
          <div className="relative bg-white rounded-lg shadow-lg w-full max-w-sm border border-neutral-200">
            {/* Body */}
            <div className="p-4">
              <div className="flex items-start gap-3">
                <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5 text-neutral-500" />
                <div className="flex-1">
                  <h3 className="text-sm font-semibold text-neutral-900">Insufficient stock detected</h3>
                  <p className="text-sm text-neutral-500 mt-1">
                    The following items do not have enough stock across all locations:{' '}
                    <span className="font-medium text-neutral-700">{stockWarnings.join(', ')}</span>.
                  </p>
                  <p className="text-sm text-neutral-500 mt-1">
                    Provide a reason to proceed — it will be visible to all approvers.
                  </p>
                  <textarea
                    value={stockOverrideReason}
                    onChange={(e) => setStockOverrideReason(e.target.value)}
                    rows={3}
                    placeholder="e.g. Stock is incoming via PO-2026-0042, expected March 12…"
                    className="mt-3 w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                  />
                  {!stockOverrideReason.trim() && (
                    <p className="text-xs text-red-500 mt-1">A reason is required to submit anyway.</p>
                  )}
                </div>
              </div>
            </div>
            {/* Footer */}
            <div className="flex items-center justify-end gap-2 p-4 border-t border-neutral-200 bg-neutral-50">
              <button
                type="button"
                onClick={() => { setShowStockWarning(false); setStockOverrideReason('') }}
                className="px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={!stockOverrideReason.trim() || submitMut.isPending}
                onClick={() => { setShowStockWarning(false); handleAction('submit') }}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 rounded hover:bg-neutral-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {submitMut.isPending ? 'Processing…' : 'Submit Anyway'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
