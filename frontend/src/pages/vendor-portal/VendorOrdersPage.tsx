import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ShoppingCart, ChevronRight } from 'lucide-react'
import { useVendorOrders } from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { statusBadges } from '@/styles/design-system'

const STATUS_LABELS: Record<string, string> = {
  sent: 'Awaiting Delivery',
  partially_received: 'Partially Delivered',
  fully_received: 'Fully Received',
  closed: 'Closed',
}

const STATUS_BADGE: Record<string, string> = {
  sent: statusBadges.pending,
  partially_received: statusBadges.partiallyReceived,
  fully_received: statusBadges.fullyReceived,
  closed: statusBadges.closed,
}

export default function VendorOrdersPage(): React.ReactElement {
  const [statusFilter, setStatusFilter] = useState('')

  const { data, isLoading, isError } = useVendorOrders(statusFilter || undefined)

  if (isError) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Purchase Orders"
          subtitle="Purchase orders assigned to your vendor account"
          icon={<ShoppingCart className="h-5 w-5 text-neutral-600" />}
        />
        <div className="bg-red-50 border border-red-200 rounded-lg px-6 py-12 text-center">
          <p className="text-red-600 text-sm font-medium">Failed to load purchase orders. Please try again.</p>
        </div>
      </div>
    )
  }

  const orders = data?.data ?? []

  return (
    <div className="space-y-5">
      <PageHeader
        title="Purchase Orders"
        subtitle="Purchase orders assigned to your vendor account"
        icon={<ShoppingCart className="h-5 w-5 text-neutral-600" />}
        actions={
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="text-sm border border-neutral-300 rounded-lg px-4 py-2 bg-white text-neutral-700 focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
          >
            <option value="">All Statuses</option>
            {Object.entries(STATUS_LABELS).map(([val, label]) => (
              <option key={val} value={val}>{label}</option>
            ))}
          </select>
        }
      />

      {isLoading ? (
        <SkeletonLoader rows={5} />
      ) : orders.length === 0 ? (
        <div className="text-center py-16">
          <div className="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <ShoppingCart className="h-8 w-8 text-neutral-400" />
          </div>
          <h3 className="text-base font-medium text-neutral-900 mb-1">No purchase orders found</h3>
          <p className="text-sm text-neutral-500 max-w-sm mx-auto">
            {statusFilter
              ? 'Try adjusting your filter criteria.'
              : 'Purchase orders will appear here once they are assigned to your vendor account.'}
          </p>
        </div>
      ) : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">PO Reference</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">PO Date</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Delivery Date</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Status</th>
                <th className="text-right px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Amount</th>
                <th className="px-4 py-3 w-10"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {orders.map((order) => (
                <tr key={order.ulid} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-mono text-xs font-medium text-neutral-800">{order.po_reference}</td>
                  <td className="px-4 py-3 text-neutral-600">{order.po_date}</td>
                  <td className="px-4 py-3 text-neutral-600">{order.delivery_date}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${STATUS_BADGE[order.status] ?? statusBadges.draft}`}>
                      {STATUS_LABELS[order.status] ?? order.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-800 font-medium">
                    &#8369;{Number(order.total_po_amount).toLocaleString()}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Link
                      to={`/vendor-portal/orders/${order.ulid}`}
                      className="inline-flex items-center gap-1 text-xs text-neutral-500 hover:text-neutral-900 font-medium transition-colors"
                    >
                      View
                      <ChevronRight className="h-3 w-3" />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
