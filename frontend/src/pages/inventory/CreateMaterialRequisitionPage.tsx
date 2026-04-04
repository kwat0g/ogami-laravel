import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useFieldArray, useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Trash2, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { useCreateMRQ, useItems } from '@/hooks/useInventory'
import { useDepartments } from '@/hooks/useEmployees'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { firstErrorMessage } from '@/lib/errorHandler'

const itemSchema = z.object({
  item_id:       z.number({ required_error: 'Item is required' }).positive('Please select an item'),
  qty_requested: z.number({ required_error: 'Qty is required' }).positive('Must be greater than 0'),
  remarks:       z.string().optional(),
})

const schema = z.object({
  department_id: z.number({ required_error: 'Department is required' }).positive('Please select a department'),
  purpose:       z.string().min(10, 'Purpose must be at least 10 characters'),
  items: z.array(itemSchema).min(1, 'At least one item is required'),
})

type FormValues = z.infer<typeof schema>

export default function CreateMaterialRequisitionPage(): React.ReactElement {
  const navigate      = useNavigate()
  const createMRQ     = useCreateMRQ()
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('inventory.mrq.create')
  const { data: deptData } = useDepartments(true)
  const [itemSearch, setItemSearch] = useState('')

  const { data: itemsData } = useItems({ search: itemSearch || undefined, is_active: true, per_page: 50, exclude_type: 'finished_good' })

  const { register, control, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: { items: [{ item_id: 0, qty_requested: 1, remarks: '' }] },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  const onSubmit = handleSubmit(async (values) => {
    // Additional validation for items
    const invalidItems = values.items.some(item => item.item_id <= 0 || item.qty_requested <= 0)
    if (invalidItems) {
      return
    }

    try {
      const res = await createMRQ.mutateAsync(values)
      toast.success('Material requisition created.')
      // Navigate to the detail page
      const created = (res.data as { data: { ulid: string } }).data
      navigate(`/inventory/requisitions/${created.ulid}`)
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  })

  const fieldCls = (err?: { message?: string }) =>
    `w-full text-sm border rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${err ? 'border-red-400' : 'border-neutral-300'}`

  const departments = deptData?.data ?? []
  const items       = itemsData?.data ?? []

  if (!canCreate) {
    return (
      <div className="max-w-4xl mx-auto">
        <PageHeader
          title="New Material Requisition"
          backTo="/inventory/requisitions"
        />
        <div className="flex flex-col items-center justify-center py-20 text-neutral-500">
          <AlertTriangle className="w-10 h-10 mb-3 text-neutral-400" />
          <p className="text-sm font-medium">You do not have permission to create material requisitions.</p>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title="New Material Requisition"
        backTo="/inventory/requisitions"
      />

      <form onSubmit={onSubmit} className="space-y-5">
        {/* Header fields */}
        <Card>
          <CardHeader>Request Details</CardHeader>
          <CardBody>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Department *</label>
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
                <label className="block text-sm font-medium text-neutral-700 mb-1">Purpose *</label>
                <textarea
                  {...register('purpose')}
                  rows={3}
                  className={fieldCls(errors.purpose)}
                  placeholder="Describe why materials are needed (min 10 characters)"
                />
                {errors.purpose && <p className="text-red-500 text-xs mt-1">{errors.purpose.message}</p>}
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Items */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between w-full">
              <span>Requested Items *</span>
              <div className="flex items-center gap-3">
                <input
                  type="text"
                  placeholder="Search items…"
                  value={itemSearch}
                  onChange={(e) => setItemSearch(e.target.value)}
                  className="text-xs border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 w-40"
                />
                <button
                  type="button"
                  onClick={() => append({ item_id: 0, qty_requested: 1, remarks: '' })}
                  className="flex items-center gap-1.5 text-xs px-3 py-2 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
                >
                  <Plus className="w-3.5 h-3.5" /> Add Line
                </button>
              </div>
            </div>
          </CardHeader>
          <CardBody>
            {errors.items && typeof errors.items.message === 'string' && (
              <div className="flex items-center gap-2 text-red-500 text-xs mb-3 p-2 bg-red-50 rounded border border-red-200">
                <AlertTriangle className="w-4 h-4" />
                {errors.items.message}
              </div>
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
                          className={`w-full text-sm border rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${errors.items?.[idx]?.item_id ? 'border-red-400' : 'border-neutral-300'}`}
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
                    {errors.items?.[idx]?.item_id && (
                      <p className="text-red-500 text-xs mt-1">{errors.items[idx]?.item_id?.message}</p>
                    )}
                  </div>

                  {/* Qty */}
                  <div className="col-span-2">
                    <input
                      type="number"
                      step="0.0001"
                      min="0.0001"
                      {...register(`items.${idx}.qty_requested`, { valueAsNumber: true })}
                      placeholder="Qty *"
                      className={`w-full text-sm border rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none ${errors.items?.[idx]?.qty_requested ? 'border-red-400' : 'border-neutral-300'}`}
                    />
                    {errors.items?.[idx]?.qty_requested && (
                      <p className="text-red-500 text-xs mt-1">{errors.items[idx]?.qty_requested?.message}</p>
                    )}
                  </div>

                  {/* Remarks */}
                  <div className="col-span-4">
                    <input
                      {...register(`items.${idx}.remarks`)}
                      placeholder="Remarks (optional)"
                      className="w-full text-sm border border-neutral-300 rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                    />
                  </div>

                  {/* Remove */}
                  <div className="col-span-1 flex items-center justify-center pt-1">
                    {fields.length > 1 && (
                      <button
                        type="button"
                        onClick={() => remove(idx)}
                        className="p-1.5 hover:bg-red-50 rounded text-neutral-400 hover:text-red-500"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>

        {/* Submit */}
        <div className="flex justify-end gap-3 pt-4">
          <button
            type="button"
            onClick={() => navigate('/inventory/requisitions')}
            className="px-5 py-2.5 bg-white text-neutral-700 text-sm font-medium rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting || !canCreate}
            className="px-6 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? 'Saving…' : 'Create Requisition'}
          </button>
        </div>
      </form>
    </div>
  )
}
