import { useAPInvoicesDueSoon, useAPInvoices } from '@/hooks/useAP'
import { Link } from 'react-router-dom'
import { RefreshCw, AlertCircle, Clock, CheckCircle } from 'lucide-react'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import type { VendorInvoice } from '@/types/ap'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatCurrency(n: number) {
  return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function daysDiff(dateStr: string): number {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const due   = new Date(dateStr + 'T00:00:00')
  return Math.round((due.getTime() - today.getTime()) / 86400000)
}

// ---------------------------------------------------------------------------
// Invoice Card
// ---------------------------------------------------------------------------

function InvoiceCard({ invoice, variant }: { invoice: VendorInvoice; variant: 'overdue' | 'soon' | 'pending' }) {
  const diff = daysDiff(invoice.due_date)

  const borderColors = {
    overdue: 'border-l-4 border-l-neutral-400 bg-neutral-50',
    soon:    'border-l-4 border-l-neutral-400 bg-neutral-50',
    pending: 'border-l-4 border-l-neutral-400 bg-neutral-50',
  }

  return (
    <Link
      to={`/accounting/ap/invoices/${invoice.ulid}`}
      className={`block rounded p-4 hover:bg-neutral-50 transition-colors ${borderColors[variant]}`}
    >
      <div className="flex items-start justify-between">
        <div>
          <div className="font-medium text-neutral-900 text-sm">{invoice.vendor?.name ?? `Vendor #${invoice.vendor_id}`}</div>
          {invoice.description && (
            <div className="text-xs text-neutral-500 mt-0.5 truncate max-w-[200px]">{invoice.description}</div>
          )}
        </div>
        <div className="text-right">
          <div className="font-mono text-sm font-semibold text-neutral-800">{formatCurrency(invoice.balance_due)}</div>
          <div className={`text-xs mt-0.5 ${diff < 0 ? 'text-neutral-700' : 'text-neutral-600'}`}>
            {diff < 0 ? `${Math.abs(diff)}d overdue` : diff === 0 ? 'Due today' : `${diff}d left`}
          </div>
        </div>
      </div>
      <div className="mt-2 flex items-center gap-3 text-xs text-neutral-500">
        <span>Due: {invoice.due_date}</span>
        <span className={`px-1.5 py-0.5 rounded capitalize border ${
          invoice.status === 'approved' ? 'bg-neutral-100 text-neutral-700 border-neutral-200' : 'bg-neutral-100 text-neutral-700 border-neutral-200'
        }`}>{invoice.status?.replace('_', ' ') || 'Unknown'}</span>
      </div>
    </Link>
  )
}

// ---------------------------------------------------------------------------
// Column
// ---------------------------------------------------------------------------

function Column({
  title,
  icon,
  color: _color,
  invoices,
  isLoading,
  variant,
}: {
  title: string
  icon: React.ReactNode
  color: string
  invoices: VendorInvoice[]
  isLoading: boolean
  variant: 'overdue' | 'soon' | 'pending'
}) {
  const total = invoices.reduce((sum, inv) => sum + inv.balance_due, 0)

  return (
    <div className="flex flex-col bg-white rounded border border-neutral-200 overflow-hidden">
      <div className={`px-4 py-3 border-b border-neutral-200 flex items-center justify-between bg-neutral-50`}>
        <div className="flex items-center gap-2 font-semibold text-sm text-neutral-900">
          {icon}
          {title}
        </div>
        <div className="text-xs font-medium text-neutral-600">
          {invoices.length} inv. · {formatCurrency(total)}
        </div>
      </div>
      <div className="flex-1 overflow-y-auto p-3 space-y-2 max-h-[calc(100vh-280px)]">
        {isLoading ? (
          <SkeletonLoader rows={3} />
        ) : invoices.length === 0 ? (
          <p className="text-center text-neutral-400 text-sm py-8">No invoices</p>
        ) : (
          invoices.map(inv => (
            <InvoiceCard key={inv.id} invoice={inv} variant={variant} />
          ))
        )}
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function APDueDateMonitorPage() {
  // Overdue: approved/partially_paid AND due_date < today
  const { data: dueSoonData, isLoading: loadingDueSoon, _refetch } = useAPInvoicesDueSoon(7)

  // Overdue: load separately via status filter
  const { data: overdueData, isLoading: loadingOverdue } = useAPInvoices({
    // The API doesn't have an explicit ?overdue filter, so we fetch all
    // approved/partially_paid and filter client-side via is_overdue flag
    status: 'approved',
  })
  const { data: partiallyPaidData, isLoading: loadingPartial } = useAPInvoices({
    status: 'partially_paid',
  })
  const { data: pendingData, isLoading: loadingPending } = useAPInvoices({
    status: 'pending_approval',
  })

  const overdueInvoices = [
    ...(overdueData?.data ?? []),
    ...(partiallyPaidData?.data ?? []),
  ].filter(inv => inv.is_overdue)

  const dueSoonInvoices = (dueSoonData ?? []).filter(inv => !inv.is_overdue)
  const pendingInvoices = pendingData?.data ?? []

  return (
    <div className="space-y-6 h-full">
      <PageHeader title="AP Due Date Monitor" />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">Real-time payables status</p>
        </div>
        <button
          onClick={() => refetch()}
          className="flex items-center gap-2 px-3 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
        >
          <RefreshCw className="w-4 h-4" /> Refresh
        </button>
      </div>

      {/* Summary row */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-neutral-50 rounded p-4 border border-neutral-200">
          <div className="text-xs font-medium text-neutral-600">Overdue</div>
          <div className="text-2xl font-bold text-neutral-900 mt-1">{overdueInvoices.length}</div>
          <div className="text-xs text-neutral-500 mt-0.5">
            {formatCurrency(overdueInvoices.reduce((s, i) => s + i.balance_due, 0))} outstanding
          </div>
        </div>
        <div className="bg-neutral-50 rounded p-4 border border-neutral-200">
          <div className="text-xs font-medium text-neutral-600">Due ≤ 7 days</div>
          <div className="text-2xl font-bold text-neutral-900 mt-1">{dueSoonInvoices.length}</div>
          <div className="text-xs text-neutral-500 mt-0.5">
            {formatCurrency(dueSoonInvoices.reduce((s, i) => s + i.balance_due, 0))} outstanding
          </div>
        </div>
        <div className="bg-neutral-50 rounded p-4 border border-neutral-200">
          <div className="text-xs font-medium text-neutral-600">Pending Approval</div>
          <div className="text-2xl font-bold text-neutral-900 mt-1">{pendingInvoices.length}</div>
          <div className="text-xs text-neutral-500 mt-0.5">
            {formatCurrency(pendingInvoices.reduce((s, i) => s + i.net_payable, 0))} net payable
          </div>
        </div>
      </div>

      {/* Kanban columns */}
      <div className="grid grid-cols-3 gap-4">
        <Column
          title="Overdue"
          icon={<AlertCircle className="w-4 h-4 text-neutral-700" />}
          color="bg-neutral-50"
          invoices={overdueInvoices}
          isLoading={loadingOverdue || loadingPartial}
          variant="overdue"
        />
        <Column
          title="Due Soon (≤7 days)"
          icon={<Clock className="w-4 h-4 text-neutral-700" />}
          color="bg-neutral-50"
          invoices={dueSoonInvoices}
          isLoading={loadingDueSoon}
          variant="soon"
        />
        <Column
          title="Pending Approval"
          icon={<CheckCircle className="w-4 h-4 text-neutral-700" />}
          color="bg-neutral-50"
          invoices={pendingInvoices}
          isLoading={loadingPending}
          variant="pending"
        />
      </div>
    </div>
  )
}
