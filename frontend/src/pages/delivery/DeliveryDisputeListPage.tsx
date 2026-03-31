import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle, Search } from 'lucide-react'
import { useDisputes } from '@/hooks/useDeliveryDisputes'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import EmptyState from '@/components/ui/EmptyState'

const STATUS_COLORS: Record<string, string> = {
  open: 'bg-red-100 text-red-700',
  investigating: 'bg-amber-100 text-amber-700',
  pending_resolution: 'bg-blue-100 text-blue-700',
  resolved: 'bg-green-100 text-green-700',
  closed: 'bg-neutral-100 text-neutral-400',
}

const STATUS_LABELS: Record<string, string> = {
  open: 'Open',
  investigating: 'Investigating',
  pending_resolution: 'Pending Resolution',
  resolved: 'Resolved',
  closed: 'Closed',
}

const FILTER_TABS = [
  { value: '', label: 'All' },
  { value: 'open', label: 'Open' },
  { value: 'investigating', label: 'Investigating' },
  { value: 'pending_resolution', label: 'Pending' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
]

export default function DeliveryDisputeListPage() {
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const params: Record<string, string | number> = { page, per_page: 20 }
  if (status) params.status = status
  if (search) params.search = search

  const { data, isLoading } = useDisputes(params)
  const disputes = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <PageHeader
        title="Delivery Disputes"
        subtitle="Track and resolve client-reported delivery issues"
        icon={<AlertTriangle className="w-5 h-5 text-amber-600" />}
      />

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div className="flex gap-1 flex-wrap">
          {FILTER_TABS.map(tab => (
            <button
              key={tab.value}
              onClick={() => { setStatus(tab.value); setPage(1) }}
              className={`px-3 py-1.5 text-xs font-medium rounded-full transition-colors ${
                status === tab.value
                  ? 'bg-neutral-900 text-white'
                  : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>
        <div className="relative w-full sm:w-64">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
          <input
            type="text"
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
            placeholder="Search disputes..."
            className="w-full pl-9 pr-3 py-2 border border-neutral-200 rounded-lg text-sm"
          />
        </div>
      </div>

      {isLoading ? <SkeletonLoader rows={5} /> : disputes.length === 0 ? (
        <EmptyState title="No disputes found" />
      ) : (
        <>
          <Card className="overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Reference</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Customer</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Status</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Items</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Resolution</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Reported</th>
                  <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Assigned</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {disputes.map(d => (
                  <tr key={d.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-3">
                      <Link to={`/delivery/disputes/${d.ulid}`} className="text-blue-600 hover:underline font-medium">
                        {d.dispute_reference}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-neutral-700">{d.customer?.name ?? '-'}</td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[d.status] ?? 'bg-neutral-100'}`}>
                        {STATUS_LABELS[d.status] ?? d.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-neutral-600">
                      {d.items?.length ?? 0} item{(d.items?.length ?? 0) !== 1 ? 's' : ''}
                    </td>
                    <td className="px-4 py-3 text-neutral-500 capitalize">
                      {d.resolution_type?.replace('_', ' ') ?? '-'}
                    </td>
                    <td className="px-4 py-3 text-neutral-500 text-xs">
                      {new Date(d.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-3 text-neutral-600 text-xs">
                      {d.assigned_to?.name ?? <span className="text-amber-500">Unassigned</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex justify-center gap-2">
              <button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-3 py-1 text-sm border rounded disabled:opacity-50"
              >
                Prev
              </button>
              <span className="px-3 py-1 text-sm text-neutral-500">
                {meta.current_page} / {meta.last_page}
              </span>
              <button
                onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
                disabled={page === meta.last_page}
                className="px-3 py-1 text-sm border rounded disabled:opacity-50"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
