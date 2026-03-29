import { firstErrorMessage } from '@/lib/errorHandler'
import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { 
  Search, 
  Package, 
  Plus, 
  Trash2, 
  Calendar, 
  FileText, 
  ShoppingBag,
  ChevronDown,
  Check,
  X,
  AlertCircle,
  Minus,
  ArrowRight,
  Tag
} from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { useAvailableProducts, useSubmitClientOrder } from '@/hooks/useClientOrders'
import type { ItemMaster } from '@/types/inventory'

interface OrderLineItem {
  item: ItemMaster
  quantity: number
  unitPrice: number
  notes: string
}

export default function ClientShopPage(): JSX.Element {
  const navigate = useNavigate()
  const [searchQuery, setSearchQuery] = useState('')
  const [showProductSelector, setShowProductSelector] = useState(false)
  const [orderItems, setOrderItems] = useState<OrderLineItem[]>([])
  const [requestedDate, setRequestedDate] = useState('')
  const [orderNotes, setOrderNotes] = useState('')
  
  const { data: products, isLoading: productsLoading } = useAvailableProducts(searchQuery)
  const submitOrder = useSubmitClientOrder()

  // Filter out already selected items from the selector
  const availableProducts = useMemo(() => {
    if (!products?.data) return []
    const selectedIds = new Set(orderItems.map(item => item.item.id))
    return products.data.filter((item: ItemMaster) => !selectedIds.has(item.id))
  }, [products, orderItems])

  const addItem = (item: ItemMaster) => {
    // Check if already in order - update quantity instead
    const existing = orderItems.find(line => line.item.id === item.id)
    if (existing) {
      setOrderItems(prev => prev.map(line => 
        line.item.id === item.id 
          ? { ...line, quantity: line.quantity + 1 }
          : line
      ))
      toast.success(`Added another ${item.name}`)
    } else {
      const unitPrice = item.standard_price_centavos || 0
      setOrderItems(prev => [...prev, {
        item,
        quantity: 1,
        unitPrice,
        notes: ''
      }])
      toast.success(`${item.name} added to order`)
    }
    setShowProductSelector(false)
    setSearchQuery('')
  }

  const removeItem = (itemId: number) => {
    setOrderItems(prev => prev.filter(line => line.item.id !== itemId))
  }

  const updateQuantity = (itemId: number, newQty: number) => {
    if (newQty < 1) return
    setOrderItems(prev => prev.map(line => 
      line.item.id === itemId 
        ? { ...line, quantity: newQty }
        : line
    ))
  }

  const updateItemNotes = (itemId: number, notes: string) => {
    setOrderItems(prev => prev.map(line => 
      line.item.id === itemId 
        ? { ...line, notes }
        : line
    ))
  }

  const orderTotal = orderItems.reduce((sum, line) => {
    return sum + (line.unitPrice * line.quantity)
  }, 0)

  const itemCount = orderItems.reduce((sum, line) => sum + line.quantity, 0)

  const handleSubmitOrder = async () => {
    if (orderItems.length === 0) {
      toast.error('Please add at least one item to your order')
      return
    }

    if (!requestedDate) {
      toast.error('Please select a requested delivery date')
      return
    }

    try {
      await submitOrder.mutateAsync({
        items: orderItems.map(line => ({
          item_master_id: line.item.id,
          quantity: line.quantity,
          unit_price_centavos: line.unitPrice,
          notes: line.notes,
        })),
        requested_delivery_date: requestedDate || undefined,
        notes: orderNotes,
      })
      
      toast.success('Order submitted successfully!')
      setOrderItems([])
      setRequestedDate('')
      setOrderNotes('')
      navigate('/client-portal/orders')
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to submit order. Please try again.'))
    }
  }

  const formatPrice = (centavos: number) => {
    return `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  }

  return (
    <div className="space-y-5 max-w-5xl mx-auto">
      <PageHeader
        title="New Order"
        subtitle="Create a purchase order from our product catalog"
        icon={<ShoppingBag className="h-5 w-5 text-neutral-700" />}
      />

      <div className="grid lg:grid-cols-3 gap-5">
        {/* Left Column - Order Form */}
        <div className="lg:col-span-2 space-y-5">
          {/* Add Items Section */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between w-full gap-4">
                <span className="flex items-center gap-2">
                  <Package className="h-4 w-4 text-neutral-500" />
                  <span>Order Items</span>
                </span>
                <span className="text-xs text-neutral-500 whitespace-nowrap">
                  {orderItems.length} product{orderItems.length !== 1 ? 's' : ''} • {itemCount} total qty
                </span>
              </div>
            </CardHeader>

            <CardBody className="space-y-4">
              {/* Product Selector */}
              <div className="relative">
                <button
                  onClick={() => setShowProductSelector(!showProductSelector)}
                  className={`w-full flex items-center justify-between px-4 py-3 rounded-xl border transition-all ${
                    showProductSelector 
                      ? 'bg-neutral-900 text-white border-neutral-900' 
                      : 'bg-white border-neutral-300 text-neutral-700 hover:border-neutral-400 hover:bg-neutral-50'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    <Plus className="h-5 w-5" />
                    <span className="font-medium">{showProductSelector ? 'Close Products' : 'Add Product'}</span>
                  </span>
                  <ChevronDown className={`h-5 w-5 transition-transform duration-200 ${showProductSelector ? 'rotate-180' : ''}`} />
                </button>

                {/* Product Selector Dropdown */}
                {showProductSelector && (
                  <div className="absolute z-30 top-full left-0 right-0 mt-2 bg-white border border-neutral-200 rounded-xl shadow-xl overflow-hidden max-h-80 flex flex-col">
                    <div className="p-3 border-b border-neutral-100">
                      <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
                        <input
                          type="text"
                          placeholder="Search products..."
                          value={searchQuery}
                          onChange={(e) => setSearchQuery(e.target.value)}
                          className="w-full pl-10 pr-4 py-2 border border-neutral-200 rounded-lg focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
                          autoFocus
                        />
                      </div>
                    </div>
                    <div className="overflow-y-auto flex-1" style={{ maxHeight: '240px' }}>
                      {productsLoading ? (
                        <div className="p-4 text-center text-neutral-500 text-sm">
                          Loading products...
                        </div>
                      ) : availableProducts.length === 0 ? (
                        <div className="p-4 text-center text-neutral-500 text-sm">
                          {searchQuery ? 'No products found' : 'All products have been added to your order'}
                        </div>
                      ) : (
                        availableProducts.map((item: ItemMaster) => (
                          <button
                            key={item.id}
                            onClick={() => addItem(item)}
                            className="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-50 border-b border-neutral-100 last:border-b-0 text-left transition-colors group"
                          >
                            <div className="flex-1 min-w-0">
                              <p className="font-medium text-neutral-900 text-sm">{item.name}</p>
                              <p className="text-xs text-neutral-500">{item.item_code} • {item.unit_of_measure}</p>
                            </div>
                            <div className="text-right ml-4">
                              {item.standard_price_centavos ? (
                                <p className="font-semibold text-neutral-900 text-sm group-hover:text-blue-700">
                                  {formatPrice(item.standard_price_centavos)}
                                </p>
                              ) : (
                                <p className="text-xs text-neutral-400">Price on request</p>
                              )}
                            </div>
                          </button>
                        ))
                      )}
                    </div>
                    <div className="p-2 border-t border-neutral-100 bg-neutral-50 shrink-0">
                      <button
                        onClick={() => setShowProductSelector(false)}
                        className="w-full py-2 text-xs text-neutral-600 hover:text-neutral-900 font-medium"
                      >
                        Close
                      </button>
                    </div>
                  </div>
                )}
              </div>

              {/* Order Items Table */}
              {orderItems.length === 0 ? (
                <div className="text-center py-10 border-2 border-dashed border-neutral-200 rounded-xl bg-neutral-50/30">
                  <div className="w-14 h-14 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <ShoppingBag className="h-7 w-7 text-neutral-400" />
                  </div>
                  <p className="text-neutral-700 font-medium">No items added yet</p>
                  <p className="text-sm text-neutral-500 mt-1">Click the button above to add products</p>
                </div>
              ) : (
                <div className="border border-neutral-200 rounded-xl overflow-hidden">
                  <table className="w-full text-sm">
                    <thead className="bg-neutral-50">
                      <tr className="text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                        <th className="px-4 py-3">Product</th>
                        <th className="px-4 py-3 w-24 text-center">Qty</th>
                        <th className="px-4 py-3 w-28 text-right">Unit Price</th>
                        <th className="px-4 py-3 w-28 text-right">Total</th>
                        <th className="px-4 py-3 w-10"></th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100">
                      {orderItems.map((line) => (
                        <tr key={line.item.id} className="group">
                          <td className="px-4 py-3">
                            <div>
                              <p className="font-medium text-neutral-900">{line.item.name}</p>
                              <p className="text-xs text-neutral-500">{line.item.item_code}</p>
                              <input
                                type="text"
                                placeholder="Add notes (optional)"
                                value={line.notes}
                                onChange={(e) => updateItemNotes(line.item.id, e.target.value)}
                                className="mt-2 w-full text-xs border-0 border-b border-dashed border-neutral-300 focus:border-neutral-500 focus:ring-0 px-0 py-1 bg-transparent placeholder:text-neutral-400"
                              />
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex items-center justify-center gap-1">
                              <button
                                onClick={() => updateQuantity(line.item.id, line.quantity - 1)}
                                className="w-7 h-7 flex items-center justify-center border border-neutral-200 rounded hover:bg-neutral-100 text-neutral-600 transition-colors"
                              >
                                <Minus className="h-3 w-3" />
                              </button>
                              <input
                                type="number"
                                min="1"
                                value={line.quantity}
                                onChange={(e) => updateQuantity(line.item.id, parseInt(e.target.value) || 1)}
                                className="w-12 text-center border border-neutral-200 rounded py-1 text-sm focus:border-neutral-400 focus:ring-1 focus:ring-neutral-100"
                              />
                              <button
                                onClick={() => updateQuantity(line.item.id, line.quantity + 1)}
                                className="w-7 h-7 flex items-center justify-center border border-neutral-200 rounded hover:bg-neutral-100 text-neutral-600 transition-colors"
                              >
                                <Plus className="h-3 w-3" />
                              </button>
                            </div>
                            <span className="text-xs text-neutral-400 text-center block mt-1">{line.item.unit_of_measure}</span>
                          </td>
                          <td className="px-4 py-3 text-right">
                            <p className="font-medium text-neutral-900">
                              {formatPrice(line.unitPrice)}
                            </p>
                          </td>
                          <td className="px-4 py-3 text-right">
                            <p className="font-semibold text-neutral-900">
                              {formatPrice(line.unitPrice * line.quantity)}
                            </p>
                          </td>
                          <td className="px-4 py-3">
                            <button
                              onClick={() => removeItem(line.item.id)}
                              className="p-2 text-neutral-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                              title="Remove item"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Delivery & Notes Section */}
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <FileText className="h-4 w-4 text-neutral-500" />
                Additional Information
              </span>
            </CardHeader>
            <CardBody className="space-y-4">
              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                    Requested Delivery Date
                  </label>
                  <div className="relative">
                    <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
                    <input
                      type="date"
                      value={requestedDate}
                      onChange={(e) => setRequestedDate(e.target.value)}
                      min={new Date().toISOString().split('T')[0]}
                      className="w-full pl-10 pr-4 py-2 border border-neutral-200 rounded-lg focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
                    />
                  </div>
                  <p className="text-xs text-neutral-500 mt-1">
                    Required - when do you need this order delivered?
                  </p>
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                  Order Notes
                </label>
                <textarea
                  value={orderNotes}
                  onChange={(e) => setOrderNotes(e.target.value)}
                  placeholder="Any special requirements, delivery instructions, or additional information..."
                  rows={3}
                  className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none resize-none text-sm"
                />
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Right Column - Order Summary */}
        <div className="space-y-5">
          <Card className="sticky top-20">
            <CardHeader>
              <span className="flex items-center gap-2">
                <Tag className="h-4 w-4 text-neutral-500" />
                Order Summary
              </span>
            </CardHeader>
            <CardBody className="space-y-4">
              {/* Summary Stats */}
              <div className="grid grid-cols-2 gap-3">
                <div className="bg-neutral-50 rounded-lg p-3 text-center border border-neutral-100">
                  <p className="text-xl font-semibold text-neutral-900">{orderItems.length}</p>
                  <p className="text-xs text-neutral-500 uppercase tracking-wide">Products</p>
                </div>
                <div className="bg-neutral-50 rounded-lg p-3 text-center border border-neutral-100">
                  <p className="text-xl font-semibold text-neutral-900">{itemCount}</p>
                  <p className="text-xs text-neutral-500 uppercase tracking-wide">Total Qty</p>
                </div>
              </div>

              {/* Order Total */}
              <div className="border-t border-neutral-100 pt-4">
                <div className="flex justify-between items-center">
                  <span className="text-sm text-neutral-600">Order Total</span>
                  <span className="text-xl font-bold text-neutral-900">{formatPrice(orderTotal)}</span>
                </div>
              </div>

              {/* Validation Alert */}
              {orderItems.length === 0 && (
                <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                  <AlertCircle className="h-4 w-4 text-amber-600 mt-0.5 shrink-0" />
                  <p className="text-xs text-amber-800">
                    Add at least one product to submit your order
                  </p>
                </div>
              )}

              <button
                onClick={handleSubmitOrder}
                disabled={submitOrder.isPending || orderItems.length === 0}
                className="w-full py-2.5 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2 text-sm"
              >
                {submitOrder.isPending ? (
                  <>
                    <span className="animate-spin">⟳</span>
                    Submitting...
                  </>
                ) : (
                  <>
                    <Check className="h-4 w-4" />
                    Submit Order
                  </>
                )}
              </button>

              <button
                onClick={() => navigate('/client-portal/orders')}
                className="w-full py-2 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors flex items-center justify-center gap-2 text-sm"
              >
                <X className="h-4 w-4" />
                Cancel
              </button>

              <p className="text-xs text-neutral-400 text-center">
                Your order will be reviewed by our sales team before confirmation
              </p>
            </CardBody>
          </Card>

          {/* Help Card */}
          <div className="bg-blue-50 rounded-xl border border-blue-100 p-4">
            <h3 className="font-medium text-blue-900 mb-1 text-sm">Need Help?</h3>
            <p className="text-xs text-blue-700 mb-3">
              If you have questions about products or pricing, our support team is here to help.
            </p>
            <button
              onClick={() => navigate('/client-portal/tickets/new')}
              className="text-xs text-blue-700 font-medium hover:text-blue-900 underline flex items-center gap-1"
            >
              Create a support ticket
              <ArrowRight className="h-3 w-3" />
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
