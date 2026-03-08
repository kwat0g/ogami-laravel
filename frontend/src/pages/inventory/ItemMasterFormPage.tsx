import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Save, ToggleLeft } from 'lucide-react'
import { toast } from 'sonner'
import {
  useItem,
  useCreateItem,
  useUpdateItem,
  useToggleItemActive,
  useItemCategories,
} from '@/hooks/useInventory'
import type { ItemMaster } from '@/types/inventory'

const schema = z.object({
  category_id:     z.number({ required_error: 'Category is required' }),
  name:            z.string().min(2, 'Name must be at least 2 characters'),
  unit_of_measure: z.string().min(1, 'UOM is required'),
  description:     z.string().optional(),
  reorder_point:   z.number().min(0).default(0),
  reorder_qty:     z.number().min(0).default(0),
  type:            z.enum(['raw_material', 'semi_finished', 'finished_good', 'consumable', 'spare_part']),
  requires_iqc:    z.boolean().default(false),
})

type FormValues = z.infer<typeof schema>

const TYPE_OPTIONS: { value: ItemMaster['type']; label: string }[] = [
  { value: 'raw_material',  label: 'Raw Material' },
  { value: 'semi_finished', label: 'Semi-Finished' },
  { value: 'finished_good', label: 'Finished Good' },
  { value: 'consumable',    label: 'Consumable' },
  { value: 'spare_part',    label: 'Spare Part' },
]

export default function ItemMasterFormPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid?: string }>()
  const navigate = useNavigate()
  const isEdit = ulid !== undefined && ulid !== 'new'

  const { data: item }         = useItem(isEdit ? ulid : null)
  const { data: categories }   = useItemCategories()
  const createMutation         = useCreateItem()
  const updateMutation         = useUpdateItem(ulid ?? '')
  const toggleMutation         = useToggleItemActive(ulid ?? '')

  const { register, handleSubmit, control, reset, formState: { errors, isDirty } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: { requires_iqc: false, reorder_point: 0, reorder_qty: 0 },
  })

  useEffect(() => {
    if (item) {
      reset({
        category_id:     item.category_id,
        name:            item.name,
        unit_of_measure: item.unit_of_measure,
        description:     item.description ?? '',
        reorder_point:   parseFloat(item.reorder_point),
        reorder_qty:     parseFloat(item.reorder_qty),
        type:            item.type,
        requires_iqc:    item.requires_iqc,
      })
    }
  }, [item, reset])

  const onSubmit = handleSubmit(async (values) => {
    try {
      if (isEdit) {
        await updateMutation.mutateAsync(values)
        toast.success('Item updated.')
      } else {
        await createMutation.mutateAsync(values)
        toast.success('Item created.')
        navigate('/inventory/items')
      }
    } catch {
      toast.error('Failed to save item.')
    }
  })

  const handleToggle = async () => {
    try {
      await toggleMutation.mutateAsync()
      toast.success(item?.is_active ? 'Item deactivated.' : 'Item activated.')
    } catch {
      toast.error('Failed to toggle status.')
    }
  }

  const fieldCls = (err?: { message?: string }) =>
    `w-full text-sm border rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${err ? 'border-red-400' : 'border-neutral-300'}`

  return (
    <div className="max-w-2xl">
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">{isEdit ? 'Edit Item' : 'New Item'}</h1>

      <form onSubmit={onSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        {/* Category */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Category *</label>
          <Controller
            control={control}
            name="category_id"
            render={({ field }) => (
              <select
                {...field}
                value={field.value ?? ''}
                onChange={(e) => field.onChange(Number(e.target.value))}
                className={fieldCls(errors.category_id)}
              >
                <option value="">Select category…</option>
                {categories?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            )}
          />
          {errors.category_id && <p className="text-red-500 text-xs mt-1">{errors.category_id.message}</p>}
        </div>

        {/* Name */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
          <input {...register('name')} className={fieldCls(errors.name)} placeholder="e.g. Steel Rod 40mm" />
          {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name.message}</p>}
        </div>

        {/* Type */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Type *</label>
          <Controller
            control={control}
            name="type"
            render={({ field }) => (
              <select {...field} className={fieldCls(errors.type)}>
                <option value="">Select type…</option>
                {TYPE_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            )}
          />
          {errors.type && <p className="text-red-500 text-xs mt-1">{errors.type.message}</p>}
        </div>

        {/* UOM */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Unit of Measure *</label>
          <input {...register('unit_of_measure')} className={fieldCls(errors.unit_of_measure)} placeholder="e.g. pcs, kg, L" />
          {errors.unit_of_measure && <p className="text-red-500 text-xs mt-1">{errors.unit_of_measure.message}</p>}
        </div>

        {/* Reorder */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Reorder Point</label>
            <input
              type="number"
              step="0.0001"
              {...register('reorder_point', { valueAsNumber: true })}
              className={fieldCls(errors.reorder_point)}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Reorder Qty</label>
            <input
              type="number"
              step="0.0001"
              {...register('reorder_qty', { valueAsNumber: true })}
              className={fieldCls(errors.reorder_qty)}
            />
          </div>
        </div>

        {/* Description */}
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Description</label>
          <textarea
            {...register('description')}
            rows={3}
            className={fieldCls(errors.description)}
            placeholder="Optional notes about this item"
          />
        </div>

        {/* Requires IQC */}
        <div>
          <label className="flex items-center gap-3 cursor-pointer">
            <Controller
              control={control}
              name="requires_iqc"
              render={({ field }) => (
                <input
                  type="checkbox"
                  checked={field.value}
                  onChange={(e) => field.onChange(e.target.checked)}
                  className="rounded border-neutral-300"
                />
              )}
            />
            <span className="text-sm font-medium text-neutral-700">Requires Incoming Quality Control (IQC)</span>
          </label>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-between pt-2 border-t border-neutral-100">
          {isEdit && item && (
            <button
              type="button"
              onClick={handleToggle}
              disabled={toggleMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-50"
            >
              <ToggleLeft className="w-4 h-4" />
              {item.is_active ? 'Deactivate' : 'Activate'}
            </button>
          )}
          <div className="ml-auto flex gap-3">
            <button
              type="button"
              onClick={() => navigate('/inventory/items')}
              className="px-4 py-2 text-sm font-medium text-neutral-600 border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!isDirty || createMutation.isPending || updateMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium rounded disabled:opacity-50"
            >
              <Save className="w-4 h-4" />
              Save
            </button>
          </div>
        </div>
      </form>
    </div>
  )
}
