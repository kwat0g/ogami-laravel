import { useNavigate } from 'react-router-dom'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Plus, Trash2, ShoppingCart } from 'lucide-react'
import { useCreatePurchaseOrder } from '@/hooks/usePurchaseOrders'
import { usePurchaseRequests } from '@/hooks/usePurchaseRequests'
import { useVendors } from '@/hooks/useAP'

const itemSchema = z.object({
  po_item_id:        z.number().optional(),
  item_description:  z.string().min(3, 'Description required'),
  quantity_ordered:  z.coerce.number().min(1),
  unit_of_measure:   z.string().min(1, 'UOM required'),
  agreed_unit_cost:  z.coerce.number().min(0.01),
})

const schema = z.object({
  purchase_request_id: z.coerce.number().min(1, 'Select a Purchase Request'),
  vendor_id:           z.coerce.number().min(1, 'Select a vendor'),
  delivery_date:       z.string().min(1, 'Delivery date required'),
  payment_terms:       z.string().min(3, 'Payment terms required'),
  delivery_address:    z.string().optional(),
  notes:               z.string().optional(),
  items:               z.array(itemSchema).min(1, 'At least one item required'),
})

type FormValues = z.infer<typeof schema>

export default function CreatePurchaseOrderPage(): React.ReactElement {
  const navigate = useNavigate()
  const createPO = useCreatePurchaseOrder()

  const { data: prData }     = usePurchaseRequests({ status: 'approved' })
  const { data: vendorData } = useVendors({ per_page: 200 })

  const {
    register,
    control,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      items: [{ item_description: '', quantity_ordered: 1, unit_of_measure: 'pcs', agreed_unit_cost: 0 }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  const items       = watch('items') ?? []
  const grandTotal  = items.reduce((sum, item) => sum + (Number(item.quantity_ordered) || 0) * (Number(item.agreed_unit_cost) || 0), 0)

  const onSubmit = async (values: FormValues): Promise<void> => {
    try {
      const po = await createPO.mutateAsync({
        purchase_request_id: values.purchase_request_id,
        vendor_id:           values.vendor_id,
        delivery_date:       values.delivery_date,
        payment_terms:       values.payment_terms,
        delivery_address:    values.delivery_address,
        notes:               values.notes,
        items:               values.items.map((it) => ({
          item_description: it.item_description,
          quantity_ordered: it.quantity_ordered,
          unit_of_measure:  it.unit_of_measure,
          agreed_unit_cost: it.agreed_unit_cost,
        })),
      })
      toast.success('Purchase Order created.')
      navigate(`/procurement/purchase-orders/${po.ulid}`)
    } catch {
      toast.error('Failed to create purchase order.')
    }
  }

  return (
    <div className="max-w-4xl">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
          <ShoppingCart className="w-5 h-5 text-purple-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Create Purchase Order</h1>
          <p className="text-sm text-gray-500 mt-0.5">Issue an order to a supplier</p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* PR & Vendor */}
        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
          <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Order Details</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Purchase Request */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Purchase Request</label>
              <select
                {...register('purchase_request_id')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white"
              >
                <option value="">— Select Approved PR —</option>
                {prData?.data?.map((pr) => (
                  <option key={pr.id} value={pr.id}>{pr.pr_reference}</option>
                ))}
              </select>
              {errors.purchase_request_id && (
                <p className="text-red-500 text-xs mt-1">{errors.purchase_request_id.message}</p>
              )}
            </div>

            {/* Vendor */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
              <select
                {...register('vendor_id')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white"
              >
                <option value="">— Select Vendor —</option>
                {vendorData?.data?.map((v: { id: number; name: string }) => (
                  <option key={v.id} value={v.id}>{v.name}</option>
                ))}
              </select>
              {errors.vendor_id && (
                <p className="text-red-500 text-xs mt-1">{errors.vendor_id.message}</p>
              )}
            </div>

            {/* Delivery Date */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Delivery Date</label>
              <input
                type="date"
                {...register('delivery_date')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
              {errors.delivery_date && (
                <p className="text-red-500 text-xs mt-1">{errors.delivery_date.message}</p>
              )}
            </div>

            {/* Payment Terms */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Payment Terms</label>
              <input
                type="text"
                placeholder="e.g. Net 30"
                {...register('payment_terms')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
              {errors.payment_terms && (
                <p className="text-red-500 text-xs mt-1">{errors.payment_terms.message}</p>
              )}
            </div>

            {/* Delivery Address */}
            <div className="sm:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">Delivery Address <span className="text-gray-400 font-normal">(optional)</span></label>
              <input
                type="text"
                {...register('delivery_address')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>

            {/* Notes */}
            <div className="sm:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">Notes <span className="text-gray-400 font-normal">(optional)</span></label>
              <textarea
                rows={2}
                {...register('notes')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none"
              />
            </div>
          </div>
        </div>

        {/* Line Items */}
        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Line Items</h2>
            <button
              type="button"
              onClick={() => append({ item_description: '', quantity_ordered: 1, unit_of_measure: 'pcs', agreed_unit_cost: 0 })}
              className="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Item
            </button>
          </div>

          <div className="space-y-3">
            {fields.map((field, idx) => (
              <div key={field.id} className="grid grid-cols-12 gap-2 items-start border border-gray-100 rounded-lg p-3 bg-gray-50">
                <div className="col-span-4">
                  <input placeholder="Description" {...register(`items.${idx}.item_description`)} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400" />
                  {errors.items?.[idx]?.item_description && <p className="text-red-500 text-xs mt-0.5">{errors.items[idx]!.item_description!.message}</p>}
                </div>
                <div className="col-span-2">
                  <Controller
                    name={`items.${idx}.quantity_ordered`}
                    control={control}
                    render={({ field: f }) => (
                      <input type="number" min={1} placeholder="Qty" {...f} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400" />
                    )}
                  />
                </div>
                <div className="col-span-2">
                  <input placeholder="UOM" {...register(`items.${idx}.unit_of_measure`)} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400" />
                </div>
                <div className="col-span-3">
                  <Controller
                    name={`items.${idx}.agreed_unit_cost`}
                    control={control}
                    render={({ field: f }) => (
                      <input type="number" min={0} step="0.01" placeholder="Unit Price" {...f} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400" />
                    )}
                  />
                </div>
                <div className="col-span-1 flex justify-center pt-1.5">
                  {fields.length > 1 && (
                    <button type="button" onClick={() => remove(idx)} className="text-red-400 hover:text-red-600">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>

          {errors.items?.root && <p className="text-red-500 text-xs">{errors.items.root.message}</p>}

          {/* Grand Total */}
          <div className="flex justify-end">
            <div className="text-right">
              <p className="text-xs text-gray-500">Grand Total</p>
              <p className="text-xl font-bold text-gray-900">
                ₱{grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
              </p>
            </div>
          </div>
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-3">
          <button type="button" onClick={() => navigate(-1)} className="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-300 text-white text-sm font-medium rounded-xl transition-colors"
          >
            {isSubmitting ? 'Creating…' : 'Create Purchase Order'}
          </button>
        </div>
      </form>
    </div>
  )
}
