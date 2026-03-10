import { z } from 'zod'

// ── Item Master Schema ───────────────────────────────────────────────────────

export const itemMasterSchema = z.object({
  item_code: z.string().trim().min(1, 'Item code is required').max(50),
  name: z.string().trim().min(1, 'Item name is required'),
  category_id: z.coerce.number({ required_error: 'Category is required' }).positive('Category is required'),
  unit_of_measure: z.string().trim().min(1, 'Unit of measure is required'),
  type: z.enum(['raw_material', 'finished_good', 'semi_finished', 'consumable', 'spare_part', 'packaging'], {
    required_error: 'Item type is required',
  }),
  reorder_point: z.coerce.number().min(0, 'Cannot be negative').default(0),
  reorder_qty: z.coerce.number().min(0, 'Cannot be negative').default(0),
  standard_cost: z.coerce.number().min(0, 'Cannot be negative').optional(),
  description: z.string().trim().optional(),
  is_active: z.boolean().default(true),
})

export type ItemMasterFormValues = z.infer<typeof itemMasterSchema>

// ── Item Category Schema ─────────────────────────────────────────────────────

export const itemCategorySchema = z.object({
  code: z.string().trim().min(1, 'Category code is required').max(20),
  name: z.string().trim().min(1, 'Category name is required'),
  description: z.string().trim().optional(),
})

export type ItemCategoryFormValues = z.infer<typeof itemCategorySchema>

// ── Warehouse Location Schema ────────────────────────────────────────────────

export const warehouseLocationSchema = z.object({
  code: z.string().trim().min(1, 'Location code is required').max(20),
  name: z.string().trim().min(1, 'Location name is required'),
  zone: z.string().trim().optional(),
  bin: z.string().trim().optional(),
  department_id: z.coerce.number().positive().optional(),
  is_active: z.boolean().default(true),
})

export type WarehouseLocationFormValues = z.infer<typeof warehouseLocationSchema>

// ── Stock Adjustment Schema ──────────────────────────────────────────────────

export const stockAdjustmentSchema = z.object({
  item_id: z.coerce.number({ required_error: 'Item is required' }).positive('Item is required'),
  location_id: z.coerce.number({ required_error: 'Location is required' }).positive('Location is required'),
  quantity: z.coerce.number().refine((v) => v !== 0, { message: 'Quantity cannot be zero' }),
  reason: z.string().trim().min(1, 'Reason is required'),
  lot_number: z.string().trim().optional(),
  remarks: z.string().trim().optional(),
})

export type StockAdjustmentFormValues = z.infer<typeof stockAdjustmentSchema>

// ── Material Requisition Schema ──────────────────────────────────────────────

const mrqItemSchema = z.object({
  item_id: z.coerce.number({ required_error: 'Item is required' }).positive('Item is required'),
  qty_requested: z.coerce.number().positive('Quantity must be greater than 0'),
  remarks: z.string().trim().optional(),
})

export type MrqItemFormValues = z.infer<typeof mrqItemSchema>

export const materialRequisitionSchema = z.object({
  department_id: z.coerce.number({ required_error: 'Department is required' }).positive('Department is required'),
  production_order_id: z.coerce.number().positive().optional(),
  purpose: z.string().trim().min(1, 'Purpose is required'),
  items: z.array(mrqItemSchema).min(1, 'At least one item is required'),
})

export type MaterialRequisitionFormValues = z.infer<typeof materialRequisitionSchema>
