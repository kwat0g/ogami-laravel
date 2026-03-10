import { z } from 'zod'

// ── Production Order ──────────────────────────────────────────────────────────

export const productionOrderSchema = z.object({
  bom_id: z.coerce.number({ required_error: 'Bill of materials is required' }).positive(),
  department_id: z.coerce.number().positive().optional(),
  quantity: z.coerce.number({ required_error: 'Quantity is required' }).positive('Quantity must be greater than 0'),
  planned_start_date: z.string().trim().min(1, 'Planned start date is required'),
  planned_end_date: z.string().trim().min(1, 'Planned end date is required'),
  notes: z.string().trim().optional(),
})

export type ProductionOrderFormValues = z.infer<typeof productionOrderSchema>

// ── Bill of Materials ─────────────────────────────────────────────────────────

const bomComponentSchema = z.object({
  item_id: z.coerce.number().positive('Item is required'),
  quantity: z.coerce.number().positive('Quantity must be greater than 0'),
  unit_of_measure: z.string().trim().min(1),
  scrap_pct: z.coerce.number().min(0).max(100).default(0),
  notes: z.string().trim().optional(),
})

export const billOfMaterialsSchema = z.object({
  product_name: z.string().trim().min(1, 'Product name is required').max(200),
  version: z.string().trim().max(20).optional(),
  unit_of_output: z.string().trim().min(1, 'Unit of output is required'),
  components: z.array(bomComponentSchema).min(1, 'At least one component is required'),
})

export type BillOfMaterialsFormValues = z.infer<typeof billOfMaterialsSchema>
