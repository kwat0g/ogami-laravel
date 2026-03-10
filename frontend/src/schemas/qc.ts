import { z } from 'zod'

// ── Inspection ────────────────────────────────────────────────────────────────

export const inspectionSchema = z.object({
  production_order_id: z.coerce.number().positive().optional(),
  inspection_type: z.enum(['incoming', 'in_process', 'final', 'vendor']),
  item_id: z.coerce.number().positive().optional(),
  sample_size: z.coerce.number().int().positive('Sample size must be a positive integer'),
  defects_found: z.coerce.number().int().min(0).default(0),
  inspector_notes: z.string().trim().optional(),
  inspection_date: z.string().trim().min(1, 'Inspection date is required'),
})

export type InspectionFormValues = z.infer<typeof inspectionSchema>

// ── Non-Conformance Report ────────────────────────────────────────────────────

export const nonConformanceReportSchema = z.object({
  inspection_id: z.coerce.number().positive().optional(),
  title: z.string().trim().min(1, 'Title is required').max(200),
  description: z.string().trim().min(10, 'Description must be at least 10 characters'),
  severity: z.enum(['minor', 'major', 'critical']),
  root_cause: z.string().trim().optional(),
  disposition: z.enum(['rework', 'scrap', 'accept_as_is', 'return_to_supplier']).optional(),
})

export type NcrFormValues = z.infer<typeof nonConformanceReportSchema>

// ── CAPA ──────────────────────────────────────────────────────────────────────

export const capaActionSchema = z.object({
  ncr_id: z.coerce.number().positive('NCR is required'),
  action_type: z.enum(['corrective', 'preventive']),
  description: z.string().trim().min(10, 'Description must be at least 10 characters'),
  assigned_to_id: z.coerce.number().positive('Assignee is required'),
  due_date: z.string().trim().min(1, 'Due date is required'),
})

export type CapaActionFormValues = z.infer<typeof capaActionSchema>
