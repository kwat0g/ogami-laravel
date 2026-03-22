import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { RefreshCw, ChevronRight } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useClientOrders } from '@/hooks/useClientOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { ClientOrder } from '@/types/client-order'

const STATUS_LABEL: Record<string, string> = {
  pending: 'Pending',
  negotiating: 'Awaiting Client',
  client_responded: 'Client Counter',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-700',
  negotiating: 'bg-blue-100 text-blue-700',
  client_responded: 'bg-purple-100 text-purple-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700',
  cancelled: 'bg-neutral-100 text-neutral-500',
}

const ALL_STATUSES = ['pending', 'negotiating', 'client_responded', 'approved', 'rejected', 'cancelled'] as const

function StatusBadge({ status }: { status: string }) {
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[status] ?? 'bg-neutral-100 text-neutral-600'}`}>
      {STATUS_LABEL[status] ?? status}
    </span>
  )
}

function formatCurrency(centavos: number) {
  return '₱' + (centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function ClientOrdersReviewPage(): JSX.Element {
  const navigate = useNavigate()
  // Default to no filter (shows all active orders: pending + negotiating + client_responded)
  const [activeStatus, setActiveStatus] = useState<string | null>(null)
  const { data, isLoading, refetch } = useClientOrders({
    status: activeStatus ?? undefined
  })

  const orders = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <PageHeader title="Client Orders" />

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">Review and process client orders</p>
        </div>
        <div className="flex items-center gap-2">
          <button 
            onClick={() => refetch()} 
            className="p-2 rounded border border-neutral-300 hover:bg-neutral-50"
          >
            <RefreshCw className="w-4 h-4 text-neutral-500" />
          </button>
        </div>
      </div>

      {/* Status Tabs */}
      <div className="flex items-center gap-2 flex-wrap">
        <button
          onClick={() => setActiveStatus(null)}
          className={`px-3 py-1.5 rounded text-xs font-medium transition-colors ${
            activeStatus === null ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
          }`}
        >
          All
        </button>
        {ALL_STATUSES.map(s => (
          <button
            key={s}
            onClick={() => setActiveStatus(s)}
            className={`px-3 py-1.5 rounded text-xs font-medium transition-colors ${
              activeStatus === s ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
            }`}
          >
            {STATUS_LABEL[s]}
          </button>
        ))}
      </div>

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : orders.length === 0 ? (
        <div className="text-center py-16 text-neutral-400">
          No orders found.
        </div>
      ) : (
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Reference</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Client</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Items</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Delivery Date</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Total</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Status</th>
                <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Submitted</th>
                <th className="text-right px-3 py-2.5 font-medium text-neutral-600"></th>
              </tr>
            </thead>
            <tbody>
            {orders.map((order: ClientOrder, index: number) => (
              <tr
                key={order.ulid}
                onClick={() => navigate(`/sales/client-orders/${order.ulid}`)}
                  className={`even:bg-neutral-100 hover:bg-neutral-50 transition-colors cursor-pointer ${
                    index % 2 === 0 ? 'bg-white' : 'bg-neutral-50'
                  }`}
                >
                  <td className="px-3 py-2 font-medium text-neutral-900">
                    {order.order_reference}
                  </td>
                  <td className="px-3 py-2">
                    <span className="font-medium text-neutral-900">{order.customer?.name}</span>
                    {order.customer?.email && (
                      <div className="text-xs text-neutral-400">{order.customer.email}</div>
                    )}
                  </td>
                  <td className="px-3 py-2 text-neutral-600">
                    {order.items.length} {order.items.length === 1 ? 'item' : 'items'}
                  </td>
                  <td className="px-3 py-2 text-neutral-600">
                    {order.requested_delivery_date 
                      ? new Date(order.requested_delivery_date).toLocaleDateString('en-PH', {
                          month: 'short',
                          day: 'numeric',
                          year: 'numeric'
                        })
                      : '—'
                    }
                    {order.agreed_delivery_date && (
                      <div className="text-xs text-emerald-600">
                        Agreed: {new Date(order.agreed_delivery_date).toLocaleDateString('en-PH', {
                          month: 'short',
                          day: 'numeric'
                        })}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right font-mono text-neutral-800">
                    {formatCurrency(order.total_amount_centavos)}
                  </td>
                  <td className="px-3 py-2">
                    <StatusBadge status={order.status} />
                  </td>
                  <td className="px-3 py-2 text-neutral-600">
                    {new Date(order.created_at).toLocaleDateString('en-PH', {
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric'
                    })}
                  </td>
                  <td className="px-3 py-2 text-right">
                    <ChevronRight className="w-4 h-4 text-neutral-400 inline-block" />
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
