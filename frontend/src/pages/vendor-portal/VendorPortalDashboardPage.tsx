import { Link } from 'react-router-dom'
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  FileText,
  CheckCircle,
  ClipboardCheck,
  ArrowRight,
} from 'lucide-react'
import {
  useVendorOrders,
  useVendorPortalItems,
  useVendorGoodsReceipts,
  useVendorInvoices,
  type VendorPortalGoodsReceipt,
  type VendorPortalInvoice,
} from '@/hooks/useVendorPortal'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { statusBadges } from '@/styles/design-system'

function QuickActionCard({
  title,
  description,
  icon: Icon,
  href,
}: {
  title: string
  description: string
  icon: React.ComponentType<{ className?: string }>
  href: string
}) {
  return (
    <Link
      to={href}
      className="group flex items-center gap-4 p-4 bg-white border border-neutral-200 rounded-lg hover:shadow-md hover:border-neutral-300 transition-all"
    >
      <div className="w-12 h-12 bg-neutral-900 rounded-lg flex items-center justify-center text-white transition-colors group-hover:bg-neutral-800">
        <Icon className="h-6 w-6" />
      </div>
      <div className="flex-1 min-w-0">
        <h3 className="font-semibold text-neutral-900">{title}</h3>
        <p className="text-sm text-neutral-500">{description}</p>
      </div>
      <ArrowRight className="h-5 w-5 text-neutral-400 group-hover:text-neutral-600 group-hover:translate-x-1 transition-all" />
    </Link>
  )
}

function StatCard({
  label,
  value,
  subtext,
  icon: Icon,
  href,
}: {
  label: string
  value: string | number
  subtext?: string
  icon: React.ComponentType<{ className?: string }>
  href: string
}) {
  return (
    <Link to={href} className="block">
      <Card className="h-full hover:shadow-md transition-shadow">
        <CardBody>
          <div className="flex items-start justify-between">
            <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center">
              <Icon className="h-5 w-5 text-neutral-600" />
            </div>
          </div>
          <div className="mt-4">
            <p className="text-lg font-semibold text-neutral-900">{value}</p>
            <p className="text-sm text-neutral-600 mt-0.5">{label}</p>
            {subtext && <p className="text-xs text-neutral-400 mt-1">{subtext}</p>}
          </div>
        </CardBody>
      </Card>
    </Link>
  )
}

const STATUS_BADGE: Record<string, string> = {
  sent: statusBadges.pending,
  negotiating: statusBadges.inProgress,
  acknowledged: statusBadges.active,
  in_transit: statusBadges.inProgress,
  delivered: statusBadges.completed,
  partially_received: statusBadges.partiallyReceived,
  fully_received: statusBadges.fullyReceived,
  closed: statusBadges.closed,
}

