import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, AlertTriangle, CheckCircle2, ClipboardCheck, Trash2 } from 'lucide-react'
import {
  useGoodsReceipt,
  useConfirmGoodsReceipt,
  useDeleteGoodsReceipt,
} from '@/hooks/useGoodsReceipts'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { GoodsReceiptStatus, GoodsReceiptCondition } from '@/types/procurement'

const statusBadgeClass: Record<GoodsReceiptStatus, string> = {
  draft:     'bg-neutral-100 text-neutral-600',
  confirmed: 'bg-neutral-200 text-neutral-800',
}

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
      <div className="max-w-4xl mx-auto p-6 space-y-4">
        <SkeletonLoader />
      </div>
    )
  }

  if (isError || !gr) {
    return (
      <div className="max-w-4xl mx-auto p-6">
        <div className="flex items-center gap-3 text-red-600 bg-red-50 rounded p-4">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <p className="text-sm">Failed to load Goods Receipt. It may have been deleted or you lack access.</p>
        </div>
      </div>
    )
  }

  const canConfirm = gr.status === 'draft'

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">

      {/* ── Header ──────────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="p-2 rounded hover:bg-neutral-100 text-neutral-500"
            aria-label="Back"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <h1 className="text-lg font-semibold text-neutral-900">{gr.gr_reference}</h1>
            <p className="text-sm text-neutral-500 mt-0.5">Goods Receipt</p>
          </div>
        </div>

        <span
          className={`inline-flex items-center px-3 py-1 rounded text-xs font-medium capitalize ${
            statusBadgeClass[gr.status]
          }`}
        >
          {gr.status}
        </span>
      </div>

      {/* ── GR info card ──────────────────────────────────────────────────── */}
      <div className="bg-white rounded border border-neutral-200 p-6">
        <h2 className="text-sm font-medium text-neutral-700 mb-4">Receipt Details</h2>
        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
          <div>
            <dt className="text-neutral-400 text-xs">Purchase Order</dt>
            <dd className="mt-0.5">
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
            </dd>
          </div>
          <div>
            <dt className="text-neutral-400 text-xs">Received Date</dt>
            <dd className="text-neutral-800 mt-0.5">
              {new Date(gr.received_date).toLocaleDateString('en-PH')}
            </dd>
          </div>
          <div>
            <dt className="text-neutral-400 text-xs">Received By</dt>
            <dd className="text-neutral-800 mt-0.5">{gr.received_by?.name ?? '—'}</dd>
          </div>
          {gr.delivery_note_number && (
            <div>
              <dt className="text-neutral-400 text-xs">Delivery Note #</dt>
              <dd className="text-neutral-800 mt-0.5 font-mono">{gr.delivery_note_number}</dd>
            </div>
          )}
          {gr.condition_notes && (
            <div className="sm:col-span-2">
              <dt className="text-neutral-400 text-xs">Condition Notes</dt>
              <dd className="text-neutral-600 italic mt-0.5">{gr.condition_notes}</dd>
            </div>
          )}
          {gr.confirmed_at && (
            <>
              <div>
                <dt className="text-neutral-400 text-xs">Confirmed By</dt>
                <dd className="text-neutral-800 mt-0.5">{gr.confirmed_by?.name ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-neutral-400 text-xs">Confirmed At</dt>
                <dd className="text-neutral-800 mt-0.5">
                  {new Date(gr.confirmed_at).toLocaleDateString('en-PH')}
                </dd>
              </div>
            </>
          )}
        </dl>
      </div>

      {/* ── Three-way match status ─────────────────────────────────────────── */}
      {gr.status === 'confirmed' && (
        <div
          className={`flex items-center gap-3 rounded p-4 ${
            gr.three_way_match_passed
              ? 'bg-neutral-50 border border-neutral-200'
              : 'bg-amber-50 border border-amber-200'
          }`}
        >
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
      )}

      {/* ── Line items ────────────────────────────────────────────────────── */}
      <div className="bg-white rounded border border-neutral-200 overflow-hidden">
        <div className="px-6 py-4 border-b border-neutral-100">
          <h2 className="text-sm font-medium text-neutral-700">Received Items</h2>
        </div>
        <div className="overflow-x-auto">
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
      </div>

      {/* ── Confirm action ────────────────────────────────────────────────── */}
      {canConfirm && (
        <div className="flex items-center gap-3">
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
            className="flex items-center gap-2 px-4 py-2.5 rounded border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50 disabled:opacity-50"
          >
            <Trash2 className="w-4 h-4" />
            Cancel GR
          </button>
        </div>
      )}
    </div>
  )
}
