// ---------------------------------------------------------------------------
// AP (Accounts Payable) Domain Types
// ---------------------------------------------------------------------------

export type VendorInvoiceStatus =
  | 'draft'
  | 'pending_approval'
  | 'head_noted'
  | 'manager_checked'
  | 'officer_reviewed'
  | 'approved'
  | 'rejected'
  | 'partially_paid'
  | 'paid'
  | 'deleted'

export type PaymentMethod = 'bank_transfer' | 'check' | 'cash'

// ── EWT Rate ──────────────────────────────────────────────────────────────

export interface EwtRate {
  id: number
  atc_code: string
  description: string
  rate: number             // e.g. 0.01 = 1%
  effective_from: string   // ISO date
  effective_to: string | null
  is_active: boolean
}

// ── Vendor ────────────────────────────────────────────────────────────────

export type VendorAccreditationStatus = 'pending' | 'accredited' | 'suspended' | 'blacklisted'

export interface Vendor {
  id: number
  name: string
  tin: string | null
  atc_code: string | null
  ewt_rate_id: number | null
  ewt_rate?: {
    id: number
    atc_code: string
    description: string
    rate: number
  } | null
  is_ewt_subject: boolean
  is_active: boolean
  accreditation_status: VendorAccreditationStatus
  accreditation_notes: string | null
  bank_name: string | null
  bank_account_no: string | null
  bank_account_name: string | null
  payment_terms: string | null
  address: string | null
  contact_person: string | null
  email: string | null
  phone: string | null
  notes: string | null
  portal_account_exists: boolean
  portal_account_email: string | null
  created_at: string
  updated_at: string
}

export interface CreateVendorPayload {
  name: string
  tin?: string | null
  atc_code?: string | null
  ewt_rate_id?: number | null
  is_ewt_subject: boolean
  is_active?: boolean
  accreditation_status?: VendorAccreditationStatus
  accreditation_notes?: string | null
  bank_name?: string | null
  bank_account_no?: string | null
  bank_account_name?: string | null
  payment_terms?: string | null
  address?: string | null
  contact_person?: string | null
  email?: string | null
  phone?: string | null
  notes?: string | null
}

// ── Vendor Invoice ────────────────────────────────────────────────────────

export interface VendorPayment {
  id: number
  vendor_invoice_id: number
  payment_date: string
  amount: number
  reference_number: string | null
  payment_method: PaymentMethod | null
  form_2307_generated: boolean
  form_2307_generated_at: string | null
  created_at: string
}

export interface VendorInvoice {
  id: number
  ulid: string
  vendor_id: number
  fiscal_period_id: number
  ap_account_id: number
  expense_account_id: number
  invoice_date: string         // ISO date
  due_date: string             // ISO date
  net_amount: number
  vat_amount: number
  ewt_amount: number
  net_payable: number          // computed: net + vat − ewt (AP-005)
  total_paid: number           // computed: SUM(payments.amount)
  balance_due: number          // computed: net_payable − total_paid
  or_number: string | null
  vat_exemption_reason: string | null
  atc_code: string | null
  ewt_rate: number | null      // snapshot rate at invoice creation
  status: VendorInvoiceStatus
  is_overdue: boolean
  rejection_note: string | null
  description: string | null
  journal_entry_id: number | null
  created_by: number
  submitted_by: number | null
  approved_by: number | null
  submitted_at: string | null
  approved_at: string | null
  created_at: string
  updated_at: string
  // eager-loaded
  vendor?: Vendor
  purchase_order?: {
    id: number
    ulid: string
    po_reference: string
  } | null
  goods_receipt?: {
    id: number
    ulid: string
    gr_reference: string
  } | null
  payments?: VendorPayment[]
}

export interface CreateVendorInvoicePayload {
  vendor_id: number
  fiscal_period_id: number
  ap_account_id: number
  expense_account_id: number
  invoice_date: string
  due_date: string
  net_amount: number
  vat_amount?: number
  or_number?: string | null
  vat_exemption_reason?: string | null
  description?: string | null
}

export interface RecordPaymentPayload {
  amount: number
  payment_date: string
  reference_number?: string | null
  payment_method?: PaymentMethod | null
  notes?: string | null
}

export interface VendorInvoiceFilters {
  status?: VendorInvoiceStatus
  vendor_id?: number
  fiscal_period_id?: number
  due_soon?: boolean
  date_from?: string
  date_to?: string
  page?: number
}
