import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { ClientOrder, CreateClientOrderPayload, ClientOrderFilters } from '@/types/client-order'

const API_BASE = '/crm/client-orders'

// ─────────────────────────────────────────────────────────────────────────────
// Queries
// ─────────────────────────────────────────────────────────────────────────────

export function useClientOrders(filters: ClientOrderFilters = {}) {
  return useQuery({
    queryKey: ['client-orders', filters],
    queryFn: async () => {
      const { data } = await api.get(API_BASE, { params: filters })
      return data
    },
  })
}

export function useMyClientOrders() {
  return useQuery({
    queryKey: ['my-client-orders'],
    queryFn: async () => {
      const { data } = await api.get(`${API_BASE}/my-orders`)
      return data
    },
  })
}

export function useClientOrder(orderUlid: string | null) {
  return useQuery({
    queryKey: ['client-order', orderUlid],
    queryFn: async () => {
      if (!orderUlid) return null
      const { data } = await api.get(`${API_BASE}/${orderUlid}`)
      return data
    },
    enabled: !!orderUlid,
  })
}

export function useAvailableProducts(search?: string) {
  return useQuery({
    queryKey: ['available-products', search],
    queryFn: async () => {
      const { data } = await api.get(`${API_BASE}/products/available`, {
        params: { search },
      })
      return data
    },
  })
}

// ─────────────────────────────────────────────────────────────────────────────
// Mutations
// ─────────────────────────────────────────────────────────────────────────────

export function useSubmitClientOrder() {
  const qc = useQueryClient()
  
  return useMutation({
    mutationFn: async (payload: CreateClientOrderPayload) => {
      const { data } = await api.post(API_BASE, payload)
      return data as ClientOrder
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my-client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-orders'] })
    },
  })
}

export function useApproveClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({ orderUlid, notes }: { orderUlid: string; notes?: string }) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/approve`, { notes })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
    },
  })
}

export function useRejectClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({
      orderUlid,
      reason,
      notes
    }: {
      orderUlid: string;
      reason: string;
      notes?: string
    }) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/reject`, { reason, notes })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
    },
  })
}

export function useNegotiateClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({
      orderUlid,
      reason,
      proposedChanges,
      notes,
    }: {
      orderUlid: string
      reason: string
      proposedChanges?: {
        deliveryDate?: string
        items?: Array<{
          itemId: number
          quantity?: number
          price?: number
        }>
      }
      notes?: string
    }) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/negotiate`, {
        reason,
        proposed_changes: {
          delivery_date: proposedChanges?.deliveryDate,
          items: proposedChanges?.items?.map(item => ({
            item_id: item.itemId,
            quantity: item.quantity,
            price: item.price,
          })),
        },
        notes,
      })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
    },
  })
}

export function useRespondToNegotiation() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({
      orderUlid,
      response,
      counterProposals,
    }: {
      orderUlid: string
      response: 'accept' | 'counter' | 'cancel'
      counterProposals?: {
        deliveryDate?: string
      }
    }) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/respond`, {
        response,
        counter_proposals: counterProposals,
      })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['my-client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
    },
  })
}

export function useCancelClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async (orderUlid: string) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/cancel`)
      return data as ClientOrder
    },
    onSuccess: (_, orderUlid) => {
      qc.invalidateQueries({ queryKey: ['my-client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', orderUlid] })
    },
  })
}

export function useUpdateClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({ orderUlid, payload }: { orderUlid: string; payload: CreateClientOrderPayload }) => {
      const { data } = await api.put(`${API_BASE}/${orderUlid}`, payload)
      return data as ClientOrder
    },
    onSuccess: (_, { orderUlid }) => {
      qc.invalidateQueries({ queryKey: ['my-client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', orderUlid] })
    },
  })
}

// Sales responds to client counter-proposal
export function useSalesRespondToCounter() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({
      orderUlid,
      response,
      counterProposals,
      notes,
    }: {
      orderUlid: string
      response: 'accept' | 'counter' | 'reject'
      counterProposals?: {
        deliveryDate?: string
        items?: Array<{
          itemId: number
          quantity?: number
          price?: number
        }>
        reason?: string
      }
      notes?: string
    }) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/sales-respond`, {
        response,
        counter_proposals: {
          delivery_date: counterProposals?.deliveryDate,
          items: counterProposals?.items?.map(item => ({
            item_id: item.itemId,
            quantity: item.quantity,
            price: item.price,
          })),
          reason: counterProposals?.reason,
        },
        notes,
      })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
    },
  })
}

export function useVpApproveClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({ orderUlid, notes }: { orderUlid: string; notes?: string }) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/vp-approve`, { notes })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
    },
  })
}

export type ForceProductionMode =
  | 'preserve_stock_produce_full'
  | 'consume_stock_then_replenish'
  | 'per_item'

export interface ForceProductionPayload {
  orderUlid: string
  mode: ForceProductionMode
  reason: string
  items?: Array<{
    item_master_id: number
    mode: 'preserve_stock_produce_full' | 'consume_stock_then_replenish'
  }>
}

export function useForceProductionClientOrder() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({ orderUlid, mode, reason, items }: ForceProductionPayload) => {
      const { data } = await api.post(`${API_BASE}/${orderUlid}/force-production`, {
        mode,
        reason,
        items,
      })
      return data as ClientOrder
    },
    onSuccess: (_, variables) => {
      qc.invalidateQueries({ queryKey: ['client-orders'] })
      qc.invalidateQueries({ queryKey: ['client-order', variables.orderUlid] })
      qc.invalidateQueries({ queryKey: ['production-orders'] })
    },
  })
}
