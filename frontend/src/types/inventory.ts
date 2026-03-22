// Inventory / Warehouse types

export interface ItemCategory {
  id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
}

export interface ItemMaster {
  id: number
  ulid: string
  item_code: string
  category_id: number
  category: { id: number; code: string; name: string } | null
  name: string
  unit_of_measure: string
  description: string | null
  standard_price_centavos: number | null
  reorder_point: string
  reorder_qty: string
  type: 'raw_material' | 'semi_finished' | 'finished_good' | 'consumable' | 'spare_part'
  requires_iqc: boolean
  is_active: boolean
  preferred_vendor_id: number | null
  preferred_vendor: { id: number; name: string } | null
  stock_balances?: StockBalance[]
  created_at: string | null
  updated_at: string | null
  deleted_at?: string | null
}

export interface WarehouseLocation {
  id: number
  code: string
  name: string
  zone: string | null
  bin: string | null
  department_id: number | null
  department: { id: number; name: string } | null
  is_active: boolean
  created_at: string | null
}

export interface LotBatch {
  id: number
  ulid: string
  lot_number: string
  item_id: number
  received_from: 'vendor' | 'production'
  received_date: string
  expiry_date: string | null
  quantity_received: string
  quantity_remaining: string
}

export interface StockBalance {
  item_id: number
  location_id: number
  quantity_on_hand: string
  updated_at: string | null
  item?: {
    id: number
    item_code: string
    name: string
    unit_of_measure: string
    reorder_point: string
  }
  location?: {
    id: number
    code: string
    name: string
  }
}

export interface StockLedger {
  id: number
  item_id: number
  location_id: number
  lot_batch_id: number | null
  transaction_type: 'goods_receipt' | 'issue' | 'transfer' | 'adjustment' | 'return' | 'production_output'
  reference_type: string | null
  reference_id: number | null
  reference_label: string | null
  reference_ulid: string | null
  quantity: string
  balance_after: string
  remarks: string | null
  created_at: string
  item?: { id: number; item_code: string; name: string }
  location?: { id: number; code: string; name: string }
  created_by?: { id: number; name: string }
}

export interface MaterialRequisitionItem {
  id: number
  item_id: number
  item?: {
    id: number
    item_code: string
    name: string
    unit_of_measure: string
  }
  qty_requested: string
  qty_issued: string | null
  remarks: string | null
  line_order: number
}

export type MaterialRequisitionStatus =
  | 'draft' | 'submitted' | 'noted' | 'checked'
  | 'reviewed' | 'approved' | 'rejected' | 'cancelled' | 'fulfilled'

export interface MaterialRequisition {
  id: number
  ulid: string
  mr_reference: string
  department_id: number
  purpose: string
  remarks: string | null
  status: MaterialRequisitionStatus
  is_cancellable: boolean
  is_convertible_to_pr: boolean
  converted_to_pr: boolean
  converted_pr_id: number | null
  submitted_at: string | null
  noted_at: string | null
  noted_comments: string | null
  checked_at: string | null
  checked_comments: string | null
  reviewed_at: string | null
  reviewed_comments: string | null
  vp_approved_at: string | null
  vp_comments: string | null
  rejected_at: string | null
  rejection_reason: string | null
  fulfilled_at: string | null
  created_at: string | null
  deleted_at?: string | null
  requested_by: { id: number; name: string } | null
  department: { id: number; name: string } | null
  noted_by: { id: number; name: string } | null
  checked_by: { id: number; name: string } | null
  reviewed_by: { id: number; name: string } | null
  vp_approved_by: { id: number; name: string } | null
  rejected_by: { id: number; name: string } | null
  fulfilled_by: { id: number; name: string } | null
  production_order: { id: number; ulid: string; po_reference: string } | null
  items?: MaterialRequisitionItem[]
}

// Payloads
export interface CreateItemMasterPayload {
  category_id: number
  name: string
  unit_of_measure: string
  description?: string
  reorder_point?: number
  reorder_qty?: number
  type: ItemMaster['type']
  requires_iqc?: boolean
}

export interface CreateMaterialRequisitionPayload {
  department_id: number
  purpose: string
  items: {
    item_id: number
    qty_requested: number
    remarks?: string
  }[]
}

export interface StockAdjustmentPayload {
  item_id: number
  location_id: number
  adjusted_qty: number
  remarks: string
}

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
