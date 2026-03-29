import { z } from 'zod'

// ── Bank Account Schemas ────────────────────────────────────────────────────

export const bankAccountSchema = z.object({
  name: z.string().trim().min(1, 'Account name is required').max(200),
  account_number: z.string().trim().min(1, 'Account number is required').max(50),
  bank_name: z.string().trim().min(1, 'Bank name is required').max(200),
  account_type: z.enum(['checking', 'savings'], {
    required_error: 'Account type is required',
  }),
  account_id: z.coerce.number().positive().nullable().optional(),
  opening_balance: z.coerce.number().min(0).default(0),
  is_active: z.boolean().default(true),
})

export type BankAccountFormValues = z.infer<typeof bankAccountSchema>

// ── Bank Transaction Schemas ────────────────────────────────────────────────

export const bankTransactionSchema = z.object({
  bank_account_id: z.coerce.number({ required_error: 'Bank account is required' }).positive(),
  transaction_date: z.string().trim().min(1, 'Transaction date is required'),
  description: z.string().trim().min(1, 'Description is required').max(500),
  amount: z.coerce.number().positive('Amount must be positive'),
  transaction_type: z.enum(['debit', 'credit'], {
    required_error: 'Transaction type is required',
  }),
  reference_number: z.string().trim().max(100).optional(),
})

export type BankTransactionFormValues = z.infer<typeof bankTransactionSchema>

// ── Bank Reconciliation Schemas ─────────────────────────────────────────────

export const bankReconciliationSchema = z.object({
  bank_account_id: z.coerce.number({ required_error: 'Bank account is required' }).positive(),
  period_from: z.string().trim().min(1, 'Period from date is required'),
  period_to: z.string().trim().min(1, 'Period to date is required'),
  opening_balance: z.coerce.number().default(0),
  closing_balance: z.coerce.number().default(0),
})

export type BankReconciliationFormValues = z.infer<typeof bankReconciliationSchema>

// ── Statement Import Schema ─────────────────────────────────────────────────

export const statementImportSchema = z.object({
  bank_account_id: z.coerce.number({ required_error: 'Bank account is required' }).positive(),
  file: z.any().refine((v) => v instanceof File, 'Statement file is required'),
})

export type StatementImportFormValues = z.infer<typeof statementImportSchema>
