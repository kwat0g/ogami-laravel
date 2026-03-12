import { useState } from 'react'
import { Plus } from 'lucide-react'
import {
  useVendorInvoices,
  useCreateVendorInvoice,
  useVendorGoodsReceipts,
  type VendorPortalInvoice,
  type VendorPortalGoodsReceipt,
} from '@/hooks/useVendorPortal'
import { toast } from 'sonner'

const defaultForm = {
  goods_receipt_id: 0,
  invoice_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  net_amount: 0,
  vat_amount: 0,
  or_number: '',
  description: '',
}

export default function VendorInvoicesPage(): React.ReactElement {
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(defaultForm)

  const { data: invoicesData, isLoading } = useVendorInvoices()
  const { data: grData } = useVendorGoodsReceipts()
  const create = useCreateVendorInvoice()

  const invoices: VendorPortalInvoice[] = invoicesData?.data ?? []
  const eligibleGRs: VendorPortalGoodsReceipt[] = (grData?.data ?? []).filter(
    (gr) => gr.status === 'confirmed' && !gr.ap_invoice_created
  )

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!form.goods_receipt_id) {
      toast.error('Please select a Goods Receipt.')
      return
    }
    create.mutate(
      {
        goods_receipt_id: form.goods_receipt_id,
        invoice_date: form.invoice_date,
        due_date: form.due_date,
        net_amount: form.net_amount,
        vat_amount: form.vat_amount || undefined,
        or_number: form.or_number || undefined,
        description: form.description || undefined,
      },
      {
        onSuccess: () => {
          toast.success('Invoice submitted. Accounting will review.')
          setShowForm(false)
          setForm(defaultForm)
        },
        onError: (err: unknown) => {
          const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
          toast.error(msg ?? 'Failed to submit invoice.')
        },
      }
    )
  }

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading invoices…</p>

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h1 className="text-2xl font-bold text-neutral-900">Invoices</h1>
          <p className="text-sm text-neutral-500">Submit and track invoices for confirmed deliveries.</p>
        </div>
        <button
          onClick={() => setShowForm(true)}
          disabled={eligibleGRs.length === 0}
          className="flex items-center gap-1.5 text-sm bg-neutral-900 text-white rounded-md px-3 py-1.5 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          title={eligibleGRs.length === 0 ? 'No confirmed GRs without an invoice' : undefined}
        >
          <Plus className="w-3.5 h-3.5" />
          Submit Invoice
        </button>
      </div>

      {showForm && (
        <div className="bg-white border border-neutral-200 rounded-lg p-5 mb-6">
          <h2 className="text-sm font-semibold text-neutral-800 mb-4">New Invoice</h2>
          <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
            <Field label="Goods Receipt" className="col-span-2">
              <select
                value={form.goods_receipt_id}
                onChange={(e) => setForm((f) => ({ ...f, goods_receipt_id: Number(e.target.value) }))}
                className="input"
                required
              >
                <option value={0}>Select a confirmed Goods Receipt…</option>
                {eligibleGRs.map((gr) => (
                  <option key={gr.id} value={gr.id}>
                    {gr.gr_reference} — PO: {gr.purchase_order?.po_reference ?? '?'} ({gr.received_date})
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Invoice Date">
              <input
                type="date"
                value={form.invoice_date}
                onChange={(e) => setForm((f) => ({ ...f, invoice_date: e.target.value }))}
                required
                className="input"
              />
            </Field>
            <Field label="Due Date">
              <input
                type="date"
                value={form.due_date}
                onChange={(e) => setForm((f) => ({ ...f, due_date: e.target.value }))}
                required
                className="input"
              />
            </Field>
            <Field label="Net Amount (₱)">
              <input
                type="number"
                step="0.01"
                min={0.01}
                value={form.net_amount}
                onChange={(e) => setForm((f) => ({ ...f, net_amount: Number(e.target.value) }))}
                required
                className="input"
              />
            </Field>
            <Field label="VAT Amount (₱)">
              <input
                type="number"
                step="0.01"
                min={0}
                value={form.vat_amount}
                onChange={(e) => setForm((f) => ({ ...f, vat_amount: Number(e.target.value) }))}
                className="input"
              />
            </Field>
            <Field label="OR Number">
              <input
                value={form.or_number}
                onChange={(e) => setForm((f) => ({ ...f, or_number: e.target.value }))}
                className="input"
                placeholder="Official receipt number"
              />
            </Field>
            <Field label="Description">
              <input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                className="input"
                placeholder="Optional description"
              />
            </Field>
            <div className="col-span-2 flex items-center gap-4 justify-end">
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="text-sm text-neutral-500 hover:text-neutral-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={create.isPending}
                className="text-sm bg-neutral-900 text-white rounded-md px-4 py-2 hover:bg-neutral-800 disabled:opacity-50"
              >
                {create.isPending ? 'Submitting…' : 'Submit Invoice'}
              </button>
            </div>
          </form>
        </div>
      )}

      {invoices.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No invoices submitted yet.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Invoice Date</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Due Date</th>
                <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Net</th>
                <th className="text-right px-4 py-2 font-medium text-neutral-600 text-xs uppercase">VAT</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">OR #</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600 text-xs uppercase">Status</th>
              </tr>
            </thead>
            <tbody>
              {invoices.map((inv) => (
                <tr key={inv.id} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 text-neutral-700">{inv.invoice_date}</td>
                  <td className="px-4 py-3 text-neutral-700">{inv.due_date}</td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-800">
                    ₱{Number(inv.net_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-600">
                    ₱{Number(inv.vat_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3 text-neutral-600">{inv.or_number ?? '—'}</td>
                  <td className="px-4 py-3">
                    <InvoiceStatusBadge status={inv.status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function Field({
  label,
  children,
  className = '',
}: {
  label: string
  children: React.ReactNode
  className?: string
}): React.ReactElement {
  return (
    <div className={className}>
      <label className="block text-xs font-medium text-neutral-600 mb-1">{label}</label>
      {children}
    </div>
  )
}

function InvoiceStatusBadge({ status }: { status: string }): React.ReactElement {
  const colors: Record<string, string> = {
    draft: 'bg-neutral-100 text-neutral-600',
    pending_approval: 'bg-amber-100 text-amber-700',
    head_noted: 'bg-blue-100 text-blue-700',
    manager_checked: 'bg-blue-100 text-blue-700',
    officer_reviewed: 'bg-blue-100 text-blue-700',
    approved: 'bg-emerald-100 text-emerald-700',
    partially_paid: 'bg-cyan-100 text-cyan-700',
    paid: 'bg-emerald-200 text-emerald-800',
  }
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[status] ?? 'bg-neutral-100 text-neutral-600'}`}>
      {status.replace(/_/g, ' ')}
    </span>
  )
}
