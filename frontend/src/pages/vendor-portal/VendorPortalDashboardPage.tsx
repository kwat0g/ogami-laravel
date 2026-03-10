import { useVendorOrders, useVendorPortalItems } from '@/hooks/useVendorPortal'

export default function VendorPortalDashboardPage(): React.ReactElement {
  const { data: ordersData, isLoading: ordersLoading } = useVendorOrders()
  const { data: items, isLoading: itemsLoading } = useVendorPortalItems()

  const activeOrders = ordersData?.data?.filter((o) =>
    ['sent', 'partially_received'].includes(o.status)
  ) ?? []
  const totalOrders = ordersData?.meta?.total ?? 0
  const totalItems = items?.length ?? 0

  return (
    <div>
      <h1 className="text-2xl font-bold text-neutral-900 mb-1">Dashboard</h1>
      <p className="text-sm text-neutral-500 mb-6">Overview of your orders and catalog.</p>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <StatCard
          label="Active Orders"
          value={ordersLoading ? '—' : String(activeOrders.length)}
          sub="Awaiting delivery"
        />
        <StatCard
          label="Total Orders"
          value={ordersLoading ? '—' : String(totalOrders)}
          sub="All time"
        />
        <StatCard
          label="Catalog Items"
          value={itemsLoading ? '—' : String(totalItems)}
          sub="Active listings"
        />
      </div>

      {activeOrders.length > 0 && (
        <div>
          <h2 className="text-base font-semibold text-neutral-800 mb-3">Pending Orders</h2>
          <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="text-left px-4 py-2 font-medium text-neutral-600">PO Reference</th>
                  <th className="text-left px-4 py-2 font-medium text-neutral-600">Delivery Date</th>
                  <th className="text-left px-4 py-2 font-medium text-neutral-600">Status</th>
                  <th className="text-left px-4 py-2 font-medium text-neutral-600">Amount</th>
                </tr>
              </thead>
              <tbody>
                {activeOrders.slice(0, 5).map((order) => (
                  <tr key={order.ulid} className="border-b border-neutral-100 last:border-0">
                    <td className="px-4 py-3 font-mono text-xs text-neutral-700">{order.po_reference}</td>
                    <td className="px-4 py-3 text-neutral-600">{order.delivery_date}</td>
                    <td className="px-4 py-3">
                      <span className="px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700 capitalize">
                        {order.status.replace(/_/g, ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-neutral-800">
                      ₱{Number(order.total_po_amount).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}

function StatCard({ label, value, sub }: { label: string; value: string; sub: string }): React.ReactElement {
  return (
    <div className="bg-white border border-neutral-200 rounded-lg px-5 py-4">
      <p className="text-xs text-neutral-500 uppercase tracking-wide">{label}</p>
      <p className="text-3xl font-bold text-neutral-900 mt-1">{value}</p>
      <p className="text-xs text-neutral-400 mt-0.5">{sub}</p>
    </div>
  )
}
