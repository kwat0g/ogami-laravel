// ---------------------------------------------------------------------------
// Accounting Domain Types
// ---------------------------------------------------------------------------

export type AccountType = 'ASSET' | 'LIABILITY' | 'EQUITY' | 'REVENUE' | 'COGS' | 'OPEX' | 'TAX'
export type NormalBalance = 'DEBIT' | 'CREDIT'
export type FiscalPeriodStatus = 'open' | 'closed'
export type JournalEntryStatus = 'draft' | 'submitted' | 'posted' | 'cancelled' | 'stale'
export type JournalEntrySourceType = 'manual' | 'payroll' | 'ap' | 'ar'

// ── Chart of Accounts ──────────────────────────────────────────────────────

export interface ChartOfAccount {
  id: number
  code: string
  name: string
  account_type: AccountType
  parent_id: number | null
  normal_balance: NormalBalance
  is_active: boolean
  is_system: boolean
  /** null when not loaded from API */
  is_leaf: boolean | null
  description: string | null
  children?: ChartOfAccount[]
  created_at: string
  updated_at: string
}

export interface CreateAccountPayload {
  code: string
  name: string
  account_type: AccountType
  parent_id?: number | null
  normal_balance: NormalBalance
  is_active?: boolean
  description?: string | null
}

// ── Fiscal Period ──────────────────────────────────────────────────────────

export interface FiscalPeriod {
  id: number
  name: string
  date_from: string       // ISO date
  date_to: string         // ISO date
  status: FiscalPeriodStatus
  closed_at: string | null
  closed_by: number | null
  created_at: string
  updated_at: string
}

export interface CreateFiscalPeriodPayload {
  name: string
  date_from: string
  date_to: string
}

// ── Journal Entry ──────────────────────────────────────────────────────────

export interface JournalEntryLine {
  id: number
  account_id: number
  account_code: string | null
  account_name: string | null
  debit: number | null       // pesos (NUMERIC 15,4)
  credit: number | null      // pesos (NUMERIC 15,4)
  cost_center_id: number | null
  description: string | null
}

export interface JournalEntryLineDraft {
  account_id: number | null
  debit: string              // string for form input
  credit: string             // string for form input
  description: string
}

export interface JournalEntry {
  id: number
  ulid: string
  je_number: string | null
  date: string               // ISO date
  description: string
  source_type: JournalEntrySourceType
  source_id: number | null
  status: JournalEntryStatus
  fiscal_period_id: number
  reversal_of: number | null
  reversal_of_ulid: string | null
  created_by: number
  submitted_by: number | null
  posted_by: number | null
  posted_at: string | null
  is_auto_posted: boolean
  lines?: JournalEntryLine[]
  fiscal_period?: FiscalPeriod
  created_at: string
  updated_at: string
}

export interface CreateJournalEntryPayload {
  date: string
  description: string
  lines: Array<{
    account_id: number
    debit?: number | null
    credit?: number | null
    cost_center_id?: number | null
    description?: string | null
  }>
}

export interface JournalEntryFilters {
  status?: JournalEntryStatus
  fiscal_period_id?: number
  source_type?: JournalEntrySourceType
  date_from?: string
  date_to?: string
  page?: number
}

// ── Journal Entry Templates ────────────────────────────────────────────────

export interface JournalEntryTemplate {
  id: number
  name: string
  description: string | null
  is_system: boolean
  line_count: number
  template_lines?: Array<{
    account_id: number
    debit_or_credit: 'debit' | 'credit'
    description: string | null
  }>
}

export interface JournalEntryTemplateLine {
  account_id: number
  account_name: string
  account_code: string
  debit_or_credit: 'debit' | 'credit'
  description: string | null
  amount: string
}
