import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { VatLedger, ClosePeriodPayload } from '@/types/tax'

// BIR Filing types (inline — no dedicated type file yet)
interface BirFiling {
  id: number
  ulid: string
  form_type: string
  fiscal_period_id: number
  due_date: string | null
  filed_date: string | null
  status: 'pending' | 'filed' | 'amended' | 'overdue'
  total_tax_due_centavos: number | null
  confirmation_number: string | null
  notes: string | null
  fiscal_period?: { id: number; name: string }
  filed_by?: { id: number; name: string }
}

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
// VAT Ledger (VAT-004)
// ===========================================================================

export function useVatLedgerList(params: {
  fiscal_period_id?: string
  is_closed?: boolean
  per_page?: number
} = {}) {
  return useQuery({
    queryKey: ['vat-ledger', params],
    queryFn: async () => {
      const res = await api.get<Paginated<VatLedger>>('/tax/vat-ledger', { params })
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useVatLedgerForPeriod(fiscalPeriodId: number | null) {
  return useQuery({
    queryKey: ['vat-ledger', 'period', fiscalPeriodId],
    queryFn: async () => {
      const res = await api.get<Paginated<VatLedger>>('/tax/vat-ledger', {
        params: { fiscal_period_id: String(fiscalPeriodId) },
      })
      return res.data.data[0] ?? null
    },
    enabled: fiscalPeriodId !== null,
    staleTime: 60_000,
  })
}

export function useVatLedger(id: number | null) {
  return useQuery({
    queryKey: ['vat-ledger', id],
    queryFn: async () => {
      const res = await api.get<{ data: VatLedger }>(`/tax/vat-ledger/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 60_000,
  })
}

/** VAT-004: close period — carries forward negative net_vat to next period. */
export function useCloseVatPeriod(vatLedgerId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ClosePeriodPayload = {}) => {
      const res = await api.patch<{ data: VatLedger }>(`/tax/vat-ledger/${vatLedgerId}/close`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vat-ledger'] })
    },
  })
}

export function useGenerateVatReturn() {
  return useMutation({
    mutationFn: async (payload: { month: number; year: number }) => {
      const res = await api.get<{ data: Record<string, unknown> }>('/tax/bir-forms/vat-return', {
        params: payload,
      })
      return res.data.data
    },
  })
}

// ===========================================================================
// BIR Filings
// ===========================================================================

export function useBirFilings(filters: {
  status?: string
  form_type?: string
  fiscal_year?: number
} = {}) {
  return useQuery({
    queryKey: ['bir-filings', filters],
    queryFn: async () => {
      const res = await api.get<{ data: BirFiling[] }>('/tax/bir-filings', { params: filters })
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useBirFilingCalendar(fiscalYear: number | null) {
  return useQuery({
    queryKey: ['bir-filings', 'calendar', fiscalYear],
    queryFn: async () => {
      const res = await api.get<{ data: Record<string, BirFiling[]> }>('/tax/bir-filings/calendar', {
        params: { fiscal_year: fiscalYear },
      })
      return res.data.data
    },
    enabled: fiscalYear !== null,
    staleTime: 300_000,
  })
}

export function useBirFilingOverdue() {
  return useQuery({
    queryKey: ['bir-filings', 'overdue'],
    queryFn: async () => {
      const res = await api.get<{ data: BirFiling[] }>('/tax/bir-filings/overdue')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useScheduleBirFiling() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      form_type: string
      fiscal_period_id: number
      due_date?: string
      total_tax_due_centavos?: number
      notes?: string
    }) => {
      const res = await api.post<{ data: BirFiling }>('/tax/bir-filings', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bir-filings'] })
    },
  })
}

export function useMarkBirFiled(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      filed_date: string
      confirmation_number?: string
      total_tax_due_centavos?: number
      notes?: string
    }) => {
      const res = await api.patch<{ data: BirFiling }>(`/tax/bir-filings/${id}/file`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bir-filings'] })
    },
  })
}

export function useMarkBirAmended(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      confirmation_number?: string
      total_tax_due_centavos?: number
      notes?: string
    }) => {
      const res = await api.patch<{ data: BirFiling }>(`/tax/bir-filings/${id}/amend`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bir-filings'] })
    },
  })
}
