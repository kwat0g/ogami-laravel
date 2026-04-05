import { useState, useMemo, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useBoms,
  useCreateProductionOrder,
  useCreateReplenishmentOrder,
  useProductionSmartDefaults,
} from '@/hooks/useProduction'
import { useItems } from '@/hooks/useInventory'
import { firstErrorMessage } from '@/lib/errorHandler'

export default function CreateProductionOrderPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateProductionOrder()
  const createReplenishmentMut = useCreateReplenishmentOrder()

  const { data: itemsData } = useItems({ type: 'finished_good', per_page: 500 })
  const items = itemsData?.data ?? []

  const [selectedItemId, setSelectedItemId] = useState<number | null>(null)
  const [targetStartDate, setTargetStartDate] = useState<string>('')
  const [creationMode, setCreationMode] = useState<'manual' | 'replenishment'>('manual')

  // Fetch smart defaults when product or start date changes
  const { data: smartDefaults } = useProductionSmartDefaults(
    selectedItemId,
    targetStartDate || undefined
  )

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

  // Auto-select BOM when smart defaults provide one
  useEffect(() => {
    if (selectedItemId && smartDefaults?.suggested_bom_id) {
      setForm(prev => ({ ...prev, bom_id: smartDefaults.suggested_bom_id! }))
    }
  }, [smartDefaults?.suggested_bom_id, selectedItemId])

  // Auto-populate end date when smart defaults provide calculated end date
  useEffect(() => {
    if (targetStartDate && smartDefaults?.calculated_end_date) {
      setForm(prev => ({ ...prev, target_end_date: smartDefaults.calculated_end_date! }))
    }
  }, [smartDefaults?.calculated_end_date, targetStartDate])

  const [form, setForm] = useState({
    product_item_id: 0,
    bom_id: 0,
    qty_required: '',
    target_stock_level: '',
    min_batch_size: '',
    target_start_date: '',
    target_end_date: '',
    notes: '',
  })

  const set = (k: keyof typeof form, v: unknown) => {
    setForm(prev => ({ ...prev, [k]: v }))
    // Track target_start_date separately for smart defaults
    if (k === 'target_start_date') {
      setTargetStartDate(v as string)
    }
  }

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))
  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.product_item_id) e.product_item_id = 'Product item is required.'
    if (creationMode === 'manual') {
      if (!form.bom_id) e.bom_id = 'Bill of materials is required.'
      const qty = Number(form.qty_required)
      if (!form.qty_required || isNaN(qty) || qty <= 0) e.qty_required = 'Must be greater than 0.'
    }
    if (creationMode === 'replenishment') {
      const targetLevel = Number(form.target_stock_level)
      if (!form.target_stock_level || isNaN(targetLevel) || targetLevel <= 0) {
        e.target_stock_level = 'Target stock level must be greater than 0.'
      }
      const minBatch = Number(form.min_batch_size)
      if (form.min_batch_size && (isNaN(minBatch) || minBatch <= 0)) {
        e.min_batch_size = 'Minimum batch size must be greater than 0.'
      }
    }
    if (!form.target_start_date) e.target_start_date = 'Start date is required.'
    if (!form.target_end_date) e.target_end_date = 'End date is required.'
    // Validate date range
    if (form.target_start_date && form.target_end_date && form.target_end_date < form.target_start_date) {
      e.target_end_date = 'End date must be after start date.'
    }
    return e
  }, [form, creationMode])
  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const validateForm = (): boolean => {
    const errors: string[] = []
    
    if (!form.product_item_id) errors.push('Product item is required.')
    if (creationMode === 'manual') {
      if (!form.bom_id) errors.push('Bill of materials is required.')
      const qty = Number(form.qty_required)
      if (!form.qty_required || isNaN(qty) || qty <= 0) errors.push('Quantity required must be greater than 0.')
    }
    if (creationMode === 'replenishment') {
      const targetLevel = Number(form.target_stock_level)
      if (!form.target_stock_level || isNaN(targetLevel) || targetLevel <= 0) {
        errors.push('Target stock level must be greater than 0.')
      }
      const minBatch = Number(form.min_batch_size)
      if (form.min_batch_size && (isNaN(minBatch) || minBatch <= 0)) {
        errors.push('Minimum batch size must be greater than 0.')
      }
    }
    if (!form.target_start_date) errors.push('Target start date is required.')
    if (!form.target_end_date) errors.push('Target end date is required.')
    if (form.target_start_date && form.target_end_date && form.target_end_date < form.target_start_date) {
      errors.push('Target end date must be after start date.')
    }
    
    if (errors.length > 0) {
      // Touch all fields to show validation state
      setTouched(new Set(['product_item_id', 'bom_id', 'qty_required', 'target_start_date', 'target_end_date']))
      return false
    }
    
    return true
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!validateForm()) return
    
    try {
      const order = creationMode === 'manual'
        ? await createMut.mutateAsync({
            product_item_id: form.product_item_id,
            bom_id: form.bom_id,
            qty_required: Number(form.qty_required),
            target_start_date: form.target_start_date,
            target_end_date: form.target_end_date,
            notes: form.notes || undefined,
          })
        : await createReplenishmentMut.mutateAsync({
            product_item_id: form.product_item_id,
            target_stock_level: Number(form.target_stock_level),
            min_batch_size: form.min_batch_size ? Number(form.min_batch_size) : undefined,
            bom_id: form.bom_id || undefined,
            target_start_date: form.target_start_date,
            target_end_date: form.target_end_date,
            notes: form.notes || undefined,
          })
      toast.success('Production order created successfully.')
      // @ts-expect-error mutation returns AxiosResponse wrapper
      const ulid: string | undefined = order?.data?.ulid
      if (ulid) navigate(`/production/orders/${ulid}`)
      else navigate('/production/orders')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Production Order" backTo="/production/orders" />

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Creation Mode *</label>
          <select
            className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white"
            value={creationMode}
            onChange={e => setCreationMode(e.target.value as 'manual' | 'replenishment')}
          >
            <option value="manual">Manual Work Order</option>
            <option value="replenishment">Replenishment Work Order</option>
          </select>
        </div>

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
            disabled={!selectedItemId}
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
          {smartDefaults?.suggested_bom_name && (
            <p className="text-xs text-green-600 mt-1">
              ✓ Auto-suggested: BOM v{smartDefaults.suggested_bom_name}
            </p>
          )}
        </div>

        {/* Qty */}
        {creationMode === 'manual' && (
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
        )}

        {creationMode === 'replenishment' && (
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Target Stock Level *</label>
              <input
                type="number"
                min="0.001"
                step="0.001"
                className={`w-full border rounded px-3 py-2 text-sm ${fe('target_stock_level') ? 'border-red-400' : 'border-neutral-300'}`}
                value={form.target_stock_level}
                onChange={e => set('target_stock_level', e.target.value)}
                onBlur={() => touch('target_stock_level')}
                required
              />
              {fe('target_stock_level') && <p className="mt-1 text-xs text-red-600">{fe('target_stock_level')}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Minimum Batch Size</label>
              <input
                type="number"
                min="0.001"
                step="0.001"
                className={`w-full border rounded px-3 py-2 text-sm ${fe('min_batch_size') ? 'border-red-400' : 'border-neutral-300'}`}
                value={form.min_batch_size}
                onChange={e => set('min_batch_size', e.target.value)}
                onBlur={() => touch('min_batch_size')}
              />
              {fe('min_batch_size') && <p className="mt-1 text-xs text-red-600">{fe('min_batch_size')}</p>}
            </div>
          </div>
        )}

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
            {!fe('target_end_date') && smartDefaults?.calculated_end_date && (
              <p className="mt-1 text-xs text-green-600">
                ✓ Auto-calculated based on BOM production days
              </p>
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
            disabled={createMut.isPending || createReplenishmentMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {(createMut.isPending || createReplenishmentMut.isPending) ? 'Creating…' : 'Create Work Order'}
          </button>
        </div>
      </form>
    </div>
  )
}
