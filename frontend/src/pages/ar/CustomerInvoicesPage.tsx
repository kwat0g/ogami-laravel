import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, RefreshCw } from 'lucide-react'
import {
  useCustomerInvoices,
  useApproveCustomerInvoice,
  useCancelCustomerInvoice,
} from '@/hooks/useAR'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { PageHeader } from '@/components/ui/PageHeader'
import type { CustomerInvoice, CustomerInvoiceStatus } from '@/types/ar'

// ---------------------------------------------------------------------------
// Status Badge
// ---------------------------------------------------------------------------

const STATUS_STYLES: Record<CustomerInvoiceStatus, string> = {
  draft:          'bg-neutral-100 text-neutral-600',
  approved:       'bg-neutral-100 text-neutral-700',
  partially_paid: 'bg-neutral-100 text-neutral-700',
  paid:           'bg-neutral-100 text-neutral-700',
  written_off:    'bg-neutral-100 text-neutral-600',
  cancelled:      'bg-neutral-100 text-neutral-400',
}

function StatusBadge({ status }: { status: CustomerInvoiceStatus }) {
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium capitalize ${STATUS_STYLES[status]}`}>
      {status?.replace('_', ' ') || 'Unknown'}
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
      <button className="text-xs text-neutral-600 hover:underline">Approve</button>
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
      <button className="text-xs text-neutral-500 hover:underline">Cancel</button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Customer Invoices Page
// ---------------------------------------------------------------------------

export default function CustomerInvoicesPage() {
  const navigate = useNavigate()
  const canCreate = useAuthStore(s => s.hasPermission('customer_invoices.create'))
  const canApprove = useAuthStore(s => s.hasPermission('customer_invoices.approve'))
  const canCancel = useAuthStore(s => s.hasPermission('customer_invoices.cancel'))
  const [activeTab, setActiveTab] = useState<CustomerInvoiceStatus | 'all'>('all')

  const filters = activeTab === 'all' ? {} : { status: activeTab }
  const { data, isLoading, refetch } = useCustomerInvoices(filters)
  const invoices = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <PageHeader title="Customer Invoices" />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">Track and manage AR invoices</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => refetch()} className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 text-neutral-500">
            <RefreshCw className="w-4 h-4" />
          </button>
          {canCreate && (
            <button
              onClick={() => navigate('/ar/invoices/new')}
              className="inline-flex items-center gap-1.5 px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
            >
              <Plus className="w-4 h-4" /> New Invoice
            </button>
          )}
        </div>
      </div>

      {/* Status Tabs */}
      <div className="flex gap-1 border-b border-neutral-200">
        {TABS.map((tab) => (
          <button
            key={tab.value}
            onClick={() => setActiveTab(tab.value)}
            className={`px-3 py-2 text-sm font-medium border-b-2 transition-colors ${
              activeTab === tab.value
                ? 'border-neutral-900 text-neutral-900'
                : 'border-transparent text-neutral-500 hover:text-neutral-700'
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
        <div className="overflow-x-auto rounded border border-neutral-200">
          <table className="min-w-full divide-y divide-neutral-100 text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {['Invoice #', 'Customer', 'Date', 'Due Date', 'Total', 'Balance Due', 'Status', ''].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-neutral-100">
              {invoices.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-3 py-8 text-center text-neutral-400">
                    No invoices found.
                  </td>
                </tr>
              ) : (
                invoices.map((inv) => (
                  <tr
                    key={inv.id}
                    className={`hover:bg-neutral-50 transition-colors ${inv.is_overdue ? 'bg-neutral-50' : ''}`}
                  >
                    <td className="px-3 py-2 font-mono text-xs text-neutral-700">
                      {inv.invoice_number ?? <span className="text-neutral-400 italic">Draft</span>}
                    </td>
                    <td className="px-3 py-2 font-medium text-neutral-900">
                      {inv.customer?.name ?? `Customer #${inv.customer_id}`}
                    </td>
                    <td className="px-3 py-2 text-neutral-500">{inv.invoice_date}</td>
                    <td className={`px-4 py-3 ${inv.is_overdue ? 'text-neutral-800 font-medium' : 'text-neutral-500'}`}>
                      {inv.due_date}
                      {inv.is_overdue && <span className="ml-1 text-xs">(overdue)</span>}
                    </td>
                    <td className="px-3 py-2 text-neutral-900">₱{inv.total_amount.toLocaleString()}</td>
                    <td className="px-3 py-2 text-neutral-700">₱{inv.balance_due.toLocaleString()}</td>
                    <td className="px-3 py-2">
                      <StatusBadge status={inv.status}>{inv.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-3">
                        <button
                          onClick={() => navigate(`/ar/invoices/${inv.ulid}`)}
                          className="text-xs text-neutral-600 hover:underline"
                        >
                          View
                        </button>
                        {inv.status === 'draft' && (
                          <>
                            {canApprove && <ApproveDraftButton invoice={inv} />}
                            {canCancel && <CancelDraftButton invoice={inv} />}
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
