import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  FixedAsset,
  FixedAssetCategory,
  AssetDisposal,
} from '@/types/fixed_assets'

interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ── Categories ────────────────────────────────────────────────────────────────

export function useFixedAssetCategories() {
  return useQuery({
    queryKey: ['fixed-asset-categories'],
    queryFn: async () => {
      const res = await api.get<{ data: FixedAssetCategory[] }>('/fixed-assets/categories')
      return res.data.data
    },
    staleTime: 120_000,
  })
}

export function useCreateFixedAssetCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Omit<FixedAssetCategory, 'id'>) => {
      const res = await api.post<{ data: FixedAssetCategory }>('/fixed-assets/categories', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-asset-categories'] })
    },
  })
}

// ── Asset Register ────────────────────────────────────────────────────────────

export function useFixedAssets(params: {
  category_id?: number
  status?: string
  search?: string
  page?: number
  per_page?: number
} = {}) {
  return useQuery({
    queryKey: ['fixed-assets', params],
    queryFn: async () => {
      const res = await api.get<Paginated<FixedAsset>>('/fixed-assets', { params })
      return res.data
    },
    staleTime: 30_000,
    placeholderData: keepPreviousData,
  })
}

export function useFixedAsset(id: string | null) {
  return useQuery({
    queryKey: ['fixed-assets', id],
    queryFn: async () => {
      const res = await api.get<{ data: FixedAsset }>(`/fixed-assets/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useCreateFixedAsset() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const res = await api.post<{ data: FixedAsset }>('/fixed-assets', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
  })
}

export function useUpdateFixedAsset(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const res = await api.put<{ data: FixedAsset }>(`/fixed-assets/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
      qc.invalidateQueries({ queryKey: ['fixed-assets', id] })
    },
  })
}

// ── Depreciation ──────────────────────────────────────────────────────────────

export function useDepreciatePeriod() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { fiscal_period_id: number }) => {
      const res = await api.post<{ data: { processed: number; skipped: number } }>('/fixed-assets/depreciate', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
  })
}

// ── Disposal ──────────────────────────────────────────────────────────────────

export function useDisposeAsset(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      disposal_date: string
      disposal_method: string
      sale_price_centavos?: number
      notes?: string
    }) => {
      const res = await api.post<{ data: AssetDisposal }>(`/fixed-assets/${id}/dispose`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
      qc.invalidateQueries({ queryKey: ['fixed-assets', id] })
    },
  })
}
