import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  PurchaseRequest,
  PurchaseRequestFilters,
  CreatePurchaseRequestPayload,
  UpdatePurchaseRequestPayload,
  PrActionPayload,
  PrRejectPayload,
  Paginated,
} from '@/types/procurement'

// ── List ─────────────────────────────────────────────────────────────────────

export function usePurchaseRequests(filters: PurchaseRequestFilters = {}) {
  return useQuery({
    queryKey: ['purchase-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<PurchaseRequest>>(
        '/procurement/purchase-requests',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Single ───────────────────────────────────────────────────────────────────

export function usePurchaseRequest(ulid: string | null) {
  return useQuery({
    queryKey: ['purchase-requests', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}`,
      )
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

// ── Create ───────────────────────────────────────────────────────────────────

export function useCreatePurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreatePurchaseRequestPayload) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        '/procurement/purchase-requests',
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
    },
  })
}

// ── Update ───────────────────────────────────────────────────────────────────

export function useUpdatePurchaseRequest(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: UpdatePurchaseRequestPayload) => {
      const res = await api.patch<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Submit ───────────────────────────────────────────────────────────────────

export function useSubmitPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/submit`,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Note (Head) ──────────────────────────────────────────────────────────────

export function useNotePurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: PrActionPayload }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/note`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Check (Manager) ──────────────────────────────────────────────────────────

export function useCheckPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: PrActionPayload }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/check`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Review (Officer) ─────────────────────────────────────────────────────────

export function useReviewPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: PrActionPayload }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/review`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── VP Approve ───────────────────────────────────────────────────────────────

export function useVpApprovePurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: PrActionPayload }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/vp-approve`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      void qc.invalidateQueries({ queryKey: ['vp-approvals'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Reject ───────────────────────────────────────────────────────────────────

export function useRejectPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: PrRejectPayload }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/reject`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Cancel ───────────────────────────────────────────────────────────────────

export function useCancelPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post(`/procurement/purchase-requests/${ulid}/cancel`)
      return res.data
    },
    onSuccess: (_data, ulid) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      void qc.invalidateQueries({ queryKey: ['purchase-requests', ulid] })
    },
  })
}

// ── Budget Check ────────────────────────────────────────────────────────────

export function useBudgetCheckPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: PrActionPayload }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/budget-check`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Return for Revision ─────────────────────────────────────────────────────

export function useReturnPurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, payload }: { ulid: string; payload: { reason: string } }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/return`,
        payload,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

