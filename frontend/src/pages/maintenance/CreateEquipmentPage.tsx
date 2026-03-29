import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCreateEquipment, useEquipment } from '@/hooks/useMaintenance'
import { useWarehouseLocations } from '@/hooks/useInventory'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { EquipmentStatus } from '@/types/maintenance'

export default function CreateEquipmentPage(): React.ReactElement {
  const navigate = useNavigate()
  const createMut = useCreateEquipment()

  // Fetch existing equipment for category suggestions + warehouse locations
  const { data: existingEquipment } = useEquipment({ per_page: 500 })
  const existingCategories = useMemo(() => {
    const cats = new Set<string>()
    ;(existingEquipment?.data ?? []).forEach(eq => { if (eq.category) cats.add(eq.category) })
    return Array.from(cats).sort()
  }, [existingEquipment])

  const { data: warehouseLocations } = useWarehouseLocations()

  const [form, setForm] = useState({
    name: '',
    category: '',
    manufacturer: '',
    model_number: '',
    serial_number: '',
    location: '',
    commissioned_on: '',
    status: 'operational' as EquipmentStatus,
    is_active: true,
  })

  const set = (k: keyof typeof form, v: unknown) =>
    setForm(prev => ({ ...prev, [k]: v }))

  const [touched, setTouched] = useState<Set<string>>(new Set())
  const touch = (k: string) => setTouched(prev => new Set([...prev, k]))

  const ve = useMemo(() => {
    const e: Record<string, string | undefined> = {}
    if (!form.name.trim()) e.name = 'Name is required.'
    return e
  }, [form])

  const fe = (k: string) => (touched.has(k) ? ve[k] : undefined)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setTouched(new Set(Object.keys(ve)))
    if (Object.keys(ve).length > 0) return
    try {
      const result = await createMut.mutateAsync({
        name: form.name,
        category: form.category || undefined,
        manufacturer: form.manufacturer || undefined,
        model_number: form.model_number || undefined,
        serial_number: form.serial_number || undefined,
        location: form.location || undefined,
        commissioned_on: form.commissioned_on || undefined,
        status: form.status,
        is_active: form.is_active,
      })
      navigate(`/maintenance/equipment/${result.data.ulid}`)
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Equipment" backTo="/maintenance/equipment" />

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded-lg p-6 space-y-5">
        {/* Name */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
          <input
            type="text"
            className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 ${fe('name') ? 'border-red-400' : 'border-neutral-300'}`}
            value={form.name}
            onChange={e => set('name', e.target.value)}
            onBlur={() => touch('name')}
            placeholder="e.g. Injection Moulding Machine #1"
            required
          />
          {fe('name') && <p className="mt-1 text-xs text-red-600">{fe('name')}</p>}
        </div>

        {/* Category & Status */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Category</label>
            <input
              type="text"
              list="equipment-categories"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.category}
              onChange={e => set('category', e.target.value)}
              placeholder="e.g. Production"
            />
            <datalist id="equipment-categories">
              {existingCategories.map(c => <option key={c} value={c} />)}
            </datalist>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Status *</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
              value={form.status}
              onChange={e => set('status', e.target.value as EquipmentStatus)}
            >
              <option value="operational">Operational</option>
              <option value="under_maintenance">Under Maintenance</option>
              <option value="decommissioned">Decommissioned</option>
            </select>
          </div>
        </div>

        {/* Manufacturer & Model */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Manufacturer</label>
            <input
              type="text"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.manufacturer}
              onChange={e => set('manufacturer', e.target.value)}
              placeholder="e.g. Engel"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Model No.</label>
            <input
              type="text"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.model_number}
              onChange={e => set('model_number', e.target.value)}
              placeholder="e.g. ES200/50"
            />
          </div>
        </div>

        {/* Serial & Location */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Serial No.</label>
            <input
              type="text"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.serial_number}
              onChange={e => set('serial_number', e.target.value)}
              placeholder="e.g. EM-2018-00123"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Location</label>
            <input
              type="text"
              list="equipment-locations"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.location}
              onChange={e => set('location', e.target.value)}
              placeholder="e.g. Production Floor A"
            />
            <datalist id="equipment-locations">
              {(warehouseLocations?.data ?? []).map((loc: { id: number; name: string }) => (
                <option key={loc.id} value={loc.name} />
              ))}
            </datalist>
          </div>
        </div>

        {/* Date Commissioned */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Date Commissioned</label>
            <input
              type="date"
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400"
              value={form.commissioned_on}
              onChange={e => set('commissioned_on', e.target.value)}
            />
          </div>
          <div className="flex items-end pb-2">
            <label className="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={e => set('is_active', e.target.checked)}
                className="rounded border-neutral-300"
              />
              Active
            </label>
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-3 pt-2">
          <button
            type="button"
            onClick={() => navigate('/maintenance/equipment')}
            className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Saving…' : 'Save'}
          </button>
        </div>
      </form>
    </div>
  )
}
