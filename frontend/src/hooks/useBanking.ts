import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  BankAccount,
  BankReconciliation,
  BankTransaction,
  BankReconciliationFilters,
  CreateBankAccountPayload,
  CreateBankReconciliationPayload,
  ImportStatementPayload,
  MatchTransactionPayload,
} from '@/types/banking'

// ---------------------------------------------------------------------------
// Paginated list helper
// ---------------------------------------------------------------------------

interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// ===========================================================================
// Bank Accounts
// ===========================================================================

export function useBankAccounts(includeInactive = false) {
  return useQuery({
    queryKey: ['bank-accounts', { includeInactive }],
    queryFn: async () => {
      const res = await api.get<{ data: BankAccount[] }>('/accounting/bank-accounts', {
        params: includeInactive ? { include_inactive: true } : {},
      })
      return res.data.data
    },
    staleTime: 30_000,
  })
}

export function useBankAccount(id: number | null) {
  return useQuery({
    queryKey: ['bank-accounts', id],
    queryFn: async () => {
      const res = await api.get<{ data: BankAccount }>(`/accounting/bank-accounts/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useCreateBankAccount() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateBankAccountPayload) => {
      const res = await api.post<{ data: BankAccount }>('/accounting/bank-accounts', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-accounts'] })
    },
  })
}

export function useUpdateBankAccount(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<CreateBankAccountPayload>) => {
      const res = await api.put<{ data: BankAccount }>(`/accounting/bank-accounts/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-accounts'] })
      qc.invalidateQueries({ queryKey: ['bank-accounts', id] })
    },
  })
}

export function useDeleteBankAccount() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/accounting/bank-accounts/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-accounts'] })
    },
  })
}

// ===========================================================================
// Bank Reconciliations
// ===========================================================================

export function useBankReconciliations(filters: BankReconciliationFilters = {}) {
  return useQuery({
    queryKey: ['bank-reconciliations', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<BankReconciliation>>(
        '/accounting/bank-reconciliations',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useBankReconciliation(id: string | null) {
  return useQuery({
    queryKey: ['bank-reconciliations', id],
    queryFn: async () => {
      const res = await api.get<{ data: BankReconciliation }>(
        `/accounting/bank-reconciliations/${id}`,
      )
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useCreateBankReconciliation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateBankReconciliationPayload) => {
      const res = await api.post<{ data: BankReconciliation }>(
        '/accounting/bank-reconciliations',
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-reconciliations'] })
    },
  })
}

export function useImportStatement(reconciliationId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ImportStatementPayload) => {
      const res = await api.post<{ imported_count: number }>(
        `/accounting/bank-reconciliations/${reconciliationId}/import-statement`,
        payload,
      )
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-reconciliations', reconciliationId] })
    },
  })
}

export function useMatchTransaction(reconciliationId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: MatchTransactionPayload) => {
      const res = await api.patch<{ data: BankTransaction }>(
        `/accounting/bank-reconciliations/${reconciliationId}/match`,
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-reconciliations', reconciliationId] })
    },
  })
}

export function useUnmatchTransaction(reconciliationId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (bankTransactionId: number) => {
      const res = await api.patch<{ data: BankTransaction }>(
        `/accounting/bank-reconciliations/${reconciliationId}/transactions/${bankTransactionId}/unmatch`,
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-reconciliations', reconciliationId] })
    },
  })
}

export function useCertifyReconciliation(reconciliationId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: BankReconciliation }>(
        `/accounting/bank-reconciliations/${reconciliationId}/certify`,
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bank-reconciliations'] })
      qc.invalidateQueries({ queryKey: ['bank-reconciliations', reconciliationId] })
    },
  })
}
