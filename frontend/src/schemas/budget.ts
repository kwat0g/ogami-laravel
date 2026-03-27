import { z } from 'zod'

// ── Cost Center Schemas ─────────────────────────────────────────────────────

export const costCenterSchema = z.object({
  name: z.string().trim().min(1, 'Cost center name is required').max(200),
  code: z.string().trim().min(1, 'Code is required').max(20)
    .transform((v: string) => v.toUpperCase()),
  description: z.string().trim().max(500).optional(),
  department_id: z.coerce.number().positive().optional(),
  parent_id: z.coerce.number().positive().optional(),
  is_active: z.boolean().default(true),
})

export type CostCenterFormValues = z.infer<typeof costCenterSchema>

// ── Budget Line Schemas ─────────────────────────────────────────────────────

export const budgetLineSchema = z.object({
  cost_center_id: z.coerce.number({ required_error: 'Cost center is required' }).positive(),
  fiscal_year: z.coerce.number({ required_error: 'Fiscal year is required' })
    .int()
    .min(2020, 'Year must be 2020 or later')
    .max(2099, 'Year must be before 2100'),
  account_id: z.coerce.number({ required_error: 'GL account is required' }).positive(),
  budgeted_amount_centavos: z.coerce.number({ required_error: 'Budget amount is required' })
    .int()
    .min(0, 'Amount cannot be negative'),
  notes: z.string().trim().max(1000).optional(),
})

export type BudgetLineFormValues = z.infer<typeof budgetLineSchema>

// ── Budget Approval Schemas ─────────────────────────────────────────────────

export const budgetApprovalSchema = z.object({
  approval_remarks: z.string().trim().max(500).optional(),
})

export type BudgetApprovalFormValues = z.infer<typeof budgetApprovalSchema>

export const budgetRejectSchema = z.object({
  approval_remarks: z.string().trim().min(5, 'Rejection reason is required').max(500),
})

export type BudgetRejectFormValues = z.infer<typeof budgetRejectSchema>

// ── Variance Filter Schema ──────────────────────────────────────────────────

export const varianceFilterSchema = z.object({
  fiscal_year: z.coerce.number().int().min(2020).max(2099),
  cost_center_id: z.coerce.number().positive().optional(),
  department_id: z.coerce.number().positive().optional(),
})

export type VarianceFilterValues = z.infer<typeof varianceFilterSchema>
