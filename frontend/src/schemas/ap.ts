import { z } from 'zod'

// ── Vendor Schemas ───────────────────────────────────────────────────────────

export const vendorSchema = z.object({
  name: z.string().trim().min(1, 'Vendor name is required'),
  contact_person: z.string().trim().min(1, 'Contact person is required'),
  email: z.string().trim().email('Invalid email address').optional().or(z.literal('')),
  phone: z.string().trim().optional(),
  address: z.string().trim().optional(),
  tin: z
    .string()
    .trim()
    .regex(/^\d{3}-\d{3}-\d{3}-\d{3}$/, 'TIN format: 000-000-000-000')
    .optional()
    .or(z.literal('')),
  is_ewt_subject: z.boolean().default(false),
  payment_terms: z.string().trim().optional(),
  bank_name: z.string().trim().optional(),
  bank_account_no: z.string().trim().optional(),
  bank_account_name: z.string().trim().optional(),
})

export type VendorFormValues = z.infer<typeof vendorSchema>

// ── Vendor Invoice Schemas ───────────────────────────────────────────────────

export const vendorInvoiceSchema = z
  .object({
    vendor_id: z.coerce.number({ required_error: 'Vendor is required' }).positive('Vendor is required'),
    fiscal_period_id: z.coerce.number({ required_error: 'Fiscal period is required' }).positive('Fiscal period is required'),
    ap_account_id: z.coerce.number({ required_error: 'AP account is required' }).positive('AP account is required'),
    expense_account_id: z.coerce.number({ required_error: 'Expense account is required' }).positive('Expense account is required'),
    invoice_no: z.string().trim().min(1, 'Invoice number is required'),
    invoice_date: z.string().trim().min(1, 'Invoice date is required'),
    due_date: z.string().trim().min(1, 'Due date is required'),
    net_amount: z.coerce.number().positive('Net amount must be greater than 0'),
    vat_amount: z.coerce.number().min(0, 'VAT amount cannot be negative').default(0),
    ewt_rate: z.string().optional(),
    or_number: z.string().trim().optional(),
    particulars: z.string().trim().optional(),
  })
  .refine((d) => d.due_date >= d.invoice_date, {
    message: 'Due date must be on or after invoice date',
    path: ['due_date'],
  })
  .refine((d) => d.vat_amount === 0 || !!d.or_number?.trim(), {
    message: 'OR number is required when VAT amount is greater than 0',
    path: ['or_number'],
  })

export type VendorInvoiceFormValues = z.infer<typeof vendorInvoiceSchema>

// ── Vendor Payment Schema ────────────────────────────────────────────────────

export const vendorPaymentSchema = z.object({
  amount: z.coerce.number().positive('Payment amount must be greater than 0'),
  payment_date: z.string().trim().min(1, 'Payment date is required'),
  payment_method: z.enum(['cash', 'check', 'bank_transfer', 'online'], {
    required_error: 'Payment method is required',
  }),
  reference_number: z.string().trim().optional(),
  bank_account_id: z.coerce.number().positive('Bank account is required').optional(),
  remarks: z.string().trim().optional(),
})

export type VendorPaymentFormValues = z.infer<typeof vendorPaymentSchema>
