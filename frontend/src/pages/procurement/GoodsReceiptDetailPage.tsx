import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, AlertTriangle, CheckCircle2, ClipboardCheck, Trash2 } from 'lucide-react'
import {
  useGoodsReceipt,
  useConfirmGoodsReceipt,
  useDeleteGoodsReceipt,
} from '@/hooks/useGoodsReceipts'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import type { GoodsReceiptStatus, GoodsReceiptCondition } from '@/types/procurement'

const conditionBadgeClass: Record<GoodsReceiptCondition, string> = {
  good:     'bg-neutral-100 text-neutral-700',
  damaged:  'bg-neutral-100 text-neutral-700',
  partial:  'bg-neutral-100 text-neutral-700',
  rejected: 'bg-neutral-100 text-neutral-500',
}

const conditionLabel: Record<GoodsReceiptCondition, string> = {
  good:     'Good',
  damaged:  'Damaged',
  partial:  'Partial',
  rejected: 'Rejected',
}

export default function GoodsReceiptDetailPage(): React.ReactElement {
  const { ulid }  = useParams<{ ulid: string }>()
  const navigate  = useNavigate()

  const { data: gr, isLoading, isError } = useGoodsReceipt(ulid ?? null)
  const confirmMutation = useConfirmGoodsReceipt()
  const deleteMutation  = useDeleteGoodsReceipt()

  function handleDelete(): void {
    if (!gr) return
    if (!window.confirm('Cancel this draft Goods Receipt? This cannot be undone.')) return
    deleteMutation.mutate(gr.ulid, {
      onSuccess: () => {
        toast.success('Goods Receipt cancelled.')
        navigate(-1)
      },
      onError: () => toast.error('Failed to cancel Goods Receipt.'),
    })
  }

  function handleConfirm(): void {
    if (!gr) return
    confirmMutation.mutate(gr.ulid, {
      onSuccess: (updated) => {
        const matchLabel = updated.three_way_match_passed
          ? 'Three-way match PASSED'
          : 'Three-way match WARNING — discrepancy detected'
        toast.success(`GR confirmed. ${matchLabel}`)
      },
      onError: () => toast.error('Failed to confirm Goods Receipt.'),
    })
  }

  // ── render states ────────────────────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="max-w-5xl mx-auto p-6 space-y-4">
        <SkeletonLoader />
      </div>
    )
  }

  if (isError || !gr) {
    return (
      <div className="max-w-5xl mx-auto p-6">
        <div className="flex items-center gap-3 text-red-600 bg-red-50 rounded p-4">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <p className="text-sm">Failed to load Goods Receipt. It may have been deleted or you lack access.</p>
        </div>
      </div>
    )
  }

  const canConfirm = gr.status === 'draft'

  const headerActions = canConfirm ? (
    <>
      <button
        type="button"
        onClick={handleConfirm}
        disabled={confirmMutation.isPending || deleteMutation.isPending}
        className="flex items-center gap-2 px-5 py-2.5 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50"
      >
        <ClipboardCheck className="w-4 h-4" />
        {confirmMutation.isPending ? 'Confirming…' : 'Confirm Receipt & Run 3-Way Match'}
      </button>
      <button
        type="button"
        onClick={handleDelete}
        disabled={confirmMutation.isPending || deleteMutation.isPending}
        className="flex items-center gap-2 px-4 py-2.5 rounded bg-white border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50 disabled:opacity-50"
      >
        <Trash2 className="w-4 h-4" />
        Cancel GR
      </button>
    </>
  ) : undefined

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      {/* ── Header ──────────────────────────────────────────────────────────── */}
      <PageHeader
        backTo="/procurement/goods-receipts"
        title={gr.gr_reference}
        subtitle="Goods Receipt"
        actions={headerActions}
      >
        <StatusBadge status={gr.status} />
      </PageHeader>

      {/* ── Receipt Details ─────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>Receipt Details</CardHeader>
        <CardBody>
          <InfoList columns={2}>
            <InfoRow label="Purchase Order">
              {gr.purchase_order ? (
                <Link
                  to={`/procurement/purchase-orders/${gr.purchase_order.ulid}`}
                  className="text-neutral-700 hover:text-neutral-900 font-medium"
                >
                  {gr.purchase_order.po_reference}
                </Link>
              ) : (
                <span className="text-neutral-800">—</span>
              )}
            </InfoRow>
            <InfoRow label="Received Date">
              {new Date(gr.received_date).toLocaleDateString('en-PH')}
            </InfoRow>
            <InfoRow label="Received By">
              {gr.received_by?.name ?? '—'}
            </InfoRow>
            {gr.delivery_note_number && (
              <InfoRow label="Delivery Note #">
                <span className="font-mono">{gr.delivery_note_number}</span>
              </InfoRow>
            )}
            {gr.condition_notes && (
              <InfoRow label="Condition Notes" fullWidth>
                <span className="text-neutral-600 italic">{gr.condition_notes}</span>
              </InfoRow>
            )}
            {gr.confirmed_at && (
              <>
                <InfoRow label="Confirmed By">
                  {gr.confirmed_by?.name ?? '—'}
                </InfoRow>
                <InfoRow label="Confirmed At">
                  {new Date(gr.confirmed_at).toLocaleDateString('en-PH')}
                </InfoRow>
              </>
            )}
          </InfoList>
        </CardBody>
      </Card>

      {/* ── Three-way match status ─────────────────────────────────────────── */}
      {gr.status === 'confirmed' && (
        <Card className={gr.three_way_match_passed ? '' : 'border-amber-200'}>
          <CardBody>
            <div className="flex items-center gap-3">
              {gr.three_way_match_passed ? (
                <CheckCircle2 className="w-5 h-5 text-neutral-600 shrink-0" />
              ) : (
                <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0" />
              )}
              <div>
                <p
                  className={`text-sm font-semibold ${
                    gr.three_way_match_passed ? 'text-neutral-700' : 'text-amber-700'
                  }`}
                >
                  Three-Way Match: {gr.three_way_match_passed ? 'Passed' : 'Discrepancy Detected'}
                </p>
                {gr.ap_invoice_created && (
                  <p className="text-xs text-neutral-500 mt-0.5">AP Invoice has been created.</p>
                )}
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Received Items ──────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>Received Items</CardHeader>
        <CardBody>
          <div className="overflow-x-auto -mx-6 -mb-6">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 text-left">
                <tr>
                  <th className="px-4 py-3 font-medium text-neutral-600">#</th>
                  <th className="px-4 py-3 font-medium text-neutral-600">PO Item ID</th>
                  <th className="px-4 py-3 font-medium text-neutral-600 text-right">Qty Received</th>
                  <th className="px-4 py-3 font-medium text-neutral-600">UOM</th>
                  <th className="px-4 py-3 font-medium text-neutral-600">Condition</th>
                  <th className="px-4 py-3 font-medium text-neutral-600">Remarks</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {gr.items.map((item, idx) => (
                  <tr key={item.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-3 text-neutral-400">{idx + 1}</td>
                    <td className="px-4 py-3 text-neutral-600 font-mono text-xs">{item.po_item_id}</td>
                    <td className="px-4 py-3 text-right text-neutral-800 font-medium">
                      {item.quantity_received}
                    </td>
                    <td className="px-4 py-3 text-neutral-600">{item.unit_of_measure}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                          conditionBadgeClass[item.condition]
                        }`}
                      >
                        {conditionLabel[item.condition]}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-neutral-500 text-xs italic">
                      {item.remarks ?? '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>
    </div>
  )
}
