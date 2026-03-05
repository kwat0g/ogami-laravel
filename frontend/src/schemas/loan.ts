import { z } from 'zod'

// ─── Loan Application ─────────────────────────────────────────────────────────

export const loanApplicationSchema = z
  .object({
    employee_id: z.coerce.number({ invalid_type_error: 'Select an employee' }).positive(),
    loan_type_id: z.coerce.number({ invalid_type_error: 'Select a loan type' }).positive(),
    /** Principal in PHP units (not centavos). */
    principal: z.coerce
      .number({ invalid_type_error: 'Enter an amount' })
      .positive('Principal amount must be greater than zero')
      .max(10_000_000, 'Amount exceeds maximum allowed'),
    term_months: z.coerce
      .number({ invalid_type_error: 'Enter number of months' })
      .int()
      .min(1, 'Minimum term is 1 month')
      .max(360, 'Maximum term is 360 months'),
    purpose: z.string().trim().max(500).optional().default(''),
    /** Expected disbursement / loan date. */
    loan_date: z
      .string()
      .min(1, 'Loan date is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
  })
  .refine(
    (data) => {
      if (!data.loan_date) return true
      return new Date(data.loan_date) >= new Date(new Date().setHours(0, 0, 0, 0))
    },
    {
      message: 'Loan date must be today or in the future',
      path: ['loan_date'],
    },
  )

export type LoanApplicationFormValues = z.infer<typeof loanApplicationSchema>

// ─── Loan Approval ────────────────────────────────────────────────────────────

export const loanApprovalSchema = z.object({
  approved: z.boolean(),
  /** Required when rejecting (approved = false). */
  rejection_reason: z.string().trim().max(500).optional().default(''),
})

export type LoanApprovalFormValues = z.infer<typeof loanApprovalSchema>
