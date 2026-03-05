import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, RefreshCw } from 'lucide-react'
import {
  useCustomerInvoices,
  useApproveCustomerInvoice,
  useCancelCustomerInvoice,
} from '@/hooks/useAR'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { CustomerInvoice, CustomerInvoiceStatus } from '@/types/ar'

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
    <span className={`px-2 py-0.5 rounded text-xs font-medium capitalize ${STATUS_STYLES[status]}`}>
      {status.replace('_', ' ')}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Tab list
// ---------------------------------------------------------------------------

const TABS: Array<{ label: string; value: CustomerInvoiceStatus | 'all' }> = [
  { label: 'All', value: 'all' },
  { label: 'Draft', value: 'draft' },
  { label: 'Approved', value: 'approved' },
  { label: 'Partially Paid', value: 'partially_paid' },
  { label: 'Paid', value: 'paid' },
  { label: 'Written Off', value: 'written_off' },
]

// ---------------------------------------------------------------------------
// Row Actions
// ---------------------------------------------------------------------------

function ApproveDraftButton({ invoice }: { invoice: CustomerInvoice }) {
  const approveMut = useApproveCustomerInvoice()
  return (
    <ConfirmDestructiveDialog
      title="Approve Invoice"
      description={`Approve invoice and auto-post journal entry? Invoice number (INV-YYYY-MM-NNNNNN) will be generated upon approval.`}
      confirmWord="APPROVE"
      confirmLabel="Approve"
      onConfirm={async () => { await approveMut.mutateAsync(invoice.ulid) }}
    >
      <button className="text-xs text-green-600 hover:underline">Approve</button>
    </ConfirmDestructiveDialog>
  )
}

function CancelDraftButton({ invoice }: { invoice: CustomerInvoice }) {
  const cancelMut = useCancelCustomerInvoice()
  return (
    <ConfirmDestructiveDialog
      title="Cancel Invoice"
      description={`Cancel this draft invoice? This action cannot be undone.`}
      confirmWord="CANCEL"
      confirmLabel="Cancel Invoice"
      onConfirm={async () => { await cancelMut.mutateAsync(invoice.ulid) }}
    >
      <button className="text-xs text-red-500 hover:underline">Cancel</button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Customer Invoices Page
// ---------------------------------------------------------------------------

export default function CustomerInvoicesPage() {
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<CustomerInvoiceStatus | 'all'>('all')

  const filters = activeTab === 'all' ? {} : { status: activeTab }
  const { data, isLoading, refetch } = useCustomerInvoices(filters)
  const invoices = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Customer Invoices</h1>
          <p className="text-sm text-gray-500 mt-0.5">Track and manage AR invoices</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => refetch()} className="p-2 rounded-lg border hover:bg-gray-50 text-gray-500">
            <RefreshCw className="w-4 h-4" />
          </button>
          <button
            onClick={() => navigate('/ar/invoices/new')}
            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700"
          >
            <Plus className="w-4 h-4" /> New Invoice
          </button>
        </div>
      </div>

      {/* Status Tabs */}
      <div className="flex gap-1 border-b">
        {TABS.map((tab) => (
          <button
            key={tab.value}
            onClick={() => setActiveTab(tab.value)}
            className={`px-3 py-2 text-sm font-medium border-b-2 transition-colors ${
              activeTab === tab.value
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : (
        <div className="overflow-x-auto rounded-xl border">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                {['Invoice #', 'Customer', 'Date', 'Due Date', 'Total', 'Balance Due', 'Status', ''].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-100">
              {invoices.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-3 py-8 text-center text-gray-400">
                    No invoices found.
                  </td>
                </tr>
              ) : (
                invoices.map((inv) => (
                  <tr
                    key={inv.id}
                    className={`even:bg-slate-50 hover:bg-blue-50/60 transition-colors ${inv.is_overdue ? 'bg-red-50' : ''}`}
                  >
                    <td className="px-3 py-2 font-mono text-xs text-gray-700">
                      {inv.invoice_number ?? <span className="text-gray-400 italic">Draft</span>}
                    </td>
                    <td className="px-3 py-2 font-medium text-gray-900">
                      {inv.customer?.name ?? `Customer #${inv.customer_id}`}
                    </td>
                    <td className="px-3 py-2 text-gray-500">{inv.invoice_date}</td>
                    <td className={`px-4 py-3 ${inv.is_overdue ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
                      {inv.due_date}
                      {inv.is_overdue && <span className="ml-1 text-xs">(overdue)</span>}
                    </td>
                    <td className="px-3 py-2 text-gray-900">₱{inv.total_amount.toLocaleString()}</td>
                    <td className="px-3 py-2 text-gray-700">₱{inv.balance_due.toLocaleString()}</td>
                    <td className="px-3 py-2">
                      <StatusBadge label={inv.status} />
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-3">
                        <button
                          onClick={() => navigate(`/ar/invoices/${inv.ulid}`)}
                          className="text-xs text-blue-600 hover:underline"
                        >
                          View
                        </button>
                        {inv.status === 'draft' && (
                          <>
                            <ApproveDraftButton invoice={inv} />
                            <CancelDraftButton invoice={inv} />
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