export default function VendorPortalDashboardPage(): React.ReactElement {
  const { data: ordersData, isLoading: ordersLoading } = useVendorOrders()
  const { data: items, isLoading: itemsLoading } = useVendorPortalItems()
  const { data: grData, isLoading: grLoading } = useVendorGoodsReceipts()
  const { data: invoiceData, isLoading: invoiceLoading } = useVendorInvoices()

  const isLoading = ordersLoading || itemsLoading || grLoading || invoiceLoading

  if (isLoading) {
    return <SkeletonLoader rows={6} />
  }

  const allOrders = ordersData?.data ?? []
  const activeOrders = allOrders.filter((o) =>
    ['sent', 'negotiating', 'acknowledged', 'in_transit', 'delivered', 'partially_received'].includes(o.status)
  )
  const awaitingConfirmation = allOrders.filter((o) =>
    ['in_transit', 'delivered'].includes(o.status)
  )
  const totalOrders = ordersData?.meta?.total ?? 0
  const totalItems = items?.length ?? 0

  const goodsReceipts: VendorPortalGoodsReceipt[] = grData?.data ?? []
  const confirmedGrWithoutInvoice = goodsReceipts.filter(
    (gr) => gr.status === 'confirmed' && !gr.ap_invoice_created
  )

  const invoices: VendorPortalInvoice[] = invoiceData?.data ?? []
  const pendingInvoices = invoices.filter((inv) => ['draft', 'submitted'].includes(inv.status))

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dashboard"
        subtitle="Overview of your purchase orders and catalog."
        icon={<LayoutDashboard className="w-5 h-5 text-neutral-600" />}
      />

      {/* Quick Actions */}
      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <QuickActionCard
          title="Purchase Orders"
          description="View and manage your orders"
          icon={ShoppingCart}
          href="/vendor-portal/orders"
        />
        <QuickActionCard
          title="Goods Receipts"
          description="Track delivery confirmations"
          icon={ClipboardCheck}
          href="/vendor-portal/goods-receipts"
        />
        <QuickActionCard
          title="My Catalog"
          description="Manage your product listings"
          icon={Package}
          href="/vendor-portal/items"
        />
      </div>

      {/* Alerts */}
      {confirmedGrWithoutInvoice.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
              <FileText className="h-5 w-5 text-amber-600" />
            </div>
            <div>
              <p className="font-medium text-amber-900">
                {confirmedGrWithoutInvoice.length} receipt{confirmedGrWithoutInvoice.length !== 1 ? 's' : ''} ready to invoice
              </p>
              <p className="text-sm text-amber-700">Submit invoices for confirmed goods receipts.</p>
            </div>
          </div>
          <Link
            to="/vendor-portal/invoices"
            className="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors"
          >
            View Invoices
          </Link>
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        <StatCard
          label="Active Orders"
          value={activeOrders.length}
          subtext="In progress"
          icon={ShoppingCart}
          href="/vendor-portal/orders"
        />
        <StatCard
          label="Awaiting Receipt"
          value={awaitingConfirmation.length}
          subtext="Delivered, pending GR"
          icon={CheckCircle}
          href="/vendor-portal/orders"
        />
        <StatCard
          label="Ready to Invoice"
          value={confirmedGrWithoutInvoice.length}
          subtext="GR confirmed, no invoice"
          icon={FileText}
          href="/vendor-portal/invoices"
        />
        <StatCard
          label="Pending Invoices"
          value={pendingInvoices.length}
          subtext="Awaiting approval"
          icon={FileText}
          href="/vendor-portal/invoices"
        />
        <StatCard
          label="Catalog Items"
          value={totalItems}
          subtext="Active listings"
          icon={Package}
          href="/vendor-portal/items"
        />
        <StatCard
          label="Total Orders"
          value={totalOrders}
          subtext="All time"
          icon={ShoppingCart}
          href="/vendor-portal/orders"
        />
      </div>

      {/* Active Orders Table */}
      {activeOrders.length > 0 && (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-neutral-900">Pending Purchase Orders</h2>
            <Link to="/vendor-portal/orders" className="text-xs text-neutral-500 hover:text-neutral-700 font-medium">
              View all &rarr;
            </Link>
          </div>
          <Card>
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">PO Reference</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Delivery Date</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Status</th>
                  <th className="text-right px-4 py-3 text-xs font-semibold text-neutral-600 uppercase tracking-wider">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {activeOrders.slice(0, 5).map((order) => (
                  <tr key={order.ulid} className="hover:bg-neutral-50 transition-colors">
                    <td className="px-4 py-3">
                      <Link
                        to={`/vendor-portal/orders/${order.ulid}`}
                        className="font-mono text-xs font-medium text-neutral-800 hover:text-neutral-900 hover:underline"
                      >
                        {order.po_reference}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-neutral-600">{order.delivery_date}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize ${STATUS_BADGE[order.status] ?? statusBadges.draft}`}>
                        {order.status.replace(/_/g, ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right text-neutral-800 font-medium font-mono">
                      &#8369;{Number(order.total_po_amount).toLocaleString()}
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
