// ---------------------------------------------------------------------------
// Tax Domain Types
// VAT-004: per-period input/output/net VAT accumulation
// ---------------------------------------------------------------------------

export interface VatLedger {
  id: number
  fiscal_period_id: number
  input_vat: number              // from approved vendor invoices
  output_vat: number             // from approved customer invoices
  net_vat: number                // storedAs: output_vat - input_vat
  carry_forward_from_prior: number
  // VAT-004: actual payable = net_vat - carry_forward_from_prior
  vat_payable: number
  is_closed: boolean
  closed_at: string | null
  closed_by?: {
    id: number
    name: string
  } | null
  created_at: string
  updated_at: string
}

export interface ClosePeriodPayload {
  next_fiscal_period_id?: number | null
}

export interface VatPeriodSummary {
  fiscal_period_id: number
  period_label: string
  input_vat: number
  output_vat: number
  net_vat: number
  carry_forward_from_prior: number
  vat_payable: number
  is_closed: boolean
}
