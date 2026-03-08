import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { CheckCircle, XCircle, FileText, CreditCard } from 'lucide-react'
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
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
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
        Invoice not found or you do not have access.{' '}
        <button onClick={() => navigate('/accounting/ap/invoices')} className="text-neutral-600 underline">Back to list</button>
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
    <div className="max-w-5xl mx-auto">
      <PageHeader
        backTo="/accounting/ap/invoices"
        title={`AP Invoice — ${invoice.or_number ?? `#${invoice.id}`}`}
        subtitle={invoice.vendor ? `Vendor: ${invoice.vendor.name}` : undefined}
        icon={<FileText className="w-5 h-5" />}
        status={<StatusBadge label={invoice.status} />}
        actions={
          <div className="flex items-center gap-2">
            {/* Submit for approval */}
            {isDraft && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.update}>
                <button
                  type="button"
                  onClick={handleSubmit}
                  disabled={submit.isPending}
                  className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
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
                  className="inline-flex items-center gap-1.5 bg-white text-red-600 border border-red-300 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded"
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
                  className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded"
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
        <div className="mb-5 bg-white border border-neutral-200 rounded p-4 space-y-3">
          <p className="text-sm font-medium text-neutral-800">Record Payment</p>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-medium text-neutral-600">Amount (₱) *</label>
                <button
                  type="button"
                  onClick={() => { setPayAmount(String(invoice.balance_due)); setPayAmountError('') }}
                  className="text-xs text-neutral-600 hover:text-neutral-800 font-medium underline underline-offset-2"
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
                className={`w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400 ${payAmountError ? 'border-red-400 bg-red-50' : 'border-neutral-300'}`}
              />
              {payAmountError && <p className="mt-1 text-xs text-red-600">{payAmountError}</p>}
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Payment Date *</label>
              <input
                type="date"
                value={payDate}
                onChange={(e) => setPayDate(e.target.value)}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Payment Method</label>
              <select
                value={payMethod}
                onChange={(e) => setPayMethod(e.target.value as 'bank_transfer' | 'check' | 'cash' | '')}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              >
                <option value="">— Select —</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="check">Check</option>
                <option value="cash">Cash</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Reference No.</label>
              <input
                type="text"
                value={payRef}
                onChange={(e) => setPayRef(e.target.value)}
                placeholder="Check no., wire ref, etc."
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
              />
            </div>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleRecordPayment}
              disabled={recordPayment.isPending}
              className="bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded"
            >
              {recordPayment.isPending ? 'Saving…' : 'Save Payment'}
            </button>
            <button
              type="button"
              onClick={() => { setShowPaymentForm(false); setPayAmount(''); setPayAmountError(''); setPayRef(''); setPayMethod('') }}
              className="bg-white text-neutral-700 border border-neutral-300 text-sm font-medium px-4 py-2 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {/* Reject form */}
      {showRejectForm && (
        <div className="mb-5 bg-white border border-neutral-200 rounded p-4 space-y-3">
          <p className="text-sm font-medium text-neutral-800">Rejection Note</p>
          <textarea
            value={rejectNote}
            onChange={(e) => setRejectNote(e.target.value)}
            rows={3}
            placeholder="Explain why this invoice is being rejected…"
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-neutral-400"
          />
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleReject}
              disabled={reject.isPending}
              className="bg-white text-red-600 border border-red-300 hover:bg-red-50 disabled:opacity-50 text-sm font-medium px-4 py-2 rounded"
            >
              {reject.isPending ? 'Rejecting…' : 'Confirm Rejection'}
            </button>
            <button
              type="button"
              onClick={() => { setShowRejectForm(false); setRejectNote('') }}
              className="bg-white text-neutral-700 border border-neutral-300 text-sm font-medium px-4 py-2 rounded hover:bg-neutral-50"
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
          <Card>
            <CardHeader>Invoice Details</CardHeader>
            <CardBody>
              {invoice.description && (
                <p className="text-sm text-neutral-500 mb-4">{invoice.description}</p>
              )}
              <InfoList>
                <InfoRow label="Invoice Date" value={invoice.invoice_date} />
                <InfoRow label="Due Date" value={invoice.due_date} />
                {invoice.atc_code && <InfoRow label="ATC Code" value={invoice.atc_code} />}
                {invoice.or_number && <InfoRow label="OR Number" value={invoice.or_number} />}
                {invoice.rejection_note && <InfoRow label="Rejection Note" value={invoice.rejection_note} />}
              </InfoList>
            </CardBody>
          </Card>

          {/* Payments table */}
          {invoice.payments && invoice.payments.length > 0 && (
            <Card>
              <CardHeader>Payment History</CardHeader>
              <CardBody className="p-0">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-neutral-200">
                      <th className="text-left pb-2 font-medium text-neutral-500 px-4 pt-3">Date</th>
                      <th className="text-left pb-2 font-medium text-neutral-500 px-4 pt-3">Reference</th>
                      <th className="text-left pb-2 font-medium text-neutral-500 px-4 pt-3">Method</th>
                      <th className="text-right pb-2 font-medium text-neutral-500 px-4 pt-3">Amount</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-100">
                    {invoice.payments.map((p) => (
                      <tr key={p.id} className="even:bg-neutral-50 hover:bg-neutral-50">
                        <td className="py-2 px-4">{p.payment_date}</td>
                        <td className="py-2 px-4 text-neutral-500">{p.reference_number ?? '—'}</td>
                        <td className="py-2 px-4 text-neutral-500">{p.payment_method ?? '—'}</td>
                        <td className="py-2 px-4 text-right font-medium">
                          <CurrencyAmount centavos={p.amount * 100} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </CardBody>
            </Card>
          )}
        </div>

        {/* Amount summary */}
        <div className="space-y-4">
          <Card>
            <CardHeader>Amount Summary</CardHeader>
            <CardBody>
              <dl className="space-y-2 text-sm">
                <AmountRow label="Net Amount"  value={invoice.net_amount * 100} />
                <AmountRow label="VAT"         value={invoice.vat_amount * 100} />
                {invoice.ewt_amount > 0 && (
                  <AmountRow label={`EWT (${((invoice.ewt_rate ?? 0) * 100).toFixed(0)}%)`} value={-(invoice.ewt_amount * 100)} />
                )}
                <div className="border-t border-neutral-200 pt-2">
                  <AmountRow label="Net Payable" value={invoice.net_payable * 100} bold />
                </div>
                <AmountRow label="Total Paid"    value={invoice.total_paid * 100}  muted />
                <AmountRow label="Balance Due"   value={invoice.balance_due * 100} highlight={invoice.balance_due > 0} />
              </dl>
            </CardBody>
          </Card>

          {/* Overdue badge */}
          {invoice.is_overdue && (
            <div className="flex items-center gap-2 bg-neutral-50 border border-neutral-200 rounded px-4 py-3 text-sm text-neutral-700">
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
      <dt className={muted ? 'text-neutral-400' : 'text-neutral-500'}>{label}</dt>
      <dd className={[
        bold ? 'font-semibold' : 'font-medium',
        highlight ? 'text-neutral-900' : muted ? 'text-neutral-400' : 'text-neutral-800',
      ].join(' ')}>
        <CurrencyAmount centavos={value} />
      </dd>
    </div>
  )
}
