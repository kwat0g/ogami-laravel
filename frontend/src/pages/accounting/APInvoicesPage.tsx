import { useState, useCallback, useEffect } from 'react'
import { Plus, RefreshCw, FilePlus, X, CheckSquare, XSquare } from 'lucide-react'
import { Link, useNavigate } from 'react-router-dom'
import { PageHeader } from '@/components/ui/PageHeader'
import { useAPInvoices, useCreateInvoiceFromPO, useBatchApproveAPInvoices, useBatchRejectAPInvoices } from '@/hooks/useAP'
import { usePurchaseOrders } from '@/hooks/usePurchaseOrders'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { toast } from 'sonner'
import type { VendorInvoiceStatus } from '@/types/ap'
import { ExportButton } from '@/components/ui/ExportButton'
import type { PurchaseOrder } from '@/types/procurement'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const STATUS_COLORS: Record<VendorInvoiceStatus, string> = {
  draft:            'bg-neutral-100 text-neutral-600',
  pending_approval: 'bg-amber-50 text-amber-700',
  head_noted:       'bg-blue-50 text-blue-700',
  manager_checked:  'bg-blue-50 text-blue-700',
  officer_reviewed: 'bg-indigo-50 text-indigo-700',
  approved:         'bg-green-50 text-green-700',
  rejected:         'bg-red-50 text-red-700',
  partially_paid:   'bg-amber-50 text-amber-700',
  paid:             'bg-green-100 text-green-800',
  deleted:          'bg-neutral-100 text-neutral-500',
}

const STATUS_LABEL: Record<VendorInvoiceStatus, string> = {
  draft:            'Draft',
  pending_approval: 'Pending Approval',
  head_noted:       'Head Noted',
  manager_checked:  'Manager Checked',
  officer_reviewed: 'Officer Reviewed',
  approved:         'Approved',
  rejected:         'Rejected',
  partially_paid:   'Partially Paid',
  paid:             'Paid',
  deleted:          'Deleted',
}

const ALL_STATUSES: VendorInvoiceStatus[] = [
  'draft', 'pending_approval', 'head_noted', 'manager_checked', 'officer_reviewed', 'approved', 'rejected', 'partially_paid', 'paid',
]

// Statuses that are eligible for batch approve/reject
const BATCH_ELIGIBLE_STATUSES: VendorInvoiceStatus[] = ['pending_approval']

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
// Create From PO Modal
// ---------------------------------------------------------------------------

interface CreateFromPOModalProps {
  isOpen: boolean
  onClose: () => void
  onSelect: (poId: number) => void
  isLoading: boolean
}

