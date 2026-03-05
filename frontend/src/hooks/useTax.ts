import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { VatLedger, ClosePeriodPayload } from '@/types/tax'

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
