import { z } from 'zod'

// ── Equipment ─────────────────────────────────────────────────────────────────

export const equipmentSchema = z.object({
  name: z.string().trim().min(1, 'Equipment name is required').max(200),
  asset_tag: z.string().trim().max(50).optional(),
  department_id: z.coerce.number().positive().optional(),
  manufacturer: z.string().trim().max(100).optional(),
  model_number: z.string().trim().max(100).optional(),
  serial_number: z.string().trim().max(100).optional(),
  location: z.string().trim().max(200).optional(),
  purchase_date: z.string().trim().optional(),
  notes: z.string().trim().optional(),
})

export type EquipmentFormValues = z.infer<typeof equipmentSchema>

// ── PM Schedule ───────────────────────────────────────────────────────────────

export const pmScheduleSchema = z.object({
  task_description: z.string().trim().min(1, 'Task description is required').max(500),
  frequency_days: z.coerce.number().int().positive('Frequency must be a positive number'),
  estimated_duration_hours: z.coerce.number().min(0).optional(),
  next_due_date: z.string().trim().min(1, 'Next due date is required'),
  assigned_to_id: z.coerce.number().positive().optional(),
  parts_required: z.string().trim().optional(),
})

export type PmScheduleFormValues = z.infer<typeof pmScheduleSchema>

// ── Maintenance Work Order ────────────────────────────────────────────────────

export const maintenanceWorkOrderSchema = z.object({
  equipment_id: z.coerce.number().positive('Equipment is required'),
  work_type: z.enum(['corrective', 'preventive', 'predictive', 'emergency']),
  description: z.string().trim().min(10, 'Description must be at least 10 characters'),
  priority: z.enum(['low', 'normal', 'high', 'critical']).default('normal'),
  requested_by_id: z.coerce.number().positive().optional(),
  scheduled_date: z.string().trim().optional(),
})

export type MaintenanceWorkOrderFormValues = z.infer<typeof maintenanceWorkOrderSchema>
