import { z } from 'zod'

// ── Customer Schema ──────────────────────────────────────────────────────────

export const customerSchema = z.object({
  name: z.string().trim().min(1, 'Customer name is required'),
  email: z.string().trim().email('Invalid email address').optional().or(z.literal('')),
  phone: z.string().trim().optional(),
  address: z.string().trim().optional(),
  tin: z
    .string()
    .trim()
    .regex(/^\d{3}-\d{3}-\d{3}-\d{3}$/, 'TIN format: 000-000-000-000')
    .optional()
    .or(z.literal('')),
  credit_limit: z.coerce.number().min(0, 'Credit limit cannot be negative').default(0),
  payment_terms: z.string().trim().optional(),
  ar_account_id: z.coerce.number().positive().optional(),
  contact_person: z.string().trim().optional(),
  is_active: z.boolean().default(true),
})

export type CustomerFormValues = z.infer<typeof customerSchema>

// ── Customer Invoice Schema ──────────────────────────────────────────────────

export const customerInvoiceSchema = z
  .object({
    customer_id: z.coerce.number({ required_error: 'Customer is required' }).positive('Customer is required'),
    fiscal_period_id: z.coerce.number({ required_error: 'Fiscal period is required' }).positive('Fiscal period is required'),
    ar_account_id: z.coerce.number({ required_error: 'AR account is required' }).positive('AR account is required'),
    revenue_account_id: z.coerce.number({ required_error: 'Revenue account is required' }).positive('Revenue account is required'),
    invoice_no: z.string().trim().min(1, 'Invoice number is required'),
    invoice_date: z.string().trim().min(1, 'Invoice date is required'),
    due_date: z.string().trim().min(1, 'Due date is required'),
    subtotal: z.coerce.number().positive('Subtotal must be greater than 0'),
    vat_amount: z.coerce.number().min(0, 'VAT cannot be negative').default(0),
    particulars: z.string().trim().optional(),
    shipment_id: z.coerce.number().positive().optional(),
  })
  .refine((d) => d.due_date >= d.invoice_date, {
    message: 'Due date must be on or after invoice date',
    path: ['due_date'],
  })

export type CustomerInvoiceFormValues = z.infer<typeof customerInvoiceSchema>

// ── Receive Payment Schema ───────────────────────────────────────────────────

export const receivePaymentSchema = z.object({
  amount: z.coerce.number().positive('Payment amount must be greater than 0'),
  payment_date: z.string().trim().min(1, 'Payment date is required'),
  payment_method: z.enum(['cash', 'check', 'bank_transfer', 'online'], {
    required_error: 'Payment method is required',
  }),
  cash_account_id: z.coerce.number().positive('Cash/bank account is required'),
  ar_account_id: z.coerce.number().positive('AR account is required'),
  reference_number: z.string().trim().optional(),
  remarks: z.string().trim().optional(),
})

export type ReceivePaymentFormValues = z.infer<typeof receivePaymentSchema>

// ── Write-Off Schema ─────────────────────────────────────────────────────────

export const writeOffSchema = z.object({
  write_off_reason: z.string().trim().min(1, 'Reason is required'),
  bad_debt_account_id: z.coerce.number({ required_error: 'Bad debt expense account is required' }).positive('Account is required'),
  ar_account_id: z.coerce.number({ required_error: 'AR account is required' }).positive('Account is required'),
})

export type WriteOffFormValues = z.infer<typeof writeOffSchema>
