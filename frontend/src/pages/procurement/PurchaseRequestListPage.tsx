import { useState, useCallback, useEffect } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { AlertTriangle, Plus, Copy, X, CheckSquare, XSquare } from 'lucide-react'
import {
  usePurchaseRequests,
  useDuplicatePurchaseRequest,
  useBatchReviewPurchaseRequests,
  useBatchRejectPurchaseRequests,
} from '@/hooks/usePurchaseRequests'
import { useAuthStore } from '@/stores/authStore'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import ArchiveViewBanner from '@/components/ui/ArchiveViewBanner'
import ArchiveRowActions from '@/components/ui/ArchiveRowActions'
import api from '@/lib/api'
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
  const canReview = hasPermission('procurement.purchase-request.review')

  const [searchParams] = useSearchParams()
  const [filters, setFilters] = useState<PurchaseRequestFilters>({
    per_page: 25,
    status: (searchParams.get('status') as PurchaseRequestFilters['status']) ?? undefined,
  })
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [prToConfirm, setPrToConfirm] = useState<PurchaseRequest | null>(null)

  // Batch selection state
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [batchRejectOpen, setBatchRejectOpen] = useState(false)
  const [batchRejectReason, setBatchRejectReason] = useState('')
  const [batchReviewComments, setBatchReviewComments] = useState('')
  const [batchReviewOpen, setBatchReviewOpen] = useState(false)

  const { data, isLoading, isError } = usePurchaseRequests({ ...filters, with_archived: undefined })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['purchase-requests', 'archived'],
    queryFn: () => api.get('/procurement/purchase-requests-archived', { params: { per_page: 20 } }),
    enabled: isArchiveView,
  })
  const duplicateMutation = useDuplicatePurchaseRequest()
  const batchReview = useBatchReviewPurchaseRequests()
  const batchReject = useBatchRejectPurchaseRequests()

  // Clear selection on filter change
  useEffect(() => {
    setSelectedIds(new Set())
  }, [filters, isArchiveView])

  const rows = data?.data ?? []
  const pendingRows = rows.filter((r) => r.status === 'pending_review')
  const allPendingSelected = pendingRows.length > 0 && pendingRows.every((r) => selectedIds.has(r.id))

  const toggleSelect = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id); else next.add(id)
      return next
    })
  }, [])

  const toggleSelectAll = useCallback(() => {
    if (allPendingSelected) setSelectedIds(new Set())
    else setSelectedIds(new Set(pendingRows.map((r) => r.id)))
  }, [allPendingSelected, pendingRows])

  const handleBatchReview = useCallback(() => {
    if (selectedIds.size === 0) return
    batchReview.mutate(
      { ids: Array.from(selectedIds), comments: batchReviewComments || undefined },
      {
        onSuccess: (result) => {
          setSelectedIds(new Set())
          setBatchReviewOpen(false)
          setBatchReviewComments('')
          const okCount = result.results.reviewed?.length ?? 0
          const failCount = result.results.failed.length
          if (failCount > 0) {
            toast.warning(`${okCount} reviewed, ${failCount} failed.`, {
              description: result.results.failed.map((f) => `#${f.id}: ${f.reason}`).join('; '),
            })
          } else {
            toast.success(`${okCount} purchase request${okCount !== 1 ? 's' : ''} reviewed.`)
          }
        },
      },
    )
  }, [selectedIds, batchReviewComments, batchReview])

  const handleBatchReject = useCallback(() => {
    if (selectedIds.size === 0 || !batchRejectReason.trim()) return
    batchReject.mutate(
      { ids: Array.from(selectedIds), reason: batchRejectReason.trim(), stage: 'review' },
      {
        onSuccess: (result) => {
          setSelectedIds(new Set())
          setBatchRejectOpen(false)
          setBatchRejectReason('')
          const okCount = result.results.rejected?.length ?? 0
          const failCount = result.results.failed.length
          if (failCount > 0) {
            toast.warning(`${okCount} rejected, ${failCount} failed.`, {
              description: result.results.failed.map((f) => `#${f.id}: ${f.reason}`).join('; '),
            })
          } else {
            toast.success(`${okCount} purchase request${okCount !== 1 ? 's' : ''} rejected.`)
          }
        },
      },
    )
  }, [selectedIds, batchRejectReason, batchReject])

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

        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {/* Batch Actions Bar */}
      {canReview && selectedIds.size > 0 && (
        <div className="mb-4 bg-accent-soft border border-accent/20 rounded-lg p-3 flex items-center gap-3 flex-wrap">
          <span className="text-sm font-medium text-neutral-800">
            {selectedIds.size} request{selectedIds.size !== 1 ? 's' : ''} selected
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setBatchReviewOpen(true)}
              disabled={batchReview.isPending}
              className="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium px-3 py-1.5 rounded transition-colors"
            >
              <CheckSquare className="h-4 w-4" />
              {batchReview.isPending ? 'Reviewing...' : 'Review All'}
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

      {/* Batch Review Comments Modal */}
      {batchReviewOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div
            className="bg-white rounded-lg shadow-floating p-6 w-full max-w-md"
            role="dialog"
            aria-label="Batch review comments"
            onKeyDown={(e) => {
              if (e.key === 'Escape') {
                setBatchReviewOpen(false)
                setBatchReviewComments('')
              }
            }}
          >
            <h3 className="text-lg font-semibold text-neutral-900 mb-2">
              Review {selectedIds.size} Purchase Request{selectedIds.size !== 1 ? 's' : ''}
            </h3>
            <p className="text-sm text-neutral-500 mb-4">
              All selected requests will be marked as reviewed. You may add optional comments.
            </p>
            <textarea
              autoFocus
              value={batchReviewComments}
              onChange={(e) => setBatchReviewComments(e.target.value)}
              placeholder="Optional review comments..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none resize-none mb-4"
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setBatchReviewOpen(false)
                  setBatchReviewComments('')
                }}
                className="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800 border border-neutral-300 rounded transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleBatchReview}
                disabled={batchReview.isPending}
                className="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded font-medium transition-colors"
              >
                {batchReview.isPending ? 'Reviewing...' : 'Review All'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Batch Reject Reason Modal */}
      {batchRejectOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div
            className="bg-white rounded-lg shadow-floating p-6 w-full max-w-md"
            role="dialog"
            aria-label="Batch reject reason"
            onKeyDown={(e) => {
              if (e.key === 'Escape') {
                setBatchRejectOpen(false)
                setBatchRejectReason('')
              }
            }}
          >
            <h3 className="text-lg font-semibold text-neutral-900 mb-2">
              Reject {selectedIds.size} Purchase Request{selectedIds.size !== 1 ? 's' : ''}
            </h3>
            <p className="text-sm text-neutral-500 mb-4">
              Provide a reason for rejecting these requests. This will be visible to the requestors.
            </p>
            <textarea
              autoFocus
              value={batchRejectReason}
              onChange={(e) => setBatchRejectReason(e.target.value)}
              placeholder="Reason for rejection..."
              rows={3}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none resize-none mb-4"
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setBatchRejectOpen(false)
                  setBatchRejectReason('')
                }}
                className="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800 border border-neutral-300 rounded transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleBatchReject}
                disabled={!batchRejectReason.trim() || batchReject.isPending}
                className="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded font-medium transition-colors"
              >
                {batchReject.isPending ? 'Rejecting...' : 'Reject'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Table */}
      <Card>
        <CardHeader>Purchase Requests</CardHeader>
        <CardBody className="p-0">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {canReview && (
                  <th className="px-3 py-3 text-left w-10">
                    <input
                      type="checkbox"
                      checked={allPendingSelected}
                      onChange={toggleSelectAll}
                      disabled={pendingRows.length === 0}
                      className="rounded border-neutral-300 text-accent focus:ring-accent/50"
                      title={pendingRows.length === 0 ? 'No pending_review requests to select' : 'Select all pending review'}
                    />
                  </th>
                )}
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
              {rows.length === 0 && (
                <tr>
                  <td colSpan={canReview ? 8 : 7} className="px-4 py-8 text-center text-neutral-400 text-sm">
                    No purchase requests found.
                  </td>
                </tr>
              )}
              {rows.map((pr) => (
                <tr
                  key={pr.id}
                  onClick={() => navigate(`/procurement/purchase-requests/${pr.ulid}`)}
                  className={`hover:bg-neutral-50/50 transition-colors cursor-pointer ${
                    selectedIds.has(pr.id) ? 'bg-accent-soft/50' : ''
                  }`}
                >
                  {canReview && (
                    <td className="px-3 py-3" onClick={(e) => e.stopPropagation()}>
                      {pr.status === 'pending_review' ? (
                        <input
                          type="checkbox"
                          checked={selectedIds.has(pr.id)}
                          onChange={() => toggleSelect(pr.id)}
                          className="rounded border-neutral-300 text-accent focus:ring-accent/50"
                        />
                      ) : (
                        <span className="block w-4" />
                      )}
                    </td>
                  )}
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
