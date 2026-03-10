import { z } from 'zod'

// ── Mold Master ───────────────────────────────────────────────────────────────

export const moldMasterSchema = z.object({
  name: z.string().trim().min(1, 'Mold name is required').max(200),
  mold_number: z.string().trim().min(1, 'Mold number is required').max(50),
  cavity_count: z.coerce.number().int().positive('Cavity count must be a positive integer'),
  material: z.string().trim().max(100).optional(),
  max_shot_life: z.coerce.number().int().positive().optional(),
  location: z.string().trim().max(200).optional(),
  notes: z.string().trim().optional(),
})

export type MoldMasterFormValues = z.infer<typeof moldMasterSchema>

// ── Mold Shot Log ─────────────────────────────────────────────────────────────

export const moldShotLogSchema = z.object({
  mold_id: z.coerce.number().positive('Mold is required'),
  production_order_id: z.coerce.number().positive().optional(),
  shot_count: z.coerce.number().int().positive('Shot count must be a positive integer'),
  good_count: z.coerce.number().int().min(0).default(0),
  defect_count: z.coerce.number().int().min(0).default(0),
  operator_id: z.coerce.number().positive().optional(),
  logged_at: z.string().trim().optional(),
  notes: z.string().trim().optional(),
})

export type MoldShotLogFormValues = z.infer<typeof moldShotLogSchema>
