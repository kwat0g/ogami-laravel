import { CreditCard, AlertTriangle, Clock3, FileText } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ExportPdfButton from '@/components/ui/ExportPdfButton'
import { useClientBillingSummary, type ClientBillingInvoice } from '@/hooks/useClientBilling'

function formatPeso(value: number): string {
  return `P${value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function InvoiceStatusBadge({ invoice }: { invoice: ClientBillingInvoice }): JSX.Element {
  if (invoice.balance_due <= 0) {
    return <span className="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700">Settled</span>
  }

  if (invoice.is_overdue) {
    return <span className="px-2 py-1 text-xs rounded-full bg-rose-100 text-rose-700">Overdue</span>
  }

  if (invoice.is_due_soon) {
    return <span className="px-2 py-1 text-xs rounded-full bg-amber-100 text-amber-700">Due Soon</span>
  }

  return <span className="px-2 py-1 text-xs rounded-full bg-sky-100 text-sky-700">Open</span>
}

export default function ClientBillingPage(): JSX.Element {
  const { data, isLoading } = useClientBillingSummary()

  if (isLoading) {
    return <SkeletonLoader rows={6} />
  }

  const invoices = data?.invoices ?? []
  const totals = data?.totals

  return (
    <div className="space-y-5">
      <PageHeader
        title="Billing"
        subtitle={`As of ${data?.as_of ?? ''}`}
        icon={<CreditCard className="h-5 w-5 text-neutral-700" />}
        actions={
          <ExportPdfButton
            href="/api/v1/crm/client-billing/statement/pdf"
            label="Export Statement PDF"
            filename={`client-statement-${data?.as_of ?? 'today'}.pdf`}
          />
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card>
          <div className="p-4">
            <div className="text-xs text-neutral-500">Outstanding Balance</div>
            <div className="mt-2 text-2xl font-semibold text-neutral-900">{formatPeso(totals?.outstanding ?? 0)}</div>
          </div>
        </Card>

        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-xs text-neutral-500">
              <AlertTriangle className="h-3.5 w-3.5 text-rose-500" />
              Overdue
            </div>
            <div className="mt-2 text-2xl font-semibold text-rose-700">{formatPeso(totals?.overdue ?? 0)}</div>
          </div>
        </Card>

        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-xs text-neutral-500">
              <Clock3 className="h-3.5 w-3.5 text-amber-500" />
              Due Within 7 Days
            </div>
            <div className="mt-2 text-2xl font-semibold text-amber-700">{formatPeso(totals?.due_soon ?? 0)}</div>
          </div>
        </Card>

        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-xs text-neutral-500">
              <FileText className="h-3.5 w-3.5 text-neutral-500" />
              Open Invoices
            </div>
            <div className="mt-2 text-2xl font-semibold text-neutral-900">{totals?.open_invoices ?? 0}</div>
          </div>
        </Card>
      </div>

      <Card>
        <div className="px-4 py-3 border-b border-neutral-100 flex items-center justify-between">
          <h2 className="font-semibold text-neutral-900">Invoice Balances</h2>
          <p className="text-xs text-neutral-500">Showing latest {totals?.invoices_returned ?? 0} invoices</p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-neutral-500 border-b border-neutral-100">
                <th className="px-4 py-3 font-medium">Invoice</th>
                <th className="px-4 py-3 font-medium">Invoice Date</th>
                <th className="px-4 py-3 font-medium">Due Date</th>
                <th className="px-4 py-3 font-medium text-right">Total</th>
                <th className="px-4 py-3 font-medium text-right">Paid</th>
                <th className="px-4 py-3 font-medium text-right">Balance</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {invoices.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">
                    No billing records available yet.
                  </td>
                </tr>
              ) : (
                invoices.map((invoice) => (
                  <tr key={invoice.ulid} className="border-b border-neutral-100 last:border-0">
                    <td className="px-4 py-3 font-medium text-neutral-900">
                      {invoice.invoice_number ?? 'Pending Approval'}
                    </td>
                    <td className="px-4 py-3 text-neutral-700">{invoice.invoice_date}</td>
                    <td className="px-4 py-3 text-neutral-700">{invoice.due_date}</td>
                    <td className="px-4 py-3 text-right text-neutral-700">{formatPeso(invoice.total_amount)}</td>
                    <td className="px-4 py-3 text-right text-neutral-700">{formatPeso(invoice.total_paid)}</td>
                    <td className="px-4 py-3 text-right font-semibold text-neutral-900">{formatPeso(invoice.balance_due)}</td>
                    <td className="px-4 py-3"><InvoiceStatusBadge invoice={invoice} /></td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  )
}
