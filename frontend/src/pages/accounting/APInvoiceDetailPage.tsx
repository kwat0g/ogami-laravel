import { useState } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, CheckCircle, XCircle, FileText, CreditCard } from 'lucide-react'
import {
  useAPInvoice,
  useSubmitAPInvoice,
  useApproveAPInvoice,
  useRejectAPInvoice,
  useHeadNoteAPInvoice,
  useManagerCheckAPInvoice,
  useOfficerReviewAPInvoice,
  useRecordPayment,
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
  const submit         = useSubmitAPInvoice(invoiceId ?? '')
  const approve        = useApproveAPInvoice(invoiceId ?? '')
  const reject         = useRejectAPInvoice(invoiceId ?? '')
  const headNote       = useHeadNoteAPInvoice(invoiceId ?? '')
  const managerCheck   = useManagerCheckAPInvoice(invoiceId ?? '')
  const officerReview  = useOfficerReviewAPInvoice(invoiceId ?? '')
  const recordPayment  = useRecordPayment(invoiceId ?? '')

  const [rejectNote, setRejectNote]           = useState('')
  const [showRejectForm, setShowRejectForm]   = useState(false)
  const [showPaymentForm, setShowPaymentForm] = useState(false)
  const [payAmount, setPayAmount]             = useState('')
  const [payAmountError, setPayAmountError]   = useState('')
  const [payDate, setPayDate]                 = useState(new Date().toISOString().slice(0, 10))
  const [payMethod, setPayMethod]             = useState<'bank_transfer' | 'check' | 'cash' | ''>('')
  const [payRef, setPayRef]                   = useState('')

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

  const handleHeadNote = async () => {
    try {
      await headNote.mutateAsync()
      toast.success('Head note recorded.')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const handleManagerCheck = async () => {
    try {
      await managerCheck.mutateAsync()
      toast.success('Manager check recorded.')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const handleOfficerReview = async () => {
    try {
      await officerReview.mutateAsync()
      toast.success('Officer review recorded.')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const handleRecordPayment = async () => {
    const amount = parseFloat(payAmount)
    if (!payAmount || isNaN(amount) || amount <= 0) {
      toast.error('Enter a valid payment amount.')
      return
    }
    if (amount > invoice.balance_due) {
      setPayAmountError(`Amount cannot exceed the balance due of ₱${invoice.balance_due.toLocaleString('en-PH', { minimumFractionDigits: 2 })}.`)
      return
    }
    if (!payDate) {
      toast.error('Payment date is required.')
      return
    }
    try {
      await recordPayment.mutateAsync({
        amount,
        payment_date: payDate,
        payment_method: payMethod || null,
        reference_number: payRef || null,
      })
      toast.success('Payment recorded.')
      setShowPaymentForm(false)
      setPayAmount('')
      setPayAmountError('')
      setPayRef('')
      setPayMethod('')
    } catch (err) {
      toast.error(parseApiError(err).message)
    }
  }

  const isDraft           = invoice.status === 'draft'
  const isPendingApproval = invoice.status === 'pending_approval'
  const isHeadNoted       = invoice.status === 'head_noted'
  const isManagerChecked  = invoice.status === 'manager_checked'
  const isOfficerReviewed = invoice.status === 'officer_reviewed'
  const isApproved        = invoice.status === 'approved'
  const isPartiallyPaid   = invoice.status === 'partially_paid'
  const canPay            = isApproved || isPartiallyPaid
  // Reject is available at any in-progress step after submission
  const isInProgress = isPendingApproval || isHeadNoted || isManagerChecked || isOfficerReviewed

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

            {/* Head Note (step 2) */}
            {isPendingApproval && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <SodActionButton
                  initiatedById={invoice.created_by}
                  label="Head Note"
                  onClick={handleHeadNote}
                  isLoading={headNote.isPending}
                  variant="primary"
                />
              </PermissionGuard>
            )}

            {/* Manager Check (step 3) */}
            {isHeadNoted && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <SodActionButton
                  initiatedById={invoice.created_by}
                  label="Manager Check"
                  onClick={handleManagerCheck}
                  isLoading={managerCheck.isPending}
                  variant="primary"
                />
              </PermissionGuard>
            )}

            {/* Officer Review (step 4) */}
            {isManagerChecked && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <SodActionButton
                  initiatedById={invoice.created_by}
                  label="Officer Review"
                  onClick={handleOfficerReview}
                  isLoading={officerReview.isPending}
                  variant="primary"
                />
              </PermissionGuard>
            )}

            {/* Approve (step 5 — only available after officer review) */}
            {isOfficerReviewed && (
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

            {/* Reject — available at any in-progress step */}
            {isInProgress && (
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

            {/* Record Payment — available when approved or partially paid */}
            {canPay && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.update}>
                <button
                  type="button"
                  onClick={() => { setShowPaymentForm(!showPaymentForm); setPayAmountError('') }}
                  className="inline-flex items-center gap-1.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2 rounded-lg"
                >
                  <CreditCard className="h-4 w-4" />
                  Record Payment
                </button>
              </PermissionGuard>
            )}
          </div>
        }
      />

      {/* Payment form */}
      {showPaymentForm && (
        <div className="mb-5 bg-teal-50 border border-teal-200 rounded-lg p-4 space-y-3">
          <p className="text-sm font-medium text-teal-800">Record Payment</p>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-medium text-gray-600">Amount (₱) *</label>
                <button
                  type="button"
                  onClick={() => { setPayAmount(String(invoice.balance_due)); setPayAmountError('') }}
                  className="text-xs text-teal-600 hover:text-teal-800 font-medium underline underline-offset-2"
                >
                  Full amount
                </button>
              </div>
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={payAmount}
                onChange={(e) => { setPayAmount(e.target.value); setPayAmountError('') }}
                placeholder={`Max: ${invoice.balance_due.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`}
                className={`w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 ${payAmountError ? 'border-red-400 bg-red-50' : 'border-gray-300'}`}
              />
              {payAmountError && <p className="mt-1 text-xs text-red-600">{payAmountError}</p>}
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Payment Date *</label>
              <input
                type="date"
                value={payDate}
                onChange={(e) => setPayDate(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Payment Method</label>
              <select
                value={payMethod}
                onChange={(e) => setPayMethod(e.target.value as 'bank_transfer' | 'check' | 'cash' | '')}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
              >
                <option value="">— Select —</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="check">Check</option>
                <option value="cash">Cash</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Reference No.</label>
              <input
                type="text"
                value={payRef}
                onChange={(e) => setPayRef(e.target.value)}
                placeholder="Check no., wire ref, etc."
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleRecordPayment}
              disabled={recordPayment.isPending}
              className="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg"
            >
              {recordPayment.isPending ? 'Saving…' : 'Save Payment'}
            </button>
            <button
              type="button"
              onClick={() => { setShowPaymentForm(false); setPayAmount(''); setPayAmountError(''); setPayRef(''); setPayMethod('') }}
              className="border border-gray-300 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

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
