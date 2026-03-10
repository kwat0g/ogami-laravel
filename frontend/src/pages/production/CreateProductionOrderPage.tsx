import { useState, useMemo, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import {
  useBoms,
  useDeliverySchedules,
  useCreateProductionOrder,
} from '@/hooks/useProduction'
import { useItems } from '@/hooks/useInventory'

export default function CreateProductionOrderPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateProductionOrder()

  const { data: itemsData } = useItems({ type: 'finished_good', per_page: 500 })
  const items = itemsData?.data ?? []

  const [selectedItemId, setSelectedItemId] = useState<number | null>(null)
  const { data: bomsData } = useBoms({
    product_item_id: selectedItemId ?? undefined,
    is_active: true,
    per_page: 100,
  })
  // Only use BOM results when an item is actually selected — prevents the query
  // from returning all BOMs (global list) before a product is chosen
  const boms = useMemo(
    () => selectedItemId ? (bomsData?.data ?? []) : [],
    [selectedItemId, bomsData]
  )

  // Auto-select BOM when only one active BOM exists for the chosen item
  useEffect(() => {
    if (selectedItemId && boms.length === 1) setForm(prev => ({ ...prev, bom_id: boms[0].id }))
  }, [boms, selectedItemId])

  const { data: dsData } = useDeliverySchedules({ status: 'open', per_page: 200 })
  const deliverySchedules = dsData?.data ?? []

  const [form, setForm] = useState({
    product_item_id: 0,
    bom_id: 0,
    delivery_schedule_id: '' as number | '',
    qty_required: '',
    target_start_date: '',
    target_end_date: '',
    notes: '',
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const selectedDs = deliverySchedules.find(d => d.id === form.delivery_schedule_id)
  const dsDueDate  = selectedDs?.target_delivery_date ?? null
  const lateDateWarning =
    dsDueDate && form.target_end_date && form.target_end_date > dsDueDate
      ? `Target end date is after the delivery schedule due date (${new Date(dsDueDate + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}).`
      : null

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.product_item_id) e.product_item_id = 'Product item is required.'
    if (!form.bom_id) e.bom_id = 'Bill of materials is required.'
    const qty = Number(form.qty_required)
    if (!form.qty_required || isNaN(qty) || qty <= 0) e.qty_required = 'Must be greater than 0.'
    if (!form.target_start_date) e.target_start_date = 'Start date is required.'
    if (!form.target_end_date) e.target_end_date = 'End date is required.'
    return e
  }, [form])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      const order = await createMut.mutateAsync({
        product_item_id: form.product_item_id,
        bom_id: form.bom_id,
        delivery_schedule_id: form.delivery_schedule_id !== '' ? Number(form.delivery_schedule_id) : undefined,
        qty_required: Number(form.qty_required),
        target_start_date: form.target_start_date,
        target_end_date: form.target_end_date,
        notes: form.notes || undefined,
      })
      toast.success('Production order created.')
      // @ts-expect-error mutation returns AxiosResponse wrapper
      const ulid: string | undefined = order?.data?.ulid
      if (ulid) navigate(`/production/orders/${ulid}`)
      else navigate('/production/orders')
    } catch {
      toast.error('Failed to create production order.')
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">New Production Order</h1>

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        {/* Product Item */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Product Item *</label>
          <select
            className={`w-full border rounded px-3 py-2 text-sm bg-white ${fe('product_item_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.product_item_id || ''}
            onChange={e => {
              const id = Number(e.target.value)
              setSelectedItemId(id || null)
              setForm(prev => ({ ...prev, product_item_id: id, bom_id: 0 }))
            }}
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

        {/* BOM */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Bill of Materials *</label>
          <select
            className={`w-full border rounded px-3 py-2 text-sm bg-white ${fe('bom_id') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.bom_id || ''}
            onChange={e => set('bom_id', Number(e.target.value))}
            onBlur={() => touch('bom_id')}
            required
            disabled={!selectedItemId || boms.length === 1}
          >
            <option value="">— Select BOM —</option>
            {boms.map(b => (
              <option key={b.id} value={b.id}>v{b.version}{b.notes ? ` — ${b.notes}` : ''}</option>
            ))}
          </select>
          {fe('bom_id') && <p className="mt-1 text-xs text-red-600">{fe('bom_id')}</p>}
          {selectedItemId && boms.length === 0 && (
            <p className="text-xs text-orange-600 mt-1">No active BOMs for this item. Create one first.</p>
          )}
        </div>

        {/* Delivery Schedule (optional) */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">
            Delivery Schedule <span className="text-neutral-400 font-normal">(optional)</span>
          </label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
            value={form.delivery_schedule_id}
            onChange={e => {
              const id = e.target.value ? Number(e.target.value) : ''
              set('delivery_schedule_id', id)
              if (id) {
                const ds = deliverySchedules.find(d => d.id === id)
                if (ds?.qty_ordered) set('qty_required', String(Math.round(ds.qty_ordered)))
              }
            }}
          >
            <option value="">— None —</option>
            {deliverySchedules.map(ds => (
              <option key={ds.id} value={ds.id}>
                {ds.ds_reference} — {ds.customer?.name ?? 'N/A'} · Due {new Date(ds.target_delivery_date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}
              </option>
            ))}
          </select>
        </div>

        {/* Qty */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Qty Required *</label>
          <input
            type="number"
            min="0.001"
            step="0.001"
            className={`w-full border rounded px-3 py-2 text-sm ${fe('qty_required') ? 'border-red-400' : 'border-neutral-300'} [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none`}
            value={form.qty_required}
            onChange={e => set('qty_required', e.target.value)}
            onBlur={() => touch('qty_required')}
            required
          />
          {fe('qty_required') && <p className="mt-1 text-xs text-red-600">{fe('qty_required')}</p>}
        </div>

        {/* Dates */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Target Start Date *</label>
            <input
              type="date"
              className={`w-full border rounded px-3 py-2 text-sm ${fe('target_start_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.target_start_date}
              onChange={e => set('target_start_date', e.target.value)}
              onBlur={() => touch('target_start_date')}
              required
            />
            {fe('target_start_date') && <p className="mt-1 text-xs text-red-600">{fe('target_start_date')}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Target End Date *</label>
            <input
              type="date"
              className={`w-full border rounded px-3 py-2 text-sm ${fe('target_end_date') ? 'border-red-400' : 'border-neutral-300'}`}
              value={form.target_end_date}
              min={form.target_start_date || undefined}
              onChange={e => set('target_end_date', e.target.value)}
              onBlur={() => touch('target_end_date')}
              required
            />
            {fe('target_end_date') && <p className="mt-1 text-xs text-red-600">{fe('target_end_date')}</p>}
            {!fe('target_end_date') && lateDateWarning && (
              <p className="mt-1 text-xs text-amber-600">⚠ {lateDateWarning}</p>
            )}
          </div>
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
            onClick={() => navigate('/production/orders')}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Creating…' : 'Create Work Order'}
          </button>
        </div>
      </form>
    </div>
  )
}
