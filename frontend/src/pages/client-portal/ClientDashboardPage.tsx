import { useNavigate, Link } from 'react-router-dom'
import { 
  ShoppingBag, 
  Package, 
  Ticket as TicketIcon, 
  ArrowRight,
  Clock,
  CheckCircle,
  MessageCircle,
  AlertCircle,
  LayoutDashboard
} from 'lucide-react'
import { Card } from '@/components/ui/Card'
import { PageHeader } from '@/components/ui/PageHeader'
import { useMyClientOrders } from '@/hooks/useClientOrders'
import { useTickets } from '@/hooks/useCRM'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { ClientOrder } from '@/types/client-order'
import type { Ticket } from '@/types/crm'

const STATUS_CONFIG: Record<string, { 
  color: string
  icon: React.ReactNode 
}> = {
  pending: { 
    color: 'text-amber-600 bg-amber-50',
    icon: <Clock className="h-4 w-4" />
  },
  negotiating: { 
    color: 'text-blue-600 bg-blue-50',
    icon: <MessageCircle className="h-4 w-4" />
  },
  client_responded: {
    color: 'text-cyan-700 bg-cyan-50',
    icon: <MessageCircle className="h-4 w-4" />,
  },
  vp_pending: {
    color: 'text-indigo-700 bg-indigo-50',
    icon: <Clock className="h-4 w-4" />,
  },
  approved: { 
    color: 'text-green-600 bg-green-50',
    icon: <CheckCircle className="h-4 w-4" />
  },
  in_production: {
    color: 'text-indigo-700 bg-indigo-50',
    icon: <Package className="h-4 w-4" />,
  },
  ready_for_delivery: {
    color: 'text-emerald-700 bg-emerald-50',
    icon: <Package className="h-4 w-4" />,
  },
  dispatched: {
    color: 'text-blue-700 bg-blue-50',
    icon: <Package className="h-4 w-4" />,
  },
  delivered: {
    color: 'text-green-700 bg-green-50',
    icon: <CheckCircle className="h-4 w-4" />,
  },
  fulfilled: {
    color: 'text-green-700 bg-green-50',
    icon: <CheckCircle className="h-4 w-4" />,
  },
  completed: {
    color: 'text-green-700 bg-green-50',
    icon: <CheckCircle className="h-4 w-4" />,
  },
  rejected: { 
    color: 'text-red-600 bg-red-50',
    icon: <AlertCircle className="h-4 w-4" />
  },
  cancelled: { 
    color: 'text-neutral-600 bg-neutral-50',
    icon: <AlertCircle className="h-4 w-4" />
  },
}

const FALLBACK_STATUS = {
  color: 'text-neutral-600 bg-neutral-50',
  icon: <AlertCircle className="h-4 w-4" />,
}

