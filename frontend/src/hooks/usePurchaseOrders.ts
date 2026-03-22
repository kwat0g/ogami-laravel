import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  PurchaseOrder,
  PurchaseOrderFilters,
  CreatePurchaseOrderPayload,
  Paginated,
} from '@/types/procurement'

// ── List ─────────────────────────────────────────────────────────────────────

export function usePurchaseOrders(filters: PurchaseOrderFilters | undefined = {}) {
  return useQuery({
    queryKey: ['purchase-orders', filters],
    enabled: filters !== undefined,
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
    mutationFn: async ({ ulid, delivery_date }: { ulid: string; delivery_date: string }) => {
      const res = await api.post<{ data: PurchaseOrder }>(
        `/procurement/purchase-orders/${ulid}/send`,
        { delivery_date },
      )
      return res.data.data
    },
    onSuccess: (po, { ulid }) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders', ulid] })
      qc.setQueryData(['purchase-orders', ulid], po)
    },
  })
}

// ── Assign Vendor ─────────────────────────────────────────────────────────────

export interface AssignVendorPayload {
  vendor_id: number
  delivery_date?: string
  payment_terms?: string
  delivery_address?: string
  notes?: string
  items: Array<{
    po_item_id: number
    item_master_id?: number | null
    agreed_unit_cost: number
  }>
}

export function useAssignVendor() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: AssignVendorPayload }) => {
      const res = await api.post<{ data: PurchaseOrder }>(
        `/procurement/purchase-orders/${ulid}/assign-vendor`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (po, { ulid }) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders', ulid] })
      qc.setQueryData(['purchase-orders', ulid], po)
    },
  })
}

// ── Accept Changes (Officer) ──────────────────────────────────────────────────

export function useAcceptChanges() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, remarks }: { ulid: string; remarks?: string }) => {
      const res = await api.post<{ data: PurchaseOrder }>(
        `/procurement/purchase-orders/${ulid}/accept-changes`,
        { remarks },
      )
      return res.data.data
    },
    onSuccess: (po, { ulid }) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders', ulid] })
      qc.setQueryData(['purchase-orders', ulid], po)
    },
  })
}

// ── Reject Changes (Officer) ──────────────────────────────────────────────────

export function useRejectChanges() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, remarks }: { ulid: string; remarks: string }) => {
      const res = await api.post<{ data: PurchaseOrder }>(
        `/procurement/purchase-orders/${ulid}/reject-changes`,
        { remarks },
      )
      return res.data.data
    },
    onSuccess: (po, { ulid }) => {
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
