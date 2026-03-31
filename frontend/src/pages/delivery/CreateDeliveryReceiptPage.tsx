import { useState, useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Truck, Plus, Trash2 } from 'lucide-react'
import { useDeliverySchedule } from '@/hooks/useProduction'
import { useCreateDeliveryReceipt } from '@/hooks/useDelivery'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { CreateDeliveryReceiptPayload } from '@/types/delivery'

interface ItemRow {
  item_master_id: number
  item_name: string
  quantity_expected: number
  quantity_received: number
  unit_of_measure: string
  lot_batch_number: string
  remarks: string
}

export default function CreateDeliveryReceiptPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  // Only read the ULID from URL -- all other data comes from the API
  const dsUlid = searchParams.get('ds')

  const { data: schedule, isLoading: dsLoading, isError: dsError } = useDeliverySchedule(dsUlid)
  const createDR = useCreateDeliveryReceipt()

  const [direction, setDirection] = useState<'outbound' | 'inbound'>('outbound')
  const [receiptDate, setReceiptDate] = useState(new Date().toISOString().split('T')[0])
  const [remarks, setRemarks] = useState('')
  const [items, setItems] = useState<ItemRow[]>([])
  const [initialized, setInitialized] = useState(false)

  // Pre-fill form when DS data loads
  useEffect(() => {
    if (!schedule || initialized) return

    // DS is always outbound (delivering to customer)
    setDirection('outbound')

    // Build items from either multi-item schedule or single-item legacy
    const dsItems = schedule.items ?? []
    if (dsItems.length > 0) {
      setItems(
        dsItems.map((item) => ({
          item_master_id: item.product_item?.id ?? item.product_item_id,
          item_name: item.product_item?.name ?? `Item #${item.product_item_id}`,
          quantity_expected: parseFloat(item.qty_ordered ?? '0'),
          quantity_received: parseFloat(item.qty_ordered ?? '0'),
          unit_of_measure: item.product_item?.unit_of_measure ?? 'pcs',
          lot_batch_number: '',
          remarks: '',
        })),
      )
    } else if (schedule.product_item) {
      // Legacy single-item delivery schedule
      setItems([
        {
          item_master_id: schedule.product_item.id,
          item_name: schedule.product_item.name,
          quantity_expected: parseFloat(schedule.qty_ordered ?? '0'),
          quantity_received: parseFloat(schedule.qty_ordered ?? '0'),
          unit_of_measure: schedule.product_item.unit_of_measure ?? 'pcs',
          lot_batch_number: '',
          remarks: '',
        },
      ])
    }

    setInitialized(true)
  }, [schedule, initialized])

  const updateItem = (idx: number, field: keyof ItemRow, value: string | number) => {
    setItems((prev) =>
      prev.map((item, i) => (i === idx ? { ...item, [field]: value } : item)),
    )
  }

  const removeItem = (idx: number) => {
    setItems((prev) => prev.filter((_, i) => i !== idx))
  }

  const addItem = () => {
    setItems((prev) => [
      ...prev,
      {
        item_master_id: 0,
        item_name: '',
        quantity_expected: 0,
        quantity_received: 0,
        unit_of_measure: 'pcs',
        lot_batch_number: '',
        remarks: '',
      },
    ])
  }

  const handleSubmit = async () => {
    if (items.length === 0) {
      toast.error('At least one item is required.')
      return
    }

    const invalidItems = items.filter((it) => !it.item_master_id || it.quantity_expected <= 0)
    if (invalidItems.length > 0) {
      toast.error('All items must have a valid item and quantity.')
      return
    }

    const payload: CreateDeliveryReceiptPayload = {
      direction,
      receipt_date: receiptDate,
      remarks: remarks || undefined,
      customer_id: schedule?.customer?.id ?? undefined,
      delivery_schedule_id: schedule?.id ?? undefined,
      items: items.map((it) => ({
        item_master_id: it.item_master_id,
        quantity_expected: it.quantity_expected,
        quantity_received: it.quantity_received,
        unit_of_measure: it.unit_of_measure || undefined,
        lot_batch_number: it.lot_batch_number || undefined,
        remarks: it.remarks || undefined,
      })),
    }

    try {
      const result = await createDR.mutateAsync(payload)
      toast.success('Delivery receipt created.')
      const ulid = result?.data?.ulid ?? result?.ulid
      if (ulid) {
        navigate(`/delivery/receipts/${ulid}`)
      } else {
        navigate('/delivery/receipts')
      }
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to create delivery receipt.')
    }
  }

  if (dsLoading) return <SkeletonLoader rows={6} />

  if (dsError || (dsUlid && !schedule)) {
    return (
      <div className="text-sm text-red-600 mt-4">
        Delivery schedule not found or you do not have access.{' '}
        <button onClick={() => navigate(-1)} className="underline text-neutral-600">
          Go back
        </button>
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title="Create Delivery Receipt"
        backTo={dsUlid ? `/production/delivery-schedules/${dsUlid}` : '/delivery/receipts'}
        icon={<Truck className="w-5 h-5" />}
      />

      {/* Source DS info banner */}
      {schedule && (
        <div className="mb-4 flex items-center gap-2 text-xs text-neutral-700 bg-neutral-50 border border-neutral-200 rounded px-4 py-2.5">
          <Truck className="w-3.5 h-3.5 shrink-0" />
          <span>
            Pre-filled from delivery schedule{' '}
            <span className="font-semibold">{schedule.ds_reference}</span>
            {schedule.customer && (
              <>
                {' '}
                &middot; Customer:{' '}
                <span className="font-semibold">{schedule.customer.name}</span>
              </>
            )}
            . Adjust quantities as needed before saving.
          </span>
        </div>
      )}

      <div className="space-y-5">
        {/* Receipt Information */}
        <Card>
          <CardHeader>Receipt Information</CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Direction */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Direction <span className="text-red-500">*</span>
                </label>
                <select
                  value={direction}
                  onChange={(e) => setDirection(e.target.value as 'outbound' | 'inbound')}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
                >
                  <option value="outbound">Outbound (to customer)</option>
                  <option value="inbound">Inbound (from vendor)</option>
                </select>
              </div>

              {/* Receipt Date */}
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

              {/* Customer (read-only when from DS) */}
              {schedule?.customer && (
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Customer</label>
                  <div className="w-full text-sm border border-neutral-200 rounded px-3 py-2 bg-neutral-100 text-neutral-600">
                    {schedule.customer.name}
                  </div>
                </div>
              )}

              {/* Delivery Schedule (read-only reference) */}
              {schedule && (
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Delivery Schedule</label>
                  <div className="w-full text-sm border border-neutral-200 rounded px-3 py-2 bg-neutral-100 text-neutral-600">
                    {schedule.ds_reference}
                  </div>
                </div>
              )}

              {/* Remarks */}
              <div className="sm:col-span-2">
                <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks</label>
                <textarea
                  value={remarks}
                  onChange={(e) => setRemarks(e.target.value)}
                  rows={2}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  placeholder="Optional remarks..."
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Items */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between w-full">
              <span>Items</span>
              {!dsUlid && (
                <button
                  type="button"
                  onClick={addItem}
                  className="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
                >
                  <Plus className="w-3.5 h-3.5" />
                  Add Item
                </button>
              )}
            </div>
          </CardHeader>
          <CardBody>
            {items.length === 0 ? (
              <p className="text-sm text-neutral-500 text-center py-4">
                No items added yet.{' '}
                <button type="button" onClick={addItem} className="text-blue-600 hover:underline">
                  Add an item
                </button>
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-neutral-50 text-xs text-neutral-600 uppercase tracking-wider">
                    <tr>
                      <th className="px-3 py-2 text-left">Item</th>
                      <th className="px-3 py-2 text-right">Qty Expected</th>
                      <th className="px-3 py-2 text-right">Qty Received</th>
                      <th className="px-3 py-2 text-left">UOM</th>
                      <th className="px-3 py-2 text-left">Lot/Batch</th>
                      <th className="px-3 py-2 text-left">Remarks</th>
                      <th className="px-3 py-2 w-10"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-100">
                    {items.map((item, idx) => (
                      <tr key={idx} className="hover:bg-neutral-50">
                        <td className="px-3 py-2">
                          {dsUlid ? (
                            <span className="text-neutral-900 font-medium">{item.item_name}</span>
                          ) : (
                            <input
                              type="number"
                              value={item.item_master_id || ''}
                              onChange={(e) => updateItem(idx, 'item_master_id', Number(e.target.value))}
                              className="w-24 text-sm border border-neutral-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                              placeholder="Item ID"
                            />
                          )}
                        </td>
                        <td className="px-3 py-2 text-right">
                          <input
                            type="number"
                            step="0.01"
                            value={item.quantity_expected}
                            onChange={(e) => updateItem(idx, 'quantity_expected', parseFloat(e.target.value) || 0)}
                            className="w-24 text-sm border border-neutral-300 rounded px-2 py-1.5 text-right focus:outline-none focus:ring-1 focus:ring-neutral-400"
                          />
                        </td>
                        <td className="px-3 py-2 text-right">
                          <input
                            type="number"
                            step="0.01"
                            value={item.quantity_received}
                            onChange={(e) => updateItem(idx, 'quantity_received', parseFloat(e.target.value) || 0)}
                            className="w-24 text-sm border border-neutral-300 rounded px-2 py-1.5 text-right focus:outline-none focus:ring-1 focus:ring-neutral-400"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <input
                            type="text"
                            value={item.unit_of_measure}
                            onChange={(e) => updateItem(idx, 'unit_of_measure', e.target.value)}
                            className="w-16 text-sm border border-neutral-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <input
                            type="text"
                            value={item.lot_batch_number}
                            onChange={(e) => updateItem(idx, 'lot_batch_number', e.target.value)}
                            className="w-24 text-sm border border-neutral-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                            placeholder="Lot/Batch"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <input
                            type="text"
                            value={item.remarks}
                            onChange={(e) => updateItem(idx, 'remarks', e.target.value)}
                            className="w-28 text-sm border border-neutral-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                            placeholder="Notes"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <button
                            type="button"
                            onClick={() => removeItem(idx)}
                            className="text-neutral-400 hover:text-red-500 transition-colors"
                            title="Remove item"
                          >
                            <Trash2 className="w-4 h-4" />
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

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-4 py-2 text-sm border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleSubmit}
            disabled={createDR.isPending || items.length === 0}
            className="px-6 py-2 text-sm bg-neutral-900 text-white font-medium rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {createDR.isPending ? 'Creating...' : 'Create Delivery Receipt'}
          </button>
        </div>
      </div>
    </div>
  )
}
