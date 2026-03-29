import { firstErrorMessage } from '@/lib/errorHandler'
import { useState, useMemo } from 'react'
import { useDebounce } from '@/hooks/useDebounce'
import { useNavigate } from 'react-router-dom'
import { 
  Package, 
  Clock, 
  CheckCircle, 
  XCircle, 
  MessageCircle, 
  ChevronRight,
  ShoppingBag,
  Search,
  Calendar,
  Trash2,
  AlertTriangle
} from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import { useMyClientOrders, useCancelClientOrder } from '@/hooks/useClientOrders'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { ClientOrder } from '@/types/client-order'

const STATUS_COLORS: Record<string, string> = {
  active: 'bg-green-100 text-green-700',
  pending: 'bg-amber-100 text-amber-700',
  negotiating: 'bg-blue-100 text-blue-700',
  client_responded: 'bg-purple-100 text-purple-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700',
  cancelled: 'bg-neutral-100 text-neutral-500',
}

const STATUS_LABELS: Record<string, string> = {
  active: 'Active',
  pending: 'Pending',
  negotiating: 'Negotiating',
  client_responded: 'Awaiting Sales',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

interface CancelModalProps {
  order: ClientOrder | null
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  isLoading: boolean
}

function CancelConfirmationModal({ order, isOpen, onClose, onConfirm, isLoading }: CancelModalProps) {
  if (!isOpen || !order) return null

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
        <div className="p-6">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
              <AlertTriangle className="h-6 w-6 text-red-600" />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-neutral-900">Cancel Order?</h3>
              <p className="text-sm text-neutral-500 mt-1">
                Are you sure you want to cancel order <span className="font-medium text-neutral-700">{order.order_reference}</span>?
              </p>
            </div>
          </div>

          <div className="mt-4 p-4 bg-neutral-50 rounded-lg border border-neutral-100">
            <div className="flex justify-between text-sm">
              <span className="text-neutral-500">Order Total:</span>
              <span className="font-semibold text-neutral-900">
                ₱{(order.total_amount_centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
              </span>
            </div>
            <div className="flex justify-between text-sm mt-2">
              <span className="text-neutral-500">Items:</span>
              <span className="font-medium text-neutral-900">{order.items.length}</span>
            </div>
          </div>

          <div className="mt-2 text-sm text-neutral-500">
            <p>This action cannot be undone. The order will be permanently cancelled.</p>
          </div>
        </div>

        <div className="p-4 border-t border-neutral-100 flex gap-3">
          <button
            onClick={onClose}
            disabled={isLoading}
            className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
          >
            Keep Order
          </button>
          <button
            onClick={onConfirm}
            disabled={isLoading}
            className="flex-1 py-2.5 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm flex items-center justify-center gap-2"
          >
            {isLoading ? (
              <>
                <span className="animate-spin">⟳</span>
                Cancelling...
              </>
            ) : (
              <>
                <Trash2 className="h-4 w-4" />
                Yes, Cancel Order
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  )
}

function formatCurrency(centavos: number) {
  return '₱' + (centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

export default function ClientOrdersPage(): JSX.Element {
  const navigate = useNavigate()
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [searchQuery, setSearchQuery] = useState('')
  const [orderToCancel, setOrderToCancel] = useState<ClientOrder | null>(null)
  const { data: orders, isLoading } = useMyClientOrders()
  const cancelMutation = useCancelClientOrder()

  // Debounce search query with 400ms delay
  const debouncedSearchQuery = useDebounce(searchQuery, 400)

  const filteredOrders = useMemo(() => {
    return orders?.data?.filter((order: ClientOrder) => {
      // Handle 'active' filter (pending + negotiating + client_responded)
      if (statusFilter === 'active') {
        if (!['pending', 'negotiating', 'client_responded'].includes(order.status)) return false
      } else if (statusFilter && order.status !== statusFilter) {
        return false
      }
      
      if (debouncedSearchQuery) {
        const query = debouncedSearchQuery.toLowerCase()
        const matchesRef = order.order_reference.toLowerCase().includes(query)
        const matchesItem = order.items.some(item => 
          item.item_description.toLowerCase().includes(query)
        )
        if (!matchesRef && !matchesItem) return false
      }
      
      return true
    }) || []
  }, [orders?.data, statusFilter, debouncedSearchQuery])

  const handleCancelConfirm = async () => {
    if (!orderToCancel) return
    
    try {
      await cancelMutation.mutateAsync(orderToCancel.id)
      toast.success('Order cancelled successfully')
      setOrderToCancel(null)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to cancel order'))
    }
  }

  return (
    <div className="space-y-5">
      <PageHeader
        title="My Orders"
        subtitle="Track and manage your purchase orders"
        icon={<Package className="h-5 w-5 text-neutral-700" />}
        actions={
          <button
            onClick={() => navigate('/client-portal/shop')}
            className="inline-flex items-center gap-2 px-4 py-2 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
          >
            <ShoppingBag className="h-4 w-4" />
            New Order
          </button>
        }
      />

      {/* Cancel Confirmation Modal */}
      <CancelConfirmationModal
        order={orderToCancel}
        isOpen={!!orderToCancel}
        onClose={() => setOrderToCancel(null)}
        onConfirm={handleCancelConfirm}
        isLoading={cancelMutation.isPending}
      />

      {/* Filters */}
      <Card className="p-4">
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
            <input
              type="text"
              placeholder="Search by order number or product..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-neutral-200 rounded-lg focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
            />
          </div>
          
      <div className="flex items-center gap-2">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="border border-neutral-200 rounded-lg px-4 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none bg-white text-sm"
        >
          <option value="active">Active Orders</option>
          <option value="">All Statuses</option>
          <option value="pending">Pending Review</option>
          <option value="negotiating">Under Negotiation</option>
          <option value="client_responded">Awaiting Sales</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
        </div>
      </Card>

      {/* Orders Table */}
      {isLoading ? (
        <SkeletonLoader rows={5} />
      ) : filteredOrders.length === 0 ? (
        <div className="text-center py-16">
          <div className="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Package className="h-8 w-8 text-neutral-400" />
          </div>
          <h3 className="text-base font-medium text-neutral-900 mb-1">
            {searchQuery || statusFilter ? 'No orders found' : 'No orders yet'}
          </h3>
          <p className="text-sm text-neutral-500 mb-6 max-w-sm mx-auto">
            {searchQuery || statusFilter 
              ? 'Try adjusting your search or filter criteria'
              : "You haven't placed any orders yet. Start by browsing our product catalog."}
          </p>
          {!searchQuery && !statusFilter && (
            <button
              onClick={() => navigate('/client-portal/shop')}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
            >
              <ShoppingBag className="h-4 w-4" />
              Browse Products
            </button>
          )}
        </div>
      ) : (
        <div className="bg-white rounded border border-neutral-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-3 font-medium text-neutral-600">Order</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600">Date</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600">Items</th>
                <th className="text-right px-4 py-3 font-medium text-neutral-600">Total</th>
                <th className="text-left px-4 py-3 font-medium text-neutral-600">Status</th>
                <th className="text-right px-4 py-3 font-medium text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredOrders.map((order: ClientOrder, index: number) => (
                <tr
                  key={order.ulid}
                  onClick={() => navigate(`/client-portal/orders/${order.ulid}`)}
                  className={`hover:bg-neutral-50 transition-colors cursor-pointer ${
                    index % 2 === 0 ? 'bg-white' : 'bg-neutral-50'
                  }`}
                >
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-neutral-100 rounded-lg flex items-center justify-center">
                        <Package className="h-4 w-4 text-neutral-500" />
                      </div>
                      <span className="font-medium text-neutral-900">{order.order_reference}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    <div className="flex items-center gap-1.5">
                      <Calendar className="h-3.5 w-3.5 text-neutral-400" />
                      {new Date(order.created_at).toLocaleDateString('en-PH', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric' 
                      })}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {order.items.length} {order.items.length === 1 ? 'item' : 'items'}
                  </td>
                  <td className="px-4 py-3 text-right font-mono font-medium text-neutral-900">
                    {formatCurrency(order.total_amount_centavos)}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-medium ${STATUS_COLORS[order.status]}`}>
                      {order.status === 'pending' && <Clock className="h-3 w-3" />}
                      {order.status === 'negotiating' && <MessageCircle className="h-3 w-3" />}
                      {order.status === 'approved' && <CheckCircle className="h-3 w-3" />}
                      {order.status === 'rejected' && <XCircle className="h-3 w-3" />}
                      {order.status === 'cancelled' && <XCircle className="h-3 w-3" />}
                      {STATUS_LABELS[order.status]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {order.status === 'pending' && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            setOrderToCancel(order)
                          }}
                          disabled={cancelMutation.isPending}
                          className="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors"
                          title="Cancel order"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      )}
                      <ChevronRight className="h-4 w-4 text-neutral-400" />
                    </div>
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
