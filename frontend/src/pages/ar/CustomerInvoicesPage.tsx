import { formatPesoAmount } from '@/lib/formatters'
import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { useNavigate } from 'react-router-dom'
import { Plus, RefreshCw } from 'lucide-react'
import SearchInput from '@/components/ui/SearchInput'
import {
  useCustomerInvoices,
  useApproveCustomerInvoice,
  useCancelCustomerInvoice,
} from '@/hooks/useAR'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { PageHeader } from '@/components/ui/PageHeader'
import { firstErrorMessage } from '@/lib/errorHandler'
import { ExportButton } from '@/components/ui/ExportButton'
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
  
  const handleApprove = async () => {
    try {
      await approveMut.mutateAsync(invoice.ulid)
      toast.success('Invoice approved successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
      throw err // Re-throw to let dialog know it failed
    }
  }

  return (
    <ConfirmDialog
      title="Approve Invoice?"
      description={`Approve invoice ${invoice.invoice_number || 'Draft'}? An invoice number (INV-YYYY-MM-NNNNNN) will be generated and a journal entry will be auto-posted.`}
      confirmLabel="Approve"
      onConfirm={handleApprove}
    >
      <button className="text-xs text-neutral-600 hover:underline">Approve</button>
    </ConfirmDialog>
  )
}

function CancelDraftButton({ invoice }: { invoice: CustomerInvoice }) {
  const cancelMut = useCancelCustomerInvoice()
  
  const handleCancel = async () => {
    try {
      await cancelMut.mutateAsync(invoice.ulid)
      toast.success('Invoice cancelled successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
      throw err // Re-throw to let dialog know it failed
    }
  }

  return (
    <ConfirmDestructiveDialog
      title="Cancel Invoice?"
      description={`Cancel invoice ${invoice.invoice_number || 'Draft'}? This action cannot be undone.`}
      confirmWord="CANCEL"
      confirmLabel="Cancel Invoice"
      onConfirm={handleCancel}
    >
      <button className="text-xs text-neutral-500 hover:underline">Cancel</button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Bulk Actions Component
// ---------------------------------------------------------------------------

function BulkActions({ 
  selectedCount, 
  onClear 
}: { 
  selectedCount: number
  onClear: () => void 
}) {
  if (selectedCount === 0) return null

  return (
    <div className="flex items-center gap-2 bg-neutral-50 px-3 py-2 rounded border border-neutral-200">
      <span className="text-sm text-neutral-600">{selectedCount} selected</span>
      <button 
        onClick={onClear}
        className="text-xs text-neutral-500 hover:text-neutral-700 underline"
      >
        Clear
      </button>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Customer Invoices Page
// ---------------------------------------------------------------------------

export default function CustomerInvoicesPage() {
  const navigate = useNavigate()
  const canCreate = useAuthStore(s => s.hasPermission(PERMISSIONS.customer_invoices.create))
  const canApprove = useAuthStore(s => s.hasPermission(PERMISSIONS.customer_invoices.approve))
  const canCancel = useAuthStore(s => s.hasPermission(PERMISSIONS.customer_invoices.cancel))
  const [activeTab, setActiveTab] = useState<CustomerInvoiceStatus | 'all'>('all')
  const [selectedInvoices, setSelectedInvoices] = useState<Set<string>>(new Set())
  const [search, setSearch] = useState('')
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [debouncedSearch, setDebouncedSearch] = useState('')

  const handleSearch = useCallback((val: string) => {
    setDebouncedSearch(val)
  }, [])

  const filters = {
    ...(activeTab === 'all' ? {} : { status: activeTab }),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  }
  const { data, isLoading, refetch } = useCustomerInvoices(filters)
  const invoices = data?.data ?? []

  const handleRefresh = async () => {
    try {
      await refetch()
      toast.success('Invoice list refreshed.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const toggleSelection = (ulid: string) => {
    setSelectedInvoices(prev => {
      const next = new Set(prev)
      if (next.has(ulid)) {
        next.delete(ulid)
      } else {
        next.add(ulid)
      }
      return next
    })
  }

  const clearSelection = () => setSelectedInvoices(new Set())

  return (
    <div className="space-y-4">
      <PageHeader
        title="Customer Invoices"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'invoice_number', label: 'Invoice #' },
                { key: 'customer.name', label: 'Customer' },
                { key: 'status', label: 'Status' },
                { key: 'total_amount_centavos', label: 'Amount', format: (v: unknown) => `${((v as number) / 100).toFixed(2)}` },
                { key: 'invoice_date', label: 'Invoice Date' },
                { key: 'due_date', label: 'Due Date' },
              ]}
              filename="ar-invoices"
            />
            <button onClick={handleRefresh} className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 text-neutral-500">
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
        }
      />

      {/* Search + Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <SearchInput
          value={search}
          onChange={setSearch}
          onSearch={handleSearch}
          placeholder="Search invoices by number or customer..."
          className="w-72"
        />
        <select
          value={activeTab}
          onChange={(e) => { setActiveTab(e.target.value as CustomerInvoiceStatus | 'all'); clearSelection() }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          {TABS.map((tab) => (
            <option key={tab.value} value={tab.value}>{tab.label}</option>
          ))}
        </select>
        <BulkActions selectedCount={selectedInvoices.size} onClear={clearSelection} />
      </div>

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : (
        <div className="overflow-x-auto rounded border border-neutral-200">
          <table className="min-w-full divide-y divide-neutral-100 text-sm">
            <thead className="bg-neutral-50">
              <tr>
                <th className="px-3 py-2.5 text-left">
                  <input 
                    type="checkbox"
                    checked={invoices.length > 0 && selectedInvoices.size === invoices.length}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelectedInvoices(new Set(invoices.map(i => i.ulid)))
                      } else {
                        clearSelection()
                      }
                    }}
                    className="rounded border-neutral-300"
                  />
                </th>
                {['Invoice #', 'Customer', 'Date', 'Due Date', 'Total', 'Balance Due', 'Status'].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-neutral-100">
              {invoices.length === 0 ? (
                <tr>
                  <td colSpan={9} className="px-3 py-8 text-center text-neutral-400">
                    No invoices found.
                  </td>
                </tr>
              ) : (
                invoices.map((inv) => (
                  <tr
                    key={inv.id}
                    onClick={() => navigate(`/ar/invoices/${inv.ulid}`)}
                    className={`hover:bg-neutral-50 transition-colors cursor-pointer ${inv.is_overdue ? 'bg-neutral-50' : ''}`}
                  >
                    <td className="px-3 py-2" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox"
                        checked={selectedInvoices.has(inv.ulid)}
                        onChange={() => toggleSelection(inv.ulid)}
                        className="rounded border-neutral-300"
                      />
                    </td>
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
                    <td className="px-3 py-2 text-neutral-900">{formatPesoAmount(inv.total_amount)}</td>
                    <td className="px-3 py-2 text-neutral-700">{formatPesoAmount(inv.balance_due)}</td>
                    <td className="px-3 py-2">
                      <StatusBadge status={inv.status}>{inv.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                    </td>
                    <td className="px-3 py-2" onClick={(e) => e.stopPropagation()}>
                      {inv.status === 'draft' && (
                        <div className="flex items-center gap-3">
                          {canApprove && <ApproveDraftButton invoice={inv} />}
                          {canCancel && <CancelDraftButton invoice={inv} />}
                        </div>
                      )}
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
