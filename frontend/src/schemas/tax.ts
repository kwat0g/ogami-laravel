import { z } from 'zod'

// ── BIR Filing Schemas ──────────────────────────────────────────────────────

export const BIR_FORM_TYPES = [
  '1601C', '0619E', '1601EQ', '2550M', '2550Q',
  '0605', '1702Q', '1702RT', '2307_alpha',
] as const

export const birFilingSchema = z.object({
  form_type: z.enum(BIR_FORM_TYPES, {
    required_error: 'BIR form type is required',
  }),
  fiscal_period_id: z.coerce.number({ required_error: 'Fiscal period is required' }).positive(),
  due_date: z.string().trim().optional(),
  amount_centavos: z.coerce.number().min(0, 'Amount cannot be negative').optional(),
  remarks: z.string().trim().max(500).optional(),
})

export type BirFilingFormValues = z.infer<typeof birFilingSchema>

export const birFilingUpdateSchema = z.object({
  status: z.enum(['pending', 'filed', 'late', 'amended', 'cancelled'], {
    required_error: 'Status is required',
  }),
  filed_date: z.string().trim().optional(),
  confirmation_number: z.string().trim().max(100).optional(),
  amount_centavos: z.coerce.number().min(0).optional(),
  remarks: z.string().trim().max(500).optional(),
})

export type BirFilingUpdateFormValues = z.infer<typeof birFilingUpdateSchema>

// ── VAT Ledger Schemas ──────────────────────────────────────────────────────

export const vatLedgerEntrySchema = z.object({
  fiscal_period_id: z.coerce.number({ required_error: 'Fiscal period is required' }).positive(),
  type: z.enum(['input', 'output'], { required_error: 'VAT type is required' }),
  reference_type: z.string().trim().min(1, 'Reference type is required'),
  reference_id: z.coerce.number().positive().optional(),
  tin: z.string().trim().min(9, 'TIN must be at least 9 characters').max(20).optional(),
  vendor_or_customer_name: z.string().trim().min(1, 'Name is required'),
  description: z.string().trim().max(500).optional(),
  vatable_amount: z.coerce.number().min(0, 'Amount cannot be negative'),
  vat_amount: z.coerce.number().min(0, 'VAT cannot be negative'),
})

export type VatLedgerEntryFormValues = z.infer<typeof vatLedgerEntrySchema>
