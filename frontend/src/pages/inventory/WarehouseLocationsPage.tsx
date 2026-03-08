import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, AlertTriangle, X } from 'lucide-react'
import { toast } from 'sonner'
import { useWarehouseLocations, useCreateLocation, useUpdateLocation } from '@/hooks/useInventory'
import { useDepartments } from '@/hooks/useEmployees'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
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
  const { data: deptData }       = useDepartments(true)
  const departments              = deptData?.data ?? []

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
    reset({ code: loc.code, name: loc.name, zone: loc.zone ?? '', bin: loc.bin ?? '', department_id: loc.department_id ?? undefined })
    setEditing(loc)
    setShowForm(true)
  }

  const onSubmit = handleSubmit(async (values) => {
    try {
      if (editing) {
        await updateMutation.mutateAsync({ name: values.name, zone: values.zone, bin: values.bin, department_id: values.department_id ?? null })
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
    `w-full text-sm border rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${err ? 'border-red-400' : 'border-neutral-300'}`

  return (
    <div>
      {/* Filter */}
      <div className="mb-5">
        <label className="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
          <input
            type="checkbox"
            checked={showInactive}
            onChange={(e) => setShowInactive(e.target.checked)}
            className="rounded border-neutral-300"
          />
          Show inactive locations
        </label>
      </div>

      {isLoading && <p className="text-neutral-500 text-sm">Loading…</p>}
      {isError && (
        <div className="flex items-center gap-2 text-red-600 text-sm">
          <AlertTriangle className="w-4 h-4" /> Failed to load locations.
        </div>
      )}

      {!isLoading && !isError && (
        <Card>
          <CardHeader
            action={
              <button
                onClick={openCreate}
                className="flex items-center gap-2 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded"
              >
                <Plus className="w-4 h-4" /> New Location
              </button>
            }
          >
            Warehouse Locations
          </CardHeader>
          <CardBody className="p-0">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  {['Code', 'Name', 'Zone', 'Bin', 'Department', 'Status', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-medium text-neutral-600">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {(locations ?? []).length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-400 text-sm">
                      No locations found.
                    </td>
                  </tr>
                )}
                {(locations ?? []).map((loc) => (
                  <tr key={loc.id} className="hover:bg-neutral-50/50 transition-colors">
                    <td className="px-4 py-3 font-mono font-medium text-neutral-900">{loc.code}</td>
                    <td className="px-4 py-3 text-neutral-900">{loc.name}</td>
                    <td className="px-4 py-3 text-neutral-500">{loc.zone ?? '—'}</td>
                    <td className="px-4 py-3 text-neutral-500">{loc.bin ?? '—'}</td>
                    <td className="px-4 py-3 text-neutral-500">{loc.department?.name ?? '—'}</td>
                    <td className="px-4 py-3">
                      {loc.is_active
                        ? <StatusBadge className="bg-neutral-200 text-neutral-800">Active</StatusBadge>
                        : <StatusBadge className="bg-neutral-100 text-neutral-500">Inactive</StatusBadge>}
                    </td>
                    <td className="px-4 py-3">
                      <button onClick={() => openEdit(loc)} className="inline-block px-2 py-1 text-xs border border-neutral-300 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-400 hover:text-neutral-900 font-medium">
                        Edit
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {/* Slide-in form modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg w-full max-w-md p-6">
            <div className="flex items-center justify-between mb-5">
              <h2 className="text-lg font-semibold text-neutral-900">
                {editing ? 'Edit Location' : 'New Warehouse Location'}
              </h2>
              <button onClick={() => setShowForm(false)} className="p-1.5 hover:bg-neutral-100 rounded">
                <X className="w-4 h-4 text-neutral-500" />
              </button>
            </div>

            <form onSubmit={onSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Code *</label>
                <input
                  {...register('code')}
                  disabled={!!editing}
                  className={fieldCls(errors.code)}
                  placeholder="e.g. WH-A-01"
                />
                {errors.code && <p className="text-red-500 text-xs mt-1">{errors.code.message}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
                <input {...register('name')} className={fieldCls(errors.name)} placeholder="e.g. Raw Materials Area A" />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name.message}</p>}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Zone</label>
                  <input {...register('zone')} className={fieldCls()} placeholder="e.g. Zone A" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Bin/Rack</label>
                  <input {...register('bin')} className={fieldCls()} placeholder="e.g. B-02" />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Department <span className="text-neutral-400 font-normal">(optional)</span></label>
                <select
                  {...register('department_id', { setValueAs: (v) => v === '' ? undefined : Number(v) })}
                  className={fieldCls()}
                >
                  <option value="">— None (shared) —</option>
                  {departments.map((d) => (
                    <option key={d.id} value={d.id}>{d.name}</option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="px-4 py-2 text-sm font-medium text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || updateMutation.isPending}
                  className="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded disabled:opacity-50"
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
