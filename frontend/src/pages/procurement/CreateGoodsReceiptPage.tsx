import React, { useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Plus, Trash2, PackageCheck } from 'lucide-react'
import { useCreateGoodsReceipt } from '@/hooks/useGoodsReceipts'
import { usePurchaseOrders, usePurchaseOrder } from '@/hooks/usePurchaseOrders'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { firstErrorMessage } from '@/lib/errorHandler'
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
  const navigate           = useNavigate()
  const [searchParams]     = useSearchParams()
  const createGR           = useCreateGoodsReceipt()

  // ── URL param: ?po_ulid=<ulid> when navigating from PO detail ──────────────
  const poUlidFromUrl = searchParams.get('po_ulid')

  // Fetch PO list for manual dropdown
  const { data: poData } = usePurchaseOrders({ per_page: 200 })

  // Fetch full PO detail (with items) when coming from PO detail page
  const { data: poDetail } = usePurchaseOrder(poUlidFromUrl)

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: {
      received_date: new Date().toISOString().split('T')[0],
      items: [{ po_item_id: undefined as unknown as number, quantity_received: 1, unit_of_measure: 'pcs', condition: 'good', remarks: '' }],
    },
  })

  const { fields, append, remove, replace } = useFieldArray({ control, name: 'items' })

  // ── Pre-populate items and PO from URL param ────────────────────────────────
  useEffect(() => {
    if (!poDetail?.items?.length) return
    replace(
      poDetail.items.map((item) => ({
        po_item_id:        item.id,
        quantity_received: item.quantity_pending,
        unit_of_measure:   item.unit_of_measure,
        condition:         'good' as GoodsReceiptCondition,
        remarks:           '',
      })),
    )
    setValue('purchase_order_id', poDetail.id)
  }, [poDetail, replace, setValue])

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
    } catch (err) {
      const message = firstErrorMessage(err)
      toast.error(message ?? 'Failed to record goods receipt.')
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader
        title="Record Goods Receipt"
        backTo="/procurement/goods-receipts"
      />

      {/* Source PO info banner when pre-filled */}
      {poDetail && (
        <div className="mb-4 flex items-center gap-2 text-xs text-neutral-700 bg-neutral-50 border border-neutral-200 rounded px-4 py-2.5">
          <PackageCheck className="w-3.5 h-3.5 shrink-0" />
          <span>
            Pre-filled from <span className="font-semibold">{poDetail.po_reference}</span>
            {poDetail.vendor && <> · Vendor: <span className="font-semibold">{poDetail.vendor.name}</span></>}.
            {' '}Adjust quantities and conditions as needed.
          </span>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
        {/* Header details */}
        <Card>
          <CardHeader>Receipt Information</CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

              {/* Purchase Order */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Purchase Order <span className="text-red-500">*</span></label>
                {poUlidFromUrl !== null ? (
                  <>
                    <input type="hidden" {...register('purchase_order_id')} />
                    <div className="w-full text-sm border border-neutral-200 rounded px-3 py-2 bg-neutral-100 text-neutral-500 cursor-not-allowed">
                      {poDetail?.po_reference ?? '—'}
                    </div>
                  </>
                ) : (
                  <select
                    {...register('purchase_order_id')}
                    className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white"
                  >
                    <option value="">— Select Sent PO —</option>
                    {poData?.data?.map((po) => (
                      <option key={po.id} value={po.id}>{po.po_reference}</option>
                    ))}
                  </select>
                )}
                {errors.purchase_order_id && (
                  <p className="text-red-500 text-xs mt-1">{errors.purchase_order_id.message}</p>
                )}
              </div>

              {/* Received Date */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Received Date</label>
                <input
                  type="date"
                  {...register('received_date')}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>

              {/* Delivery Note Number */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Delivery Note No. <span className="text-neutral-400 font-normal">(optional)</span></label>
                <input
                  type="text"
                  placeholder="DR-XXXX"
                  {...register('delivery_note_number')}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>

              {/* Condition Notes */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Overall Condition Notes <span className="text-neutral-400 font-normal">(optional)</span></label>
                <input
                  type="text"
                  {...register('condition_notes')}
                  className="w-full text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Line Items */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between w-full">
              <span>Items Received</span>
              {!poUlidFromUrl && (
                <button
                  type="button"
                  onClick={() => append({ po_item_id: undefined as unknown as number, quantity_received: 1, unit_of_measure: 'pcs', condition: 'good', remarks: '' })}
                  className="flex items-center gap-1.5 text-xs px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white font-medium rounded"
                >
                  <Plus className="w-3.5 h-3.5" />
                  Add Item
                </button>
              )}
            </div>
          </CardHeader>
          <CardBody>
            {/* Column headers */}
            {fields.length > 0 && (
              <div className="grid grid-cols-12 gap-2 px-3 mb-2">
                {poUlidFromUrl ? (
                  <>
                    <div className="col-span-4 text-xs font-medium text-neutral-600">Description</div>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">Qty Received</div>
                    <div className="col-span-1 text-xs font-medium text-neutral-600">UOM</div>
                    <div className="col-span-1 text-xs font-medium text-neutral-600 text-center">Pending</div>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">Condition</div>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">Remarks</div>
                  </>
                ) : (
                  <>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">PO Item ID</div>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">Qty</div>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">UOM</div>
                    <div className="col-span-2 text-xs font-medium text-neutral-600">Condition</div>
                    <div className="col-span-3 text-xs font-medium text-neutral-600">Remarks</div>
                    <div className="col-span-1" />
                  </>
                )}
              </div>
            )}

            <div className="space-y-3">
              {fields.map((field, idx) => {
                const poItem = poDetail?.items?.[idx]
                return (
                  <div key={field.id} className="grid grid-cols-12 gap-2 items-start border border-neutral-100 rounded p-3 bg-neutral-50">
                    {poUlidFromUrl ? (
                      <>
                        {/* Hidden po_item_id */}
                        <input type="hidden" {...register(`items.${idx}.po_item_id`)} />

                        {/* Description (locked) */}
                        <div className="col-span-4">
                          <input
                            disabled
                            value={poItem?.item_description ?? ''}
                            readOnly
                            className="w-full text-sm border border-neutral-200 rounded px-2.5 py-1.5 bg-neutral-100 text-neutral-500 cursor-not-allowed"
                          />
                        </div>

                        {/* Qty Received (editable) */}
                        <div className="col-span-2">
                          <Controller
                            name={`items.${idx}.quantity_received`}
                            control={control}
                            render={({ field: f }) => (
                              <input
                                type="number"
                                min={0.01}
                                step="0.01"
                                max={poItem?.quantity_pending}
                                placeholder="Qty"
                                {...f}
                                className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                              />
                            )}
                          />
                          {errors.items?.[idx]?.quantity_received && (
                            <p className="text-red-500 text-xs mt-0.5">{errors.items[idx]!.quantity_received!.message}</p>
                          )}
                        </div>

                        {/* UOM (locked) */}
                        <div className="col-span-1">
                          <input
                            disabled
                            value={poItem?.unit_of_measure ?? ''}
                            readOnly
                            className="w-full text-sm border border-neutral-200 rounded px-2.5 py-1.5 bg-neutral-100 text-neutral-500 cursor-not-allowed"
                          />
                        </div>

                        {/* Pending qty reference */}
                        <div className="col-span-1 flex items-center justify-center">
                          <span className="text-xs font-semibold text-neutral-600">
                            {poItem?.quantity_pending ?? '—'}
                          </span>
                        </div>

                        {/* Condition (editable) */}
                        <div className="col-span-2">
                          <select
                            {...register(`items.${idx}.condition`)}
                            className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400 appearance-none"
                            style={{ backgroundImage: 'none' }}
                          >
                            {CONDITIONS.map((c) => <option key={c} value={c}>{c}</option>)}
                          </select>
                        </div>

                        {/* Remarks (editable) */}
                        <div className="col-span-2">
                          <input
                            placeholder="Remarks (optional)"
                            {...register(`items.${idx}.remarks`)}
                            className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                          />
                        </div>
                      </>
                    ) : (
                      <>
                        {/* Manual mode: raw PO Item ID */}
                        <div className="col-span-2">
                          <Controller
                            name={`items.${idx}.po_item_id`}
                            control={control}
                            render={({ field: f }) => (
                              <input type="number" placeholder="PO Item ID" {...f} className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                            )}
                          />
                          {errors.items?.[idx]?.po_item_id && <p className="text-red-500 text-xs mt-0.5">{errors.items[idx]!.po_item_id!.message}</p>}
                        </div>
                        <div className="col-span-2">
                          <Controller
                            name={`items.${idx}.quantity_received`}
                            control={control}
                            render={({ field: f }) => (
                              <input type="number" min={0} step="0.01" placeholder="Qty" {...f} className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                            )}
                          />
                        </div>
                        <div className="col-span-2">
                          <input placeholder="UOM" {...register(`items.${idx}.unit_of_measure`)} className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400" />
                        </div>
                        <div className="col-span-2">
                          <select {...register(`items.${idx}.condition`)} className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400">
                            {CONDITIONS.map((c) => <option key={c} value={c}>{c}</option>)}
                          </select>
                        </div>
                        <div className="col-span-3">
                          <input placeholder="Remarks (optional)" {...register(`items.${idx}.remarks`)} className="w-full text-sm border border-neutral-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-neutral-400" />
                        </div>
                        <div className="col-span-1 flex justify-center pt-1.5">
                          {fields.length > 1 && (
                            <button type="button" onClick={() => remove(idx)} className="text-neutral-400 hover:text-red-500">
                              <Trash2 className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </>
                    )}
                  </div>
                )
              })}
            </div>
            {errors.items?.root && <p className="text-red-500 text-xs">{errors.items.root.message}</p>}
          </CardBody>
        </Card>

        {/* Submit */}
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
            {isSubmitting ? 'Saving…' : 'Record Goods Receipt'}
          </button>
        </div>
      </form>
    </div>
  )
}
