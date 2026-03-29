import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Plus, AlertTriangle } from 'lucide-react'
import { useMaterialRequisitions } from '@/hooks/useInventory'
import { useAuthStore } from '@/stores/authStore'
import { useQuery } from '@tanstack/react-query'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import api from '@/lib/api'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import { DepartmentGuard } from '@/components/ui/guards'
import { ExportButton } from '@/components/ui/ExportButton'
import type { MaterialRequisitionStatus } from '@/types/inventory'

const statusBadge: Record<MaterialRequisitionStatus, string> = {
  draft:      'bg-neutral-100 text-neutral-600',
  submitted:  'bg-neutral-100 text-neutral-700',
  noted:      'bg-neutral-100 text-neutral-700',
  checked:    'bg-neutral-100 text-neutral-700',
  reviewed:   'bg-neutral-100 text-neutral-700',
  approved:   'bg-neutral-200 text-neutral-800',
  rejected:   'bg-neutral-100 text-neutral-400',
  cancelled:  'bg-neutral-100 text-neutral-400',
  fulfilled:  'bg-neutral-200 text-neutral-800',
}

const ALL_STATUSES: MaterialRequisitionStatus[] = [
  'draft', 'submitted', 'noted', 'checked', 'reviewed', 'approved', 'rejected', 'cancelled', 'fulfilled',
]

export default function MaterialRequisitionListPage(): React.ReactElement {
  const navigate = useNavigate()
  const [status, setStatus] = useState<MaterialRequisitionStatus | ''>('')
  const [page, setPage]     = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('inventory.mrq.create')

  const { data, isLoading, isError } = useMaterialRequisitions({
    status: status || undefined,
    page,
    per_page: 20,
    with_archived: undefined,
  })

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['mrqs', 'archived'],
    queryFn: () => api.get('/inventory/mrqs-archived', { params: { per_page: 20 } }),
    enabled: isArchiveView,
  })

  return (
    <div>
      <PageHeader
        title="Material Requisitions"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'mr_reference', label: 'MR Reference' },
                { key: 'department.name', label: 'Department' },
                { key: 'status', label: 'Status' },
                { key: 'purpose', label: 'Purpose' },
                { key: 'created_at', label: 'Created' },
              ]}
              filename="material-requisitions"
            />
            <DepartmentGuard module="requisitions">
              {canCreate && (
                <Link
                  to="/inventory/requisitions/new"
                  className="flex items-center gap-2 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded"
                >
                  <Plus className="w-4 h-4" /> New Requisition
                </Link>
              )}
            </DepartmentGuard>
          </div>
        }
      />

      {/* Filter */}
      <div className="mb-5 flex items-center gap-3">
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value as MaterialRequisitionStatus | ''); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Statuses</option>
          {ALL_STATUSES.map((s) => (
            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
          ))}
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load requisitions.
        </div>
      )}

      {!isLoading && !isError && (
        <>
          <Card>
            <CardHeader>Material Requisitions</CardHeader>
            <CardBody className="p-0">
              <table className="min-w-full text-sm">
                <thead className="bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    {['MR Reference', 'Type', 'Department', 'Purpose', 'Status', 'Requested By', 'Date'].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {data?.data?.length === 0 && (
                    <tr>
                      <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">No requisitions found.</td>
                    </tr>
                  )}
                  {data?.data?.map((mrq) => (
                    <tr key={mrq.id} className="hover:bg-neutral-50/50 transition-colors cursor-pointer" onClick={() => navigate(`/inventory/requisitions/${mrq.ulid}`)}>
                      <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{mrq.mr_reference}</td>
                      <td className="px-4 py-3">
                        {mrq.production_order
                          ? <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-600">Auto — WO</span>
                          : <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-white border border-neutral-300 text-neutral-500">Manual</span>
                        }
                      </td>
                      <td className="px-4 py-3 text-neutral-600">{mrq.department?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-neutral-500 max-w-xs truncate">
                        {mrq.production_order
                          ? (
                            <Link
                              to={`/production/orders/${mrq.production_order.ulid}`}
                              className="text-neutral-700 hover:text-neutral-900 underline underline-offset-2"
                              title={`Go to ${mrq.production_order.po_reference}`}
                              onClick={(e) => e.stopPropagation()}
                            >
                              {mrq.purpose}
                            </Link>
                          )
                          : mrq.purpose
                        }
                      </td>
                      <td className="px-4 py-3">
                        {mrq.deleted_at && <StatusBadge className="bg-neutral-100 text-neutral-500 mr-1">Archived</StatusBadge>}
                        <StatusBadge className={`capitalize ${statusBadge[mrq.status]}`}>
                          {mrq.status}
                        </StatusBadge>
                      </td>
                      <td className="px-4 py-3 text-neutral-500">{mrq.requested_by?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-neutral-400 text-xs">
                        {mrq.created_at ? new Date(mrq.created_at).toLocaleDateString('en-PH') : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardBody>
          </Card>

          {data && data.meta.last_page > 1 && (
            <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
              <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} requisitions</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Previous</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page >= data.meta.last_page} className="px-3 py-1.5 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Next</button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
