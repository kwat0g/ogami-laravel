import { useState } from 'react'
import { Plus, FileText } from 'lucide-react'
import {
  useVendorInvoices,
  useCreateVendorInvoice,
  useVendorGoodsReceipts,
  type VendorPortalInvoice,
  type VendorPortalGoodsReceipt,
} from '@/hooks/useVendorPortal'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { statusBadges } from '@/styles/design-system'

const defaultForm = {
  goods_receipt_id: 0,
  invoice_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  net_amount: 0,
  vat_amount: 0,
  or_number: '',
  description: '',
}

const INVOICE_STATUS_COLORS: Record<string, string> = {
  draft: statusBadges.draft,
  submitted: statusBadges.sent,
  pending_approval: statusBadges.pending,
  head_noted: statusBadges.inProgress,
  manager_checked: statusBadges.inProgress,
  officer_reviewed: statusBadges.underReview,
  approved: statusBadges.approved,
  partially_paid: statusBadges.partiallyReceived,
  paid: statusBadges.completed,
}

export default function VendorInvoicesPage(): React.ReactElement {
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(defaultForm)

  const { data: invoicesData, isLoading } = useVendorInvoices()
  const { data: grData } = useVendorGoodsReceipts()
  const create = useCreateVendorInvoice()
  const canSubmitInvoice = useAuthStore((s) => s.hasPermission('vendor_portal.update_fulfillment'))

  const invoices: VendorPortalInvoice[] = invoicesData?.data ?? []
  const eligibleGRs: VendorPortalGoodsReceipt[] = (grData?.data ?? []).filter(
    (gr) => gr.status === 'confirmed' && !gr.ap_invoice_created
  )

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!form.goods_receipt_id) {
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
          toast.error(firstErrorMessage(err))
        },
      }
    )
  }

  return (
    <div className="space-y-5">
      <PageHeader
        title="Invoices"
        subtitle="Submit and track invoices for confirmed deliveries"
        icon={<FileText className="h-5 w-5 text-neutral-600" />}
        actions={
          canSubmitInvoice ? (
            <button
              onClick={() => setShowForm(true)}
              disabled={eligibleGRs.length === 0}
              className="inline-flex items-center gap-2 text-sm bg-neutral-900 text-white rounded-lg px-4 py-2 font-medium hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title={eligibleGRs.length === 0 ? 'No confirmed GRs without an invoice' : undefined}
            >
              <Plus className="w-4 h-4" />
              Submit Invoice
            </button>
          ) : undefined
        }
      />

      {showForm && canSubmitInvoice && (
        <Card className="overflow-hidden">
          <div className="px-5 py-4 border-b border-neutral-100">
            <h2 className="text-sm font-semibold text-neutral-900">New Invoice</h2>
            <p className="text-xs text-neutral-500 mt-0.5">Fill in the details to submit a new invoice for a confirmed Goods Receipt.</p>
          </div>
          <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4 p-5">
            <Field label="Goods Receipt" className="col-span-2">
              <select
                value={form.goods_receipt_id}
                onChange={(e) => setForm((f) => ({ ...f, goods_receipt_id: Number(e.target.value) }))}
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
                required
              >
                <option value={0}>Select a confirmed Goods Receipt...</option>
                {eligibleGRs.map((gr) => (
                  <option key={gr.id} value={gr.id}>
                    {gr.gr_reference} -- PO: {gr.purchase_order?.po_reference ?? '?'} ({gr.received_date})
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
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
              />
            </Field>
            <Field label="Due Date">
              <input
                type="date"
                value={form.due_date}
                onChange={(e) => setForm((f) => ({ ...f, due_date: e.target.value }))}
                required
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
              />
            </Field>
            <Field label="Net Amount (&#8369;)">
              <input
                type="number"
                step="0.01"
                min={0.01}
                value={form.net_amount}
                onChange={(e) => setForm((f) => ({ ...f, net_amount: Number(e.target.value) }))}
                required
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
              />
            </Field>
            <Field label="VAT Amount (&#8369;)">
              <input
                type="number"
                step="0.01"
                min={0}
                value={form.vat_amount}
                onChange={(e) => setForm((f) => ({ ...f, vat_amount: Number(e.target.value) }))}
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
              />
            </Field>
            <Field label="OR Number">
              <input
                value={form.or_number}
                onChange={(e) => setForm((f) => ({ ...f, or_number: e.target.value }))}
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
                placeholder="Official receipt number"
              />
            </Field>
            <Field label="Description">
              <input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                className="w-full px-3 py-2 bg-white border border-neutral-300 rounded-md text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
                placeholder="Optional description"
              />
            </Field>
            <div className="col-span-2 flex items-center gap-3 justify-end pt-2 border-t border-neutral-100">
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-900 font-medium transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={create.isPending}
                className="inline-flex items-center gap-2 text-sm bg-neutral-900 text-white rounded-lg px-4 py-2 font-medium hover:bg-neutral-800 disabled:opacity-50 transition-colors"
              >
                {create.isPending ? 'Submitting...' : 'Submit Invoice'}
              </button>
            </div>
          </form>
        </Card>
      )}

      {isLoading ? (
        <SkeletonLoader rows={5} />
      ) : invoices.length === 0 ? (
        <div className="text-center py-16">
          <div className="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <FileText className="h-8 w-8 text-neutral-400" />
          </div>
          <h3 className="text-base font-medium text-neutral-900 mb-1">No invoices submitted yet</h3>
          <p className="text-sm text-neutral-500 max-w-sm mx-auto">
            Submit invoices after your goods receipts are confirmed.
          </p>
        </div>
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Invoice Date</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Due Date</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Net</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">VAT</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">OR #</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {invoices.map((inv) => (
                <tr key={inv.id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 text-neutral-700">{inv.invoice_date}</td>
                  <td className="px-4 py-3 text-neutral-700">{inv.due_date}</td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-800">
                    &#8369;{Number(inv.net_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-600">
                    &#8369;{Number(inv.vat_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3 text-neutral-600">{inv.or_number ?? '\u2014'}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize ${INVOICE_STATUS_COLORS[inv.status] ?? statusBadges.draft}`}>
                      {inv.status.replace(/_/g, ' ')}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
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
      <label className="block text-sm font-medium text-neutral-700 mb-1.5">{label}</label>
      {children}
    </div>
  )
}