function formatStatusLabel(status: string): string {
  return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function QuickActionCard({ 
  title, 
  description, 
  icon: Icon, 
  href,
  color = 'neutral'
}: { 
  title: string
  description: string
  icon: React.ComponentType<{ className?: string }>
  href: string
  color?: 'blue' | 'green' | 'neutral'
}) {
  const colorClasses = {
    blue: 'bg-blue-600 hover:bg-blue-700',
    green: 'bg-emerald-600 hover:bg-emerald-700',
    neutral: 'bg-neutral-900 hover:bg-neutral-800'
  }

  return (
    <Link 
      to={href}
      className="group flex items-center gap-4 p-4 bg-white border border-neutral-200 rounded-xl hover:shadow-md hover:border-neutral-300 transition-all"
    >
      <div className={`w-12 h-12 ${colorClasses[color]} rounded-xl flex items-center justify-center text-white transition-colors`}>
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
  href
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
        <div className="p-5">
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
        </div>
      </Card>
    </Link>
  )
}

function RecentOrderRow({ order }: { order: ClientOrder }) {
  const navigate = useNavigate()
  const status = STATUS_CONFIG[order.status] ?? FALLBACK_STATUS

    return (
      <div
        onClick={() => navigate(`/client-portal/orders/${order.ulid}`)}
      className="flex items-center justify-between px-4 py-3 hover:bg-neutral-50 cursor-pointer transition-colors border-b border-neutral-100 last:border-b-0"
    >
      <div className="flex items-center gap-3">
        <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${status.color}`}>
          {status.icon}
        </div>
        <div>
          <p className="font-medium text-neutral-900 text-sm">{order.order_reference}</p>
          <p className="text-xs text-neutral-500">
            {order.items.length} item{order.items.length !== 1 ? 's' : ''} • {' '}
            {new Date(order.created_at).toLocaleDateString('en-PH', { 
              month: 'short', 
              day: 'numeric' 
            })}
          </p>
        </div>
      </div>
      <div className="text-right">
        <p className="font-semibold text-neutral-900 text-sm">
          ₱{(order.total_amount_centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
        </p>
        <p className="text-xs text-neutral-500">{formatStatusLabel(order.status)}</p>
      </div>
    </div>
  )
}

function RecentTicketRow({ ticket }: { ticket: Ticket }) {
  const navigate = useNavigate()
  
  return (
    <div 
      onClick={() => navigate(`/client-portal/tickets/${ticket.ulid}`)}
      className="flex items-center justify-between px-4 py-3 hover:bg-neutral-50 cursor-pointer transition-colors border-b border-neutral-100 last:border-b-0"
    >
      <div className="flex items-center gap-3">
        <div className="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
          <TicketIcon className="h-4 w-4 text-blue-600" />
        </div>
        <div className="min-w-0">
          <p className="font-medium text-neutral-900 text-sm truncate">{ticket.subject}</p>
          <p className="text-xs text-neutral-500">
            {ticket.ticket_number} • {new Date(ticket.created_at).toLocaleDateString('en-PH', { 
              month: 'short', 
              day: 'numeric' 
            })}
          </p>
        </div>
      </div>
      <div className={`px-2 py-1 rounded text-xs font-medium capitalize ${
        ticket.status === 'open' ? 'bg-amber-50 text-amber-700' :
        ticket.status === 'in_progress' ? 'bg-blue-50 text-blue-700' :
        ticket.status === 'resolved' ? 'bg-green-50 text-green-700' :
        'bg-neutral-100 text-neutral-600'
      }`}>
        {ticket.status.replace('_', ' ')}
      </div>
    </div>
  )
}

export default function ClientDashboardPage(): JSX.Element {
  const navigate = useNavigate()
  const { data: orders, isLoading: ordersLoading } = useMyClientOrders()
  const { data: tickets, isLoading: ticketsLoading } = useTickets({ per_page: 5 })

  const isLoading = ordersLoading || ticketsLoading

  if (isLoading) {
    return <SkeletonLoader rows={6} />
  }

  const recentOrders = orders?.data?.slice(0, 5) || []
  const recentTickets = tickets?.data?.slice(0, 5) || []
  
  const pendingOrders = orders?.data?.filter((o: ClientOrder) => ['pending', 'negotiating'].includes(o.status)) || []
  const deliveredOrders = orders?.data?.filter((o: ClientOrder) => o.status === 'delivered') || []
  const inTransitOrders = orders?.data?.filter((o: ClientOrder) => ['dispatched', 'in_production', 'ready_for_delivery'].includes(o.status)) || []
  const activeTickets = tickets?.data?.filter((t: Ticket) => ['open', 'in_progress'].includes(t.status)) || []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dashboard"
        subtitle="Welcome to your client portal"
        icon={<LayoutDashboard className="h-5 w-5 text-neutral-700" />}
      />

      {/* Quick Actions */}
      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <QuickActionCard
          title="New Order"
          description="Place a new purchase order"
          icon={ShoppingBag}
          href="/client-portal/shop"
          color="blue"
        />
        <QuickActionCard
          title="View Orders"
          description="Track your order history"
          icon={Package}
          href="/client-portal/orders"
          color="neutral"
        />
        <QuickActionCard
          title="Support Ticket"
          description="Get help from our team"
          icon={TicketIcon}
          href="/client-portal/tickets/new"
          color="green"
        />
      </div>

      {/* Pending Deliveries Alert */}
      {deliveredOrders.length > 0 && (
        <div className="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
              <Package className="h-5 w-5 text-green-600" />
            </div>
            <div>
              <p className="font-medium text-green-900">
                {deliveredOrders.length} delivery{deliveredOrders.length !== 1 ? 's' : ''} awaiting your acknowledgment
              </p>
              <p className="text-sm text-green-700">Please confirm receipt and report any issues.</p>
            </div>
          </div>
          <Link
            to="/client-portal/orders?status=delivered"
            className="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
          >
            Review Deliveries
          </Link>
        </div>
      )}

      {/* In-Transit Orders Alert */}
      {inTransitOrders.length > 0 && (
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
              <Package className="h-5 w-5 text-blue-600" />
            </div>
            <div>
              <p className="font-medium text-blue-900">
                {inTransitOrders.length} order{inTransitOrders.length !== 1 ? 's' : ''} in progress
              </p>
              <p className="text-sm text-blue-700">Your orders are being manufactured or shipped.</p>
            </div>
          </div>
          <Link
            to="/client-portal/orders"
            className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            Track Orders
          </Link>
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Total Orders"
          value={orders?.meta?.total || 0}
          subtext="All time"
          icon={Package}
          href="/client-portal/orders"
        />
        <StatCard
          label="Pending Orders"
          value={pendingOrders.length}
          subtext="Awaiting response"
          icon={Clock}
          href="/client-portal/orders"
        />
        <StatCard
          label="Active Tickets"
          value={activeTickets.length}
          subtext="Open or in progress"
          icon={TicketIcon}
          href="/client-portal/tickets"
        />
        <StatCard
          label="Total Spent"
          value={`₱${((orders?.data?.filter((o: ClientOrder) => o.status === 'approved').reduce((sum: number, o: ClientOrder) => sum + o.total_amount_centavos, 0) || 0) / 100).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`}
          subtext="Approved orders only"
          icon={ShoppingBag}
          href="/client-portal/orders"
        />
      </div>

      {/* Recent Activity */}
      <div className="grid lg:grid-cols-2 gap-6">
        {/* Recent Orders */}
        <Card>
          <div className="px-5 py-4 border-b border-neutral-100 flex items-center justify-between">
            <h2 className="font-semibold text-neutral-900">Recent Orders</h2>
            <Link 
              to="/client-portal/orders"
              className="text-sm text-neutral-600 hover:text-neutral-900 flex items-center gap-1"
            >
              View all
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
          <div className="divide-y divide-neutral-100">
            {recentOrders.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <Package className="h-10 w-10 text-neutral-300 mx-auto mb-2" />
                <p className="text-sm text-neutral-500">No orders yet</p>
                <button
                  onClick={() => navigate('/client-portal/shop')}
                  className="mt-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                  Place your first order →
                </button>
              </div>
            ) : (
            recentOrders.map((order: ClientOrder) => (
              <RecentOrderRow key={order.ulid} order={order} />
            ))
            )}
          </div>
        </Card>

        {/* Recent Tickets */}
        <Card>
          <div className="px-5 py-4 border-b border-neutral-100 flex items-center justify-between">
            <h2 className="font-semibold text-neutral-900">Recent Tickets</h2>
            <Link 
              to="/client-portal/tickets"
              className="text-sm text-neutral-600 hover:text-neutral-900 flex items-center gap-1"
            >
              View all
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
          <div className="divide-y divide-neutral-100">
            {recentTickets.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <TicketIcon className="h-10 w-10 text-neutral-300 mx-auto mb-2" />
                <p className="text-sm text-neutral-500">No tickets yet</p>
                <Link
                  to="/client-portal/tickets/new"
                  className="mt-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                  Create a ticket →
                </Link>
              </div>
            ) : (
              recentTickets.map((ticket: Ticket) => (
                <RecentTicketRow key={ticket.id} ticket={ticket} />
              ))
            )}
          </div>
        </Card>
      </div>

      {/* Help Section */}
      <div className="bg-blue-50 border border-blue-100 rounded-xl p-5">
        <div className="flex items-start gap-4">
          <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
            <AlertCircle className="h-5 w-5 text-blue-600" />
          </div>
          <div className="flex-1">
            <h3 className="font-medium text-blue-900">Need Help?</h3>
            <p className="text-sm text-blue-700 mt-1">
              If you have questions about products, pricing, or your orders, our support team is here to help.
            </p>
            <div className="flex gap-3 mt-3">
              <Link
                to="/client-portal/tickets/new"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
              >
                <TicketIcon className="h-4 w-4" />
                Create Ticket
              </Link>
              <Link
                to="/client-portal/shop"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-blue-200 text-blue-700 text-sm font-medium rounded-lg hover:bg-blue-50 transition-colors"
              >
                <ShoppingBag className="h-4 w-4" />
                Browse Products
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
