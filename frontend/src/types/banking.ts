// ---------------------------------------------------------------------------
// Banking Domain Types (GL-006 — Bank Reconciliation)
// ---------------------------------------------------------------------------

export type BankAccountType = 'checking' | 'savings'
export type BankTransactionType = 'debit' | 'credit'
export type BankTransactionStatus = 'unmatched' | 'matched' | 'reconciled'
export type BankReconciliationStatus = 'draft' | 'certified'

// ── Bank Account ─────────────────────────────────────────────────────────

export interface BankAccount {
  id: number
  name: string
  account_number: string
  bank_name: string
  account_type: BankAccountType
  /** FK → chart_of_accounts */
  account_id: number | null
  opening_balance: number
  is_active: boolean
  chart_account?: {
    id: number
    code: string
    name: string
  } | null
  created_at: string
  updated_at: string
}

export interface CreateBankAccountPayload {
  name: string
  account_number: string
  bank_name: string
  account_type: BankAccountType
  account_id?: number | null
  opening_balance?: number
  is_active?: boolean
}

// ── Bank Transaction ──────────────────────────────────────────────────────

export interface BankTransaction {
  id: number
  bank_account_id: number
  transaction_date: string
  description: string
  amount: number
  transaction_type: BankTransactionType
  reference_number: string | null
  status: BankTransactionStatus
  journal_entry_line_id: number | null
  bank_reconciliation_id: number | null
  created_at: string
}

export interface BankStatementLine {
  transaction_date: string
  description: string
  amount: number
  transaction_type: BankTransactionType
  reference_number?: string | null
}

export interface ImportStatementPayload {
  transactions: BankStatementLine[]
}

export interface MatchTransactionPayload {
  bank_transaction_id: number
  journal_entry_line_id: number
}

// ── Bank Reconciliation ────────────────────────────────────────────────────

export interface BankReconciliation {
  id: number
  ulid: string
  bank_account_id: number
  period_from: string
  period_to: string
  opening_balance: number
  closing_balance: number
  status: BankReconciliationStatus
  created_by: number
  created_by_id: number   // alias for created_by — used by SoD checks
  certified_by: number | null
  certified_at: string | null
  notes: string | null
  bank_account?: BankAccount | null
  transactions?: BankTransaction[]
  unmatched_count: number
  created_at: string
  updated_at: string
}

export interface CreateBankReconciliationPayload {
  bank_account_id: number
  period_from: string
  period_to: string
  opening_balance?: number
  closing_balance?: number
  notes?: string | null
}

// ── Filters ────────────────────────────────────────────────────────────────

export interface BankReconciliationFilters {
  bank_account_id?: number | null
  status?: BankReconciliationStatus | null
}
