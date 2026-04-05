// ---------------------------------------------------------------------------
// AR (Accounts Receivable) Domain Types
// ---------------------------------------------------------------------------

export type CustomerInvoiceStatus =
  | 'draft'
  | 'approved'
  | 'partially_paid'
  | 'paid'
  | 'written_off'
  | 'cancelled'

export type ARPaymentMethod = 'bank_transfer' | 'check' | 'cash' | 'online'

// ── Customer ──────────────────────────────────────────────────────────────

export interface Customer {
  id: number
  name: string
  tin: string | null
  email: string | null
  phone: string | null
  contact_person: string | null
  address: string | null
  billing_address: string | null
  // AR-001/AR-004: credit exposure
  credit_limit: number
  current_outstanding: number   // computed accessor — never stored
  available_credit: number
  is_active: boolean
  ar_account_id: number | null
  notes: string | null
  // Client portal account (only visible to admin)
  portal_account_exists: boolean
  portal_account_email: string | null
  created_at: string
  updated_at: string
}

export interface CreateCustomerPayload {
  name: string
  tin?: string | null
  email?: string | null
  phone?: string | null
  contact_person?: string | null
  address?: string | null
  billing_address?: string | null
  credit_limit?: number
  ar_account_id?: number | null
  notes?: string | null
}

// ── Customer Invoice ──────────────────────────────────────────────────────

export interface CustomerPayment {
  id: number
  customer_invoice_id: number
  payment_date: string
  amount: number
  reference_number: string | null
  payment_method: ARPaymentMethod | null
  notes: string | null
  created_by: number
  created_at: string
}

export interface CustomerInvoice {
  id: number
  ulid: string
  // AR-003: INV-YYYY-MM-NNNNNN, set on approval
  invoice_number: string | null
  customer_id: number
  fiscal_period_id: number
  ar_account_id: number
  revenue_account_id: number
  invoice_date: string       // ISO date
  due_date: string           // ISO date
  subtotal: number
  vat_amount: number
  total_amount: number       // subtotal + vat_amount
  vat_exemption_reason: string | null
  description: string | null
  status: CustomerInvoiceStatus
  // Computed
  total_paid: number
  balance_due: number
  is_overdue: boolean
  // AR-006
  write_off_reason: string | null
  write_off_at: string | null
  // Auth trail
  created_by: number
  approved_by: number | null
  approved_at: string | null
  created_at: string
  updated_at: string
  // Eager-loaded
  customer?: Customer
  fiscal_period?: {
    id: number
    name: string
    date_from: string
    date_to: string
    status: string
  }
  payments?: CustomerPayment[]
}

export interface CreateCustomerInvoicePayload {
  customer_id: number
  fiscal_period_id: number
  ar_account_id: number
  revenue_account_id: number
  invoice_date: string
  due_date: string
  subtotal: number
  vat_amount?: number
  // VAT-001: required when vat_amount > 0
  or_number?: string | null
  vat_exemption_reason?: string | null
  description?: string | null
  // AR-002: credit limit override (requires permission)
  bypass_credit_check?: boolean
}

export interface ReceivePaymentPayload {
  amount: number
  payment_date: string
  reference_number?: string | null
  payment_method?: ARPaymentMethod | null
  notes?: string | null
  cash_account_id: number
  ar_account_id: number
}

export interface WriteOffPayload {
  write_off_reason: string
  bad_debt_account_id: number
  ar_account_id: number
}

export interface CustomerInvoiceFilters {
  customer_id?: number
  status?: CustomerInvoiceStatus | string
  due_soon?: number        // days
  overdue?: boolean
  page?: number
  per_page?: number
}
