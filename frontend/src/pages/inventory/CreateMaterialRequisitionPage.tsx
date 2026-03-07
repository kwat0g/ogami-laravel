import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useFieldArray, useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ClipboardList, ArrowLeft, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateMRQ, useItems } from '@/hooks/useInventory'
import { useDepartments } from '@/hooks/useEmployees'

const schema = z.object({
  department_id: z.number({ required_error: 'Department is required' }),
  purpose:       z.string().min(10, 'Purpose must be at least 10 characters'),
  items: z.array(z.object({
    item_id:       z.number({ required_error: 'Item is required' }),
    qty_requested: z.number({ required_error: 'Qty is required' }).positive('Must be > 0'),
    remarks:       z.string().optional(),
  })).min(1, 'At least one item is required'),
})

type FormValues = z.infer<typeof schema>

export default function CreateMaterialRequisitionPage(): React.ReactElement {
  const navigate      = useNavigate()
  const createMRQ     = useCreateMRQ()
  const { data: deptData } = useDepartments(true)
  const [itemSearch, setItemSearch] = useState('')

  const { data: itemsData } = useItems({ search: itemSearch || undefined, is_active: true, per_page: 50 })

  const { register, control, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: { items: [{ item_id: 0, qty_requested: 1, remarks: '' }] },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  const onSubmit = handleSubmit(async (values) => {
    try {
      const res = await createMRQ.mutateAsync(values)
      toast.success('Material requisition created.')
      // Navigate to the detail page
      const created = (res.data as { data: { ulid: string } }).data
      navigate(`/inventory/requisitions/${created.ulid}`)
    } catch {
      toast.error('Failed to create requisition.')
    }
  })

  const fieldCls = (err?: { message?: string }) =>
    `w-full text-sm border rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-500 ${err ? 'border-red-400' : 'border-gray-300'}`

  const departments = deptData?.data ?? []
  const items       = itemsData?.data ?? []

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <button onClick={() => navigate('/inventory/requisitions')} className="p-2 hover:bg-gray-100 rounded-lg transition-colors">
          <ArrowLeft className="w-4 h-4 text-gray-500" />
        </button>
        <div className="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center">
          <ClipboardList className="w-5 h-5 text-teal-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">New Material Requisition</h1>
          <p className="text-sm text-gray-500 mt-0.5">Request materials from warehouse stock</p>
        </div>
      </div>

      <form onSubmit={onSubmit} className="space-y-5">
        {/* Header fields */}
        <div className="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
          <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Request Details</h2>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Department *</label>
            <Controller
              control={control}
              name="department_id"
              render={({ field }) => (
                <select
                  {...field}
                  value={field.value ?? ''}
                  onChange={(e) => field.onChange(Number(e.target.value))}
                  className={fieldCls(errors.department_id)}
                >
                  <option value="">Select department…</option>
                  {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                </select>
              )}
            />
            {errors.department_id && <p className="text-red-500 text-xs mt-1">{errors.department_id.message}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Purpose *</label>
            <textarea
              {...register('purpose')}
              rows={3}
              className={fieldCls(errors.purpose)}
              placeholder="Describe why materials are needed (min 10 characters)"
            />
            {errors.purpose && <p className="text-red-500 text-xs mt-1">{errors.purpose.message}</p>}
          </div>
        </div>

        {/* Items */}
        <div className="bg-white border border-gray-200 rounded-xl p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Requested Items</h2>
            <div className="flex items-center gap-3">
              <input
                type="text"
                placeholder="Search items…"
                value={itemSearch}
                onChange={(e) => setItemSearch(e.target.value)}
                className="text-xs border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-500 w-40"
              />
              <button
                type="button"
                onClick={() => append({ item_id: 0, qty_requested: 1, remarks: '' })}
                className="flex items-center gap-1.5 text-xs px-3 py-2 border border-teal-300 text-teal-700 rounded-lg hover:bg-teal-50"
              >
                <Plus className="w-3.5 h-3.5" /> Add Line
              </button>
            </div>
          </div>

          {errors.items && typeof errors.items.message === 'string' && (
            <p className="text-red-500 text-xs mb-3">{errors.items.message}</p>
          )}

          <div className="space-y-3">
            {fields.map((field, idx) => (
              <div key={field.id} className="grid grid-cols-12 gap-2 items-start">
                {/* Item select */}
                <div className="col-span-5">
                  <Controller
                    control={control}
                    name={`items.${idx}.item_id`}
                    render={({ field: f }) => (
                      <select
                        {...f}
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(Number(e.target.value))}
                        className={`w-full text-sm border rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-500 ${errors.items?.[idx]?.item_id ? 'border-red-400' : 'border-gray-300'}`}
                      >
                        <option value={0}>Select item…</option>
                        {items.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.item_code} — {item.name} ({item.unit_of_measure})
                          </option>
                        ))}
                      </select>
                    )}
                  />
                </div>

                {/* Qty */}
                <div className="col-span-2">
                  <input
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    {...register(`items.${idx}.qty_requested`, { valueAsNumber: true })}
                    placeholder="Qty"
                    className={`w-full text-sm border rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-500 ${errors.items?.[idx]?.qty_requested ? 'border-red-400' : 'border-gray-300'}`}
                  />
                </div>

                {/* Remarks */}
                <div className="col-span-4">
                  <input
                    {...register(`items.${idx}.remarks`)}
                    placeholder="Remarks (optional)"
                    className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-500"
                  />
                </div>

                {/* Remove */}
                <div className="col-span-1 flex items-center justify-center pt-1">
                  {fields.length > 1 && (
                    <button
                      type="button"
                      onClick={() => remove(idx)}
                      className="p-1.5 hover:bg-red-50 rounded-lg text-red-400 hover:text-red-600 transition-colors"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate('/inventory/requisitions')}
            className="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMRQ.isPending}
            className="px-6 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
          >
            {createMRQ.isPending ? 'Saving…' : 'Create Requisition'}
          </button>
        </div>
      </form>
    </div>
  )
}
