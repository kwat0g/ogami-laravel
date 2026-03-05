// ---------------------------------------------------------------------------
// Procurement domain types
// ---------------------------------------------------------------------------

// ── Pagination ────────────────────────────────────────────────────────────────

export interface PaginatedMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginatedMeta
}

// ── Enums ────────────────────────────────────────────────────────────────────

export type PurchaseRequestStatus =
  | 'draft'
  | 'submitted'
  | 'noted'
  | 'checked'
  | 'reviewed'
  | 'approved'
  | 'rejected'
  | 'cancelled'
  | 'converted_to_po'

export type PurchaseRequestUrgency = 'normal' | 'urgent' | 'critical'

export type PurchaseOrderStatus =
  | 'draft'
  | 'sent'
  | 'partially_received'
  | 'fully_received'
  | 'closed'
  | 'cancelled'

export type GoodsReceiptStatus = 'draft' | 'confirmed'

export type GoodsReceiptCondition = 'good' | 'damaged' | 'partial' | 'rejected'

// ── Models ───────────────────────────────────────────────────────────────────

export interface PurchaseRequestItem {
  id: number
  item_description: string
  unit_of_measure: string
  quantity: number
  estimated_unit_cost: number
  estimated_total: number
  specifications: string | null
  line_order: number
}

export interface PurchaseRequest {
  id: number
  ulid: string
  pr_reference: string
  department_id: number
  urgency: PurchaseRequestUrgency
  justification: string
  notes: string | null
  status: PurchaseRequestStatus
  total_estimated_cost: number

  // Actors
  requested_by_id: number
  requested_by: { id: number; name: string } | null

  submitted_by_id: number | null
  submitted_at: string | null
  submitted_by: { id: number; name: string } | null

  noted_by_id: number | null
  noted_at: string | null
  noted_comments: string | null
  noted_by: { id: number; name: string } | null

  checked_by_id: number | null
  checked_at: string | null
  checked_comments: string | null
  checked_by: { id: number; name: string } | null

  reviewed_by_id: number | null
  reviewed_at: string | null
  reviewed_comments: string | null
  reviewed_by: { id: number; name: string } | null

  vp_approved_by_id: number | null
  vp_approved_at: string | null
  vp_comments: string | null
  vp_approved_by: { id: number; name: string } | null

  rejected_by_id: number | null
  rejected_at: string | null
  rejection_reason: string | null
  rejection_stage: string | null
  rejected_by: { id: number; name: string } | null

  converted_to_po_id: number | null
  converted_at: string | null

  isCancellable: boolean

  items: PurchaseRequestItem[]

  created_at: string
  updated_at: string
}

export interface PurchaseOrderItem {
  id: number
  pr_item_id: number | null
  item_description: string
  unit_of_measure: string
  quantity_ordered: number
  agreed_unit_cost: number
  total_cost: number
  quantity_received: number
  quantity_pending: number
  line_order: number
}

export interface PurchaseOrder {
  id: number
  ulid: string
  po_reference: string
  purchase_request_id: number
  vendor_id: number
  po_date: string
  delivery_date: string
  payment_terms: string
  delivery_address: string | null
  status: PurchaseOrderStatus
  total_po_amount: number
  notes: string | null
  sent_at: string | null
  closed_at: string | null
  cancellation_reason: string | null

  created_by_id: number
  created_by: { id: number; name: string } | null
  vendor: { id: number; name: string } | null
  purchase_request: { id: number; ulid: string; pr_reference: string } | null

  items: PurchaseOrderItem[]

  created_at: string
  updated_at: string
}

export interface GoodsReceiptItem {
  id: number
  po_item_id: number
  quantity_received: number
  unit_of_measure: string
  condition: GoodsReceiptCondition
  remarks: string | null
}

export interface GoodsReceipt {
  id: number
  ulid: string
  gr_reference: string
  purchase_order_id: number
  received_date: string
  delivery_note_number: string | null
  condition_notes: string | null
  status: GoodsReceiptStatus
  three_way_match_passed: boolean
  ap_invoice_created: boolean
  ap_invoice_id: number | null
  confirmed_at: string | null

  received_by_id: number
  received_by: { id: number; name: string } | null
  confirmed_by_id: number | null
  confirmed_by: { id: number; name: string } | null
  purchase_order: { id: number; ulid: string; po_reference: string } | null

  items: GoodsReceiptItem[]

  created_at: string
  updated_at: string
}

// ── Payloads ─────────────────────────────────────────────────────────────────

export interface PurchaseRequestItemPayload {
  item_description: string
  unit_of_measure: string
  quantity: number
  estimated_unit_cost: number
  specifications?: string
}

export interface CreatePurchaseRequestPayload {
  department_id: number
  urgency?: PurchaseRequestUrgency
  justification: string
  notes?: string
  items: PurchaseRequestItemPayload[]
}

export interface UpdatePurchaseRequestPayload extends Partial<CreatePurchaseRequestPayload> {
  items?: PurchaseRequestItemPayload[]
}

export interface PrActionPayload {
  comments?: string
}

export interface PrRejectPayload {
  reason: string
  stage: string
}

export interface PurchaseOrderItemPayload {
  pr_item_id?: number
  item_description: string
  unit_of_measure: string
  quantity_ordered: number
  agreed_unit_cost: number
}

export interface CreatePurchaseOrderPayload {
  purchase_request_id: number
  vendor_id: number
  po_date?: string
  delivery_date: string
  payment_terms: string
  delivery_address?: string
  notes?: string
  items: PurchaseOrderItemPayload[]
}

export interface GoodsReceiptItemPayload {
  po_item_id: number
  quantity_received: number
  unit_of_measure: string
  condition?: GoodsReceiptCondition
  remarks?: string
}

export interface CreateGoodsReceiptPayload {
  purchase_order_id: number
  received_date?: string
  delivery_note_number?: string
  condition_notes?: string
  items: GoodsReceiptItemPayload[]
}

// ── Filters ──────────────────────────────────────────────────────────────────

export interface PurchaseRequestFilters {
  status?: PurchaseRequestStatus
  urgency?: PurchaseRequestUrgency
  department_id?: number
  page?: number
  per_page?: number
}

export interface PurchaseOrderFilters {
  status?: PurchaseOrderStatus
  vendor_id?: number
  page?: number
  per_page?: number
}

export interface GoodsReceiptFilters {
  status?: GoodsReceiptStatus
  purchase_order_id?: number
  page?: number
  per_page?: number
}
