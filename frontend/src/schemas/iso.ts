import { z } from 'zod'

// ── Controlled Document ───────────────────────────────────────────────────────

export const controlledDocumentSchema = z.object({
  title: z.string().trim().min(1, 'Title is required').max(200),
  document_number: z.string().trim().min(1, 'Document number is required').max(50),
  document_type: z.string().trim().min(1, 'Document type is required').max(50),
  revision: z.string().trim().max(20).default('1'),
  department_id: z.coerce.number().positive().optional(),
  effective_date: z.string().trim().min(1, 'Effective date is required'),
  review_date: z.string().trim().optional(),
  description: z.string().trim().optional(),
})

export type ControlledDocumentFormValues = z.infer<typeof controlledDocumentSchema>

// ── Internal Audit ────────────────────────────────────────────────────────────

export const internalAuditSchema = z.object({
  title: z.string().trim().min(1, 'Title is required').max(200),
  audit_type: z.string().trim().min(1, 'Audit type is required').max(50),
  department_id: z.coerce.number().positive().optional(),
  scheduled_date: z.string().trim().min(1, 'Scheduled date is required'),
  lead_auditor_id: z.coerce.number().positive('Lead auditor is required'),
  scope: z.string().trim().min(10, 'Scope must be at least 10 characters'),
})

export type InternalAuditFormValues = z.infer<typeof internalAuditSchema>

// ── Audit Finding ─────────────────────────────────────────────────────────────

export const auditFindingSchema = z.object({
  audit_id: z.coerce.number().positive('Audit is required'),
  finding_type: z.enum(['nonconformity', 'observation', 'opportunity_for_improvement']),
  description: z.string().trim().min(10, 'Description must be at least 10 characters'),
  reference_clause: z.string().trim().max(50).optional(),
  evidence: z.string().trim().optional(),
})

export type AuditFindingFormValues = z.infer<typeof auditFindingSchema>
