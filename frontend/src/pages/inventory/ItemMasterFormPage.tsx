import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Save, ToggleLeft } from 'lucide-react'
import { toast } from 'sonner'
import { useVendors } from '@/hooks/useAP'
import {
  useItem,
  useCreateItem,
  useUpdateItem,
  useToggleItemActive,
  useItemCategories,
} from '@/hooks/useInventory'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import type { ItemMaster } from '@/types/inventory'

const schema = z.object({
  item_code:       z.string().optional(),
  category_id:     z.number({ required_error: 'Category is required' }),
  name:            z.string().min(2, 'Name must be at least 2 characters'),
  unit_of_measure: z.string().min(1, 'UOM is required'),
  description:     z.string().min(5, 'Description must be at least 5 characters'),
  standard_price:  z.number().min(0, 'Price cannot be negative').default(0),
  reorder_point:   z.number().min(0).default(0),
  reorder_qty:     z.number().min(0).default(0),
  type:                z.enum(['raw_material', 'semi_finished', 'finished_good', 'consumable', 'spare_part']),
  requires_iqc:        z.boolean().default(false),
  preferred_vendor_id: z.number().nullable().optional(),
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
  const { data: vendorData }   = useVendors({ accreditation_status: 'accredited', is_active: true, per_page: 500 })
  const vendors                = vendorData?.data ?? []
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
        item_code:       item.item_code,
        category_id:     item.category_id,
        name:            item.name,
        unit_of_measure: item.unit_of_measure,
        description:     item.description ?? '',
        standard_price:  (item.standard_price_centavos ?? 0) / 100,
        reorder_point:   parseFloat(item.reorder_point),
        reorder_qty:         parseFloat(item.reorder_qty),
        type:                item.type,
        requires_iqc:        item.requires_iqc,
        preferred_vendor_id: item.preferred_vendor_id ?? null,
      })
    }
  }, [item, reset])

  const onSubmit = handleSubmit(async (values) => {
    try {
      // Convert peso amount to centavos for the backend
      const payload = {
        ...values,
        standard_price_centavos: Math.round((values.standard_price ?? 0) * 100),
      }
      // Remove the peso field -- backend expects centavos
      delete (payload as Record<string, unknown>).standard_price

      if (isEdit) {
        await updateMutation.mutateAsync(payload)
        toast.success('Item updated.')
      } else {
        await createMutation.mutateAsync(payload)
        toast.success('Item created.')
        navigate('/inventory/items')
      }
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  })

  const handleToggle = async () => {
    try {
      await toggleMutation.mutateAsync()
      toast.success(item?.is_active ? 'Item deactivated.' : 'Item activated.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const fieldCls = (err?: { message?: string }) =>
    `w-full text-sm border rounded px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 ${err ? 'border-red-400' : 'border-neutral-300'}`

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title={isEdit ? 'Edit Item' : 'New Item'}
        backTo="/inventory/items"
      />

      <Card>
        <CardHeader>Item Information</CardHeader>
        <CardBody>
          <form onSubmit={onSubmit} className="space-y-5">
            {/* Item Code — auto-generated on create, read-only on edit */}
            {isEdit && (
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Item Code</label>
                <input
                  value={item?.item_code ?? ''}
                  disabled
                  className="w-full text-sm border rounded px-3 py-2.5 bg-neutral-50 border-neutral-200 text-neutral-500 font-mono"
                />
                <p className="text-xs text-neutral-400 mt-1">Item code cannot be changed after creation.</p>
              </div>
            )}
            {!isEdit && (
              <div className="bg-blue-50 border border-blue-200 rounded px-3 py-2.5 text-sm text-blue-700">
                Item code will be auto-generated based on the item type (e.g., RM-00001, FG-00001, SP-00001).
              </div>
            )}

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

            {/* Standard Price */}
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Standard Price (PHP)</label>
              <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 text-sm">₱</span>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  {...register('standard_price', { valueAsNumber: true })}
                  className={`${fieldCls(errors.standard_price)} pl-7`}
                  placeholder="0.00"
                />
              </div>
              {errors.standard_price && <p className="text-red-500 text-xs mt-1">{errors.standard_price.message}</p>}
              <p className="text-xs text-neutral-400 mt-1">Unit cost used for BOM calculations and inventory valuation. Auto-updated on goods receipt.</p>
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
              <label className="block text-sm font-medium text-neutral-700 mb-1">Description *</label>
              <textarea
                {...register('description')}
                rows={3}
                className={fieldCls(errors.description)}
                placeholder="Describe the item (min 5 characters)"
              />
              {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description.message}</p>}
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

            {/* Preferred Vendor */}
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Preferred Vendor
                <span className="text-xs font-normal text-neutral-400 ml-2">(optional — auto-suggested in PRs)</span>
              </label>
              <Controller
                control={control}
                name="preferred_vendor_id"
                render={({ field }) => (
                  <select
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : null)}
                    className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  >
                    <option value="">— No preferred vendor —</option>
                    {vendors.map((v) => (
                      <option key={v.id} value={v.id}>{v.name}</option>
                    ))}
                  </select>
                )}
              />
            </div>

            {/* Actions */}
            <div className="flex items-center justify-between pt-4 border-t border-neutral-100">
              {isEdit && item && (
                <ConfirmDialog
                  title={item.is_active ? 'Deactivate item?' : 'Activate item?'}
                  description={
                    item.is_active
                      ? 'This item will no longer be available for new transactions. Existing records remain unchanged.'
                      : 'This item will become available for new transactions.'
                  }
                  confirmLabel={item.is_active ? 'Deactivate' : 'Activate'}
                  onConfirm={handleToggle}
                >
                  <button
                    type="button"
                    disabled={toggleMutation.isPending}
                    className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <ToggleLeft className="w-4 h-4" />
                    {item.is_active ? 'Deactivate' : 'Activate'}
                  </button>
                </ConfirmDialog>
              )}
              <div className="ml-auto flex gap-3">
                <button
                  type="button"
                  onClick={() => navigate('/inventory/items')}
                  className="px-5 py-2.5 bg-white text-neutral-700 text-sm font-medium rounded border border-neutral-300 hover:bg-neutral-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={!isDirty || createMutation.isPending || updateMutation.isPending}
                  className="flex items-center gap-2 px-5 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <Save className="w-4 h-4" />
                  Save
                </button>
              </div>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  )
}
