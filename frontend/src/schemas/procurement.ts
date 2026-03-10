import { z } from 'zod'

// ── Purchase Request Schemas ─────────────────────────────────────────────────

const prItemSchema = z.object({
  item_description: z.string().trim().min(1, 'Description is required'),
  unit_of_measure: z.string().trim().min(1, 'Unit of measure is required'),
  quantity: z.coerce.number().positive('Quantity must be greater than 0'),
  estimated_unit_cost: z.coerce.number().min(0, 'Cost cannot be negative').default(0),
  item_id: z.coerce.number().positive().optional(),
  remarks: z.string().trim().optional(),
})

export type PrItemFormValues = z.infer<typeof prItemSchema>

export const purchaseRequestSchema = z.object({
  department_id: z.coerce.number({ required_error: 'Department is required' }).positive('Department is required'),
  urgency: z.enum(['normal', 'urgent', 'critical'], {
    required_error: 'Urgency is required',
  }),
  justification: z.string().trim().min(10, 'Justification must be at least 10 characters'),
  needed_by_date: z.string().trim().optional(),
  items: z.array(prItemSchema).min(1, 'At least one item is required'),
})

export type PurchaseRequestFormValues = z.infer<typeof purchaseRequestSchema>

// ── Purchase Order Schemas ───────────────────────────────────────────────────

const poItemSchema = z.object({
  item_description: z.string().trim().min(1, 'Description is required'),
  unit_of_measure: z.string().trim().min(1, 'Unit of measure is required'),
  quantity: z.coerce.number().positive('Quantity must be greater than 0'),
  unit_price: z.coerce.number().min(0, 'Unit price cannot be negative'),
  item_id: z.coerce.number().positive().optional(),
  pr_item_id: z.coerce.number().positive().optional(),
})

export type PoItemFormValues = z.infer<typeof poItemSchema>

export const purchaseOrderSchema = z
  .object({
    purchase_request_id: z.coerce.number({ required_error: 'Purchase request is required' }).positive('Purchase request is required'),
    vendor_id: z.coerce.number({ required_error: 'Vendor is required' }).positive('Vendor is required'),
    ap_account_id: z.coerce.number({ required_error: 'AP account is required' }).positive('AP account is required'),
    po_date: z.string().trim().min(1, 'PO date is required'),
    expected_delivery_date: z.string().trim().min(1, 'Expected delivery date is required'),
    payment_terms: z.string().trim().optional(),
    delivery_address: z.string().trim().optional(),
    terms_and_conditions: z.string().trim().optional(),
    items: z.array(poItemSchema).min(1, 'At least one item is required'),
  })
  .refine((d) => d.expected_delivery_date >= d.po_date, {
    message: 'Expected delivery date must be on or after PO date',
    path: ['expected_delivery_date'],
  })

export type PurchaseOrderFormValues = z.infer<typeof purchaseOrderSchema>

// ── Goods Receipt Schema ─────────────────────────────────────────────────────

const grItemSchema = z.object({
  po_item_id: z.coerce.number().positive('PO item reference is required'),
  qty_received: z.coerce.number().positive('Quantity received must be greater than 0'),
  condition: z.enum(['good', 'damaged', 'partial', 'rejected'], {
    required_error: 'Condition is required',
  }),
  lot_number: z.string().trim().optional(),
  expiry_date: z.string().trim().optional(),
  remarks: z.string().trim().optional(),
})

export type GrItemFormValues = z.infer<typeof grItemSchema>

export const goodsReceiptSchema = z.object({
  purchase_order_id: z.coerce.number({ required_error: 'Purchase order is required' }).positive('Purchase order is required'),
  receipt_date: z.string().trim().min(1, 'Receipt date is required'),
  delivery_note_no: z.string().trim().optional(),
  remarks: z.string().trim().optional(),
  items: z.array(grItemSchema).min(1, 'At least one item is required'),
})

export type GoodsReceiptFormValues = z.infer<typeof goodsReceiptSchema>
