import { useState } from 'react'
import { Plus, RefreshCw, ChevronRight } from 'lucide-react'
import { Link, useNavigate } from 'react-router-dom'
import { PageHeader } from '@/components/ui/PageHeader'
import { useAPInvoices } from '@/hooks/useAP'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { VendorInvoiceStatus } from '@/types/ap'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const STATUS_COLORS: Record<VendorInvoiceStatus, string> = {
  draft:            'bg-neutral-100 text-neutral-600',
  pending_approval: 'bg-neutral-100 text-neutral-700',
  approved:         'bg-neutral-100 text-neutral-700',
  partially_paid:   'bg-neutral-100 text-neutral-700',
  paid:             'bg-neutral-100 text-neutral-700',
  deleted:          'bg-neutral-100 text-neutral-500',
}

const STATUS_LABEL: Record<VendorInvoiceStatus, string> = {
  draft:            'Draft',
  pending_approval: 'Pending Approval',
  approved:         'Approved',
  partially_paid:   'Partially Paid',
  paid:             'Paid',
  deleted:          'Deleted',
}

const ALL_STATUSES: VendorInvoiceStatus[] = [
  'draft', 'pending_approval', 'approved', 'partially_paid', 'paid',
]

function StatusBadge({ status }: { status: VendorInvoiceStatus }) {
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium border border-neutral-200 ${STATUS_COLORS[status]}`}>
      {STATUS_LABEL[status]}
    </span>
  )
}

function formatCurrency(n: number) {
  return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function APInvoicesPage() {
  const navigate = useNavigate()
  const canCreate = useAuthStore(s => s.hasPermission('vendor_invoices.create'))
  const [activeStatus, setActiveStatus] = useState<VendorInvoiceStatus | null>(null)
  const [dueSoonOnly, setDueSoonOnly] = useState(false)

  const { data, isLoading, refetch } = useAPInvoices({
    status: activeStatus ?? undefined,
    due_soon: dueSoonOnly || undefined,
  })

  const invoices = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <PageHeader title="AP Invoices" />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">Accounts payable invoice lifecycle</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => refetch()} className="p-2 rounded border border-neutral-300 hover:bg-neutral-50">
            <RefreshCw className="w-4 h-4 text-neutral-500" />
          </button>
          {canCreate && (
            <button
              onClick={() => navigate('/accounting/ap/invoices/new')}
              className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
            >
              <Plus className="w-4 h-4" /> New Invoice
            </button>
          )}
        </div>
      </div>

      {/* Status Tabs */}
      <div className="flex items-center gap-2 flex-wrap">
        <button
          onClick={() => { setActiveStatus(null); setDueSoonOnly(false) }}
          className={`px-3 py-1.5 rounded text-xs font-medium transition-colors ${activeStatus === null && !dueSoonOnly ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'}`}
        >
          All
        </button>
        {ALL_STATUSES.map(s => (
          <button
            key={s}
            onClick={() => { setActiveStatus(s); setDueSoonOnly(false) }}
            className={`px-3 py-1.5 rounded text-xs font-medium transition-colors ${activeStatus === s ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'}`}
          >
            {STATUS_LABEL[s]}
          </button>
        ))}
        <button
          onClick={() => { setDueSoonOnly(true); setActiveStatus(null) }}
          className={`px-3 py-1.5 rounded text-xs font-medium transition-colors ${dueSoonOnly ? 'bg-neutral-700 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'}`}
        >
          Due Soon / Overdue
        </button>
      </div>

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : invoices.length === 0 ? (
        <div className="text-center py-16 text-neutral-400">No invoices found.</div>
      ) : (
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Vendor</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Invoice Date</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Due Date</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Net Payable</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Balance Due</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Status</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {invoices.map(inv => (
                <tr key={inv.id} className={`hover:bg-neutral-50 transition-colors ${inv.is_overdue ? 'bg-neutral-50' : ''}`}>
                  <td className="px-3 py-2 font-medium text-neutral-900">
                    {inv.vendor?.name ?? `Vendor #${inv.vendor_id}`}
                    {inv.description && (
                      <div className="text-xs text-neutral-400 font-normal truncate max-w-[180px]">{inv.description}</div>
                    )}
                  </td>
                  <td className="px-3 py-2 text-neutral-600">{inv.invoice_date}</td>
                  <td className={`px-3 py-2 ${inv.is_overdue ? 'text-neutral-800 font-medium' : 'text-neutral-600'}`}>
                    {inv.due_date}
                    {inv.is_overdue && <span className="ml-1 text-xs text-neutral-500">overdue</span>}
                  </td>
                  <td className="px-3 py-2 text-right font-mono text-neutral-800">{formatCurrency(inv.net_payable)}</td>
                  <td className="px-3 py-2 text-right font-mono text-neutral-600">{formatCurrency(inv.balance_due)}</td>
                  <td className="px-3 py-2"><StatusBadge status={inv.status} /></td>
                  <td className="px-3 py-2 text-right">
                    <Link
                      to={`/accounting/ap/invoices/${inv.ulid}`}
                      className="inline-flex items-center gap-1 text-xs text-neutral-700 hover:text-neutral-900 font-medium"
                    >
                      View <ChevronRight className="w-3 h-3" />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {meta && meta.last_page > 1 && (
            <div className="px-4 py-3 border-t border-neutral-200 text-xs text-neutral-500 flex items-center justify-between">
              <span>Page {meta.current_page} of {meta.last_page} — {meta.total} total</span>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
