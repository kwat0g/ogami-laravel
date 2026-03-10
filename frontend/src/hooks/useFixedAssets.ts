import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { FixedAsset, FixedAssetCategory, AssetDisposal } from '@/types/fixed_assets'

interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// ── Categories ───────────────────────────────────────────────────────────────

export function useFixedAssetCategories() {
  return useQuery({
    queryKey: ['fixed-asset-categories'],
    queryFn: async () => {
      const res = await api.get<FixedAssetCategory[]>('/fixed-assets/categories')
      return res.data
    },
    staleTime: 300_000,
  })
}

export function useStoreFixedAssetCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<FixedAssetCategory>) => {
      const res = await api.post<{ data: FixedAssetCategory }>('/fixed-assets/categories', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-asset-categories'] })
    },
  })
}

// ── Asset Register ───────────────────────────────────────────────────────────

export function useFixedAssets(filters: {
  status?: string
  category_id?: number
  per_page?: number
} = {}) {
  return useQuery({
    queryKey: ['fixed-assets', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<FixedAsset>>('/fixed-assets', { params: filters })
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useFixedAsset(ulid: string | null) {
  return useQuery({
    queryKey: ['fixed-assets', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: FixedAsset }>(`/fixed-assets/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
    staleTime: 30_000,
  })
}

export function useStoreFixedAsset() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<FixedAsset>) => {
      const res = await api.post<{ data: FixedAsset }>('/fixed-assets', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
  })
}

export function useUpdateFixedAsset(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<FixedAsset>) => {
      const res = await api.patch<{ data: FixedAsset }>(`/fixed-assets/${ulid}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
  })
}

// ── Depreciation ─────────────────────────────────────────────────────────────

export function useDepreciatePeriod() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { fiscal_period_id: number }) => {
      const res = await api.post<{ message: string; count: number }>('/fixed-assets/depreciate', payload)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
  })
}

// ── Disposal ─────────────────────────────────────────────────────────────────

export function useDisposeAsset(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      disposal_date: string
      proceeds_centavos?: number
      disposal_method?: string
      notes?: string
    }) => {
      const res = await api.post<{ data: AssetDisposal }>(`/fixed-assets/${ulid}/dispose`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
  })
}
