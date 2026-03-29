import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  GoodsReceipt,
  GoodsReceiptFilters,
  CreateGoodsReceiptPayload,
  Paginated,
} from '@/types/procurement'

// ── List ─────────────────────────────────────────────────────────────────────

export function useGoodsReceipts(filters: GoodsReceiptFilters = {}) {
  return useQuery({
    queryKey: ['goods-receipts', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<GoodsReceipt>>(
        '/procurement/goods-receipts',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Single ───────────────────────────────────────────────────────────────────

export function useGoodsReceipt(ulid: string | null) {
  return useQuery({
    queryKey: ['goods-receipts', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}`,
      )
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

// ── Create ───────────────────────────────────────────────────────────────────

export function useCreateGoodsReceipt() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateGoodsReceiptPayload) => {
      const res = await api.post<{ data: GoodsReceipt }>(
        '/procurement/goods-receipts',
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })
}

// ── Delete (draft only) ─────────────────────────────────────────────────────

export function useDeleteGoodsReceipt() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      await api.delete(`/procurement/goods-receipts/${ulid}`)
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })
}

// ── Confirm (triggers three-way match) ───────────────────────────────────────

export function useConfirmGoodsReceipt() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}/confirm`,
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}

// ── Update draft GR header ───────────────────────────────────────────────────

export function useUpdateGoodsReceipt() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, data }: { ulid: string; data: Record<string, unknown> }) => {
      const res = await api.put<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}`,
        data,
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}

// ── Update draft GR item (condition, quantity, remarks) ──────────────────────

export function useUpdateGoodsReceiptItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, itemId, data }: { ulid: string; itemId: number; data: Record<string, unknown> }) => {
      const res = await api.patch<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}/items/${itemId}`,
        data,
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}

// ── Submit for QC (draft -> pending_qc) ──────────────────────────────────────

export function useSubmitForQc() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}/submit-for-qc`,
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}

// ── Reject (draft, pending_qc, or qc_failed) ────────────────────────────────

export function useRejectGoodsReceipt() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, reason }: { ulid: string; reason: string }) => {
      const res = await api.post<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}/reject`,
        { reason },
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}

// ── Accept with Defects (qc_failed -> partial_accept) ────────────────────────

export interface AcceptWithDefectsPayload {
  ulid: string
  items: Array<{
    gr_item_id: number
    quantity_accepted: number
    quantity_rejected: number
    defect_type?: string
    defect_description?: string
    ncr_id?: number
  }>
  notes?: string
}

export function useAcceptWithDefects() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, ...data }: AcceptWithDefectsPayload) => {
      const res = await api.post<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}/accept-with-defects`,
        data,
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}

// ── Return to Supplier (confirmed -> returned) ───────────────────────────────

export interface ReturnToSupplierPayload {
  ulid: string
  reason: string
  items?: Array<{ gr_item_id: number; quantity_returned: number }>
}

export function useReturnToSupplier() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, ...data }: ReturnToSupplierPayload) => {
      const res = await api.post<{ data: GoodsReceipt }>(
        `/procurement/goods-receipts/${ulid}/return-to-supplier`,
        data,
      )
      return res.data.data
    },
    onSuccess: (gr) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      qc.setQueryData(['goods-receipts', gr.ulid], gr)
    },
  })
}
