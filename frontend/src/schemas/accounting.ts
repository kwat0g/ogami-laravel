import { z } from 'zod'

// ─── Journal Entry Line ───────────────────────────────────────────────────────

export const journalEntryLineSchema = z
  .object({
    account_id: z.coerce.number({ invalid_type_error: 'Select an account' }).positive(),
    /** Debit amount in pesos. Zero means no debit on this line. */
    debit: z.coerce
      .number({ invalid_type_error: 'Enter an amount' })
      .min(0, 'Debit cannot be negative')
      .default(0),
    /** Credit amount in pesos. Zero means no credit on this line. */
    credit: z.coerce
      .number({ invalid_type_error: 'Enter an amount' })
      .min(0, 'Credit cannot be negative')
      .default(0),
    description: z.string().trim().max(255).optional().default(''),
  })
  .refine((line) => line.debit > 0 || line.credit > 0, {
    message: 'Each line must have a debit or credit amount',
    path: ['debit'],
  })
  .refine((line) => !(line.debit > 0 && line.credit > 0), {
    message: 'A line cannot have both a debit and a credit',
    path: ['debit'],
  })

export type JournalEntryLineFormValues = z.infer<typeof journalEntryLineSchema>

// ─── Journal Entry ────────────────────────────────────────────────────────────

export const journalEntrySchema = z
  .object({
    entry_date: z
      .string()
      .min(1, 'Entry date is required')
      .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
    reference_no: z.string().trim().max(50).optional().default(''),
    description: z.string().trim().min(1, 'Description is required').max(500),
    fiscal_period_id: z.coerce
      .number({ invalid_type_error: 'Select a fiscal period' })
      .positive('Select a fiscal period'),
    lines: z
      .array(journalEntryLineSchema)
      .min(2, 'A journal entry must have at least two lines'),
  })
  .refine(
    (data) => {
      const totalDebit  = data.lines.reduce((s, l) => s + l.debit,  0)
      const totalCredit = data.lines.reduce((s, l) => s + l.credit, 0)
      return Math.abs(totalDebit - totalCredit) < 0.001  // tolerance for floating-point UI input
    },
    {
      message: 'Total debits must equal total credits (the entry must balance)',
      path: ['lines'],
    },
  )

export type JournalEntryFormValues = z.infer<typeof journalEntrySchema>

// ─── Chart of Account ─────────────────────────────────────────────────────────

export const chartOfAccountSchema = z.object({
  code: z
    .string()
    .trim()
    .min(1, 'Account code is required')
    .max(20)
    .regex(/^\d{3,}(-\d+)*$/, 'Code must be numeric segments separated by hyphens (e.g. 1001 or 1001-01)'),
  name: z.string().trim().min(1, 'Account name is required').max(150),
  account_type: z.enum(['asset', 'liability', 'equity', 'revenue', 'expense'], {
    errorMap: () => ({ message: 'Select an account type' }),
  }),
  normal_balance: z.enum(['debit', 'credit'], {
    errorMap: () => ({ message: 'Select a normal balance side' }),
  }),
  parent_account_id: z.coerce.number().positive().optional(),
  is_control_account: z.boolean().default(false),
  is_active: z.boolean().default(true),
  description: z.string().trim().max(500).optional().default(''),
})

export type ChartOfAccountFormValues = z.infer<typeof chartOfAccountSchema>
