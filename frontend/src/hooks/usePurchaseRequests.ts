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

// ── Review (Purchasing Dept) ─────────────────────────────────────────────────
// Note: useNotePurchaseRequest and useCheckPurchaseRequest removed
// Workflow simplified: draft → pending_review → reviewed → budget_verified → approved

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

// ── Budget Verification (Accounting) ─────────────────────────────────────────

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

// ── Budget Pre-Check (before creating PR) ────────────────────────────────────

export interface BudgetCheckPayload {
  department_id: number
  items: Array<{
    quantity: number
    estimated_unit_cost: number
  }>
}

export interface BudgetCheckResult {
  available: boolean
  budget: number
  ytd_spend: number
  this_pr: number
  remaining: number
  formatted: {
    budget: string
    ytd_spend: string
    this_pr: string
    remaining: string
  }
  message?: string
}

export function useCheckBudgetAvailability() {
  return useMutation({
    mutationFn: async (payload: BudgetCheckPayload) => {
      const res = await api.post<BudgetCheckResult>(
        '/procurement/budget-check',
        payload,
      )
      return res.data
    },
  })
}

// ── Convert MRQ → Purchase Request ──────────────────────────────────────────

export function useConvertMrqToPr() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ mrqUlid, justification }: { mrqUlid: string; justification?: string }) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/from-mrq/${mrqUlid}`,
        { justification },
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      void qc.invalidateQueries({ queryKey: ['material-requisitions'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}

// ── Suggest Vendors (reverse catalog lookup) ─────────────────────────────────

export interface VendorSuggestion {
  vendor_id: number
  vendor_name: string
  vendor_item_id: number
  item_code: string
  item_name: string
  unit_of_measure: string
  unit_price: number
}

export function useSuggestVendors(query: string) {
  return useQuery({
    queryKey: ['suggest-vendors', query],
    queryFn: async () => {
      const res = await api.get<{ data: VendorSuggestion[] }>(
        '/procurement/items/suggest-vendors',
        { params: { q: query } },
      )
      return res.data.data
    },
    enabled: query.trim().length >= 3,
    staleTime: 60_000,
  })
}

// ── Duplicate Purchase Request ───────────────────────────────────────────────

export function useDuplicatePurchaseRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: PurchaseRequest }>(
        `/procurement/purchase-requests/${ulid}/duplicate`,
      )
      return res.data.data
    },
    onSuccess: (pr) => {
      void qc.invalidateQueries({ queryKey: ['purchase-requests'] })
      qc.setQueryData(['purchase-requests', pr.ulid], pr)
    },
  })
}


