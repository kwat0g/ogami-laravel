import { useNavigate } from 'react-router-dom'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Trash2, Plus, AlertTriangle } from 'lucide-react'
import { useCreatePurchaseRequest } from '@/hooks/usePurchaseRequests'
import { useDepartments } from '@/hooks/useEmployees'
import { useVendors, useVendorItems } from '@/hooks/useAP'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import type { PurchaseRequestUrgency } from '@/types/procurement'

// ── Zod schema ────────────────────────────────────────────────────────────────

const itemSchema = z.object({
  vendor_item_id:      z.coerce.number().positive('Vendor item is required'),
  item_description:    z.string(),
  unit_of_measure:     z.string(),
  quantity:            z.coerce.number().gt(0, 'Must be > 0'),
  estimated_unit_cost: z.coerce.number().gt(0, 'Must be > 0'),
  specifications:      z.string().optional(),
})

const schema = z.object({
  vendor_id:      z.coerce.number().int().positive('Vendor is required'),
  department_id:  z.coerce.number().int().positive('Department is required'),
  urgency:        z.enum(['normal', 'urgent', 'critical']).default('normal'),
  justification:  z.string().min(20, 'Justification must be at least 20 characters'),
  notes:          z.string().optional(),
  items:          z.array(itemSchema).min(1, 'At least one line item is required'),
})

type FormValues = z.infer<typeof schema>

// ── Component ─────────────────────────────────────────────────────────────────

