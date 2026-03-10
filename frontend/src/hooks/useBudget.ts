import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { AnnualBudget, BudgetUtilisationLine, CostCenter } from '@/types/budget'

// ── Cost Centers ─────────────────────────────────────────────────────────────

export function useCostCenters(filters: {
  active_only?: boolean
  department_id?: number
} = {}) {
  return useQuery({
    queryKey: ['cost-centers', filters],
    queryFn: async () => {
      const res = await api.get<{ data: CostCenter[] }>('/budget/cost-centers', { params: filters })
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useStoreCostCenter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<CostCenter>) => {
      const res = await api.post<{ data: CostCenter }>('/budget/cost-centers', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cost-centers'] })
    },
  })
}

export function useUpdateCostCenter(costCenterUlid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<CostCenter>) => {
      const res = await api.patch<{ data: CostCenter }>(`/budget/cost-centers/${costCenterUlid}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cost-centers'] })
    },
  })
}

// ── Annual Budgets ────────────────────────────────────────────────────────────

export function useBudgetLines(costCenterId: number | null, fiscalYear: number | null) {
  return useQuery({
    queryKey: ['budget-lines', costCenterId, fiscalYear],
    queryFn: async () => {
      const res = await api.get<{ data: AnnualBudget[] }>('/budget/annual', {
        params: { cost_center_id: costCenterId, fiscal_year: fiscalYear },
      })
      return res.data.data
    },
    enabled: costCenterId !== null && fiscalYear !== null,
    staleTime: 60_000,
  })
}

export function useSetBudgetLine() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      cost_center_id: number
      fiscal_year: number
      account_id: number
      budgeted_amount_centavos: number
      notes?: string
    }) => {
      const res = await api.post<{ data: AnnualBudget }>('/budget/annual', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['budget-lines'] })
    },
  })
}

// ── Utilisation ───────────────────────────────────────────────────────────────

export function useBudgetUtilisation(costCenterUlid: string | null, fiscalYear: number | null) {
  return useQuery({
    queryKey: ['budget-utilisation', costCenterUlid, fiscalYear],
    queryFn: async () => {
      const res = await api.get<{ data: { cost_center: CostCenter; fiscal_year: number; lines: BudgetUtilisationLine[] } }>(
        `/budget/cost-centers/${costCenterUlid}/utilisation`,
        { params: { fiscal_year: fiscalYear } },
      )
      return res.data.data
    },
    enabled: costCenterUlid !== null && fiscalYear !== null,
    staleTime: 30_000,
  })
}
