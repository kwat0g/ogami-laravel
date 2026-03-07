import { z } from 'zod'

// ─── Leave Request ────────────────────────────────────────────────────────────

export const leaveRequestSchema = z
  .object({
    employee_id: z.coerce
      .number({ invalid_type_error: 'Select an employee' })
      .positive('Select an employee'),
    leave_type_id: z.coerce
      .number({ invalid_type_error: 'Select a leave type' })
      .positive('Select a leave type'),
    date_from: z
      .string()
      .min(1, 'Start date is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
    date_to: z
      .string()
      .min(1, 'End date is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
    reason: z.string().trim().min(1, 'Reason is required').max(500),
    is_half_day: z.boolean().default(false),
    half_day_period: z.enum(['AM', 'PM']).optional(),
  })
  .refine(
    (data) => {
      if (!data.date_from || !data.date_to) return true
      return new Date(data.date_to) >= new Date(data.date_from)
    },
    {
      message: 'End date must be on or after start date',
      path: ['date_to'],
    },
  )
  .refine(
    (data) => !data.is_half_day || !!data.half_day_period,
    {
      message: 'Select AM or PM for half-day leaves',
      path: ['half_day_period'],
    },
  )

export type LeaveRequestFormValues = z.infer<typeof leaveRequestSchema>

// ─── Leave Approval ───────────────────────────────────────────────────────────

export const leaveApprovalSchema = z.object({
  approved: z.boolean(),
  remarks: z.string().trim().max(500).optional().default(''),
})

export type LeaveApprovalFormValues = z.infer<typeof leaveApprovalSchema>
