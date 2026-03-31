export type DrDirection = 'inbound' | 'outbound';
export type DrStatus = 'draft' | 'confirmed' | 'dispatched' | 'partially_delivered' | 'delivered' | 'cancelled';
export type ShipmentStatus = 'pending' | 'in_transit' | 'delivered' | 'returned';

export interface DeliveryReceiptItem {
  id: number;
  item_master_id: number;
  item_name: string | null;
  quantity_expected: number;
  quantity_received: number;
  unit_of_measure: string | null;
  lot_batch_number: string | null;
  remarks: string | null;
}

export interface DeliveryReceipt {
  id: number;
  ulid: string;
  dr_reference: string;
  direction: DrDirection;
  status: DrStatus;
  receipt_date: string;
  remarks: string | null;
  vendor: { id: number; name: string } | null;
  customer: { id: number; name: string } | null;
  received_by: { id: number; name: string } | null;
  items?: DeliveryReceiptItem[];
  shipments_count?: number;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface Shipment {
  id: number;
  ulid: string;
  shipment_reference: string;
  carrier: string | null;
  tracking_number: string | null;
  shipped_at: string | null;
  estimated_arrival: string | null;
  actual_arrival: string | null;
  status: ShipmentStatus;
  notes: string | null;
  delivery_receipt?: { id: number; dr_reference: string } | null;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
}

export interface CreateShipmentPayload {
  delivery_receipt_id?: number | null;
  carrier?: string;
  tracking_number?: string;
  shipped_at?: string;
  estimated_arrival?: string;
  notes?: string;
}

export interface UpdateShipmentStatusPayload {
  status: ShipmentStatus;
  actual_arrival?: string;
}

export interface CreateDeliveryReceiptPayload {
  vendor_id?: number | null;
  customer_id?: number | null;
  sales_order_id?: number | null;
  delivery_schedule_id?: number | null;
  direction: DrDirection;
  receipt_date: string;
  remarks?: string;
  received_by_id?: number | null;
  items?: {
    item_master_id: number;
    quantity_expected: number;
    quantity_received: number;
    unit_of_measure?: string;
    lot_batch_number?: string;
    remarks?: string;
  }[];
}
