import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Truck } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateDeliverySchedule } from '@/hooks/useProduction'
import { useCustomers } from '@/hooks/useAR'
import { useItems } from '@/hooks/useInventory'
import type { DeliveryScheduleType } from '@/types/production'

export default function CreateDeliverySchedulePage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateDeliverySchedule()

  const { data: customersData } = useCustomers({ is_active: true, per_page: 500 })
  const customers = customersData?.data ?? []

  const { data: itemsData } = useItems({ type: 'finished_good', per_page: 500 })
  const items = itemsData?.data ?? []

  const [form, setForm] = useState({
    customer_id: 0,
    product_item_id: 0,
    qty_ordered: '',
    unit_price: '',
    target_delivery_date: '',
    type: 'local' as DeliveryScheduleType,
    notes: '',
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.customer_id) e.customer_id = 'Customer is required.'
    if (!form.product_item_id) e.product_item_id = 'Product item is required.'
    const qty = Number(form.qty_ordered)
    if (!form.qty_ordered || isNaN(qty) || qty <= 0) e.qty_ordered = 'Must be greater than 0.'
    if (!form.target_delivery_date) e.target_delivery_date = 'Delivery date is required.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await createMut.mutateAsync({
        customer_id: form.customer_id,
        product_item_id: form.product_item_id,
        qty_ordered: Number(form.qty_ordered),
        unit_price: form.unit_price !== '' ? Number(form.unit_price) : null,
        target_delivery_date: form.target_delivery_date,
        type: form.type,
        notes: form.notes || undefined,
      })
      toast.success('Delivery schedule created.')
      navigate('/production/delivery-schedules')
    } catch {
      toast.error('Failed to create delivery schedule.')
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">New Delivery Schedule</h1>

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        {/* Customer */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Customer *</label>
          <select
            className={`w-full border rounded px-3 py-2 text-sm bg-white ${fe('customer_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.customer_id || ''}
            onChange={e => set('customer_id', Number(e.target.value))}
            onBlur={() => touch('customer_id')}
            required
          >
            <option value="">— Select Customer —</option>
            {customers.map(c => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </select>
          {fe('customer_id') && <p className="mt-1 text-xs text-red-600">{fe('customer_id')}</p>}
        </div>

        {/* Product */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Product Item *</label>
          <select
            className={`w-full border rounded px-3 py-2 text-sm bg-white ${fe('product_item_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.product_item_id || ''}
            onChange={e => set('product_item_id', Number(e.target.value))}
            onBlur={() => touch('product_item_id')}
            required
          >
            <option value="">— Select Item —</option>
            {items.map(i => (
              <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>
            ))}
          </select>
          {fe('product_item_id') && <p className="mt-1 text-xs text-red-600">{fe('product_item_id')}</p>}
        </div>

        {/* Qty & Unit Price */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Qty Ordered *</label>
            <input
              type="number"
              min="0.001"
              step="0.001"
              className={`w-full border rounded px-3 py-2 text-sm ${fe('qty_ordered') ? 'border-red-400' : 'border-neutral-300'} [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`}
              value={form.qty_ordered}
              onChange={e => set('qty_ordered', e.target.value)}
              onBlur={() => touch('qty_ordered')}
              required
            />
            {fe('qty_ordered') && <p className="mt-1 text-xs text-red-600">{fe('qty_ordered')}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Unit Price (₱) <span className="text-neutral-400 font-normal">— for auto-invoice</span></label>
            <input
              type="number"
              min="0"
              step="0.0001"
              placeholder="0.00"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
              value={form.unit_price}
              onChange={e => set('unit_price', e.target.value)}
            />
          </div>
        </div>

        {/* Target Delivery Date */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Target Delivery Date *</label>
          <input
              type="date"
              className={`w-full border rounded px-3 py-2 text-sm ${fe('target_delivery_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.target_delivery_date}
              onChange={e => set('target_delivery_date', e.target.value)}
              onBlur={() => touch('target_delivery_date')}
              required
            />
            {fe('target_delivery_date') && <p className="mt-1 text-xs text-red-600">{fe('target_delivery_date')}</p>}
        </div>

        {/* Type */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Type *</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
            value={form.type}
            onChange={e => set('type', e.target.value)}
          >
            <option value="local">Local</option>
            <option value="export">Export</option>
          </select>
        </div>

        {/* Notes */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Notes</label>
          <textarea
            rows={2}
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm resize-none"
            value={form.notes}
            onChange={e => set('notes', e.target.value)}
          />
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/production/delivery-schedules')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Create Schedule'}
          </button>
        </div>
      </form>
    </div>
  )
}
