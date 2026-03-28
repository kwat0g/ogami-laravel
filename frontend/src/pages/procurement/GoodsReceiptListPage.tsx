import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { AlertTriangle } from 'lucide-react'
import { useGoodsReceipts } from '@/hooks/useGoodsReceipts'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import { ExportButton } from '@/components/ui/ExportButton'
import type { GoodsReceiptStatus } from '@/types/procurement'

const statusBadge: Record<GoodsReceiptStatus, string> = {
  draft:     'bg-neutral-100 text-neutral-600',
  confirmed: 'bg-neutral-200 text-neutral-800',
}

export default function GoodsReceiptListPage(): React.ReactElement {
  const navigate = useNavigate()
  const [statusFilter, setStatusFilter] = useState<GoodsReceiptStatus | ''>('')
  const [page, setPage] = useState(1)
  const [isArchiveView, setIsArchiveView] = useState(false)
  // Note: GRs are auto-created by vendors via markDelivered
  // Internal users can only view and confirm GRs, not create them

  const { data, isLoading, isError } = useGoodsReceipts({
    ...(statusFilter ? { status: statusFilter } : {}),

  const { data: archivedData, isLoading: archivedLoading, refetch: refetchArchived } = useQuery({
    queryKey: ['goods-receipts', 'archived'],
    queryFn: () => api.get('/procurement/goods-receipts-archived', { params: { per_page: 20 } }),
    enabled: isArchiveView,
  })
    page,
    with_archived: undefined,
  })

  return (
    <div>
      <PageHeader
        title="Goods Receipts"
        description="View and confirm goods receipts created by vendors"
        actions={
          <ExportButton
            data={data?.data ?? []}
            columns={[
              { key: 'gr_reference', label: 'GR Reference' },
              { key: 'purchase_order.po_reference', label: 'PO Reference' },
              { key: 'vendor.name', label: 'Vendor' },
              { key: 'status', label: 'Status' },
              { key: 'received_date', label: 'Received Date' },
            ]}
            filename="goods-receipts"
          />
        }
      />

      {/* Filters */}
      <div className="flex items-center gap-3 mb-5">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value as GoodsReceiptStatus | ''); setPage(1) }}
          className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
        >
          <option value="">All Statuses</option>
          {(['draft', 'confirmed'] as GoodsReceiptStatus[]).map((s) => (
            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
          ))}
        </select>
        <ArchiveToggleButton isArchiveView={isArchiveView} onToggle={() => setIsArchiveView(prev => !prev)} />
      </div>

      {/* Table */}
      {isLoading && <SkeletonLoader rows={6} />}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" />
          Failed to load goods receipts.
        </div>
      )}
      {!isLoading && !isError && (
        <Card>
          <CardHeader>Goods Receipts</CardHeader>
          <CardBody className="p-0">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['GR Number', 'PO Number', 'Received Date', 'Status', 'Confirmed By'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {data?.data?.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-neutral-400 text-sm">
                      No goods receipts found.
                    </td>
                  </tr>
                )}
                {data?.data?.map((gr) => (
                  <tr key={gr.id} className="hover:bg-neutral-50/50 transition-colors cursor-pointer" onClick={() => navigate(`/procurement/goods-receipts/${gr.ulid}`)}>
                    <td className="px-4 py-3 font-mono text-neutral-900 font-medium">{gr.gr_reference}</td>
                    <td className="px-4 py-3 text-neutral-600">
                      {gr.purchase_order
                        ? <Link to={`/procurement/purchase-orders/${gr.purchase_order.ulid}`} className="text-neutral-700 hover:text-neutral-900 underline underline-offset-2 font-mono text-xs" onClick={(e) => e.stopPropagation()}>{gr.purchase_order.po_reference}</Link>
                        : `#${gr.purchase_order_id}`
                      }
                    </td>
                    <td className="px-4 py-3 text-neutral-500">
                      {gr.received_date ? new Date(gr.received_date).toLocaleDateString('en-PH') : '—'}
                    </td>
                    <td className="px-4 py-3">
                      {gr.deleted_at && <StatusBadge className="bg-neutral-100 text-neutral-500 mr-1">Archived</StatusBadge>}
                      <StatusBadge className={statusBadge[gr.status]}>
                        {gr.status}
                      </StatusBadge>
                    </td>
                    <td className="px-4 py-3 text-neutral-500">{gr.confirmed_by?.name ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Pagination */}
            {data?.meta && data.meta.last_page > 1 && (
              <div className="flex items-center justify-between px-4 py-3 border-t border-neutral-200 text-sm text-neutral-600">
                <span>Page {data.meta.current_page} of {data.meta.last_page}</span>
                <div className="flex gap-2">
                  <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Prev</button>
                  <button disabled={page === data.meta.last_page} onClick={() => setPage(p => p + 1)} className="px-3 py-1 border border-neutral-300 rounded disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50">Next</button>
                </div>
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  )
}
