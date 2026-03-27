import { z } from 'zod'

// ── Delivery Receipt Schemas ────────────────────────────────────────────────

const deliveryReceiptItemSchema = z.object({
  item_master_id: z.coerce.number({ required_error: 'Item is required' }).positive(),
  quantity_expected: z.coerce.number().min(0, 'Quantity cannot be negative').default(0),
  quantity_received: z.coerce.number().min(0, 'Quantity cannot be negative').default(0),
  unit_of_measure: z.string().trim().optional(),
  lot_batch_number: z.string().trim().max(100).optional(),
  remarks: z.string().trim().max(500).optional(),
})

export type DeliveryReceiptItemFormValues = z.infer<typeof deliveryReceiptItemSchema>

export const deliveryReceiptSchema = z.object({
  direction: z.enum(['inbound', 'outbound'], {
    required_error: 'Direction is required',
  }),
  vendor_id: z.coerce.number().positive().optional(),
  customer_id: z.coerce.number().positive().optional(),
  delivery_schedule_id: z.coerce.number().positive().optional(),
  receipt_date: z.string().trim().min(1, 'Receipt date is required'),
  remarks: z.string().trim().max(1000).optional(),
  received_by_id: z.coerce.number().positive().optional(),
  items: z.array(deliveryReceiptItemSchema).min(1, 'At least one item is required'),
})

export type DeliveryReceiptFormValues = z.infer<typeof deliveryReceiptSchema>

// ── Shipment Schemas ────────────────────────────────────────────────────────

export const shipmentSchema = z.object({
  delivery_receipt_id: z.coerce.number({ required_error: 'Delivery receipt is required' }).positive(),
  vehicle_id: z.coerce.number().positive().optional(),
  driver_name: z.string().trim().max(200).optional(),
  driver_phone: z.string().trim().max(30).optional(),
  plate_number: z.string().trim().max(20).optional(),
  shipped_date: z.string().trim().min(1, 'Shipped date is required'),
  estimated_arrival: z.string().trim().optional(),
  tracking_number: z.string().trim().max(100).optional(),
  remarks: z.string().trim().max(1000).optional(),
})

export type ShipmentFormValues = z.infer<typeof shipmentSchema>

// ── Vehicle Schemas ─────────────────────────────────────────────────────────

export const vehicleSchema = z.object({
  plate_number: z.string().trim().min(1, 'Plate number is required').max(20),
  type: z.string().trim().min(1, 'Vehicle type is required'),
  model: z.string().trim().max(200).optional(),
  capacity_kg: z.coerce.number().min(0).optional(),
  is_active: z.boolean().default(true),
  remarks: z.string().trim().max(500).optional(),
})

export type VehicleFormValues = z.infer<typeof vehicleSchema>
