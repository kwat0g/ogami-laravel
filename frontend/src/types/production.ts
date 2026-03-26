// Production / PPC types

export interface BomComponent {
  id: number
  component_item_id: number
  component_item?: { id: number; item_code: string; name: string; unit_of_measure: string }
  qty_per_unit: string
  unit_of_measure: string
  scrap_factor_pct: string
}

export interface Bom {
  id: number
  ulid: string
  product_item: { id: number; item_code: string; name: string; unit_of_measure: string } | null
  product_item_id: number
  version: string
  name?: string | null
  is_active: boolean
  notes: string | null
  standard_production_days?: number
  components?: BomComponent[]
  created_at: string | null
  updated_at: string | null
  deleted_at?: string | null
}

export type DeliveryScheduleStatus = 'open' | 'in_production' | 'ready' | 'dispatched' | 'delivered' | 'cancelled'
export type DeliveryScheduleType   = 'local' | 'export'

export interface DeliverySchedule {
  id: number
  ulid: string
  ds_reference: string
  customer: { id: number; name: string; email?: string } | null
  product_item: { id: number; item_code: string; name: string; unit_of_measure?: string } | null
  qty_ordered: string
  unit_price: string | null
  target_delivery_date: string
  type: DeliveryScheduleType
  status: DeliveryScheduleStatus
  notes: string | null
  production_orders?: ProductionOrder[]
  delivery_receipts?: Record<string, any>[]
  created_at: string | null
  deleted_at?: string | null
}

export type ProductionOrderStatus = 'draft' | 'released' | 'in_progress' | 'completed' | 'cancelled'

export interface ProductionOrder {
  id: number
  ulid: string
  po_reference: string
  delivery_schedule?: { id: number; ulid: string; ds_reference: string } | null
  product_item?: { id: number; item_code: string; name: string } | null
  bom?: Bom | null
  qty_required: string
  qty_produced: string
  progress_pct: number
  target_start_date: string
  target_end_date: string
  status: ProductionOrderStatus
  mrq_pending?: boolean
  notes: string | null
  created_by?: { id: number; name: string } | null
  output_logs?: ProductionOutputLog[]
  inspections?: Record<string, any>[]
  created_at: string | null
  updated_at: string | null
  deleted_at?: string | null
}

export interface ProductionOutputLog {
  id: number
  production_order_id: number
  shift: 'A' | 'B' | 'C'
  log_date: string
  qty_produced: string
  qty_rejected: string
  operator?: { id: number; name: string } | null
  recorded_by?: { id: number; name: string } | null
  remarks: string | null
  created_at: string | null
}

// Payloads
export interface CreateBomPayload {
  product_item_id: number
  version?: string
  notes?: string
  components: {
    component_item_id: number
    qty_per_unit: number
    unit_of_measure: string
    scrap_factor_pct?: number
  }[]
}

export interface CreateDeliverySchedulePayload {
  customer_id: number
  product_item_id: number
  qty_ordered: number
  unit_price?: number | null
  target_delivery_date: string
  type?: DeliveryScheduleType
  notes?: string
}

export interface CreateProductionOrderPayload {
  delivery_schedule_id?: number
  product_item_id: number
  bom_id: number
  qty_required: number
  target_start_date: string
  target_end_date: string
  notes?: string
}

export interface LogProductionOutputPayload {
  shift: 'A' | 'B' | 'C'
  log_date: string
  qty_produced: number
  qty_rejected?: number
  operator_id: number
  remarks?: string
}

export interface SmartDefaults {
  suggested_bom_id: number | null
  suggested_bom_name: string | null
  calculated_end_date: string | null
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type CombinedDeliveryScheduleStatus = 'planning' | 'ready' | 'partially_ready' | 'dispatched' | 'delivered' | 'cancelled'

export interface ItemStatusSummary {
  delivery_schedule_id: number
  product_name: string
  qty_ordered: string
  status: string
  is_ready: boolean
  is_missing?: boolean
  missing_reason?: string
  expected_delivery?: string
}

export interface CombinedDeliverySchedule {
  id: number
  ulid: string
  cds_reference: string
  client_order_id: number
  customer_id: number
  status: CombinedDeliveryScheduleStatus
  status_label?: string
  target_delivery_date: string
  actual_delivery_date: string | null
  delivery_address: string | null
  delivery_instructions: string | null
  item_status_summary: ItemStatusSummary[] | null
  total_items: number
  ready_items: number
  missing_items: number
  progress_percentage: number
  can_dispatch: boolean
  is_ready: boolean
  dispatched_at: string | null
  has_dispute: boolean
  dispute_summary: Record<string, unknown> | string | null
  client_order?: {
    id: number
    order_reference: string
    total_amount: string
  }
  customer?: {
    id: number
    name: string
    email: string
    phone: string
  }
  item_schedules: DeliverySchedule[]
  created_at: string
}

export interface CombinedDeliveryScheduleFilters {
  status?: string
  customer_id?: number
  date_from?: string
  date_to?: string
  per_page?: number
  page?: number
}
