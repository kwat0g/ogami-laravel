// Sales Module types

export interface PriceList {
  id: number
  ulid: string
  name: string
  effective_from: string
  effective_to: string | null
  is_default: boolean
  customer_id: number | null
  customer?: { id: number; name: string } | null
  created_by_id: number
  items?: PriceListItem[]
  created_at: string
  updated_at: string
}

export interface PriceListItem {
  id: number
  price_list_id: number
  item_id: number
  item?: { id: number; item_code: string; name: string }
  unit_price_centavos: number
  min_qty: string
  max_qty: string | null
}

export interface Quotation {
  id: number
  ulid: string
  quotation_number: string
  customer_id: number
  customer?: { id: number; name: string }
  contact_id: number | null
  contact?: { id: number; first_name: string; last_name: string } | null
  opportunity_id: number | null
  validity_date: string
  total_centavos: number
  status: 'draft' | 'sent' | 'accepted' | 'converted_to_order' | 'rejected' | 'expired'
  notes: string | null
  terms_and_conditions: string | null
  items?: QuotationItem[]
  created_by?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface QuotationItem {
  id: number
  quotation_id: number
  item_id: number
  item?: { id: number; item_code: string; name: string }
  quantity: string
  unit_price_centavos: number
  line_total_centavos: number
  remarks: string | null
}

export interface SalesOrder {
  id: number
  ulid: string
  order_number: string
  customer_id: number
  customer?: { id: number; name: string }
  contact_id: number | null
  quotation_id: number | null
  quotation?: Quotation | null
  opportunity_id: number | null
  status: 'draft' | 'confirmed' | 'in_production' | 'partially_delivered' | 'delivered' | 'invoiced' | 'cancelled'
  requested_delivery_date: string | null
  promised_delivery_date: string | null
  total_centavos: number
  notes: string | null
  items?: SalesOrderItem[]
  created_by?: { id: number; name: string }
  approved_by?: { id: number; name: string } | null
  approved_at: string | null
  created_at: string
  updated_at: string
}

export interface SalesOrderItem {
  id: number
  sales_order_id: number
  item_id: number
  item?: { id: number; item_code: string; name: string }
  quantity: string
  unit_price_centavos: number
  line_total_centavos: number
  quantity_delivered: string
  remarks: string | null
}

export interface PriceResolveResult {
  unit_price_centavos: number
  source: 'customer_price_list' | 'default_price_list' | 'item_standard_price'
  price_list_id: number | null
}