function CreateFromPOModal({ isOpen, onClose, onSelect, isLoading }: CreateFromPOModalProps) {
  const [selectedPoId, setSelectedPoId] = useState<number | null>(null)
  
  // Fetch eligible POs only when modal is open (avoids 403 for non-procurement roles)
  const { data: poData, isLoading: isLoadingPOs } = usePurchaseOrders(
    isOpen ? { per_page: 100 } : undefined,
  )
  
  const purchaseOrders = poData?.data ?? []
  // Filter to only show POs that are sent or partially_received
  const eligiblePOs = purchaseOrders.filter(
    (po: PurchaseOrder) => po.status === 'sent' || po.status === 'partially_received'
  )

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[80vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-neutral-200">
          <h2 className="text-lg font-semibold text-neutral-900">Create Invoice from PO</h2>
          <button
            onClick={onClose}
            className="p-1 rounded hover:bg-neutral-100 text-neutral-500"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-auto p-4">
          <p className="text-sm text-neutral-600 mb-4">
            Select a purchase order to create a new AP invoice. The invoice will be pre-populated with PO data.
          </p>
          
          {isLoadingPOs ? (
            <div className="text-center py-8 text-neutral-500">Loading purchase orders...</div>
          ) : eligiblePOs.length === 0 ? (
            <div className="text-center py-8 text-neutral-500">
              No eligible purchase orders found.
              <p className="text-xs mt-2 text-neutral-400">
                POs must have status sent or partially_received.
              </p>
            </div>
          ) : (
            <div className="space-y-2">
              {eligiblePOs.map((po: PurchaseOrder) => (
                <button
                  key={po.id}
                  onClick={() => setSelectedPoId(po.id)}
                  className={`w-full text-left p-3 rounded border transition-colors ${
                    selectedPoId === po.id
                      ? 'border-neutral-900 bg-neutral-50'
                      : 'border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="font-medium text-neutral-900">{po.po_reference}</div>
                      <div className="text-sm text-neutral-500">
                        {po.vendor?.name ?? `Vendor #${po.vendor_id}`}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-sm font-mono text-neutral-700">
                        {formatCurrency(po.total_po_amount)}
                      </div>
                      <div className="text-xs text-neutral-400 capitalize">
                        {po.status.replace('_', ' ')}
                      </div>
                    </div>
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 p-4 border-t border-neutral-200">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            onClick={() => selectedPoId && onSelect(selectedPoId)}
            disabled={!selectedPoId || isLoading}
            className="px-4 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? 'Creating...' : 'Create Invoice'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function APInvoicesPage() {
  const navigate = useNavigate()
  const canCreate = useAuthStore(s => s.hasPermission('vendor_invoices.create'))
  const canApprove = useAuthStore(s => s.hasPermission('vendor_invoices.approve'))
  const [activeStatus, setActiveStatus] = useState<VendorInvoiceStatus | null>(null)
  const [dueSoonOnly, setDueSoonOnly] = useState(false)
  const [isModalOpen, setIsModalOpen] = useState(false)

  // Batch selection state
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [batchRejectOpen, setBatchRejectOpen] = useState(false)
  const [batchRejectNote, setBatchRejectNote] = useState('')

  const { data, isLoading, refetch } = useAPInvoices({
    status: activeStatus ?? undefined,
    due_soon: dueSoonOnly || undefined,
  })

  const createFromPOMutation = useCreateInvoiceFromPO()
  const batchApprove = useBatchApproveAPInvoices()
  const batchReject = useBatchRejectAPInvoices()

  const invoices = data?.data ?? []
  const meta = data?.meta

  // Only pending_approval invoices are eligible for batch operations
  const pendingRows = invoices.filter((inv) => BATCH_ELIGIBLE_STATUSES.includes(inv.status))
  const allPendingSelected = pendingRows.length > 0 && pendingRows.every((inv) => selectedIds.has(inv.id))

  // Clear selection on filter change
  useEffect(() => {
    setSelectedIds(new Set())
  }, [activeStatus, dueSoonOnly])

  const toggleSelect = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id); else next.add(id)
      return next
    })
  }, [])

  const toggleSelectAll = useCallback(() => {
    if (allPendingSelected) setSelectedIds(new Set())
    else setSelectedIds(new Set(pendingRows.map((inv) => inv.id)))
  }, [allPendingSelected, pendingRows])

  const handleBatchApprove = useCallback(() => {
    if (selectedIds.size === 0) return
    batchApprove.mutate(
      { ids: Array.from(selectedIds) },
      {
        onSuccess: (result) => {
          setSelectedIds(new Set())
          const okCount = result.results.approved?.length ?? 0
          const failCount = result.results.failed.length
          if (failCount > 0) {
            toast.warning(`${okCount} approved, ${failCount} failed.`, {
              description: result.results.failed.map((f) => `#${f.id}: ${f.reason}`).join('; '),
            })
          } else {
            toast.success(`${okCount} invoice${okCount !== 1 ? 's' : ''} approved.`)
          }
        },
      },
    )
  }, [selectedIds, batchApprove])

  const handleBatchReject = useCallback(() => {
    if (selectedIds.size === 0 || !batchRejectNote.trim()) return
    batchReject.mutate(
      { ids: Array.from(selectedIds), rejection_note: batchRejectNote.trim() },
      {
        onSuccess: (result) => {
          setSelectedIds(new Set())
          setBatchRejectOpen(false)
          setBatchRejectNote('')
          const okCount = result.results.rejected?.length ?? 0
          const failCount = result.results.failed.length
          if (failCount > 0) {
            toast.warning(`${okCount} rejected, ${failCount} failed.`, {
              description: result.results.failed.map((f) => `#${f.id}: ${f.reason}`).join('; '),
            })
          } else {
            toast.success(`${okCount} invoice${okCount !== 1 ? 's' : ''} rejected.`)
          }
        },
      },
    )
  }, [selectedIds, batchRejectNote, batchReject])

  const handleCreateFromPO = async (poId: number) => {
    try {
      const result = await createFromPOMutation.mutateAsync(poId)
      toast.success(`Invoice created from PO ${result.po_reference}`)
      setIsModalOpen(false)
      // Navigate to the invoice detail page where user can edit
      navigate(`/accounting/ap/invoices/${result.invoice.ulid}`)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } }
      toast.error(error.response?.data?.message ?? 'Failed to create invoice from PO')
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="AP Invoices"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'invoice_number', label: 'Invoice #' },
                { key: 'vendor.name', label: 'Vendor' },
                { key: 'status', label: 'Status' },
                { key: 'net_amount_centavos', label: 'Net Amount', format: (v: unknown) => `${((v as number) / 100).toFixed(2)}` },
                { key: 'invoice_date', label: 'Invoice Date' },
                { key: 'due_date', label: 'Due Date' },
              ]}
              filename="ap-invoices"
            />
            <Link to="/accounting/ap/monitor" className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-3 py-2 rounded transition-colors">
              Due Date Monitor
            </Link>
          </div>
        }
      />

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
            <>
              <button
                onClick={() => setIsModalOpen(true)}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-100 text-neutral-800 text-sm rounded hover:bg-neutral-200 border border-neutral-300"
              >
                <FilePlus className="w-4 h-4" /> From PO
              </button>
              <button
                onClick={() => navigate('/accounting/ap/invoices/new')}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800"
              >
                <Plus className="w-4 h-4" /> New Invoice
              </button>
            </>
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

      {/* Batch Actions Bar */}
      {canApprove && selectedIds.size > 0 && (
        <div className="bg-accent-soft border border-accent/20 rounded-lg p-3 flex items-center gap-3 flex-wrap">
          <span className="text-sm font-medium text-neutral-800">
            {selectedIds.size} invoice{selectedIds.size !== 1 ? 's' : ''} selected
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={handleBatchApprove}
              disabled={batchApprove.isPending}
              className="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium px-3 py-1.5 rounded transition-colors"
            >
              <CheckSquare className="h-4 w-4" />
              {batchApprove.isPending ? 'Approving...' : 'Approve All'}
            </button>
            <button
              onClick={() => setBatchRejectOpen(true)}
              disabled={batchReject.isPending}
              className="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium px-3 py-1.5 rounded transition-colors"
            >
              <XSquare className="h-4 w-4" />
              Reject All
            </button>
            <button
              onClick={() => setSelectedIds(new Set())}
              className="text-sm text-neutral-500 hover:text-neutral-700 underline ml-2"
            >
              Clear selection
            </button>
          </div>
        </div>
      )}

      {/* Batch Reject Modal */}
      {batchRejectOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div
            className="bg-white rounded-lg shadow-floating p-6 w-full max-w-md"
            role="dialog"
            aria-label="Batch reject invoices"
            onKeyDown={(e) => {
              if (e.key === 'Escape') {
                setBatchRejectOpen(false)
                setBatchRejectNote('')
              }
            }}
          >
            <h3 className="text-lg font-semibold text-neutral-900 mb-2">
              Reject {selectedIds.size} Invoice{selectedIds.size !== 1 ? 's' : ''}
            </h3>
            <p className="text-sm text-neutral-500 mb-4">
              Provide a rejection note for these invoices. This will be recorded in the invoice history.
            </p>
            <textarea
              autoFocus
              value={batchRejectNote}
              onChange={(e) => setBatchRejectNote(e.target.value)}
              placeholder="Rejection note..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none resize-none mb-4"
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setBatchRejectOpen(false)
                  setBatchRejectNote('')
                }}
                className="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800 border border-neutral-300 rounded transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleBatchReject}
                disabled={!batchRejectNote.trim() || batchReject.isPending}
                className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded font-medium transition-colors"
              >
                {batchReject.isPending ? 'Rejecting...' : 'Reject'}
              </button>
            </div>
          </div>
        </div>
      )}

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
                {canApprove && (
                  <th className="px-3 py-2.5 text-left w-10">
                    <input
                      type="checkbox"
                      checked={allPendingSelected}
                      onChange={toggleSelectAll}
                      disabled={pendingRows.length === 0}
                      className="rounded border-neutral-300 text-accent focus:ring-accent/50"
                      title={pendingRows.length === 0 ? 'No pending invoices to select' : 'Select all pending approval'}
                    />
                  </th>
                )}
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Vendor</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Invoice Date</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Due Date</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Net Payable</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Balance Due</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {invoices.map(inv => (
                <tr
                  key={inv.id}
                  onClick={() => navigate(`/accounting/ap/invoices/${inv.ulid}`)}
                  className={`hover:bg-neutral-50 transition-colors cursor-pointer ${
                    selectedIds.has(inv.id) ? 'bg-accent-soft/50' : inv.is_overdue ? 'bg-neutral-50' : ''
                  }`}
                >
                  {canApprove && (
                    <td className="px-3 py-2" onClick={(e) => e.stopPropagation()}>
                      {BATCH_ELIGIBLE_STATUSES.includes(inv.status) ? (
                        <input
                          type="checkbox"
                          checked={selectedIds.has(inv.id)}
                          onChange={() => toggleSelect(inv.id)}
                          className="rounded border-neutral-300 text-accent focus:ring-accent/50"
                        />
                      ) : (
                        <span className="block w-4" />
                      )}
                    </td>
                  )}
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

      {/* Create From PO Modal */}
      <CreateFromPOModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSelect={handleCreateFromPO}
        isLoading={createFromPOMutation.isPending}
      />
    </div>
  )
}
