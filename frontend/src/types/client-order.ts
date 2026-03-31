import type { Customer } from './ar'
import type { ItemMaster } from './inventory'
import type { User } from './auth'

export interface Proposal {
  round: number
  by: 'sales' | 'client'
  reason?: string
  changes: {
    delivery_date?: string
    items?: Array<{
      item_id: number
      quantity?: number
      price?: number
    }>
    notes?: string
  }
  notes?: string
  proposed_at: string
}

export interface ClientOrder {
  id: number
  ulid: string
  customer_id: number
  order_reference: string
  status: 'pending' | 'negotiating' | 'client_responded' | 'vp_pending' | 'approved' | 'in_production' | 'ready_for_delivery' | 'dispatched' | 'delivered' | 'fulfilled' | 'completed' | 'rejected' | 'cancelled'
  requested_delivery_date: string | null
  agreed_delivery_date: string | null
  total_amount_centavos: number
  client_notes: string | null
  internal_notes: string | null
  rejection_reason: string | null
  negotiation_reason: string | null
  negotiation_notes: string | null
  negotiation_turn: 'sales' | 'client' | null
  negotiation_round: number
  last_negotiation_by: 'sales' | 'client' | null
  last_negotiation_at: string | null
  last_proposal: Proposal | null
  delivery_schedule_id: number | null
  approved_by: number | null
  approved_at: string | null
  rejected_by: number | null
  rejected_at: string | null
  submitted_by: number | null
  submitted_at: string | null
  vp_approved_by: number | null
  vp_approved_at: string | null
  cancelled_by: number | null
  cancelled_at: string | null
  sla_deadline: string | null
  created_at: string
  updated_at: string

  // Relationships
  customer: Customer
  items: ClientOrderItem[]
  activities: ClientOrderActivity[]
  approvedBy?: User
  rejectedBy?: User
  // Multi-item delivery schedules (one per item)
  deliverySchedules?: Array<{
    id: number
    deliverySchedule: {
      id: number
      ulid: string
      ds_reference: string
      status: string
      target_delivery_date: string
    }
  }>
}

export interface ClientOrderItem {
  id: number
  client_order_id: number
  item_master_id: number
  item_description: string
  quantity: number
  unit_of_measure: string
  unit_price_centavos: number
  line_total_centavos: number
  negotiated_quantity: number | null
  negotiated_price_centavos: number | null
  line_notes: string | null
  line_order: number

  // Relationships
  itemMaster: ItemMaster
  deliverySchedule?: {
    id: number
    deliverySchedule: {
      id: number
      ulid: string
      ds_reference: string
      status: string
      target_delivery_date: string
    }
  } | null
}

export interface ClientOrderActivity {
  id: number
  client_order_id: number
  user_id: number | null
  user_type: 'staff' | 'client'
  action: string
  from_status: string | null
  to_status: string | null
  comment: string | null
  metadata: Record<string, unknown> | null
  created_at: string
  
  // Relationships
  user?: User
}

export interface CreateClientOrderPayload {
  items: Array<{
    item_master_id: number
    quantity: number
    unit_price_centavos: number
    notes?: string
  }>
  requested_delivery_date?: string
  notes?: string
}

export interface ClientOrderFilters {
  status?: string
  customer_id?: number
  date_from?: string
  date_to?: string
  per_page?: number
}

export const NEGOTIATION_REASONS = {
  stock_low: 'Insufficient stock - proposed delivery date',
  production_delay: 'Production delay - new ETA',
  price_change: 'Price changed due to material cost',
  partial_fulfillment: 'Partial fulfillment available',
  other: 'Other - please contact sales',
} as const

export type NegotiationReason = keyof typeof NEGOTIATION_REASONS

export const REJECTION_REASONS = {
  stock_unavailable: 'Item(s) currently out of stock',
  price_issue: 'Pricing discrepancy',
  invalid_items: 'Invalid or discontinued items',
  credit_hold: 'Account on credit hold',
  other: 'Other reason',
} as const

export type RejectionReason = keyof typeof REJECTION_REASONS
