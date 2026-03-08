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
  is_active: boolean
  notes: string | null
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
  customer: { id: number; name: string } | null
  product_item: { id: number; item_code: string; name: string } | null
  qty_ordered: string
  unit_price: string | null
  target_delivery_date: string
  type: DeliveryScheduleType
  status: DeliveryScheduleStatus
  notes: string | null
  production_orders?: ProductionOrder[]
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

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
