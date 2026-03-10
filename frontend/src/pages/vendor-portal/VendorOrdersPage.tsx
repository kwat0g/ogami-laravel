import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useVendorOrders } from '@/hooks/useVendorPortal'

const STATUS_LABELS: Record<string, string> = {
  sent: 'Awaiting Delivery',
  partially_received: 'Partially Delivered',
  fully_received: 'Fully Received',
  closed: 'Closed',
}

const STATUS_BADGE: Record<string, string> = {
  sent: 'bg-neutral-100 text-neutral-700',
  partially_received: 'bg-yellow-100 text-yellow-700',
  fully_received: 'bg-neutral-200 text-neutral-700',
  closed: 'bg-neutral-100 text-neutral-500',
}

export default function VendorOrdersPage(): React.ReactElement {
  const [statusFilter, setStatusFilter] = useState('')

  const { data, isLoading, isError } = useVendorOrders(statusFilter || undefined)

  if (isLoading) return <p className="text-sm text-neutral-500 mt-4">Loading orders…</p>
  if (isError) return <p className="text-sm text-red-500 mt-4">Failed to load orders.</p>

  const orders = data?.data ?? []

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h1 className="text-2xl font-bold text-neutral-900">My Orders</h1>
          <p className="text-sm text-neutral-500">Purchase orders assigned to your vendor account.</p>
        </div>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="text-sm border border-neutral-300 rounded-md px-3 py-1.5 bg-white text-neutral-700"
        >
          <option value="">All Statuses</option>
          {Object.entries(STATUS_LABELS).map(([val, label]) => (
            <option key={val} value={val}>{label}</option>
          ))}
        </select>
      </div>

      {orders.length === 0 ? (
        <div className="bg-white border border-neutral-200 rounded-lg px-6 py-12 text-center">
          <p className="text-neutral-500 text-sm">No orders found for the selected filter.</p>
        </div>
      ) : (
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">PO Reference</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">PO Date</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Delivery Date</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Status</th>
                <th className="text-right px-4 py-3 font-medium text-neutral-600 text-xs uppercase">Amount</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.ulid} className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                  <td className="px-4 py-3 font-mono text-xs font-medium text-neutral-800">{order.po_reference}</td>
                  <td className="px-4 py-3 text-neutral-600">{order.po_date}</td>
                  <td className="px-4 py-3 text-neutral-600">{order.delivery_date}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_BADGE[order.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                      {STATUS_LABELS[order.status] ?? order.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-neutral-800 font-medium">
                    ₱{Number(order.total_po_amount).toLocaleString()}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Link
                      to={`/vendor-portal/orders/${order.ulid}`}
                      className="text-xs text-neutral-700 hover:underline"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
