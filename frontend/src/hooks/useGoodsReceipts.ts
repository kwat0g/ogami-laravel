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
