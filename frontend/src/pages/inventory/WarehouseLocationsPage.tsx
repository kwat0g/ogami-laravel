import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { MapPin, Plus, AlertTriangle, X } from 'lucide-react'
import { toast } from 'sonner'
import { useWarehouseLocations, useCreateLocation, useUpdateLocation } from '@/hooks/useInventory'
import type { WarehouseLocation } from '@/types/inventory'

const schema = z.object({
  code:          z.string().min(2, 'Code is required (min 2 chars)'),
  name:          z.string().min(2, 'Name is required'),
  zone:          z.string().optional(),
  bin:           z.string().optional(),
  department_id: z.number().optional(),
})

type FormValues = z.infer<typeof schema>

export default function WarehouseLocationsPage(): React.ReactElement {
  const [showForm, setShowForm]             = useState(false)
  const [editing, setEditing]               = useState<WarehouseLocation | null>(null)
  const [showInactive, setShowInactive]     = useState(false)

  const { data: locations, isLoading, isError } = useWarehouseLocations({
    is_active: showInactive ? undefined : true,
  })

  const createMutation = useCreateLocation()
  const updateMutation = useUpdateLocation(editing?.id ?? 0)

  const { register, handleSubmit, reset, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
  })

  const openCreate = () => {
    reset({})
    setEditing(null)
    setShowForm(true)
  }

  const openEdit = (loc: WarehouseLocation) => {
    reset({ code: loc.code, name: loc.name, zone: loc.zone ?? '', bin: loc.bin ?? '' })
    setEditing(loc)
    setShowForm(true)
  }

  const onSubmit = handleSubmit(async (values) => {
    try {
      if (editing) {
        await updateMutation.mutateAsync({ name: values.name, zone: values.zone, bin: values.bin })
        toast.success('Location updated.')
      } else {
        await createMutation.mutateAsync(values)
        toast.success('Location created.')
      }
      setShowForm(false)
    } catch {
      toast.error('Failed to save location.')
    }
  })

  const fieldCls = (err?: { message?: string }) =>
    `w-full text-sm border rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-500 ${err ? 'border-red-400' : 'border-gray-300'}`

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
            <MapPin className="w-5 h-5 text-teal-600" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Warehouse Locations</h1>
            <p className="text-sm text-gray-500 mt-0.5">Zones, bins, and storage areas</p>
          </div>
        </div>
        <button
          onClick={openCreate}
          className="flex items-center gap-2 px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-xl transition-colors"
        >
          <Plus className="w-4 h-4" /> New Location
        </button>
      </div>

      {/* Filter */}
      <div className="mb-4">
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input
            type="checkbox"
            checked={showInactive}
            onChange={(e) => setShowInactive(e.target.checked)}
            className="rounded border-gray-300 text-teal-600 focus:ring-teal-500"
          />
          Show inactive locations
        </label>
      </div>

      {isLoading && <p className="text-gray-500 text-sm">Loading…</p>}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load locations.
        </div>
      )}

      {!isLoading && !isError && (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {['Code', 'Name', 'Zone', 'Bin', 'Department', 'Status', ''].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(locations ?? []).length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-gray-400 text-sm">
                    No locations found.
                  </td>
                </tr>
              )}
              {(locations ?? []).map((loc) => (
                <tr key={loc.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono font-medium text-teal-700">{loc.code}</td>
                  <td className="px-4 py-3 text-gray-900">{loc.name}</td>
                  <td className="px-4 py-3 text-gray-500">{loc.zone ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{loc.bin ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{loc.department?.name ?? '—'}</td>
                  <td className="px-4 py-3">
                    {loc.is_active
                      ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>
                      : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>}
                  </td>
                  <td className="px-4 py-3">
                    <button onClick={() => openEdit(loc)} className="text-xs text-teal-600 hover:text-teal-800 font-medium">
                      Edit
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Slide-in form modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div className="flex items-center justify-between mb-5">
              <h2 className="text-lg font-semibold text-gray-900">
                {editing ? 'Edit Location' : 'New Warehouse Location'}
              </h2>
              <button onClick={() => setShowForm(false)} className="p-1.5 hover:bg-gray-100 rounded-lg">
                <X className="w-4 h-4 text-gray-500" />
              </button>
            </div>

            <form onSubmit={onSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Code *</label>
                <input
                  {...register('code')}
                  disabled={!!editing}
                  className={fieldCls(errors.code)}
                  placeholder="e.g. WH-A-01"
                />
                {errors.code && <p className="text-red-500 text-xs mt-1">{errors.code.message}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input {...register('name')} className={fieldCls(errors.name)} placeholder="e.g. Raw Materials Area A" />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name.message}</p>}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                  <input {...register('zone')} className={fieldCls()} placeholder="e.g. Zone A" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Bin/Rack</label>
                  <input {...register('bin')} className={fieldCls()} placeholder="e.g. B-02" />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || updateMutation.isPending}
                  className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                >
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
