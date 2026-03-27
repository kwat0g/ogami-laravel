import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { AlertTriangle, Plus, Copy, X } from 'lucide-react'
import { usePurchaseRequests, useDuplicatePurchaseRequest } from '@/hooks/usePurchaseRequests'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import { ExportButton } from '@/components/ui/ExportButton'
import type {
  PurchaseRequest,
  PurchaseRequestFilters,
  PurchaseRequestStatus,
  PurchaseRequestUrgency,
} from '@/types/procurement'

const STATUSES: PurchaseRequestStatus[] = [
  'draft', 'pending_review', 'reviewed', 'budget_verified',
  'approved', 'returned', 'rejected', 'cancelled', 'converted_to_po',
]

const URGENCIES: PurchaseRequestUrgency[] = ['normal', 'urgent', 'critical']

const statusBadgeClass: Record<PurchaseRequestStatus, string> = {
  draft:            'bg-neutral-100 text-neutral-600',
  pending_review:   'bg-neutral-100 text-neutral-700',
  reviewed:         'bg-neutral-100 text-neutral-700',
  budget_verified:  'bg-neutral-200 text-neutral-800',
  approved:         'bg-neutral-200 text-neutral-800',
  returned:         'bg-neutral-100 text-neutral-500',
  rejected:         'bg-neutral-100 text-neutral-400',
  cancelled:        'bg-neutral-100 text-neutral-400',
  converted_to_po:  'bg-neutral-200 text-neutral-800',
}

const urgencyBadgeClass: Record<PurchaseRequestUrgency, string> = {
  normal:   'bg-neutral-100 text-neutral-600',
  urgent:   'bg-neutral-200 text-neutral-700',
  critical: 'bg-neutral-800 text-white',
}

// ── Duplicate Confirmation Modal ─────────────────────────────────────────────

