import { z } from 'zod'

// ── Ticket ────────────────────────────────────────────────────────────────────

export const ticketSchema = z.object({
  subject: z.string().trim().min(1, 'Subject is required').max(200),
  description: z.string().trim().min(10, 'Description must be at least 10 characters'),
  type: z.enum(['complaint', 'inquiry', 'request'], {
    required_error: 'Ticket type is required',
  }),
  priority: z.enum(['low', 'normal', 'high', 'critical']).optional(),
  customer_id: z.coerce.number().positive().optional().nullable(),
  client_user_id: z.coerce.number().positive().optional().nullable(),
})

export type TicketFormValues = z.infer<typeof ticketSchema>

// ── Reply ─────────────────────────────────────────────────────────────────────

export const ticketReplySchema = z.object({
  body: z.string().trim().min(1, 'Reply body is required'),
  is_internal: z.boolean().default(false),
})

export type TicketReplyFormValues = z.infer<typeof ticketReplySchema>

// ── Assign ────────────────────────────────────────────────────────────────────

export const assignTicketSchema = z.object({
  assigned_to_id: z.coerce.number({ required_error: 'Assignee is required' }).positive('Assignee is required'),
})

export type AssignTicketFormValues = z.infer<typeof assignTicketSchema>
