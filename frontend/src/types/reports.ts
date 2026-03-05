// ---------------------------------------------------------------------------
// Government Report Types
// ---------------------------------------------------------------------------

export interface GovReportParams {
  year: number
  month?: number
  employee_id?: number
}

// YTD summary returned by GET /employee/me/ytd
export interface YtdSummary {
  year: number
  ytd_gross_centavos: number
  ytd_net_centavos: number
  ytd_sss_centavos: number
  ytd_philhealth_centavos: number
  ytd_pagibig_centavos: number
  ytd_withholding_tax_centavos: number
  ytd_taxable_income_centavos: number
  ytd_tax_withheld_centavos: number
}

// ---------------------------------------------------------------------------
// Financial Statement Types (GL-001 to GL-005)
// ---------------------------------------------------------------------------

// ── GL-001: General Ledger ────────────────────────────────────────────────

export interface GLAccountMeta {
  id: number
  code: string
  name: string
  normal_balance: 'DEBIT' | 'CREDIT'
}

export interface GLLine {
  date: string
  je_number: string | null
  description: string
  source_type: string
  debit: number | null
  credit: number | null
  running_balance: number
}

export interface GLReport {
  data: {
    account: GLAccountMeta
    opening_balance: number
    lines: GLLine[]
    closing_balance: number
  }
  meta: {
    filters: { date_from: string; date_to: string; cost_center_id: number | null }
    generated_at: string
  }
}

export interface GLFilters {
  account_id: number
  date_from: string
  date_to: string
  cost_center_id?: number | null
}

// ── GL-002: Trial Balance ─────────────────────────────────────────────────

export interface TrialBalanceLine {
  id: number
  code: string
  name: string
  account_type: string
  normal_balance: string
  opening_debit: number
  opening_credit: number
  period_debit: number
  period_credit: number
  closing_debit: number
  closing_credit: number
}

export interface TrialBalanceTotals {
  opening_debit: number
  opening_credit: number
  period_debit: number
  period_credit: number
  closing_debit: number
  closing_credit: number
}

export interface TrialBalance {
  data: {
    accounts: TrialBalanceLine[]
    totals: TrialBalanceTotals
  }
  meta: {
    filters: { date_from: string; date_to: string }
    generated_at: string
  }
}

// ── GL-003: Balance Sheet ─────────────────────────────────────────────────

export type BSClassification =
  | 'current_asset'
  | 'non_current_asset'
  | 'current_liability'
  | 'non_current_liability'
  | 'equity'
  | 'none'

export interface BSAccountLine {
  id: number
  code: string
  name: string
  balance: number
  comparative: number | null
}

export interface BSSection {
  key: BSClassification
  label: string
  accounts: BSAccountLine[]
  total: number
  comparative_total: number | null
}

export interface BSTotals {
  total_assets: number
  total_liabilities: number
  total_equity: number
  total_liabilities_and_equity: number
  comparative_total_assets: number | null
  comparative_total_liabilities: number | null
  comparative_total_equity: number | null
}

export interface BalanceSheet {
  data: {
    sections: BSSection[]
    totals: BSTotals
  }
  meta: {
    filters: { as_of_date: string; comparative_date: string | null }
    generated_at: string
  }
}

// ── GL-004: Income Statement ──────────────────────────────────────────────

export interface ISAccountLine {
  id: number
  code: string
  name: string
  balance: number
}

export interface ISSection {
  accounts: ISAccountLine[]
  total: number
}

export interface IncomeStatement {
  data: {
    revenue: ISSection
    cogs: ISSection
    gross_profit: number
    operating_expenses: ISSection
    operating_income: number
    income_tax: ISSection
    net_income: number
  }
  meta: {
    filters: { date_from: string; date_to: string }
    generated_at: string
  }
}

// ── GL-005: Cash Flow Statement ───────────────────────────────────────────

export interface CFLine {
  id: number
  code: string
  name: string
  amount: number
}

export interface OperatingSection {
  net_income: number
  adjustments: CFLine[]
  total_operating: number
}

export interface CashFlowStatement {
  data: {
    operating: OperatingSection
    investing: { lines: CFLine[]; total_investing: number }
    financing: { lines: CFLine[]; total_financing: number }
    net_change_in_cash: number
    opening_cash_balance: number
    closing_cash_balance: number
  }
  meta: {
    filters: { date_from: string; date_to: string }
    generated_at: string
  }
}

// ── Shared filters ─────────────────────────────────────────────────────────

export interface PeriodFilters {
  date_from: string
  date_to: string
}

export interface BalanceSheetFilters {
  as_of_date: string
  comparative_date?: string | null
}
