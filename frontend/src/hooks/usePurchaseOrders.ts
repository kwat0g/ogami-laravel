import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  PurchaseOrder,
  PurchaseOrderFilters,
  CreatePurchaseOrderPayload,
  Paginated,
} from '@/types/procurement'

// ── List ─────────────────────────────────────────────────────────────────────

export function usePurchaseOrders(filters: PurchaseOrderFilters = {}) {
  return useQuery({
    queryKey: ['purchase-orders', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<PurchaseOrder>>(
        '/procurement/purchase-orders',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Single ───────────────────────────────────────────────────────────────────

export function usePurchaseOrder(ulid: string | null) {
  return useQuery({
    queryKey: ['purchase-orders', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: PurchaseOrder }>(
        `/procurement/purchase-orders/${ulid}`,
      )
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

// ── Create ───────────────────────────────────────────────────────────────────

export function useCreatePurchaseOrder() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreatePurchaseOrderPayload) => {
      const res = await api.post<{ data: PurchaseOrder }>(
        '/procurement/purchase-orders',
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
    },
  })
}

// ── Send to Vendor ────────────────────────────────────────────────────────────

export function useSendPurchaseOrder() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: PurchaseOrder }>(
        `/procurement/purchase-orders/${ulid}/send`,
      )
      return res.data.data
    },
    onSuccess: (po, ulid) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders', ulid] })
      qc.setQueryData(['purchase-orders', ulid], po)
    },
  })
}

// ── Cancel ───────────────────────────────────────────────────────────────────

export function useCancelPurchaseOrder() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, reason }: { ulid: string; reason: string }) => {
      const res = await api.post(
        `/procurement/purchase-orders/${ulid}/cancel`,
        { reason },
      )
      return res.data
    },
    onSuccess: (_data, { ulid }) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders', ulid] })
    },
  })
}
