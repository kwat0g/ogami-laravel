import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface CostCenter {
  id: number
  ulid: string
  code: string
  name: string
  is_active: boolean
  created_at: string
}

export interface BudgetLine {
  id: number
  ulid: string
  cost_center_id: number
  account_id: number
  fiscal_year: number
  budgeted_amount_centavos: number
  status: 'draft' | 'submitted' | 'approved' | 'rejected'
  submitted_by_id: number | null
  submitted_at: string | null
  approved_by_id: number | null
  approved_at: string | null
  approval_remarks: string | null
  cost_center?: CostCenter
  account?: { id: number; code: string; name: string }
}

export interface BudgetUtilisation {
  cost_center: CostCenter
  fiscal_year: number
  lines: {
    account_id: number
    account_code: string
    account_name: string
    budgeted_centavos: number
    actual_centavos: number
    remaining_centavos: number
    utilisation_pct: number
  }[]
  total_budgeted_centavos: number
  total_actual_centavos: number
}

interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ── Cost Centres ──────────────────────────────────────────────────────────────

export function useCostCenters(params: { is_active?: boolean } = {}) {
  return useQuery({
    queryKey: ['cost-centers', params],
    queryFn: async () => {
      const res = await api.get<Paginated<CostCenter>>('/budget/cost-centers', { params })
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useCreateCostCenter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { code: string; name: string }) => {
      const res = await api.post<{ data: CostCenter }>('/budget/cost-centers', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cost-centers'] })
    },
  })
}

export function useUpdateCostCenter(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<{ code: string; name: string; is_active: boolean }>) => {
      const res = await api.patch<{ data: CostCenter }>(`/budget/cost-centers/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cost-centers'] })
    },
  })
}

// ── Budget Lines ──────────────────────────────────────────────────────────────

export function useBudgetLines(params: { cost_center_id?: number; fiscal_year?: number } = {}) {
  return useQuery({
    queryKey: ['budget-lines', params],
    queryFn: async () => {
      const res = await api.get<Paginated<BudgetLine>>('/budget/lines', { params })
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useSetBudgetLine() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      cost_center_id: number
      account_id: number
      fiscal_year: number
      amount_centavos: number
    }) => {
      const res = await api.post<{ data: BudgetLine }>('/budget/lines', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['budget-lines'] })
      qc.invalidateQueries({ queryKey: ['budget-utilisation'] })
    },
  })
}

// ── Approval Workflow ─────────────────────────────────────────────────────────

export function useSubmitBudget() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.patch<{ data: BudgetLine }>(`/budget/lines/${ulid}/submit`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['budget-lines'] })
    },
  })
}

export function useApproveBudget() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { ulid: string; remarks?: string }) => {
      const res = await api.patch<{ data: BudgetLine }>(`/budget/lines/${payload.ulid}/approve`, {
        remarks: payload.remarks,
      })
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['budget-lines'] })
    },
  })
}

export function useRejectBudget() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { ulid: string; remarks?: string }) => {
      const res = await api.patch<{ data: BudgetLine }>(`/budget/lines/${payload.ulid}/reject`, {
        remarks: payload.remarks,
      })
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['budget-lines'] })
    },
  })
}

// ── Utilisation ───────────────────────────────────────────────────────────────

export function useBudgetUtilisation(costCenterId: number | null) {
  return useQuery({
    queryKey: ['budget-utilisation', costCenterId],
    queryFn: async () => {
      const res = await api.get<{ data: BudgetUtilisation }>(`/budget/utilisation/${costCenterId}`)
      return res.data.data
    },
    enabled: costCenterId !== null,
    staleTime: 30_000,
  })
}
