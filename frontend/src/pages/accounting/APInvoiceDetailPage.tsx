import { useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { toast } from 'sonner'
import { CheckCircle, XCircle, FileText, CreditCard, Trash2, Ban } from 'lucide-react'
import {
  useAPInvoice,
  useSubmitAPInvoice,
  useApproveAPInvoice,
  useRejectAPInvoice,
  useHeadNoteAPInvoice,
  useManagerCheckAPInvoice,
  useOfficerReviewAPInvoice,
  useRecordPayment,
  useCancelAPInvoice,
  useDeleteAPInvoice,
} from '@/hooks/useAP'
import { firstErrorMessage } from '@/lib/errorHandler'
import SodActionButton from '@/components/ui/SodActionButton'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import PageHeader from '@/components/ui/PageHeader'
import { ExportPdfButton } from '@/components/ui/ExportPdfButton'
import PermissionGuard from '@/components/ui/PermissionGuard'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import { PERMISSIONS } from '@/lib/permissions'
import StatusTimeline from '@/components/ui/StatusTimeline'
import ChainRecordTimeline from '@/components/ui/ChainRecordTimeline'
import ApprovalStepForm from '@/components/ui/ApprovalStepForm'
import { getVendorInvoiceSteps, isRejectedStatus } from '@/lib/workflowSteps'

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
  const cancel         = useCancelAPInvoice(invoiceId ?? '')
  const deleteInvoice  = useDeleteAPInvoice(invoiceId ?? '')

  const [rejectNote, setRejectNote]           = useState('')
  const [showRejectForm, setShowRejectForm]   = useState(false)
  const [showPaymentForm, setShowPaymentForm] = useState(false)
  const [payAmount, setPayAmount]             = useState('')
  const [payAmountError, setPayAmountError]   = useState('')
  const [payDate, setPayDate]                 = useState(new Date().toISOString().slice(0, 10))
  const [payMethod, setPayMethod]             = useState<'bank_transfer' | 'check' | 'cash' | ''>('')
  const [payRef, setPayRef]                   = useState('')
  const [cancelReason, setCancelReason]       = useState('')
  const [_showCancelForm, setShowCancelForm]   = useState(false)

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
      toast.error(firstErrorMessage(err))
    }
  }

  const handleApprove = async () => {
    try {
      await approve.mutateAsync()
      toast.success('Invoice approved. An official invoice number has been generated.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleReject = async () => {
    if (!rejectNote.trim()) {
      return
    }
    try {
      await reject.mutateAsync(rejectNote)
      toast.success('Invoice rejected.')
      setShowRejectForm(false)
      setRejectNote('')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleHeadNote = async () => {
    try {
      await headNote.mutateAsync()
      toast.success('Head note recorded.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleManagerCheck = async () => {
    try {
      await managerCheck.mutateAsync()
      toast.success('Manager check recorded.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleOfficerReview = async () => {
    try {
      await officerReview.mutateAsync()
      toast.success('Officer review recorded.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleRecordPayment = async () => {
    const amount = parseFloat(payAmount)
    if (!payAmount || isNaN(amount) || amount <= 0) {
      return
    }
    if (amount > invoice.balance_due) {
      setPayAmountError(`Amount cannot exceed the balance due of ₱${invoice.balance_due.toLocaleString('en-PH', { minimumFractionDigits: 2 })}.`)
      return
    }
    if (!payDate) {
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
      toast.error(firstErrorMessage(err))
    }
  }

  const handleCancel = async () => {
    try {
      await cancel.mutateAsync(cancelReason || undefined)
      toast.success('Invoice cancelled.')
      setShowCancelForm(false)
      setCancelReason('')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const handleDelete = async () => {
    try {
      await deleteInvoice.mutateAsync()
      toast.success('Invoice deleted.')
      navigate('/accounting/ap/invoices')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const isDraft           = invoice.status === 'draft'
  const isPendingApproval = invoice.status === 'pending_approval'
  const isHeadNoted       = invoice.status === 'head_noted'
  const isManagerChecked  = invoice.status === 'manager_checked'
  const isOfficerReviewed = invoice.status === 'officer_reviewed'
  const isApproved        = invoice.status === 'approved'
  const isPartiallyPaid   = invoice.status === 'partially_paid'
  const isCancelled       = invoice.status === 'cancelled'
  const canPay            = isApproved || isPartiallyPaid
  // Reject is available at any in-progress step after submission
  const isInProgress = isPendingApproval || isHeadNoted || isManagerChecked || isOfficerReviewed
  // Cancel is available for draft or in-progress invoices
  const canCancel = isDraft || isInProgress
  // Delete is only available for draft invoices
  const canDelete = isDraft

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        backTo="/accounting/ap/invoices"
        title={`AP Invoice — ${invoice.or_number ?? `#${invoice.id}`}`}
        subtitle={invoice.vendor ? `Vendor: ${invoice.vendor.name}` : undefined}
        icon={<FileText className="w-5 h-5" />}
        status={<StatusBadge status={invoice.status}>{invoice.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>}
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            {/* Submit for approval */}
            {isDraft && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.update}>
                <ApprovalStepForm
                  title="Submit for Approval"
                  description="This will submit the invoice for the multi-step approval workflow. Once submitted, it cannot be edited."
                  confirmLabel="Submit"
                  onConfirm={(_comments) => handleSubmit()}
                  isLoading={submit.isPending}
                >
                  <button
                    type="button"
                    disabled={submit.isPending}
                    className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2 rounded"
                  >
                    <FileText className="h-4 w-4" />
                    {submit.isPending ? 'Submitting…' : 'Submit for Approval'}
                  </button>
                </ApprovalStepForm>
              </PermissionGuard>
            )}

            {/* Head Note (step 2) */}
            {isPendingApproval && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <ApprovalStepForm
                  title="Head Note Review"
                  description="Review the invoice details, verify the vendor and amounts are correct, then record your review."
                  confirmLabel="Record Head Note"
                  onConfirm={(_comments) => handleHeadNote()}
                  isLoading={headNote.isPending}
                  checklist={['Vendor details verified', 'Invoice amounts match delivery']}
                >
                  <SodActionButton
                    initiatedById={invoice.created_by}
                    label="Head Note"
                    onClick={() => {}}
                    isLoading={headNote.isPending}
                    variant="primary"
                  />
                </ApprovalStepForm>
              </PermissionGuard>
            )}

            {/* Manager Check (step 3) */}
            {isHeadNoted && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <ApprovalStepForm
                  title="Manager Check"
                  description="Verify the invoice has been properly reviewed and amounts are within budget allocation."
                  confirmLabel="Confirm Manager Check"
                  onConfirm={(_comments) => handleManagerCheck()}
                  isLoading={managerCheck.isPending}
                  checklist={['Budget allocation verified', 'Head Note review acknowledged']}
                >
                  <SodActionButton
                    initiatedById={invoice.created_by}
                    label="Manager Check"
                    onClick={() => {}}
                    isLoading={managerCheck.isPending}
                    variant="primary"
                  />
                </ApprovalStepForm>
              </PermissionGuard>
            )}

            {/* Officer Review (step 4) */}
            {isManagerChecked && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <ApprovalStepForm
                  title="Officer Review"
                  description="Accounting review -- verify GL account codes, tax calculations, and EWT withholding are correct."
                  confirmLabel="Record Officer Review"
                  onConfirm={(_comments) => handleOfficerReview()}
                  isLoading={officerReview.isPending}
                  checklist={['GL account codes correct', 'VAT computation verified', 'EWT withholding correct (if applicable)']}
                >
                  <SodActionButton
                    initiatedById={invoice.created_by}
                    label="Officer Review"
                    onClick={() => {}}
                    isLoading={officerReview.isPending}
                    variant="primary"
                  />
                </ApprovalStepForm>
              </PermissionGuard>
            )}

            {/* Approve (step 5 — only available after officer review) */}
            {isOfficerReviewed && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.approve}>
                <ApprovalStepForm
                  title="Final Approval"
                  description="This will approve the invoice and generate an official invoice number. This action cannot be undone."
                  confirmLabel="Approve Invoice"
                  onConfirm={(_comments) => handleApprove()}
                  isLoading={approve.isPending}
                  checklist={['All previous review steps completed', 'Invoice amount matches PO and GR']}
                >
                  <SodActionButton
                    initiatedById={invoice.created_by}
                    label="Approve"
                    onClick={() => {}}
                    isLoading={approve.isPending}
                    variant="primary"
                  />
                </ApprovalStepForm>
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

            {/* Cancel — available for draft or in-progress */}
            {canCancel && (
              <PermissionGuard permission={PERMISSIONS.vendor_invoices.update}>
                <ConfirmDialog
                  title="Cancel Invoice?"
                  description="This will cancel the invoice. Cancelled invoices cannot be processed further."
                  confirmLabel="Cancel Invoice"
                  variant="danger"
                  onConfirm={handleCancel}
                >
                  <button
                    type="button"
                    disabled={cancel.isPending}
                    className="inline-flex items-center gap-1.5 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium px-4 py-2 rounded"
                  >
                    <Ban className="h-4 w-4" />
                    {cancel.isPending ? 'Cancelling…' : 'Cancel'}
                  </button>
                </ConfirmDialog>
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

            {/* Export invoice as PDF */}
            <ExportPdfButton href={`/api/v1/accounting/ap/invoices/${invoice.ulid ?? invoice.id}/pdf`} />

            {/* Download BIR Form 2307 — available when EWT applies and invoice is approved/paid */}
            {(invoice.ewt_amount > 0) && ['approved', 'partially_paid', 'paid'].includes(invoice.status) && (
              <a
                href={`/api/v1/accounting/ap/invoices/${invoice.ulid ?? invoice.id}/form-2307`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 text-sm font-medium px-4 py-2 rounded"
              >
                <FileText className="h-4 w-4" />
                Form 2307
              </a>
            )}
          </div>
        }
      />

      {/* Workflow Timeline */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-5">
        <StatusTimeline
          steps={getVendorInvoiceSteps(invoice)}
          currentStatus={invoice.status}
          direction="horizontal"
          isRejected={isRejectedStatus(invoice.status)}
        />
      </div>

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
              className="bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2 rounded"
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
              className="bg-white text-red-600 border border-red-300 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium px-4 py-2 rounded"
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

      <div className="space-y-5">
        {/* Invoice details with Amount Summary inside */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              Invoice Details
              {/* Source reference badges */}
              {(invoice.goods_receipt || invoice.purchase_order) && (
                <span className="text-xs text-neutral-500 font-normal">
                  {invoice.goods_receipt && (
                    <>
                      <Link 
                        to={`/procurement/goods-receipts/${invoice.goods_receipt.ulid}`}
                        className="text-neutral-600 hover:text-neutral-900 underline underline-offset-2"
                      >
                        GR {invoice.goods_receipt.gr_reference}
                      </Link>
                    </>
                  )}
                  {invoice.purchase_order && (
                    <>
                      {' / '}
                      <Link 
                        to={`/procurement/purchase-orders/${invoice.purchase_order.ulid}`}
                        className="text-neutral-600 hover:text-neutral-900 underline underline-offset-2"
                      >
                        PO {invoice.purchase_order.po_reference}
                      </Link>
                    </>
                  )}
                </span>
              )}
            </div>
          </CardHeader>
          <CardBody>
            {invoice.description && (
              <p className="text-sm text-neutral-500 mb-4">{invoice.description}</p>
            )}
            
            {/* Invoice Info - 2 column grid */}
            <InfoList columns={2}>
              <InfoRow label="Invoice Date" value={invoice.invoice_date} />
              <InfoRow label="Due Date" value={invoice.due_date} />
              {invoice.atc_code && <InfoRow label="ATC Code" value={invoice.atc_code} />}
              {invoice.or_number && <InfoRow label="OR Number" value={invoice.or_number} />}
            </InfoList>
            
            {/* Amount Summary - compact horizontal layout below */}
            <div className="mt-4 bg-neutral-50 rounded-lg p-4">
              <h4 className="text-sm font-medium text-neutral-900 mb-3">Amount Summary</h4>
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                <AmountBox label="Net Amount" value={invoice.net_amount * 100} />
                <AmountBox label="VAT" value={invoice.vat_amount * 100} />
                {invoice.ewt_amount > 0 ? (
                  <AmountBox label={`EWT (${((invoice.ewt_rate ?? 0) * 100).toFixed(0)}%)`} value={invoice.ewt_amount * 100} />
                ) : (
                  <AmountBox label="EWT" value={0} />
                )}
                <AmountBox label="Net Payable" value={invoice.net_payable * 100} bold />
                <AmountBox label="Balance Due" value={invoice.balance_due * 100} highlight={invoice.balance_due > 0} />
              </div>
              {(invoice.total_paid ?? 0) > 0 && (
                <div className="mt-3 pt-3 border-t border-neutral-200">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-neutral-500">Total Paid</span>
                    <CurrencyAmount centavos={invoice.total_paid * 100} />
                  </div>
                </div>
              )}
            </div>
            
            {invoice.rejection_note && (
              <div className="mt-4 p-3 bg-red-50 border border-red-100 rounded-lg">
                <p className="text-xs font-medium text-red-600 mb-1">Rejection Note</p>
                <p className="text-sm text-red-700">{invoice.rejection_note}</p>
              </div>
            )}

            {isCancelled && (
              <div className="mt-4 p-3 bg-neutral-100 border border-neutral-200 rounded-lg">
                <p className="text-xs font-medium text-neutral-600 mb-1">Cancelled</p>
                <p className="text-sm text-neutral-700">This invoice has been cancelled.</p>
              </div>
            )}
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

        {/* Overdue badge */}
        {invoice.is_overdue && !isCancelled && (
          <div className="flex items-center gap-2 bg-neutral-50 border border-neutral-200 rounded px-4 py-3 text-sm text-neutral-700">
            <CheckCircle className="h-4 w-4 flex-shrink-0" />
            This invoice is <span className="font-semibold">overdue</span>.
          </div>
        )}

        {/* Document Chain */}
        <Card>
          <CardHeader>Document Chain</CardHeader>
          <CardBody>
            <ChainRecordTimeline documentType="vendor_invoice" documentId={invoice.id} />
          </CardBody>
        </Card>
      </div>
    </div>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function AmountBox({
  label,
  value,
  bold,
  highlight,
}: {
  label: string
  value: number
  bold?: boolean
  highlight?: boolean
}) {
  return (
    <div className="text-center">
      <div className={[
        'font-mono tabular-nums',
        bold ? 'font-semibold text-base' : 'font-medium text-sm',
        highlight ? 'text-neutral-900' : 'text-neutral-800',
      ].join(' ')}>
        <CurrencyAmount centavos={value} size={bold ? 'base' : 'sm'} />
      </div>
      <div className="text-xs text-neutral-500 mt-0.5">{label}</div>
    </div>
  )
}
