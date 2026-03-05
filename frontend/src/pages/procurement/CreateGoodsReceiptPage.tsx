import { useNavigate } from 'react-router-dom'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Plus, Trash2, PackageCheck } from 'lucide-react'
import { useCreateGoodsReceipt } from '@/hooks/useGoodsReceipts'
import { usePurchaseOrders } from '@/hooks/usePurchaseOrders'
import type { GoodsReceiptCondition } from '@/types/procurement'

const CONDITIONS: GoodsReceiptCondition[] = ['good', 'damaged', 'partial', 'rejected']

const itemSchema = z.object({
  po_item_id:        z.coerce.number().min(1, 'PO Item ID required'),
  quantity_received: z.coerce.number().min(0.01),
  unit_of_measure:   z.string().min(1, 'UOM required'),
  condition:         z.enum(['good', 'damaged', 'partial', 'rejected']).optional(),
  remarks:           z.string().optional(),
})

const schema = z.object({
  purchase_order_id:    z.coerce.number().min(1, 'Select a Purchase Order'),
  received_date:        z.string().optional(),
  delivery_note_number: z.string().optional(),
  condition_notes:      z.string().optional(),
  items:                z.array(itemSchema).min(1, 'At least one item required'),
})

type FormValues = z.infer<typeof schema>

export default function CreateGoodsReceiptPage(): React.ReactElement {
  const navigate  = useNavigate()
  const createGR  = useCreateGoodsReceipt()

  const { data: poData } = usePurchaseOrders({ status: 'sent', per_page: 200 })

  const {
    register,
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      items: [{ po_item_id: undefined as unknown as number, quantity_received: 1, unit_of_measure: 'pcs', condition: 'good', remarks: '' }],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  const onSubmit = async (values: FormValues): Promise<void> => {
    try {
      const gr = await createGR.mutateAsync({
        purchase_order_id:    values.purchase_order_id,
        received_date:        values.received_date,
        delivery_note_number: values.delivery_note_number,
        condition_notes:      values.condition_notes,
        items:                values.items.map((it) => ({
          po_item_id:        it.po_item_id,
          quantity_received: it.quantity_received,
          unit_of_measure:   it.unit_of_measure,
          condition:         it.condition,
          remarks:           it.remarks,
        })),
      })
      toast.success('Goods Receipt recorded.')
      navigate(`/procurement/goods-receipts/${gr.ulid}`)
    } catch {
      toast.error('Failed to record goods receipt.')
    }
  }

  return (
    <div className="max-w-4xl">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
          <PackageCheck className="w-5 h-5 text-green-600" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Record Goods Receipt</h1>
          <p className="text-sm text-gray-500 mt-0.5">Confirm items received from vendor delivery</p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Header details */}
        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
          <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Receipt Header</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Purchase Order */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Purchase Order <span className="text-red-500">*</span></label>
              <select
                {...register('purchase_order_id')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"
              >
                <option value="">— Select Sent PO —</option>
                {poData?.data?.map((po) => (
                  <option key={po.id} value={po.id}>{po.po_reference}</option>
                ))}
              </select>
              {errors.purchase_order_id && (
                <p className="text-red-500 text-xs mt-1">{errors.purchase_order_id.message}</p>
              )}
            </div>

            {/* Received Date */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Received Date</label>
              <input
                type="date"
                {...register('received_date')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              />
            </div>

            {/* Delivery Note Number */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Delivery Note No. <span className="text-gray-400 font-normal">(optional)</span></label>
              <input
                type="text"
                placeholder="DR-XXXX"
                {...register('delivery_note_number')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              />
            </div>

            {/* Condition Notes */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Overall Condition Notes <span className="text-gray-400 font-normal">(optional)</span></label>
              <input
                type="text"
                {...register('condition_notes')}
                className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              />
            </div>
          </div>
        </div>

        {/* Line Items */}
        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Items Received</h2>
            <button
              type="button"
              onClick={() => append({ po_item_id: undefined as unknown as number, quantity_received: 1, unit_of_measure: 'pcs', condition: 'good', remarks: '' })}
              className="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Item
            </button>
          </div>

          <div className="space-y-3">
            {fields.map((field, idx) => (
              <div key={field.id} className="grid grid-cols-12 gap-2 items-start border border-gray-100 rounded-lg p-3 bg-gray-50">
                {/* PO Item ID */}
                <div className="col-span-2">
                  <Controller
                    name={`items.${idx}.po_item_id`}
                    control={control}
                    render={({ field: f }) => (
                      <input type="number" placeholder="PO Item ID" {...f} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-green-400" />
                    )}
                  />
                  {errors.items?.[idx]?.po_item_id && <p className="text-red-500 text-xs mt-0.5">{errors.items[idx]!.po_item_id!.message}</p>}
                </div>
                {/* Qty */}
                <div className="col-span-2">
                  <Controller
                    name={`items.${idx}.quantity_received`}
                    control={control}
                    render={({ field: f }) => (
                      <input type="number" min={0} step="0.01" placeholder="Qty" {...f} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-green-400" />
                    )}
                  />
                </div>
                {/* UOM */}
                <div className="col-span-2">
                  <input placeholder="UOM" {...register(`items.${idx}.unit_of_measure`)} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-green-400" />
                </div>
                {/* Condition */}
                <div className="col-span-2">
                  <select {...register(`items.${idx}.condition`)} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-green-400">
                    {CONDITIONS.map((c) => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
                {/* Remarks */}
                <div className="col-span-3">
                  <input placeholder="Remarks (optional)" {...register(`items.${idx}.remarks`)} className="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-green-400" />
                </div>
                {/* Remove */}
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
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-3">
          <button type="button" onClick={() => navigate(-1)} className="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-300 text-white text-sm font-medium rounded-xl transition-colors"
          >
            {isSubmitting ? 'Saving…' : 'Record Goods Receipt'}
          </button>
        </div>
      </form>
    </div>
  )
}
