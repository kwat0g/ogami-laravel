import { z } from 'zod'

// ── Client Order Schemas ────────────────────────────────────────────────────

const clientOrderItemSchema = z.object({
  item_id: z.coerce.number({ required_error: 'Item is required' }).positive(),
  item_description: z.string().trim().min(1, 'Description is required').max(300),
  quantity: z.coerce.number().positive('Quantity must be positive'),
  unit_of_measure: z.string().trim().max(50).optional(),
  unit_price_centavos: z.coerce.number().min(0, 'Price cannot be negative'),
  remarks: z.string().trim().max(500).optional(),
})

export type ClientOrderItemFormValues = z.infer<typeof clientOrderItemSchema>

export const clientOrderSchema = z.object({
  customer_id: z.coerce.number({ required_error: 'Customer is required' }).positive(),
  requested_delivery_date: z.string().trim().min(1, 'Delivery date is required'),
  client_notes: z.string().trim().max(2000).optional(),
  internal_notes: z.string().trim().max(2000).optional(),
  items: z.array(clientOrderItemSchema).min(1, 'At least one item is required'),
})

export type ClientOrderFormValues = z.infer<typeof clientOrderSchema>

// ── Negotiation Schemas ─────────────────────────────────────────────────────

export const negotiationProposalSchema = z.object({
  reason: z.string().trim().min(1, 'Reason is required').max(1000),
  delivery_date: z.string().trim().optional(),
  notes: z.string().trim().max(2000).optional(),
  items: z.array(z.object({
    item_id: z.coerce.number().positive(),
    quantity: z.coerce.number().positive().optional(),
    price: z.coerce.number().min(0).optional(),
  })).optional(),
})

export type NegotiationProposalFormValues = z.infer<typeof negotiationProposalSchema>

// ── Approval/Rejection Schemas ──────────────────────────────────────────────

export const clientOrderApprovalSchema = z.object({
  remarks: z.string().trim().max(1000).optional(),
})

export type ClientOrderApprovalFormValues = z.infer<typeof clientOrderApprovalSchema>

export const clientOrderRejectionSchema = z.object({
  rejection_reason: z.string().trim().min(1, 'Rejection reason is required').max(1000),
})

export type ClientOrderRejectionFormValues = z.infer<typeof clientOrderRejectionSchema>
