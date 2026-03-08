import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Box, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateDeliveryReceipt } from '@/hooks/useDelivery'
import { useVendors } from '@/hooks/useAP'
import { useCustomers } from '@/hooks/useAR'
import { useItems } from '@/hooks/useInventory'
import type { DrDirection } from '@/types/delivery'

const UOM_OPTIONS = ['pcs', 'kg', 'g', 'L', 'mL', 'm', 'cm', 'box', 'roll', 'set', 'pair']

interface LineItem {
  item_master_id: number
  quantity_expected: string
  quantity_received: string
  unit_of_measure: string
  lot_batch_number: string
  remarks: string
}

export default function CreateDeliveryReceiptPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateDeliveryReceipt()

  const { data: vendorsData } = useVendors({ per_page: 500 })
  const vendors = vendorsData?.data ?? []

  const { data: customersData } = useCustomers({ is_active: true, per_page: 500 })
  const customers = customersData?.data ?? []

  const { data: itemsData } = useItems({ per_page: 500 })
  const allItems = itemsData?.data ?? []

  const [direction, setDirection] = useState<DrDirection>('inbound')
  const [vendorId, setVendorId] = useState<number>(0)
  const [customerId, setCustomerId] = useState<number>(0)
  const [receiptDate, setReceiptDate] = useState(new Date().toISOString().split('T')[0])
  const [remarks, setRemarks] = useState('')
  const [lineItems, setLineItems] = useState<LineItem[]>([
    { item_master_id: 0, quantity_expected: '', quantity_received: '', unit_of_measure: 'pcs', lot_batch_number: '', remarks: '' },
  ])

  const items = direction === 'outbound'
    ? allItems.filter(i => i.type === 'finished_good' || i.type === 'semi_finished')
    : allItems

  const [touchedDate, setTouchedDate] = useState(false)
  const receiptDateError = useMemo(
    () => (touchedDate && !receiptDate ? 'Receipt date is required.' : undefined),
    [touchedDate, receiptDate],
  )

  const addRow = () =>
    setLineItems(prev => [
      ...prev,
      { item_master_id: 0, quantity_expected: '', quantity_received: '', unit_of_measure: 'pcs', lot_batch_number: '', remarks: '' },
    ])

  const removeRow = (idx: number) =>
    setLineItems(prev => prev.filter((_, i) => i !== idx))

  const updateRow = (idx: number, key: keyof LineItem, value: string | number) =>
    setLineItems(prev => prev.map((row, i) => (i === idx ? { ...row, [key]: value } : row)))

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await createMut.mutateAsync({
        direction,
        receipt_date: receiptDate,
        vendor_id: direction === 'inbound' && vendorId ? vendorId : null,
        customer_id: direction === 'outbound' && customerId ? customerId : null,
        remarks: remarks || undefined,
        items: lineItems
          .filter(it => it.item_master_id)
          .map(it => ({
            item_master_id: it.item_master_id,
            quantity_expected: Number(it.quantity_expected),
            quantity_received: Number(it.quantity_received),
            unit_of_measure: it.unit_of_measure || undefined,
            lot_batch_number: it.lot_batch_number || undefined,
            remarks: it.remarks || undefined,
          })),
      })
      toast.success('Delivery receipt created.')
      navigate('/delivery/receipts')
    } catch {
      toast.error('Failed to create delivery receipt.')
    }
  }

  return (
    <div className="max-w-4xl">
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">New Delivery Receipt</h1>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Header */}
        <div className="bg-white border border-neutral-200 rounded-lg p-6 space-y-5">
          <h2 className="text-sm font-medium text-neutral-700">Receipt Details</h2>

          {/* Direction */}
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Direction *</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none"
              value={direction}
              onChange={e => setDirection(e.target.value as DrDirection)}
            >
              <option value="inbound">Inbound (Receiving)</option>
              <option value="outbound">Outbound (Dispatch)</option>
            </select>
          </div>

          {/* Vendor or Customer */}
          {direction === 'inbound' ? (
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Vendor</label>
              <select
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                value={vendorId || ''}
                onChange={e => setVendorId(Number(e.target.value))}
              >
                <option value="">— Select Vendor —</option>
                {vendors.map(v => (
                  <option key={v.id} value={v.id}>{v.name}</option>
                ))}
              </select>
            </div>
          ) : (
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Customer</label>
              <select
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                value={customerId || ''}
                onChange={e => setCustomerId(Number(e.target.value))}
              >
                <option value="">— Select Customer —</option>
                {customers.map(c => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>
          )}

          {/* Date & Remarks */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Receipt Date *</label>
              <input
                type="date"
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none ${receiptDateError ? 'border-red-400' : 'border-neutral-300'}`}
                value={receiptDate}
                onChange={e => setReceiptDate(e.target.value)}
                onBlur={() => setTouchedDate(true)}
                required
              />
              {receiptDateError && <p className="mt-1 text-xs text-red-600">{receiptDateError}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks</label>
              <input
                type="text"
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                value={remarks}
                onChange={e => setRemarks(e.target.value)}
              />
            </div>
          </div>
        </div>

        {/* Line Items */}
        <div className="bg-white border border-neutral-200 rounded-lg p-6 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-medium text-neutral-700">Items</h2>
            <button
              type="button"
              onClick={addRow}
              className="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Item
            </button>
          </div>

          <div className="space-y-2">
            {lineItems.map((row, idx) => (
              <div key={idx} className="grid grid-cols-12 gap-2 items-start bg-neutral-50 rounded p-3">
                {/* Item */}
                <div className="col-span-4">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Item *</p>}
                  <select
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                    value={row.item_master_id || ''}
                    onChange={e => updateRow(idx, 'item_master_id', Number(e.target.value))}
                    required
                  >
                    <option value="">— Select —</option>
                    {items.map(i => (
                      <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>
                    ))}
                  </select>
                </div>
                {/* Expected Qty */}
                <div className="col-span-1">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Expected</p>}
                  <input
                    type="number"
                    step="0.001"
                    min="0"
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                    value={row.quantity_expected}
                    onChange={e => updateRow(idx, 'quantity_expected', e.target.value)}
                    required
                  />
                </div>
                {/* Received Qty */}
                <div className="col-span-1">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Received</p>}
                  <input
                    type="number"
                    step="0.001"
                    min="0"
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                    value={row.quantity_received}
                    onChange={e => updateRow(idx, 'quantity_received', e.target.value)}
                    required
                  />
                </div>
                {/* UoM */}
                <div className="col-span-2">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">UoM</p>}
                  <select
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                    value={row.unit_of_measure}
                    onChange={e => updateRow(idx, 'unit_of_measure', e.target.value)}
                  >
                    {UOM_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                  </select>
                </div>
                {/* Lot/Batch */}
                <div className="col-span-2">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Lot/Batch</p>}
                  <input
                    type="text"
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                    value={row.lot_batch_number}
                    onChange={e => updateRow(idx, 'lot_batch_number', e.target.value)}
                    placeholder="Optional"
                  />
                </div>
                {/* Remarks */}
                <div className="col-span-1">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Note</p>}
                  <input
                    type="text"
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                    value={row.remarks}
                    onChange={e => updateRow(idx, 'remarks', e.target.value)}
                  />
                </div>
                {/* Remove */}
                <div className="col-span-1 flex justify-center pt-2">
                  <button
                    type="button"
                    disabled={lineItems.length === 1}
                    onClick={() => removeRow(idx)}
                    className="p-1 text-neutral-400 hover:text-red-500 disabled:opacity-30"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate('/delivery/receipts')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50"
          >
            {createMut.isPending ? 'Saving…' : 'Create Receipt'}
          </button>
        </div>
      </form>
    </div>
  )
}
