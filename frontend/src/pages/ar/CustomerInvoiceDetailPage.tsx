import { useState } from 'react'
import { toast } from 'sonner'
import { useParams, useNavigate } from 'react-router-dom'
import { FileText } from 'lucide-react'
import {
  useCustomerInvoice,
  useReceivePayment,
  useWriteOffInvoice,
  useApproveCustomerInvoice,
} from '@/hooks/useAR'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import PageHeader from '@/components/ui/PageHeader'
import StatusBadge from '@/components/ui/StatusBadge'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import type { CustomerInvoiceStatus, ReceivePaymentPayload, WriteOffPayload } from '@/types/ar'

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
        className="px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
      >
        Receive Payment
      </button>
    )
  }

  return (
    <div className="rounded border border-neutral-200 bg-white p-4 space-y-3">
      <h3 className="font-semibold text-neutral-800">Record Payment</h3>
      {payMut.error && (
        <p className="text-sm text-red-600">{(payMut.error as Error).message}</p>
      )}
      <div className="grid grid-cols-2 gap-3">
        <label className="block">
          <span className="text-xs font-medium text-neutral-600">Amount (₱) *</span>
          <input
            type="number"
            min={0.01}
            step="0.01"
            className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
            value={form.amount}
            onChange={(e) => setForm((p) => ({ ...p, amount: parseFloat(e.target.value) || 0 }))}
          />
          {form.amount > balanceDue && (
            <p className="text-xs text-neutral-600 mt-0.5">
              ₱{(form.amount - balanceDue).toLocaleString()} excess → advance payment (AR-005)
            </p>
          )}
        </label>
        <label className="block">
          <span className="text-xs font-medium text-neutral-600">Payment Date *</span>
          <input
            type="date"
            className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
            value={form.payment_date}
            onChange={(e) => setForm((p) => ({ ...p, payment_date: e.target.value }))}
          />
        </label>
        <label className="block">
          <span className="text-xs font-medium text-neutral-600">Cash Account ID *</span>
          <input
            type="number"
            className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
            value={form.cash_account_id || ''}
            onChange={(e) => setForm((p) => ({ ...p, cash_account_id: parseInt(e.target.value) || 0 }))}
          />
        </label>
        <label className="block">
          <span className="text-xs font-medium text-neutral-600">AR Account ID *</span>
          <input
            type="number"
            className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
            value={form.ar_account_id || ''}
            onChange={(e) => setForm((p) => ({ ...p, ar_account_id: parseInt(e.target.value) || 0 }))}
          />
        </label>
        <label className="block">
          <span className="text-xs font-medium text-neutral-600">Reference #</span>
          <input
            className="mt-1 block w-full border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
            value={form.reference_number ?? ''}
            onChange={(e) => setForm((p) => ({ ...p, reference_number: e.target.value || null }))}
          />
        </label>
      </div>
      <div className="flex gap-2">
        <button
          onClick={async () => {
            try {
              await payMut.mutateAsync(form)
              toast.success('Payment recorded.')
              setOpen(false)
            } catch {
              toast.error('Failed to record payment.')
            }
          }}
          disabled={payMut.isPending}
          className="px-4 py-1.5 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800 disabled:opacity-60"
        >
          {payMut.isPending ? 'Saving…' : 'Record'}
        </button>
        <button onClick={() => setOpen(false)} className="px-4 py-1.5 bg-white text-neutral-700 border border-neutral-300 text-sm rounded hover:bg-neutral-50">
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
      description="This will write off the remaining balance as bad debt. A journal entry (DR Bad Debt Expense / CR Accounts Receivable) will be auto-posted. This action requires the `manager` role with accounting department access."
      confirmWord="WRITEOFF"
      confirmLabel="Write Off"
      onConfirm={async () => {
        try {
          await writeOffMut.mutateAsync(form)
          toast.success('Invoice written off.')
        } catch {
          toast.error('Failed to write off invoice.')
        }
      }}
    >
      <button className="px-4 py-2 rounded bg-white text-red-600 border border-red-300 text-sm font-medium hover:bg-red-50">
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
    <div className="max-w-5xl mx-auto space-y-6">
      <PageHeader
        backTo="/ar/invoices"
        title={invoice.invoice_number ?? 'Draft Invoice'}
        subtitle={invoice.customer?.name ?? `Customer #${invoice.customer_id}`}
        icon={<FileText className="w-5 h-5" />}
        status={<StatusBadge label={invoice.status} />}
      />

      {/* Actions */}
      <div className="flex flex-wrap gap-3">
        {canApprove && (
          <ConfirmDestructiveDialog
            title="Approve Invoice"
            description="Approve this invoice? An invoice number (INV-YYYY-MM-NNNNNN) will be generated and a journal entry will be auto-posted."
            confirmWord="APPROVE"
            confirmLabel="Approve"
            onConfirm={async () => {
              try {
                await approveMut.mutateAsync(invoiceId ?? '')
                toast.success('Invoice approved.')
              } catch {
                toast.error('Failed to approve invoice.')
              }
            }}
          >
            <button className="px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800">
              Approve Invoice
            </button>
          </ConfirmDestructiveDialog>
        )}
        {canWriteOff && <WriteOffSection invoiceId={invoiceId ?? ''} />}
      </div>

      {/* Details grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card>
          <CardBody>
            <InfoRow label="Invoice Date" value={invoice.invoice_date} />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow 
              label="Due Date" 
              value={invoice.due_date + (invoice.is_overdue ? ' ⚠️' : '')} 
            />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow label="Subtotal" value={`₱${invoice.subtotal.toLocaleString()}`} />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow label="VAT" value={`₱${invoice.vat_amount.toLocaleString()}`} />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow label="Total" value={`₱${invoice.total_amount.toLocaleString()}`} />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow label="Total Paid" value={`₱${invoice.total_paid.toLocaleString()}`} />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow label="Balance Due" value={`₱${invoice.balance_due.toLocaleString()}`} />
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <InfoRow label="Fiscal Period" value={`#${invoice.fiscal_period_id}`} />
          </CardBody>
        </Card>
      </div>

      {/* Description */}
      {invoice.description && (
        <Card>
          <CardHeader>Description</CardHeader>
          <CardBody>
            <p className="text-sm text-neutral-600">{invoice.description}</p>
          </CardBody>
        </Card>
      )}

      {/* Payment panel */}
      {canPay && (
        <ReceivePaymentPanel invoiceId={invoiceId ?? ''} balanceDue={invoice.balance_due} />
      )}

      {/* Payment history */}
      {invoice.payments && invoice.payments.length > 0 && (
        <Card>
          <CardHeader>Payment History</CardHeader>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-neutral-100 text-sm">
              <thead className="bg-neutral-50">
                <tr>
                  {['Date', 'Amount', 'Method', 'Reference'].map((h) => (
                    <th key={h} className="px-4 py-2 text-left text-xs font-semibold text-neutral-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 bg-white">
                {invoice.payments.map((p) => (
                  <tr key={p.id}>
                    <td className="px-4 py-2 text-neutral-700">{p.payment_date}</td>
                    <td className="px-4 py-2 font-medium text-neutral-900">₱{p.amount.toLocaleString()}</td>
                    <td className="px-4 py-2 text-neutral-500 capitalize">{p.payment_method?.replace('_', ' ') ?? '—'}</td>
                    <td className="px-4 py-2 text-neutral-500">{p.reference_number ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {/* Write-off info */}
      {invoice.status === 'written_off' && invoice.write_off_reason && (
        <Card>
          <CardHeader>Write-Off Information</CardHeader>
          <CardBody>
            <p className="text-sm text-neutral-700">{invoice.write_off_reason}</p>
            {invoice.write_off_at && (
              <p className="text-xs text-neutral-500 mt-1">{new Date(invoice.write_off_at).toLocaleString()}</p>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  )
}
