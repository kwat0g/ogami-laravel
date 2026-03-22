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
  JournalEntryTemplate,
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

// ===========================================================================
// Recurring Journal Templates
// ===========================================================================

export interface RecurringJournalTemplate {
  id: number
  ulid: string
  description: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'yearly'
  next_run_date: string
  is_active: boolean
  lines: { account_id: number; debit_centavos: number; credit_centavos: number; description: string }[]
  created_at: string
}

export function useRecurringTemplates() {
  return useQuery({
    queryKey: ['recurring-templates'],
    queryFn: async () => {
      const res = await api.get<{ data: RecurringJournalTemplate[] }>('/accounting/recurring-templates')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useRecurringTemplate(id: string | null) {
  return useQuery({
    queryKey: ['recurring-templates', id],
    queryFn: async () => {
      const res = await api.get<{ data: RecurringJournalTemplate }>(`/accounting/recurring-templates/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 60_000,
  })
}

export function useCreateRecurringTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const res = await api.post<{ data: RecurringJournalTemplate }>('/accounting/recurring-templates', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recurring-templates'] })
    },
  })
}

export function useUpdateRecurringTemplate(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const res = await api.put<{ data: RecurringJournalTemplate }>(`/accounting/recurring-templates/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recurring-templates'] })
      qc.invalidateQueries({ queryKey: ['recurring-templates', id] })
    },
  })
}

export function useToggleRecurringTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await api.patch<{ data: RecurringJournalTemplate }>(`/accounting/recurring-templates/${id}/toggle`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recurring-templates'] })
    },
  })
}

export function useDeleteRecurringTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      await api.delete(`/accounting/recurring-templates/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recurring-templates'] })
    },
  })
}


// ===========================================================================
// Journal Entry Templates
// ===========================================================================

export function useJournalEntryTemplates() {
  return useQuery({
    queryKey: ['journal-entry-templates'],
    queryFn: async () => {
      const res = await api.get<{ data: JournalEntryTemplate[] }>('/accounting/journal-entry-templates')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useApplyJournalEntryTemplate() {
  return useMutation({
    mutationFn: async (templateId: number) => {
      const res = await api.get<{
        template_id: number
        template_name: string
        lines: Array<{
          account_id: number
          account_name: string
          account_code: string
          debit_or_credit: 'debit' | 'credit'
          description: string | null
          amount: string
        }>
      }>(`/accounting/journal-entry-templates/${templateId}/apply`)
      return res.data
    },
  })
}

export function useCreateJournalEntryTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      name: string
      description?: string
      lines: Array<{
        account_id: number
        debit_or_credit: 'debit' | 'credit'
        description?: string
      }>
    }) => {
      const res = await api.post('/accounting/journal-entry-templates', payload)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['journal-entry-templates'] })
    },
  })
}

export function useDeleteJournalEntryTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (templateId: number) => {
      await api.delete(`/accounting/journal-entry-templates/${templateId}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['journal-entry-templates'] })
    },
  })
}
