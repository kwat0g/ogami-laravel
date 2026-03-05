import { z } from 'zod'

// ─── Create Payroll Run ───────────────────────────────────────────────────────

export const createPayrollRunSchema = z
  .object({
    run_type: z.enum(['regular', 'thirteenth_month'], {
      errorMap: () => ({ message: 'Select a run type' }),
    }),
    cutoff_start: z
      .string()
      .min(1, 'Cutoff start is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
    cutoff_end: z
      .string()
      .min(1, 'Cutoff end is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
    pay_date: z
      .string()
      .min(1, 'Pay date is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
    notes: z.string().trim().max(1000).optional().default(''),
  })
  .refine(
    (data) => {
      if (!data.cutoff_start || !data.cutoff_end) return true
      return new Date(data.cutoff_end) >= new Date(data.cutoff_start)
    },
    {
      message: 'Cutoff end must be on or after cutoff start',
      path: ['cutoff_end'],
    },
  )
  .refine(
    (data) => {
      if (!data.cutoff_end || !data.pay_date) return true
      return new Date(data.pay_date) >= new Date(data.cutoff_end)
    },
    {
      message: 'Pay date must be on or after the cutoff end',
      path: ['pay_date'],
    },
  )

export type CreatePayrollRunFormValues = z.infer<typeof createPayrollRunSchema>

// ─── Payroll Adjustment ───────────────────────────────────────────────────────

export const payrollAdjustmentSchema = z.object({
  employee_id: z.coerce.number({ invalid_type_error: 'Select an employee' }).positive(),
  type: z.enum(['earning', 'deduction'], {
    errorMap: () => ({ message: 'Select earning or deduction' }),
  }),
  nature: z
    .enum(['taxable', 'non_taxable'])
    .optional()
    .default('taxable'),
  description: z.string().trim().min(1, 'Description is required').max(255),
  /** Amount in pesos (UI). Converted to centavos before API call. */
  amount: z.coerce
    .number({ invalid_type_error: 'Enter an amount' })
    .positive('Amount must be greater than zero')
    .max(9_999_999.99, 'Amount exceeds maximum'),
})

export type PayrollAdjustmentFormValues = z.infer<typeof payrollAdjustmentSchema>
