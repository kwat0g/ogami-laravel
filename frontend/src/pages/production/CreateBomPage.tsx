import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Package, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateBom } from '@/hooks/useProduction'
import { useItems } from '@/hooks/useInventory'

const UOM_OPTIONS = ['pcs', 'kg', 'g', 'L', 'mL', 'm', 'cm', 'box', 'roll', 'set', 'pair']

interface ComponentRow {
  component_item_id: number
  qty_per_unit: string
  unit_of_measure: string
  scrap_factor_pct: string
}

export default function CreateBomPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateBom()

  const { data: itemsData } = useItems({ per_page: 500 })
  const items = itemsData?.data ?? []
  const finishedGoods = items.filter(i => i.type === 'finished_good')
  const rawMaterials   = items.filter(i => i.type !== 'finished_good')

  const [productItemId, setProductItemId] = useState<number>(0)
  const [version, setVersion] = useState('1.0')
  const [notes, setNotes] = useState('')
  const [components, setComponents] = useState<ComponentRow[]>([
    { component_item_id: 0, qty_per_unit: '1', unit_of_measure: 'pcs', scrap_factor_pct: '0' },
  ])

  const [touchedProductItem, setTouchedProductItem] = useState(false)
  const productItemError = useMemo(
    () => (touchedProductItem && !productItemId ? 'Product item is required.' : undefined),
    [touchedProductItem, productItemId],
  )

  const addRow = () =>
    setComponents(prev => [
      ...prev,
      { component_item_id: 0, qty_per_unit: '1', unit_of_measure: 'pcs', scrap_factor_pct: '0' },
    ])

  const removeRow = (idx: number) =>
    setComponents(prev => prev.filter((_, i) => i !== idx))

  const updateRow = (idx: number, key: keyof ComponentRow, value: string | number) =>
    setComponents(prev =>
      prev.map((row, i) => (i === idx ? { ...row, [key]: value } : row)),
    )

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!productItemId) return toast.error('Select a product item.')
    if (components.some(c => !c.component_item_id)) return toast.error('All component rows must have an item selected.')

    try {
      await createMut.mutateAsync({
        product_item_id: productItemId,
        version: version || undefined,
        notes: notes || undefined,
        components: components.map(c => ({
          component_item_id: c.component_item_id,
          qty_per_unit: Number(c.qty_per_unit),
          unit_of_measure: c.unit_of_measure,
          scrap_factor_pct: Number(c.scrap_factor_pct) || undefined,
        })),
      })
      toast.success('BOM created.')
      navigate('/production/boms')
    } catch {
      toast.error('Failed to create BOM.')
    }
  }

  return (
    <div className="max-w-4xl">
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">New Bill of Materials</h1>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Header */}
        <div className="bg-white border border-neutral-200 rounded p-6 space-y-4">
          <h2 className="text-sm font-medium text-neutral-700">BOM Details</h2>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Product Item *</label>
              <select
                className={`w-full border rounded px-3 py-2 text-sm bg-white ${productItemError ? 'border-red-400' : 'border-neutral-300'}`}
                value={productItemId || ''}
                onChange={e => setProductItemId(Number(e.target.value))}
                onBlur={() => setTouchedProductItem(true)}
                required
              >
                <option value="">— Select Finished Good —</option>
                {finishedGoods.map(i => (
                  <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>
                ))}
              </select>
              {productItemError && <p className="mt-1 text-xs text-red-600">{productItemError}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Version</label>
              <input
                type="text"
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
                value={version}
                onChange={e => setVersion(e.target.value)}
                placeholder="e.g. 1.0"
              />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Notes</label>
            <textarea
              rows={2}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm resize-none"
              value={notes}
              onChange={e => setNotes(e.target.value)}
              placeholder="Optional engineering notes"
            />
          </div>
        </div>

        {/* Components */}
        <div className="bg-white border border-neutral-200 rounded p-6 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-medium text-neutral-700">Components</h2>
            <button
              type="button"
              onClick={addRow}
              className="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Component
            </button>
          </div>

          <div className="space-y-2">
            {components.map((row, idx) => (
              <div key={idx} className="grid grid-cols-12 gap-2 items-center bg-neutral-50 rounded p-3">
                {/* Component Item */}
                <div className="col-span-5">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Component Item *</p>}
                  <select
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm bg-white"
                    value={row.component_item_id || ''}
                    onChange={e => updateRow(idx, 'component_item_id', Number(e.target.value))}
                    required
                  >
                    <option value="">— Select Item —</option>
                    {rawMaterials.map(i => (
                      <option key={i.id} value={i.id}>{i.item_code} — {i.name}</option>
                    ))}
                  </select>
                </div>
                {/* Qty */}
                <div className="col-span-2">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Qty/Unit *</p>}
                  <input
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm"
                    value={row.qty_per_unit}
                    onChange={e => updateRow(idx, 'qty_per_unit', e.target.value)}
                    required
                  />
                </div>
                {/* UoM */}
                <div className="col-span-2">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">UoM *</p>}
                  <select
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm bg-white"
                    value={row.unit_of_measure}
                    onChange={e => updateRow(idx, 'unit_of_measure', e.target.value)}
                    required
                  >
                    {UOM_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                  </select>
                </div>
                {/* Scrap % */}
                <div className="col-span-2">
                  {idx === 0 && <p className="text-xs text-neutral-500 mb-1">Scrap %</p>}
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm"
                    value={row.scrap_factor_pct}
                    onChange={e => updateRow(idx, 'scrap_factor_pct', e.target.value)}
                  />
                </div>
                {/* Remove */}
                <div className="col-span-1 flex justify-center">
                  <button
                    type="button"
                    disabled={components.length === 1}
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
            onClick={() => navigate(-1)}
            className="px-4 py-2 text-sm rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-6 py-2 text-sm rounded bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50"
          >
            {createMut.isPending ? 'Saving…' : 'Create BOM'}
          </button>
        </div>
      </form>
    </div>
  )
}
