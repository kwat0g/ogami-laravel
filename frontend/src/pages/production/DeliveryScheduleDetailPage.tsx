import { firstErrorMessage } from '@/lib/errorHandler'
import { useState, useEffect } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Package, Truck, Factory, FileText, AlertTriangle, Plus } from 'lucide-react'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { useDeliverySchedule, useCreateProductionOrder, useBoms, useFulfillFromStock } from '@/hooks/useProduction'
import { useCreateDeliveryReceipt } from '@/hooks/useDelivery'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ChainRecordTimeline from '@/components/ui/ChainRecordTimeline'
import { toast } from 'sonner'
import type { CreateDeliveryReceiptPayload } from '@/types/delivery'

interface CreateWOModalProps {
  isOpen: boolean
  onClose: () => void
  schedule: {
    id: number
    ulid: string
    ds_reference: string
    product_item_id: number
    qty_ordered: string
    target_delivery_date: string
  } | null
}

function CreateWOModal({ isOpen, onClose, schedule }: CreateWOModalProps): JSX.Element | null {
  const navigate = useNavigate()
  const [targetStartDate, setTargetStartDate] = useState('')
  const [targetEndDate, setTargetEndDate] = useState('')
  const [selectedBomId, setSelectedBomId] = useState<number | ''>('')

  // Always fetch BOMs if we have a schedule, modal visibility controls rendering not hook calls
  const { data: bomsData } = useBoms({ 
    product_item_id: schedule?.product_item_id 
  })
  const createWOMutation = useCreateProductionOrder()

  const boms = bomsData?.data || []
  const activeBoms = boms.filter(b => b.is_active)

  // Auto-select the first active BOM when data loads - only when modal is open
  useEffect(() => {
    if (!isOpen || !schedule) return
    if (activeBoms.length === 1) {
      setSelectedBomId(activeBoms[0].id)
    } else if (activeBoms.length > 0 && !selectedBomId) {
      // If multiple active BOMs, select the latest version
      const sortedBoms = [...activeBoms].sort((a, b) => b.version.localeCompare(a.version))
      setSelectedBomId(sortedBoms[0].id)
    }
  }, [activeBoms, isOpen, schedule])

  // Don't render anything if modal is closed - but only after all hooks
  if (!isOpen || !schedule) {
    return null
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!selectedBomId) {
      toast.error('Please select a BOM')
      return
    }

    if (!targetStartDate || !targetEndDate) {
      toast.error('Please set both start and end dates')
      return
    }

    if (new Date(targetEndDate) < new Date(targetStartDate)) {
      toast.error('End date must be after start date')
      return
    }

    try {
      const newOrder = await createWOMutation.mutateAsync({
        product_item_id: schedule.product_item_id,
        bom_id: Number(selectedBomId),
        delivery_schedule_id: schedule.id,
        qty_required: parseFloat(schedule.qty_ordered),
        target_start_date: targetStartDate,
        target_end_date: targetEndDate,
        notes: `Created from Delivery Schedule ${schedule.ds_reference}`,
      })

      toast.success('Production Order created successfully')
      navigate(`/production/orders/${newOrder.ulid}`)
    } catch (_error) {
      toast.error(firstErrorMessage(_error, 'Failed to create Production Order'))
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
        <div className="p-5 border-b border-neutral-100">
          <h2 className="text-base font-semibold text-neutral-900">Create Production Order</h2>
          <p className="text-sm text-neutral-500 mt-0.5">Create WO for {schedule.ds_reference}</p>
        </div>

        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          {/* BOM Selection */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1.5">
              Bill of Materials
            </label>
            <select
              value={selectedBomId}
              onChange={(e) => setSelectedBomId(e.target.value ? Number(e.target.value) : '')}
              className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
              required
            >
              <option value="">Select BOM</option>
              {activeBoms.map((bom) => (
                <option key={bom.id} value={bom.id}>
                  Version {bom.version} ({bom.components?.length || 0} components)
                </option>
              ))}
            </select>
            {activeBoms.length === 0 && (
              <p className="text-xs text-amber-600 mt-1">No active BOM found for this product</p>
            )}
          </div>

          {/* Quantity */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1.5">
              Quantity Required
            </label>
            <input
              type="text"
              value={parseFloat(schedule.qty_ordered).toLocaleString('en-PH')}
              disabled
              className="w-full border border-neutral-200 bg-neutral-50 rounded-lg px-3 py-2 text-sm text-neutral-500"
            />
            <p className="text-xs text-neutral-400 mt-1">From delivery schedule</p>
          </div>

          {/* Target Dates */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                Target Start Date
              </label>
              <input
                type="date"
                value={targetStartDate}
                onChange={(e) => setTargetStartDate(e.target.value)}
                min={new Date().toISOString().split('T')[0]}
                max={schedule.target_delivery_date}
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1.5">
                Target End Date
              </label>
              <input
                type="date"
                value={targetEndDate}
                onChange={(e) => setTargetEndDate(e.target.value)}
                min={targetStartDate || new Date().toISOString().split('T')[0]}
                max={schedule.target_delivery_date}
                className="w-full border border-neutral-200 rounded-lg px-3 py-2 focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100 outline-none text-sm"
                required
              />
            </div>
          </div>

          <div className="p-3 bg-amber-50 border border-amber-100 rounded-lg">
            <p className="text-xs text-amber-700">
              <strong>Delivery Due:</strong> {new Date(schedule.target_delivery_date).toLocaleDateString('en-PH')}
            </p>
          </div>

          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createWOMutation.isPending || !selectedBomId}
              className="flex-1 py-2.5 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
            >
              {createWOMutation.isPending ? 'Creating...' : 'Create WO'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ── Create Delivery Receipt Modal ────────────────────────────────────────────
function CreateDRModal({
  isOpen,
  onClose,
  schedule,
  onCreated,
}: {
  isOpen: boolean
  onClose: () => void
  schedule: {
    id: number
    ulid: string
    ds_reference: string
    customer?: { id: number; name: string } | null
    product_item?: { id: number; name: string; item_code?: string; unit_of_measure?: string } | null
    product_item_id?: number | null
    qty_ordered?: string | null
    items?: { product_item_id: number; product_item?: { id: number; name: string; unit_of_measure?: string } | null; qty_ordered: string }[]
    client_order?: { id: number; order_reference: string } | null
  }
  onCreated: (drUlid: string) => void
}): JSX.Element | null {
  const createDR = useCreateDeliveryReceipt()
  const [receiptDate, setReceiptDate] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  })
  const [remarks, setRemarks] = useState('')

  if (!isOpen) return null

  // Build items from either multi-item schedule or single-item legacy
  const dsItems = schedule.items ?? []
  const previewItems = dsItems.length > 0
    ? dsItems.map((item) => ({
        name: item.product_item?.name ?? `Item #${item.product_item_id}`,
        qty: parseFloat(item.qty_ordered ?? '0'),
        uom: item.product_item?.unit_of_measure ?? 'pcs',
        item_master_id: item.product_item?.id ?? item.product_item_id,
      }))
    : schedule.product_item
      ? [{
          name: schedule.product_item.name,
          qty: parseFloat(schedule.qty_ordered ?? '0'),
          uom: schedule.product_item.unit_of_measure ?? 'pcs',
          item_master_id: schedule.product_item.id,
        }]
      : []

  const handleSubmit = async () => {
    const payload: CreateDeliveryReceiptPayload = {
      direction: 'outbound',
      receipt_date: receiptDate,
      remarks: remarks || undefined,
      customer_id: schedule.customer?.id ?? undefined,
      delivery_schedule_id: schedule.id,
      items: previewItems.map((item) => ({
        item_master_id: item.item_master_id,
        quantity_expected: item.qty,
        quantity_received: item.qty,
        unit_of_measure: item.uom,
      })),
    }

    try {
      const result = await createDR.mutateAsync(payload)
      toast.success('Delivery receipt created successfully.')
      onClose()
      const drUlid = result?.data?.ulid ?? result?.ulid
      if (drUlid) onCreated(drUlid)
    } catch (err) {
      toast.error(firstErrorMessage(err) ?? 'Failed to create delivery receipt.')
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl w-full max-w-lg shadow-xl border border-neutral-200 max-h-[90vh] overflow-y-auto">
        <div className="p-5 border-b border-neutral-100">
          <h2 className="text-base font-semibold text-neutral-900 flex items-center gap-2">
            <Truck className="w-4 h-4 text-blue-600" />
            Create Delivery Receipt
          </h2>
          <p className="text-sm text-neutral-500 mt-0.5">
            From {schedule.ds_reference}
            {schedule.customer && <> &middot; {schedule.customer.name}</>}
          </p>
        </div>

        <div className="p-5 space-y-4">
          {/* Client Order reference */}
          {schedule.client_order && (
            <div className="text-xs text-neutral-500 bg-neutral-50 rounded px-3 py-2">
              Client Order: <span className="font-medium text-neutral-700">{schedule.client_order.order_reference}</span>
            </div>
          )}

          {/* Items preview (read-only) */}
          <div>
            <p className="text-xs font-medium text-neutral-500 uppercase tracking-wide mb-2">Items to Deliver</p>
            <div className="border border-neutral-200 rounded overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-neutral-50 text-xs text-neutral-600">
                  <tr>
                    <th className="px-3 py-2 text-left">Item</th>
                    <th className="px-3 py-2 text-right">Qty</th>
                    <th className="px-3 py-2 text-left">UOM</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {previewItems.map((item, idx) => (
                    <tr key={idx}>
                      <td className="px-3 py-2 font-medium text-neutral-900">{item.name}</td>
                      <td className="px-3 py-2 text-right text-neutral-700">
                        {item.qty.toLocaleString('en-PH', { maximumFractionDigits: 2 })}
                      </td>
                      <td className="px-3 py-2 text-neutral-500">{item.uom}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Receipt date */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Receipt Date <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              value={receiptDate}
              onChange={(e) => setReceiptDate(e.target.value)}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
            />
          </div>

          {/* Remarks */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Remarks <span className="text-neutral-400 font-normal">(optional)</span>
            </label>
            <textarea
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              rows={2}
              className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
              placeholder="Optional delivery notes..."
            />
          </div>
        </div>

        <div className="flex gap-2 justify-end p-5 border-t border-neutral-100">
          <button
            type="button"
            onClick={onClose}
            disabled={createDR.isPending}
            className="px-4 py-2.5 text-sm border border-neutral-300 rounded-lg hover:bg-neutral-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleSubmit}
            disabled={createDR.isPending || previewItems.length === 0}
            className="px-5 py-2.5 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm"
          >
            {createDR.isPending ? 'Creating...' : 'Create Delivery Receipt'}
          </button>
        </div>
      </div>
    </div>
  )
}

const STATUS_COLORS: Record<string, string> = {
  open: 'bg-neutral-100 text-neutral-700',
  in_production: 'bg-blue-100 text-blue-700',
  ready: 'bg-green-100 text-green-700',
  dispatched: 'bg-purple-100 text-purple-700',
  delivered: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-neutral-100 text-neutral-400',
}

export default function DeliveryScheduleDetailPage(): JSX.Element {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { hasPermission } = useAuthStore()
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showCreateDRModal, setShowCreateDRModal] = useState(false)
  const [showFulfillConfirm, setShowFulfillConfirm] = useState(false)

  const { data: schedule, isLoading, isError } = useDeliverySchedule(ulid || null)
  const fulfillMutation = useFulfillFromStock(ulid || '')

  const canCreateWO = hasPermission('production.orders.create')
  const canManage = hasPermission('production.delivery-schedule.manage')
  const canFulfill = hasPermission('production.delivery-schedule.manage')

  const handleFulfillFromStock = async () => {
    try {
      await fulfillMutation.mutateAsync()
      toast.success('Order fulfilled from stock successfully')
      setShowFulfillConfirm(false)
    } catch (error: unknown) {
      // Extract specific error message from API response
      const message = error?.response?.data?.message || error?.message || 'Failed to fulfill from stock'
      toast.error(message)
    }
  }

  if (isLoading) {
    return <SkeletonLoader rows={5} />
  }

  if (isError || !schedule) {
    return (
      <div className="text-center py-16">
        <AlertTriangle className="w-12 h-12 text-red-400 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-neutral-900 mb-2">Delivery Schedule not found</h3>
        <p className="text-sm text-neutral-500 mb-6">The schedule you&apos;re looking for doesn&apos;t exist or you don&apos;t have access.</p>
        <button
          onClick={() => navigate('/production/delivery-schedules')}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded-lg hover:bg-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Schedules
        </button>
      </div>
    )
  }

  const status = schedule.status as keyof typeof STATUS_COLORS

  return (
    <div className="space-y-5 max-w-6xl mx-auto">
      {/* Back Button */}
      <button
        onClick={() => navigate('/production/delivery-schedules')}
        className="inline-flex items-center gap-2 text-sm text-neutral-600 hover:text-neutral-900 font-medium"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to Schedules
      </button>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h1 className="text-lg font-semibold text-neutral-900">{schedule.ds_reference}</h1>
          <p className="text-sm text-neutral-500 mt-1">
            Delivery Schedule Details
          </p>
        </div>
        <div className="flex items-center gap-2">
          {canManage && schedule.status === 'ready' && (
            (schedule.delivery_receipts ?? []).length > 0 ? (
              <Link
                to={`/delivery/receipts/${schedule.delivery_receipts?.[0]?.ulid}`}
                className="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Truck className="w-4 h-4" />
                Open Delivery Receipt
              </Link>
            ) : (
              <button
                onClick={() => setShowCreateDRModal(true)}
                className="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
                title="Create a delivery receipt for this schedule"
              >
                <Truck className="w-4 h-4" />
                Create Delivery Receipt
              </button>
            )
          )}

          {/* Show Fulfill from Stock button if: status is open AND no production orders exist AND user has permission */}
          {canFulfill && schedule.status === 'open' && (!schedule.production_orders || schedule.production_orders.length === 0) && (
            <button
              onClick={() => setShowFulfillConfirm(true)}
              className="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
            >
              <Package className="w-4 h-4" />
              Fulfill from Stock
            </button>
          )}
          {canCreateWO && schedule.status === 'open' && (
            <button
              onClick={() => setShowCreateModal(true)}
              className="inline-flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
            >
              <Plus className="w-4 h-4" />
              Create WO
            </button>
          )}
        </div>
      </div>

      {/* Status Banner */}
      <div className={`px-4 py-3 rounded-lg border ${STATUS_COLORS[status] || 'bg-neutral-100'}`}>
        <div className="flex items-center gap-2">
          <Truck className="h-4 w-4" />
          <span className="font-medium capitalize">{schedule.status?.replace('_', ' ')}</span>
          {schedule.type && (
            <span className="ml-2 text-xs uppercase tracking-wide opacity-70">({schedule.type})</span>
          )}
        </div>
      </div>

      <div className="grid lg:grid-cols-3 gap-5">
        {/* Left Column - Main Info */}
        <div className="lg:col-span-2 space-y-5">
          {/* Customer & Product Card */}
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <Package className="h-4 w-4 text-neutral-500" />
                Order Information
              </span>
            </CardHeader>
            <CardBody className="space-y-4">
              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Customer</p>
                  <p className="font-medium text-neutral-900">{schedule.customer?.name || '—'}</p>
                  {schedule.customer?.email && (
                    <p className="text-sm text-neutral-500">{schedule.customer.email}</p>
                  )}
                </div>
                <div>
                  <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Product</p>
                  <p className="font-medium text-neutral-900">{schedule.product_item?.name || '—'}</p>
                  <p className="text-xs text-neutral-400 font-mono">{schedule.product_item?.item_code}</p>
                </div>
              </div>

              <div className="border-t border-neutral-100 pt-4">
                <div className="grid sm:grid-cols-3 gap-4">
                  <div>
                    <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Quantity Ordered</p>
                    <p className="text-lg font-semibold text-neutral-900">
                      {parseFloat(schedule.qty_ordered).toLocaleString('en-PH', { maximumFractionDigits: 2 })}
                    </p>
                    <p className="text-xs text-neutral-400">{schedule.product_item?.unit_of_measure}</p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Target Delivery</p>
                    <p className="font-medium text-neutral-900">
                      {new Date(schedule.target_delivery_date).toLocaleDateString('en-PH', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                      })}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Days Until Due</p>
                    <p className={`font-medium ${
                      Math.ceil((new Date(schedule.target_delivery_date).getTime() - Date.now()) / (1000 * 60 * 60 * 24)) < 3
                        ? 'text-red-600'
                        : 'text-neutral-900'
                    }`}>
                      {Math.ceil((new Date(schedule.target_delivery_date).getTime() - Date.now()) / (1000 * 60 * 60 * 24))} days
                    </p>
                  </div>
                </div>
              </div>

              {schedule.notes && (
                <div className="border-t border-neutral-100 pt-4">
                  <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Notes</p>
                  <p className="text-sm text-neutral-700 bg-neutral-50 p-3 rounded-lg">{schedule.notes}</p>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Items & Production Orders */}
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <Package className="h-4 w-4 text-neutral-500" />
                Order Items ({schedule.total_items || schedule.items?.length || 0})
              </span>
            </CardHeader>
            <CardBody>
              {(schedule.items ?? []).length > 0 ? (
                <div className="divide-y divide-neutral-100">
                  {schedule.items?.map((item) => (
                    <div key={item.id} className="py-4 first:pt-0 last:pb-0">
                      <div className="flex items-center justify-between mb-2">
                        <div>
                          <span className="font-mono text-xs text-neutral-400">{item.product_item?.item_code}</span>
                          <p className="font-medium text-neutral-900">{item.product_item?.name}</p>
                        </div>
                        <div className="text-right">
                          <span className="text-sm font-semibold tabular-nums">
                            {parseFloat(item.qty_ordered).toLocaleString('en-PH')} {item.product_item?.unit_of_measure ?? 'pcs'}
                          </span>
                          <div>
                            <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                              item.status === 'ready' ? 'bg-green-100 text-green-700' :
                              item.status === 'in_production' ? 'bg-blue-100 text-blue-700' :
                              item.status === 'delivered' ? 'bg-emerald-100 text-emerald-700' :
                              item.status === 'cancelled' ? 'bg-neutral-100 text-neutral-400' :
                              'bg-neutral-100 text-neutral-600'
                            }`}>
                              {item.status?.replace('_', ' ')}
                            </span>
                          </div>
                        </div>
                      </div>
                      {/* Per-item production orders */}
                      {(item.production_orders ?? []).length > 0 && (
                        <div className="ml-4 border-l-2 border-neutral-200 pl-3">
                          {item.production_orders?.map((po) => (
                            <Link
                              key={po.id}
                              to={`/production/orders/${po.ulid}`}
                              className="flex items-center justify-between py-1.5 hover:bg-neutral-50 rounded px-2 -mx-2"
                            >
                              <span className="font-mono text-sm text-neutral-700">{po.po_reference}</span>
                              <div className="flex items-center gap-3">
                                <span className="text-xs text-neutral-500">
                                  {parseFloat(po.qty_produced).toLocaleString('en-PH')} / {parseFloat(po.qty_required).toLocaleString('en-PH')}
                                </span>
                                <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-medium capitalize ${
                                  po.status === 'completed' ? 'bg-green-100 text-green-700' :
                                  po.status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                                  po.status === 'cancelled' ? 'bg-neutral-100 text-neutral-500' :
                                  'bg-neutral-100 text-neutral-700'
                                }`}>
                                  {po.status?.replace('_', ' ')}
                                </span>
                              </div>
                            </Link>
                          ))}
                        </div>
                      )}
                      {(item.production_orders ?? []).length === 0 && item.status !== 'ready' && item.status !== 'cancelled' && (
                        <p className="text-xs text-neutral-400 ml-4">No production order yet</p>
                      )}
                    </div>
                  ))}
                </div>
              ) : schedule.production_orders?.length === 0 ? (
                <div className="text-center py-8 text-neutral-400">
                  <Factory className="w-12 h-12 mx-auto mb-3 opacity-30" />
                  <p className="text-sm">No items or production orders created yet</p>
                  {canCreateWO && schedule.status === 'open' && (
                    <button
                      onClick={() => setShowCreateModal(true)}
                      className="mt-4 inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium"
                    >
                      <Plus className="w-4 h-4" />
                      Create Production Order
                    </button>
                  )}
                </div>
              ) : (
                <div className="divide-y divide-neutral-100">
                  {schedule.production_orders?.map((po) => (
                    <Link
                      key={po.id}
                      to={`/production/orders/${po.ulid}`}
                      className="flex items-center justify-between p-3 hover:bg-neutral-50 rounded-lg transition-colors"
                    >
                      <div>
                        <p className="font-medium text-neutral-900">{po.po_reference}</p>
                        <p className="text-xs text-neutral-500">
                          Qty: {parseFloat(po.qty_required).toLocaleString('en-PH')} |
                          Produced: {parseFloat(po.qty_produced).toLocaleString('en-PH')}
                        </p>
                      </div>
                      <div className="text-right">
                        <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${
                          po.status === 'completed' ? 'bg-green-100 text-green-700' :
                          po.status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                          po.status === 'cancelled' ? 'bg-neutral-100 text-neutral-500' :
                          'bg-neutral-100 text-neutral-700'
                        }`}>
                          {po.status?.replace('_', ' ')}
                        </span>
                        <p className="text-xs text-neutral-400 mt-1">
                          {new Date(po.target_start_date).toLocaleDateString('en-PH')} -
                          {new Date(po.target_end_date).toLocaleDateString('en-PH')}
                        </p>
                      </div>
                    </Link>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>

          {/* Delivery Receipts */}
          {(schedule.delivery_receipts ?? []).length > 0 && (
            <Card>
              <CardHeader>
                <span className="flex items-center gap-2">
                  <Truck className="h-4 w-4 text-neutral-500" />
                  Delivery Receipts
                </span>
              </CardHeader>
              <CardBody>
                <div className="divide-y divide-neutral-100">
                  {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                  {schedule.delivery_receipts?.map((dr: any) => (
                    <Link
                      key={dr.ulid}
                      to={`/delivery/receipts/${dr.ulid}`}
                      className="flex items-center justify-between p-3 hover:bg-neutral-50 rounded-lg transition-colors"
                    >
                      <div>
                        <p className="font-mono text-sm text-neutral-900">{dr.dr_reference}</p>
                        <p className="text-xs text-neutral-500 mt-1">
                          Date: {dr.receipt_date ?? '—'}
                        </p>
                      </div>
                      <div className="text-right">
                        <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium capitalize ${
                          dr.status === 'delivered' ? 'bg-emerald-100 text-emerald-800' :
                          dr.status === 'in_transit' ? 'bg-blue-100 text-blue-800' :
                          dr.status === 'cancelled' ? 'bg-neutral-100 text-neutral-500' :
                          'bg-amber-100 text-amber-800'
                        }`}>
                          {dr.status?.replace('_', ' ')}
                        </span>
                      </div>
                    </Link>
                  ))}
                </div>
              </CardBody>
            </Card>
          )}
        </div>

        {/* Right Column - Metadata */}
        <div className="space-y-5">
          <Card>
            <CardHeader>
              <span className="flex items-center gap-2">
                <FileText className="h-4 w-4 text-neutral-500" />
                Details
              </span>
            </CardHeader>
            <CardBody className="space-y-3">
              <div>
                <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Reference</p>
                <p className="font-mono text-sm text-neutral-900">{schedule.ds_reference}</p>
              </div>
              <div>
                <p className="text-xs text-neutral-500 uppercase tracking-wide mb-1">Created</p>
                <p className="text-sm text-neutral-700">
                  {new Date(schedule.created_at).toLocaleDateString('en-PH')}
                </p>
              </div>
              {schedule.deleted_at && (
                <div className="p-3 bg-amber-50 border border-amber-100 rounded-lg">
                  <p className="text-xs text-amber-700 font-medium">Archived</p>
                  <p className="text-xs text-amber-600">
                    {new Date(schedule.deleted_at).toLocaleDateString('en-PH')}
                  </p>
                </div>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      {/* Create WO Modal */}
      <CreateWOModal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        schedule={schedule}
      />

      {/* Fulfill from Stock Confirmation Modal */}
      {showFulfillConfirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
            <div className="p-5 border-b border-neutral-100">
              <h2 className="text-base font-semibold text-neutral-900">Fulfill from Stock?</h2>
              <p className="text-sm text-neutral-500 mt-0.5">
                This will deduct stock immediately and mark the schedule as ready for delivery.
              </p>
            </div>

            <div className="p-5 space-y-4">
              <div className="p-4 bg-neutral-50 rounded-lg border border-neutral-200">
                <div className="flex justify-between text-sm">
                  <span className="text-neutral-500">Product:</span>
                  <span className="font-medium text-neutral-900">{schedule.product_item?.name}</span>
                </div>
                <div className="flex justify-between text-sm mt-2">
                  <span className="text-neutral-500">Quantity:</span>
                  <span className="font-medium text-neutral-900">
                    {parseFloat(schedule.qty_ordered).toLocaleString('en-PH')}
                  </span>
                </div>
              </div>

              <div className="p-3 bg-amber-50 border border-amber-100 rounded-lg">
                <p className="text-xs text-amber-700">
                  <strong>Note:</strong> This action will deduct stock from the warehouse.
                  No Production Order will be created.
                </p>
              </div>
            </div>

            <div className="p-5 border-t border-neutral-100 flex gap-3">
              <button
                onClick={() => setShowFulfillConfirm(false)}
                disabled={fulfillMutation.isPending}
                className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
              >
                Cancel
              </button>
              <button
                onClick={handleFulfillFromStock}
                disabled={fulfillMutation.isPending}
                className="flex-1 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm flex items-center justify-center gap-2"
              >
                {fulfillMutation.isPending ? (
                  <>
                    <span className="animate-spin">⟳</span>
                    Processing...
                  </>
                ) : (
                  <>
                    <Package className="w-4 h-4" />
                    Yes, Fulfill
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Document Chain */}
      <div className="bg-white rounded border border-neutral-200 overflow-hidden mt-5">
        <div className="px-4 py-3 border-b border-neutral-100">
          <h2 className="text-sm font-semibold text-neutral-900">Document Chain</h2>
        </div>
        <div className="p-4">
          <ChainRecordTimeline documentType="delivery_schedule" documentId={schedule.id} />
        </div>
      </div>

      {/* Create Delivery Receipt Modal */}
      <CreateDRModal
        isOpen={showCreateDRModal}
        onClose={() => setShowCreateDRModal(false)}
        schedule={schedule}
        onCreated={(drUlid) => navigate(`/delivery/receipts/${drUlid}`)}
      />
    </div>
  )
}
