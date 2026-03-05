import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type { PurchaseRequest, Paginated } from '@/types/procurement'
import type { Loan } from '@/types/hr'
import type { MaterialRequisition } from '@/types/inventory'

// ── Purchase Requests awaiting VP approval ────────────────────────────────────

export function useVpPendingPurchaseRequests() {
  return useQuery({
    queryKey: ['vp-approvals', 'purchase-requests'],
    queryFn: async () => {
      const res = await api.get<Paginated<PurchaseRequest>>(
        '/procurement/purchase-requests',
        { params: { status: 'reviewed', per_page: 50 } },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Loans awaiting VP approval ────────────────────────────────────────────────

export function useVpPendingLoans() {
  return useQuery({
    queryKey: ['vp-approvals', 'loans'],
    queryFn: async () => {
      const res = await api.get<Paginated<Loan>>(
        '/loans',
        { params: { status: 'officer_reviewed', per_page: 50 } },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Material Requisitions awaiting VP approval ────────────────────────────────

export function useVpPendingMrqs() {
  return useQuery({
    queryKey: ['vp-approvals', 'mrqs'],
    queryFn: async () => {
      const res = await api.get<Paginated<MaterialRequisition>>(
        '/inventory/requisitions',
        { params: { status: 'reviewed', per_page: 50 } },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}
