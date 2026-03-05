import { useState } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, CheckCircle, XCircle, FileText } from 'lucide-react'
import {
  useAPInvoice,
  useSubmitAPInvoice,
  useApproveAPInvoice,
  useRejectAPInvoice,
} from '@/hooks/useAP'
import { parseApiError } from '@/lib/errorHandler'
import SodActionButton from '@/components/ui/SodActionButton'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import PageHeader from '@/components/ui/PageHeader'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { PERMISSIONS } from '@/lib/permissions'

export default function APInvoiceDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const invoiceId = id ?? null

  const { data: invoice, isLoading, isError } = useAPInvoice(invoiceId)
  const submit  = useSubmitAPInvoice(invoiceId ?? '')
  const approve = useApproveAPInvoice(invoiceId ?? '')
  const reject  = useRejectAPInvoice(invoiceId ?? '')

  const [rejectNote, setRejectNote]       = useState('')
  const [showRejectForm, setShowRejectForm] = useState(false)

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError || !invoice) {
    return (
      <div className="text-sm text-red-600 mt-4">
      <ExecutiveReadOnlyBanner />
        Invoice not found or you do not have access.{' '}
        <Link to="/accounting/ap/invoices" className="text-blue-600 underline">Back to list</Link>
      </div>
    )
  }

  const handleSubmit = async () => {
    try {
      await submit.mutateAsync()
      toast.success('Invoice submitted for approval.')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const handleApprove = async () => {
    try {
      await approve.mutateAsync()
      toast.success('Invoice approved.')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const handleReject = async () => {
    if (!rejectNote.trim()) {
      toast.error('Rejection note is required.')
      return
    }
    try {
      await reject.mutateAsync(rejectNote)
      toast.success('Invoice rejected.')
      setShowRejectForm(false)
      setRejectNote('')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const isDraft          = invoice.status === 'draft'
  const isPendingApproval = invoice.status === 'pending_approval'

  return (
    <div>
      {/* Header */}
      <div className="flex items-center gap-2 mb-1">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
        >
          <ArrowLeft className="h-4 w-4" /> Back
        </button>
      </div>

      <PageHeader
        title={`AP Invoice — ${invoice.or_number ?? `#${invoice.id}`}`}
        subtitle={invoice.vendor ? `Vendor: ${invoice.vendor.name}` : undefined}
        actions={
          <div className="flex items-center gap-2">
            {/* Submit for approval */}
            {isDraft && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.update}>
                <button
                  type="button"
                  onClick={handleSubmit}
                  disabled={submit.isPending}
                  className="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg"
                >
                  <FileText className="h-4 w-4" />
                  {submit.isPending ? 'Submitting…' : 'Submit for Approval'}
                </button>
              </PermissionGuard>
            )}

            {/* Approve with SoD enforcement */}
            {isPendingApproval && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <SodActionButton
                  initiatedById={invoice.created_by}
                  label="Approve"
                  onClick={handleApprove}
                  isLoading={approve.isPending}
                  variant="primary"
                />
              </PermissionGuard>
            )}

            {/* Reject */}
            {isPendingApproval && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <button
                  type="button"
                  onClick={() => setShowRejectForm(!showRejectForm)}
                  className="inline-flex items-center gap-1.5 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg"
                >
                  <XCircle className="h-4 w-4" />
                  Reject
                </button>
              </PermissionGuard>
            )}
          </div>
        }
      />

      {/* Reject form */}
      {showRejectForm && (
        <div className="mb-5 bg-red-50 border border-red-200 rounded-lg p-4 space-y-3">
          <p className="text-sm font-medium text-red-700">Rejection Note</p>
          <textarea
            value={rejectNote}
            onChange={(e) => setRejectNote(e.target.value)}
            rows={3}
            placeholder="Explain why this invoice is being rejected…"
            className="w-full border border-red-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
          />
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleReject}
              disabled={reject.isPending}
              className="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg"
            >
              {reject.isPending ? 'Rejecting…' : 'Confirm Rejection'}
            </button>
            <button
              type="button"
              onClick={() => { setShowRejectForm(false); setRejectNote('') }}
              className="border border-gray-300 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      <div className="grid grid-cols-3 gap-5">
        {/* Invoice details */}
        <div className="col-span-2 space-y-5">
          {/* Header card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5">
            <div className="flex items-start justify-between mb-4">
              <div>
                <h3 className="text-sm font-semibold text-gray-700 mb-1">Invoice Details</h3>
                {invoice.description && (
                  <p className="text-sm text-gray-500">{invoice.description}</p>
                )}
              </div>
              <StatusBadge label={invoice.status} />
            </div>

            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <dt className="text-gray-500">Invoice Date</dt>
              <dd className="font-medium">{invoice.invoice_date}</dd>

              <dt className="text-gray-500">Due Date</dt>
              <dd className="font-medium">{invoice.due_date}</dd>

              {invoice.atc_code && (
                <>
                  <dt className="text-gray-500">ATC Code</dt>
                  <dd className="font-medium">{invoice.atc_code}</dd>
                </>
              )}

              {invoice.or_number && (
                <>
                  <dt className="text-gray-500">OR Number</dt>
                  <dd className="font-medium">{invoice.or_number}</dd>
                </>
              )}

              {invoice.rejection_note && (
                <>
                  <dt className="text-gray-500">Rejection Note</dt>
                  <dd className="font-medium text-red-600">{invoice.rejection_note}</dd>
                </>
              )}
            </dl>
          </div>

          {/* Payments table */}
          {invoice.payments && invoice.payments.length > 0 && (
            <div className="bg-white rounded-xl border border-gray-200 p-5">
              <h3 className="text-sm font-semibold text-gray-700 mb-4">Payment History</h3>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-100">
                    <th className="text-left pb-2 font-medium text-gray-500">Date</th>
                    <th className="text-left pb-2 font-medium text-gray-500">Reference</th>
                    <th className="text-left pb-2 font-medium text-gray-500">Method</th>
                    <th className="text-right pb-2 font-medium text-gray-500">Amount</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {invoice.payments.map((p) => (
                    <tr key={p.id}>
                      <td className="py-2">{p.payment_date}</td>
                      <td className="py-2 text-gray-500">{p.reference_number ?? '—'}</td>
                      <td className="py-2 text-gray-500">{p.payment_method ?? '—'}</td>
                      <td className="py-2 text-right font-medium">
                        <CurrencyAmount centavos={p.amount * 100} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Amount summary */}
        <div className="space-y-4">
          <div className="bg-white rounded-xl border border-gray-200 p-5">
            <h3 className="text-sm font-semibold text-gray-700 mb-4">Amount Summary</h3>
            <dl className="space-y-2 text-sm">
              <AmountRow label="Net Amount"  value={invoice.net_amount * 100} />
              <AmountRow label="VAT"         value={invoice.vat_amount * 100} />
              {invoice.ewt_amount > 0 && (
                <AmountRow label={`EWT (${((invoice.ewt_rate ?? 0) * 100).toFixed(0)}%)`} value={-(invoice.ewt_amount * 100)} />
              )}
              <div className="border-t border-gray-100 pt-2">
                <AmountRow label="Net Payable" value={invoice.net_payable * 100} bold />
              </div>
              <AmountRow label="Total Paid"    value={invoice.total_paid * 100}  muted />
              <AmountRow label="Balance Due"   value={invoice.balance_due * 100} highlight={invoice.balance_due > 0} />
            </dl>
          </div>

          {/* Overdue badge */}
          {invoice.is_overdue && (
            <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
              <CheckCircle className="h-4 w-4 flex-shrink-0" />
              This invoice is <span className="font-semibold">overdue</span>.
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function AmountRow({
  label,
  value,
  bold,
  muted,
  highlight,
}: {
  label: string
  value: number
  bold?: boolean
  muted?: boolean
  highlight?: boolean
}) {
  return (
    <div className="flex justify-between">
      <dt className={muted ? 'text-gray-400' : 'text-gray-500'}>{label}</dt>
      <dd className={[
        bold ? 'font-semibold' : 'font-medium',
        highlight ? 'text-red-600' : muted ? 'text-gray-400' : 'text-gray-800',
      ].join(' ')}>
        <CurrencyAmount centavos={value} />
      </dd>
    </div>
  )
}
