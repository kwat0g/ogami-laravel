import { useState } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import {
  useCustomerInvoice,
  useReceivePayment,
  useWriteOffInvoice,
  useApproveCustomerInvoice,
} from '@/hooks/useAR'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { CustomerInvoiceStatus, ReceivePaymentPayload, WriteOffPayload } from '@/types/ar'

// ---------------------------------------------------------------------------
// Status Badge
// ---------------------------------------------------------------------------

const STATUS_STYLES: Record<CustomerInvoiceStatus, string> = {
  draft:          'bg-gray-100 text-gray-600',
  approved:       'bg-blue-100 text-blue-700',
  partially_paid: 'bg-yellow-100 text-yellow-700',
  paid:           'bg-green-100 text-green-700',
  written_off:    'bg-red-100 text-red-700',
  cancelled:      'bg-gray-100 text-gray-400',
}

function StatusBadge({ status }: { status: CustomerInvoiceStatus }) {
  return (
    <span className={`px-2.5 py-0.5 rounded text-sm font-medium capitalize ${STATUS_STYLES[status]}`}>
      {status.replace('_', ' ')}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Receive Payment Panel
// ---------------------------------------------------------------------------

function ReceivePaymentPanel({
  invoiceId,
  balanceDue,
}: {
  invoiceId: string
  balanceDue: number
}) {
  const payMut = useReceivePayment(invoiceId)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<ReceivePaymentPayload>({
    amount: balanceDue,
    payment_date: new Date().toISOString().slice(0, 10),
    reference_number: '',
    payment_method: 'bank_transfer',
    cash_account_id: 0,
    ar_account_id: 0,
  })

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700"
      >
        Receive Payment
      </button>
    )
  }

  return (
    <div className="rounded-xl border p-4 space-y-3">
      <ExecutiveReadOnlyBanner />
      <h3 className="font-semibold text-gray-800">Record Payment</h3>
      {payMut.error && (
        <p className="text-sm text-red-600">{(payMut.error as Error).message}</p>
      )}
      <div className="grid grid-cols-2 gap-3">
        <label className="block">
          <span className="text-xs font-medium text-gray-600">Amount (₱) *</span>
          <input
            type="number"
            min={0.01}
            step="0.01"
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.amount}
            onChange={(e) => setForm((p) => ({ ...p, amount: parseFloat(e.target.value) || 0 }))}
          />
          {form.amount > balanceDue && (
            <p className="text-xs text-yellow-600 mt-0.5">
              ₱{(form.amount - balanceDue).toLocaleString()} excess → advance payment (AR-005)
            </p>
          )}
        </label>
        <label className="block">
          <span className="text-xs font-medium text-gray-600">Payment Date *</span>
          <input
            type="date"
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.payment_date}
            onChange={(e) => setForm((p) => ({ ...p, payment_date: e.target.value }))}
          />
        </label>
        <label className="block">
          <span className="text-xs font-medium text-gray-600">Cash Account ID *</span>
          <input
            type="number"
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.cash_account_id || ''}
            onChange={(e) => setForm((p) => ({ ...p, cash_account_id: parseInt(e.target.value) || 0 }))}
          />
        </label>
        <label className="block">
          <span className="text-xs font-medium text-gray-600">AR Account ID *</span>
          <input
            type="number"
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.ar_account_id || ''}
            onChange={(e) => setForm((p) => ({ ...p, ar_account_id: parseInt(e.target.value) || 0 }))}
          />
        </label>
        <label className="block">
          <span className="text-xs font-medium text-gray-600">Reference #</span>
          <input
            className="mt-1 block w-full border rounded-lg px-3 py-1.5 text-sm"
            value={form.reference_number ?? ''}
            onChange={(e) => setForm((p) => ({ ...p, reference_number: e.target.value || null }))}
          />
        </label>
      </div>
      <div className="flex gap-2">
        <button
          onClick={async () => {
            await payMut.mutateAsync(form)
            setOpen(false)
          }}
          disabled={payMut.isPending}
          className="px-4 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 disabled:opacity-60"
        >
          {payMut.isPending ? 'Saving…' : 'Record'}
        </button>
        <button onClick={() => setOpen(false)} className="px-4 py-1.5 border text-sm rounded-lg hover:bg-gray-50">
          Cancel
        </button>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Write-Off Button (AR-006)
// ---------------------------------------------------------------------------

