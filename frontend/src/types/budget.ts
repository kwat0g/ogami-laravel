export interface CostCenter {
  id: number
  ulid: string
  name: string
  code: string
  description: string | null
  is_active: boolean
  department_id: number | null
  parent_id: number | null
  created_at: string
  updated_at: string
  department?: { id: number; name: string }
  parent?: Pick<CostCenter, 'id' | 'name' | 'code'>
}

export interface AnnualBudget {
  id: number
  cost_center_id: number
  fiscal_year: number
  account_id: number
  budgeted_amount_centavos: number
  notes: string | null
  created_by: number
  updated_at: string
  account?: { id: number; name: string; code: string }
}

export interface BudgetUtilisationLine {
  account_id: number
  account_name: string
  account_code: string
  budgeted_centavos: number
  actual_centavos: number
  utilisation_pct: number
}
