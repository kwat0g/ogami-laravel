/**
 * PayrollWizardContext — Stores in-progress wizard state (Steps 1 & 2) in
 * sessionStorage so the DB is only written when computation actually begins.
 *
 * Lifecycle:
 *   Step 1 (Define Run)  → saves WizardStep1 to context, navigates to /new/scope
 *   Step 2 (Set Scope)   → saves WizardStep2 to context, navigates to /new/validate
 *   Step 3 (Commit)      → reads both steps, calls the API sequence, then clears context
 *
 * If the user abandons the wizard before Step 3, nothing is written to the DB.
 */
import { createContext, useContext, useState, useEffect, type ReactNode } from 'react'
import type { PayrollRunType } from '@/types/payroll'

// ── Data shapes ──────────────────────────────────────────────────────────────

export interface WizardStep1 {
  run_type: PayrollRunType
  pay_period_id?: number
  cutoff_start: string
  cutoff_end: string
  pay_date: string
  notes?: string
}

export interface DraftExclusion {
  employee_id: number
  /** Cached display name — just for UI; not sent to the API */
  label: string
  reason: string
}

export interface WizardStep2 {
  departments: number[]
  employment_types: string[]
  include_unpaid_leave: boolean
  include_probation_end: boolean
  exclusions: DraftExclusion[]
}

// ── Context type ─────────────────────────────────────────────────────────────

interface WizardState {
  step1: WizardStep1 | null
  step2: WizardStep2 | null
}

interface PayrollWizardContextValue {
  state: WizardState
  setStep1: (data: WizardStep1) => void
  setStep2: (data: WizardStep2) => void
  clear: () => void
}

// ── Constants ────────────────────────────────────────────────────────────────

const STORAGE_KEY = 'ogami_payroll_wizard_v1'
const EMPTY: WizardState = { step1: null, step2: null }

// ── Context ──────────────────────────────────────────────────────────────────

const PayrollWizardCtx = createContext<PayrollWizardContextValue | null>(null)

// ── Provider ─────────────────────────────────────────────────────────────────

export function PayrollWizardProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<WizardState>(() => {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY)
      return raw ? (JSON.parse(raw) as WizardState) : EMPTY
    } catch {
      return EMPTY
    }
  })

  // Sync to sessionStorage on every change
  useEffect(() => {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state))
  }, [state])

  const value: PayrollWizardContextValue = {
    state,
    setStep1: (data) => setState((s) => ({ ...s, step1: data })),
    setStep2: (data) => setState((s) => ({ ...s, step2: data })),
    clear: () => {
      setState(EMPTY)
      sessionStorage.removeItem(STORAGE_KEY)
    },
  }

  return <PayrollWizardCtx.Provider value={value}>{children}</PayrollWizardCtx.Provider>
}

// ── Hook ─────────────────────────────────────────────────────────────────────

// eslint-disable-next-line react-refresh/only-export-components
export function usePayrollWizard(): PayrollWizardContextValue {
  const ctx = useContext(PayrollWizardCtx)
  if (!ctx) throw new Error('usePayrollWizard must be used inside <PayrollWizardProvider>')
  return ctx
}