export default function CreatePurchaseRequestPage(): React.ReactElement {
  const navigate = useNavigate()
  const createPR = useCreatePurchaseRequest()
  const { data: deptData } = useDepartments()
  const departments = deptData?.data ?? []

  const {
    register,
    control,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: {
      urgency: 'normal',
      vendor_id: 0,
      items: [{ vendor_item_id: 0, item_description: '', unit_of_measure: '', quantity: 1, estimated_unit_cost: 0 }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  // Watch form values
  const watchedVendorId = watch('vendor_id')
  const items = watch('items')

  // Fetch vendors and vendor items
  const { data: vendorData } = useVendors({ is_active: true, per_page: 200 })
  const vendors = vendorData?.data ?? []
  const { data: vendorItems, isLoading: loadingItems } = useVendorItems(
    watchedVendorId > 0 ? watchedVendorId : null,
  )

  // Live total computation
  const grandTotal = items.reduce((sum, item) => {
    const qty  = Number(item.quantity)  || 0
    const cost = Number(item.estimated_unit_cost) || 0
    return sum + qty * cost
  }, 0)

  // Handle vendor item selection — auto-fill description, UoM, price
  const handleVendorItemSelect = (index: number, vendorItemId: number): void => {
    const vendorItem = vendorItems?.find(vi => vi.id === vendorItemId)
    if (!vendorItem) return

    setValue(`items.${index}.vendor_item_id`, vendorItem.id)
    setValue(`items.${index}.estimated_unit_cost`, vendorItem.unit_price_centavos / 100)
  }

  const onSubmit = async (values: FormValues): Promise<void> => {
    try {
      const pr = await createPR.mutateAsync({
        vendor_id:     values.vendor_id,
        department_id: values.department_id,
        urgency:       values.urgency as PurchaseRequestUrgency,
        justification: values.justification,
        notes:         values.notes,
        items:         values.items.map(item => ({
          vendor_item_id:      item.vendor_item_id,
          item_description:    item.item_description,
          unit_of_measure:     item.unit_of_measure,
          quantity:            item.quantity,
          estimated_unit_cost: item.estimated_unit_cost,
          specifications:      item.specifications,
        })),
      })
      toast.success(`Purchase Request ${pr.pr_reference} created as draft.`)
      navigate(`/procurement/purchase-requests/${pr.ulid}`)
    } catch {
      toast.error('Failed to create purchase request. Please try again.')
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title="New Purchase Request"
        backTo="/procurement/purchase-requests"
      />

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
        {/* Header Section */}
        <Card>
          <CardHeader>Request Details</CardHeader>
          <CardBody>
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-4">
                {/* Vendor (required — first selection) */}
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    Vendor <span className="text-red-500">*</span>
                  </label>
                  <Controller
                    control={control}
                    name="vendor_id"
                    render={({ field }) => (
                      <select
                        {...field}
                        onChange={e => field.onChange(Number(e.target.value))}
                        className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                      >
                        <option value="">— Select Vendor —</option>
                        {vendors.map(v => (
                          <option key={v.id} value={v.id}>{v.name}</option>
                        ))}
                      </select>
                    )}
                  />
                  {errors.vendor_id && (
                    <p className="text-xs text-red-600 mt-1">{errors.vendor_id.message}</p>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    Department <span className="text-red-500">*</span>
                  </label>
                  <Controller
                    control={control}
                    name="department_id"
                    render={({ field }) => (
                      <select
                        {...field}
                        onChange={e => field.onChange(Number(e.target.value))}
                        className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                      >
                        <option value="">— Select Department —</option>
                        {departments.map(d => (
                          <option key={d.id} value={d.id}>{d.name}</option>
                        ))}
                      </select>
                    )}
                  />
                  {errors.department_id && (
                    <p className="text-xs text-red-600 mt-1">{errors.department_id.message}</p>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">
                    Urgency
                  </label>
                  <Controller
                    control={control}
                    name="urgency"
                    render={({ field }) => (
                      <select
                        {...field}
                        className="w-full text-sm border border-neutral-300 rounded px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                      >
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent</option>
                        <option value="critical">Critical</option>
                      </select>
                    )}
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Justification <span className="text-red-500">*</span>
                </label>
                <textarea
                  {...register('justification')}
                  rows={3}
                  placeholder="Explain why this purchase is needed (min. 20 characters)"
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                />
                {errors.justification && (
                  <p className="text-xs text-red-600 mt-1">{errors.justification.message}</p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Additional Notes
                </label>
                <textarea
                  {...register('notes')}
                  rows={2}
                  placeholder="Optional notes for approvers"
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 resize-none"
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Line Items */}
        <Card>
          <CardHeader
            action={
              <button
                type="button"
                onClick={() =>
                  append({
                    vendor_item_id: 0,
                    item_description: '',
                    unit_of_measure: '',
                    quantity: 1,
                    estimated_unit_cost: 0,
                  })
                }
                className="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
              >
                <Plus className="w-3.5 h-3.5" />
                Add Item
              </button>
            }
          >
            Line Items
            {watchedVendorId > 0 && vendorItems && (
              <span className="ml-2 text-xs font-normal text-neutral-400">
                ({vendorItems.length} items in vendor catalog)
              </span>
            )}
          </CardHeader>
          <CardBody>
            {!watchedVendorId || watchedVendorId <= 0 ? (
              <div className="flex items-center gap-2 text-amber-600 text-sm py-6 justify-center">
                <AlertTriangle className="w-4 h-4" />
                Please select a vendor first to load their item catalog.
              </div>
            ) : loadingItems ? (
              <div className="text-sm text-neutral-400 py-6 text-center">Loading vendor items…</div>
            ) : (
              <>
                {errors.items?.root && (
                  <p className="text-xs text-red-600 mb-3">{errors.items.root.message}</p>
                )}

                <div className="space-y-3">
                  {fields.map((field, index) => {
                    const qty  = Number(items[index]?.quantity)  || 0
                    const cost = Number(items[index]?.estimated_unit_cost) || 0
                    const lineTotal = qty * cost

                    return (
                      <div key={field.id} className="bg-neutral-50 rounded p-3 space-y-2">
                        <div className="grid grid-cols-12 gap-2 items-start">
                          {/* Item selection */}
                          <div className="col-span-4">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">Vendor Item *</p>}
                            <select
                              value={items[index]?.vendor_item_id ?? ''}
                              onChange={e => handleVendorItemSelect(index, Number(e.target.value))}
                              className="w-full text-sm border border-neutral-300 rounded px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
                            >
                              <option value="">— Select Item —</option>
                              {vendorItems?.map(vi => (
                                <option key={vi.id} value={vi.id}>
                                  {vi.item_code} — {vi.name} (₱{(vi.unit_price_centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })})
                                </option>
                              ))}
                            </select>
                            {errors.items?.[index]?.vendor_item_id && (
                              <p className="text-xs text-red-600 mt-0.5">
                                {errors.items[index]?.vendor_item_id?.message}
                              </p>
                            )}
                          </div>

                          {/* UoM */}
                          <div className="col-span-1">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">UoM</p>}
                            <div className="text-sm text-neutral-600 py-1.5 px-2 bg-neutral-100 rounded">
                              {items[index]?.unit_of_measure || '—'}
                            </div>
                          </div>

                          {/* Quantity */}
                          <div className="col-span-2">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">Qty *</p>}
                            <input
                              type="number"
                              step="0.001"
                              {...register(`items.${index}.quantity`)}
                              className="w-full text-sm border border-neutral-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            {errors.items?.[index]?.quantity && (
                              <p className="text-xs text-red-600 mt-0.5">
                                {errors.items[index]?.quantity?.message}
                              </p>
                            )}
                          </div>

                          {/* Unit Cost */}
                          <div className="col-span-2">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">Unit Cost</p>}
                            <div className="text-sm text-neutral-700 font-medium py-1.5 px-2 bg-neutral-100 rounded">
                              ₱{cost.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </div>
                          </div>

                          {/* Line Total */}
                          <div className="col-span-2">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1">Est. Total</p>}
                            <div className="text-sm text-neutral-700 font-medium py-1.5 px-2">
                              ₱{lineTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </div>
                          </div>

                          {/* Remove */}
                          <div className="col-span-1 flex items-end justify-center pb-1">
                            {index === 0 && <p className="text-xs text-neutral-500 mb-1 opacity-0">—</p>}
                            <button
                              type="button"
                              disabled={fields.length === 1}
                              onClick={() => remove(index)}
                              className="p-1 text-neutral-400 hover:text-red-500 disabled:opacity-30 transition-colors"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>

                        {/* Specifications */}
                        <div>
                          <input
                            {...register(`items.${index}.specifications`)}
                            placeholder="Specifications (optional)"
                            className="w-full text-sm border border-neutral-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-neutral-50"
                          />
                        </div>
                      </div>
                    )
                  })}
                </div>

                {/* Grand Total */}
                <div className="flex justify-end mt-4 pt-4 border-t border-neutral-200">
                  <div className="text-right">
                    <p className="text-xs text-neutral-500">Total Estimated Cost</p>
                    <p className="text-xl font-bold text-neutral-900 mt-0.5">
                      ₱{grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </p>
                  </div>
                </div>
              </>
            )}
          </CardBody>
        </Card>

        {/* Actions */}
        <div className="flex justify-end gap-3 pt-4">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-5 py-2.5 bg-white text-neutral-700 text-sm font-medium rounded border border-neutral-300 hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2.5 bg-neutral-900 text-white text-sm font-medium rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? 'Saving…' : 'Save Draft'}
          </button>
        </div>
      </form>
    </div>
  )
}
