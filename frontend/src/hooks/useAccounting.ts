import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  ChartOfAccount,
  CreateAccountPayload,
  FiscalPeriod,
  CreateFiscalPeriodPayload,
  JournalEntry,
  CreateJournalEntryPayload,
  JournalEntryFilters,
} from '@/types/accounting'

// ---------------------------------------------------------------------------
// Paginated list helper types
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
// Chart of Accounts
// ===========================================================================

export function useChartOfAccounts(params: {
  tree?: boolean
  parent_id?: number | null
  include_archived?: boolean
} = {}) {
  return useQuery({
    queryKey: ['chart-of-accounts', params],
    queryFn: async () => {
      const res = await api.get<{ data: ChartOfAccount[] }>('/accounting/accounts', { params })
      return res.data.data
    },
    staleTime: 60_000,
    refetchOnWindowFocus: true,
  })
}

export function useChartOfAccount(id: number | null) {
  return useQuery({
    queryKey: ['chart-of-accounts', id],
    queryFn: async () => {
      const res = await api.get<{ data: ChartOfAccount }>(`/accounting/accounts/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 60_000,
  })
}

export function useCreateAccount() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateAccountPayload) => {
      const res = await api.post<{ data: ChartOfAccount }>('/accounting/accounts', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}

export function useUpdateAccount(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<CreateAccountPayload>) => {
      const res = await api.put<{ data: ChartOfAccount }>(`/accounting/accounts/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}

export function useArchiveAccount(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.delete<{ message: string }>(`/accounting/accounts/${id}`)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}

// ===========================================================================
// Fiscal Periods
// ===========================================================================

export function useFiscalPeriods(status?: 'open' | 'closed') {
  return useQuery({
    queryKey: ['fiscal-periods', status],
    queryFn: async () => {
      const res = await api.get<Paginated<FiscalPeriod>>('/accounting/fiscal-periods', {
        params: status ? { status } : {},
      })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useCreateFiscalPeriod() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateFiscalPeriodPayload) => {
      const res = await api.post<{ data: FiscalPeriod }>('/accounting/fiscal-periods', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fiscal-periods'] })
    },
  })
}

export function useOpenFiscalPeriod(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: FiscalPeriod }>(`/accounting/fiscal-periods/${id}/open`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fiscal-periods'] })
    },
  })
}

export function useCloseFiscalPeriod(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: FiscalPeriod }>(`/accounting/fiscal-periods/${id}/close`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fiscal-periods'] })
    },
  })
}

// ===========================================================================
// Journal Entries
// ===========================================================================

export function useJournalEntries(filters: JournalEntryFilters = {}) {
  return useQuery({
    queryKey: ['journal-entries', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<JournalEntry>>('/accounting/journal-entries', {
        params: filters,
      })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useJournalEntry(id: string | null) {
  return useQuery({
    queryKey: ['journal-entries', id],
    queryFn: async () => {
      const res = await api.get<{ data: JournalEntry }>(`/accounting/journal-entries/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useCreateJournalEntry() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateJournalEntryPayload) => {
      const res = await api.post<{ data: JournalEntry }>('/accounting/journal-entries', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['journal-entries'] })
    },
  })
}

export function useSubmitJournalEntry(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: JournalEntry }>(`/accounting/journal-entries/${id}/submit`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['journal-entries'] })
      qc.invalidateQueries({ queryKey: ['journal-entries', id] })
    },
  })
}

export function usePostJournalEntry(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: JournalEntry }>(`/accounting/journal-entries/${id}/post`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['journal-entries'] })
      qc.invalidateQueries({ queryKey: ['journal-entries', id] })
    },
  })
}

export function useReverseJournalEntry(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (description?: string) => {
      const res = await api.post<{ data: JournalEntry }>(
        `/accounting/journal-entries/${id}/reverse`,
        { description: description ?? '' },
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['journal-entries'] })
    },
  })
}