function DuplicateConfirmModal({
  pr,
  onConfirm,
  onCancel,
  isLoading,
}: {
  pr: PurchaseRequest
  onConfirm: () => void
  onCancel: () => void
  isLoading: boolean
}): React.ReactElement {
  const totalItems = pr.items?.length ?? 0
  const totalCost = Number(pr.total_estimated_cost)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="bg-white rounded-lg w-full max-w-lg shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-neutral-200">
          <div>
            <h2 className="text-base font-semibold text-neutral-900">Duplicate Purchase Request</h2>
            <p className="text-xs text-neutral-500 mt-0.5">A new draft PR will be created with the details below.</p>
          </div>
          <button
            onClick={onCancel}
            disabled={isLoading}
            className="p-1.5 rounded hover:bg-neutral-100 text-neutral-400 hover:text-neutral-600 transition-colors disabled:opacity-50"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Content */}
        <div className="px-5 py-4 space-y-3">
          {/* Reference + Department */}
          <div className="grid grid-cols-2 gap-3">
            <div className="bg-neutral-50 rounded-md p-3">
              <p className="text-xs text-neutral-400 mb-1">Source PR</p>
              <p className="text-sm font-mono font-semibold text-neutral-900">{pr.pr_reference}</p>
            </div>
            <div className="bg-neutral-50 rounded-md p-3">
              <p className="text-xs text-neutral-400 mb-1">Department</p>
              <p className="text-sm font-medium text-neutral-800">
                {pr.department?.name ?? `Dept #${pr.department_id}`}
              </p>
            </div>
          </div>

          {/* Urgency + Item Count + Total Cost */}
          <div className="grid grid-cols-3 gap-3">
            <div className="bg-neutral-50 rounded-md p-3">
              <p className="text-xs text-neutral-400 mb-1">Urgency</p>
              <p className="text-sm font-medium text-neutral-800 capitalize">{pr.urgency}</p>
            </div>
            <div className="bg-neutral-50 rounded-md p-3">
              <p className="text-xs text-neutral-400 mb-1">Line Items</p>
              <p className="text-sm font-medium text-neutral-800">{totalItems} item{totalItems !== 1 ? 's' : ''}</p>
            </div>
            <div className="bg-neutral-50 rounded-md p-3">
              <p className="text-xs text-neutral-400 mb-1">Total Est. Cost</p>
              <p className="text-sm font-semibold text-neutral-900">
                ₱{totalCost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
              </p>
            </div>
          </div>

          {/* Items preview */}
          {(pr.items?.length ?? 0) > 0 && (
            <div>
              <p className="text-xs text-neutral-500 font-medium mb-1.5">Items to be duplicated:</p>
              <div className="border border-neutral-200 rounded-md divide-y divide-neutral-100 max-h-40 overflow-y-auto">
                {pr.items.map((item, idx) => (
                  <div key={idx} className="flex items-center justify-between px-3 py-2">
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-medium text-neutral-800 truncate">{item.item_description}</p>
                      <p className="text-xs text-neutral-400">{item.quantity} {item.unit_of_measure}</p>
                    </div>
                    <p className="text-xs font-medium text-neutral-700 ml-3 tabular-nums">
                      ₱{Number(item.estimated_unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Justification */}
          {pr.justification && (
            <div>
              <p className="text-xs text-neutral-500 font-medium mb-1">Justification:</p>
              <p className="text-xs text-neutral-600 bg-neutral-50 rounded p-2.5 line-clamp-3">
                {pr.justification}
              </p>
            </div>
          )}

          {/* Notice */}
          <div className="bg-neutral-50 border border-neutral-200 rounded-md px-3 py-2.5">
            <p className="text-xs text-neutral-600">
              The duplicated PR will be created as a <strong>draft</strong>. You will be redirected to edit it before submitting.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 px-5 py-4 border-t border-neutral-200">
          <button
            onClick={onCancel}
            disabled={isLoading}
            className="px-4 py-2 text-sm font-medium text-neutral-700 border border-neutral-300 rounded-md hover:bg-neutral-50 transition-colors disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={isLoading}
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-neutral-900 rounded-md hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <Copy className="w-4 h-4" />
            {isLoading ? 'Duplicating…' : 'Confirm Duplicate'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Main Page ────────────────────────────────────────────────────────────────

export default function PurchaseRequestListPage(): React.ReactElement {
  const navigate = useNavigate()
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('procurement.purchase-request.create') || hasPermission('procurement.purchase-request.create-dept')

  const [searchParams] = useSearchParams()
  const [filters, setFilters] = useState<PurchaseRequestFilters>({
    per_page: 25,
    status: (searchParams.get('status') as PurchaseRequestFilters['status']) ?? undefined,
  })
  const [withArchived, setWithArchived] = useState(false)
  const [prToConfirm, setPrToConfirm] = useState<PurchaseRequest | null>(null)

  const { data, isLoading, isError } = usePurchaseRequests({ ...filters, with_archived: withArchived || undefined })
  const duplicateMutation = useDuplicatePurchaseRequest()

  const handleDuplicateConfirmed = async (): Promise<void> => {
    if (!prToConfirm) return
    try {
      const duplicatedPr = await duplicateMutation.mutateAsync(prToConfirm.ulid)
      toast.success(`Purchase Request ${duplicatedPr.pr_reference} created successfully.`)
      setPrToConfirm(null)
      navigate(`/procurement/purchase-requests/${duplicatedPr.ulid}/edit`)
    } catch {
      toast.error('Failed to duplicate purchase request. Please try again.')
    }
  }

  const canDuplicate = (status: PurchaseRequestStatus): boolean =>
    status === 'converted_to_po'

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="flex items-center gap-2 text-red-600 text-sm mt-4">
        <AlertTriangle className="w-4 h-4" />
        Failed to load purchase requests. Please try again.
      </div>
    )
  }

  return (
    <div>
      <PageHeader
        title="Purchase Requests"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'pr_number', label: 'PR Number' },
                { key: 'department.name', label: 'Department' },
                { key: 'status', label: 'Status' },
                { key: 'urgency', label: 'Urgency' },
                { key: 'total_amount', label: 'Total Amount' },
                { key: 'requested_by_name', label: 'Requested By' },
                { key: 'created_at', label: 'Created' },
              ]}
              filename="purchase-requests"
            />
            {canCreate && (
              <Link
                to="/procurement/purchase-requests/new"
                className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="w-4 h-4" />
                New Request
              </Link>
            )}
          </div>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-5">
        <select
          className="text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
          value={filters.status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              status: (e.target.value as PurchaseRequestStatus) || undefined,
              page: 1,
            }))
          }
        >
          <option value="">All Statuses</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replace(/_/g, ' ')}
            </option>
          ))}
        </select>

        <select
          className="text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
          value={filters.urgency ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              urgency: (e.target.value as PurchaseRequestUrgency) || undefined,
              page: 1,
            }))
          }
        >
          <option value="">All Urgencies</option>
          {URGENCIES.map((u) => (
            <option key={u} value={u}>
              {u.charAt(0).toUpperCase() + u.slice(1)}
            </option>
          ))}
        </select>

        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer select-none">
          <input
            type="checkbox"
            checked={withArchived}
            onChange={(e) => setWithArchived(e.target.checked)}
            className="rounded border-neutral-300"
          />
          <span>Show Archived</span>
        </label>
      </div>

      {/* Table */}
      <Card>
        <CardHeader>Purchase Requests</CardHeader>
        <CardBody className="p-0">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['PR Reference', 'Department', 'Urgency', 'Total Est. Cost', 'Status', 'Submitted By', 'Date'].map(
                  (h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">
                      {h}
                    </th>
                  ),
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {data?.data?.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">
                    No purchase requests found.
                  </td>
                </tr>
              )}
              {data?.data?.map((pr) => (
                <tr
                  key={pr.id}
                  onClick={() => navigate(`/procurement/purchase-requests/${pr.ulid}`)}
                  className="hover:bg-neutral-50/50 transition-colors cursor-pointer"
                >
                  <td className="px-4 py-3 font-mono text-neutral-900 font-medium">
                    {pr.pr_reference}
                  </td>
                  <td className="px-4 py-3 text-neutral-700">
                    {pr.department?.name ?? `Dept #${pr.department_id}`}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge className={urgencyBadgeClass[pr.urgency]}>
                      {pr.urgency}
                    </StatusBadge>
                  </td>
                  <td className="px-4 py-3 text-neutral-700 font-medium tabular-nums">
                    ₱{Number(pr.total_estimated_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-4 py-3">
                    {pr.deleted_at && (
                      <StatusBadge className="bg-neutral-100 text-neutral-500 mr-1">Archived</StatusBadge>
                    )}
                    <StatusBadge className={statusBadgeClass[pr.status]}>
                      {pr.status?.replace(/_/g, ' ') || 'Unknown'}
                    </StatusBadge>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {pr.requested_by?.name ?? '—'}
                  </td>
                  <td className="px-4 py-3 text-neutral-500">
                    {new Date(pr.created_at).toLocaleDateString('en-PH')}
                  </td>
                  {canCreate && canDuplicate(pr.status) && (
                    <td className="px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                      <button
                        onClick={() => setPrToConfirm(pr)}
                        className="inline-flex items-center gap-1 px-2 py-1 text-xs border border-neutral-300 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-400 hover:text-neutral-900 font-medium"
                        title="Duplicate this purchase request"
                      >
                        <Copy className="w-3 h-3" />
                        Duplicate
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>

          {/* Pagination */}
          {data?.meta && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t border-neutral-200">
              <span className="text-sm text-neutral-600">
                Page {data.meta.current_page} of {data.meta.last_page}
              </span>
              <div className="flex gap-2">
                <button
                  disabled={data.meta.current_page <= 1}
                  onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                  className="text-sm px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
                >
                  Previous
                </button>
                <button
                  disabled={data.meta.current_page >= data.meta.last_page}
                  onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                  className="text-sm px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Duplicate Confirmation Modal */}
      {prToConfirm && (
        <DuplicateConfirmModal
          pr={prToConfirm}
          onConfirm={handleDuplicateConfirmed}
          onCancel={() => setPrToConfirm(null)}
          isLoading={duplicateMutation.isPending}
        />
      )}
    </div>
  )
}