function WriteOffSection({ invoiceId }: { invoiceId: string }) {
  const writeOffMut = useWriteOffInvoice(invoiceId)
  const [form] = useState<WriteOffPayload>({
    write_off_reason: '',
    bad_debt_account_id: 0,
    ar_account_id: 0,
  })

  return (
    <ConfirmDestructiveDialog
      title="Write Off Invoice (AR-006)"
      description="This will write off the remaining balance as bad debt. A journal entry (DR Bad Debt Expense / CR Accounts Receivable) will be auto-posted. This action requires the \`manager\` role with accounting department access."
      confirmWord="WRITEOFF"
      confirmLabel="Write Off"
      onConfirm={async () => { await writeOffMut.mutateAsync(form) }}
    >
      <button className="px-4 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50">
        Write Off (AR-006)
      </button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Invoice Detail Page
// ---------------------------------------------------------------------------

export default function CustomerInvoiceDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const invoiceId = id ?? null
  const { data: invoice, isLoading } = useCustomerInvoice(invoiceId)
  const approveMut = useApproveCustomerInvoice()

  if (isLoading) return <SkeletonLoader rows={10} />
  if (!invoice) return <p className="p-6 text-gray-500">Invoice not found.</p>

  const canApprove = invoice.status === 'draft'
  const canPay     = invoice.status === 'approved' || invoice.status === 'partially_paid'
  const canWriteOff = canPay && invoice.balance_due > 0

  return (
    <div className="p-6 max-w-3xl space-y-6">
      {/* Back */}
      <button
        onClick={() => navigate('/ar/invoices')}
        className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft className="w-4 h-4" /> Back to Invoices
      </button>

      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {invoice.invoice_number ?? <span className="italic text-gray-400">Draft Invoice</span>}
          </h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {invoice.customer?.name ?? `Customer #${invoice.customer_id}`}
          </p>
        </div>
        <StatusBadge label={invoice.status} />
      </div>

      {/* Details grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Invoice Date', value: invoice.invoice_date },
          { label: 'Due Date', value: invoice.due_date + (invoice.is_overdue ? ' ⚠️' : '') },
          { label: 'Subtotal', value: `₱${invoice.subtotal.toLocaleString()}` },
          { label: 'VAT', value: `₱${invoice.vat_amount.toLocaleString()}` },
          { label: 'Total', value: `₱${invoice.total_amount.toLocaleString()}` },
          { label: 'Total Paid', value: `₱${invoice.total_paid.toLocaleString()}` },
          { label: 'Balance Due', value: `₱${invoice.balance_due.toLocaleString()}` },
          { label: 'Fiscal Period', value: `#${invoice.fiscal_period_id}` },
        ].map(({ label, value }) => (
          <div key={label} className="rounded-lg border bg-gray-50 p-3">
            <p className="text-xs text-gray-500">{label}</p>
            <p className="font-semibold text-gray-900 mt-0.5">{value}</p>
          </div>
        ))}
      </div>

      {/* Description */}
      {invoice.description && (
        <p className="text-sm text-gray-600">{invoice.description}</p>
      )}

      {/* Actions */}
      <div className="flex flex-wrap gap-3">
        {canApprove && (
          <ConfirmDestructiveDialog
            title="Approve Invoice"
            description="Approve this invoice? An invoice number (INV-YYYY-MM-NNNNNN) will be generated and a journal entry will be auto-posted."
            confirmWord="APPROVE"
            confirmLabel="Approve"
            onConfirm={async () => { await approveMut.mutateAsync(invoiceId ?? '') }}
          >
            <button className="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
              Approve Invoice
            </button>
          </ConfirmDestructiveDialog>
        )}
        {canWriteOff && <WriteOffSection invoiceId={invoiceId ?? ''} />}
      </div>

      {/* Payment panel */}
      {canPay && (
        <ReceivePaymentPanel invoiceId={invoiceId ?? ''} balanceDue={invoice.balance_due} />
      )}

      {/* Payment history */}
      {invoice.payments && invoice.payments.length > 0 && (
        <div>
          <h2 className="text-base font-semibold text-gray-800 mb-2">Payment History</h2>
          <div className="rounded-xl border overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
              <thead className="bg-gray-50">
                <tr>
                  {['Date', 'Amount', 'Method', 'Reference'].map((h) => (
                    <th key={h} className="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 bg-white">
                {invoice.payments.map((p) => (
                  <tr key={p.id}>
                    <td className="px-4 py-2 text-gray-700">{p.payment_date}</td>
                    <td className="px-4 py-2 font-medium text-gray-900">₱{p.amount.toLocaleString()}</td>
                    <td className="px-4 py-2 text-gray-500 capitalize">{p.payment_method?.replace('_', ' ') ?? '—'}</td>
                    <td className="px-4 py-2 text-gray-500">{p.reference_number ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Write-off info */}
      {invoice.status === 'written_off' && invoice.write_off_reason && (
        <div className="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          <p className="font-semibold">Written Off</p>
          <p className="mt-1">{invoice.write_off_reason}</p>
          {invoice.write_off_at && (
            <p className="text-xs mt-1 opacity-70">{new Date(invoice.write_off_at).toLocaleString()}</p>
          )}
        </div>
      )}
    </div>
  )
}
