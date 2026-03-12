import { LayoutDashboard, ShoppingCart, Package } from 'lucide-react'
import { useVendorOrders, useVendorPortalItems } from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody } from '@/components/ui/Card'

export default function VendorPortalDashboardPage(): React.ReactElement {
  const { data: ordersData, isLoading: ordersLoading } = useVendorOrders()
  const { data: items, isLoading: itemsLoading } = useVendorPortalItems()

  const activeOrders = ordersData?.data?.filter((o) =>
    ['sent', 'partially_received'].includes(o.status)
  ) ?? []
  const totalOrders = ordersData?.meta?.total ?? 0
  const totalItems = items?.length ?? 0

  const statCards = [
    { label: 'Active Orders', value: ordersLoading ? '—' : String(activeOrders.length), sub: 'Awaiting delivery', icon: ShoppingCart },
    { label: 'Total Orders', value: ordersLoading ? '—' : String(totalOrders), sub: 'All time', icon: ShoppingCart },
    { label: 'Catalog Items', value: itemsLoading ? '—' : String(totalItems), sub: 'Active listings', icon: Package },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dashboard"
        subtitle="Overview of your orders and catalog."
        icon={<LayoutDashboard className="w-5 h-5 text-neutral-600" />}
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {statCards.map((card) => (
          <Card key={card.label}>
            <CardBody className="py-4">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs text-neutral-500 uppercase tracking-wide">{card.label}</p>
                  <p className="text-3xl font-bold text-neutral-900 mt-1">{card.value}</p>
                  <p className="text-xs text-neutral-400 mt-0.5">{card.sub}</p>
                </div>
                <card.icon className="w-5 h-5 text-neutral-300" />
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      {activeOrders.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-base font-semibold text-neutral-800">Pending Orders</h2>
          <Card>
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">PO Reference</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">Delivery Date</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">Status</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {activeOrders.slice(0, 5).map((order) => (
                  <tr key={order.ulid} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                    <td className="px-4 py-3 font-mono text-xs text-neutral-700">{order.po_reference}</td>
                    <td className="px-4 py-3 text-neutral-600">{order.delivery_date}</td>
                    <td className="px-4 py-3">
                      <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium border capitalize shadow-sm bg-neutral-50 text-neutral-700 border-neutral-200">
                        {order.status.replace(/_/g, ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-neutral-800 font-mono">
                      ₱{Number(order.total_po_amount).toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
        </div>
      )}
    </div>
  )
}
