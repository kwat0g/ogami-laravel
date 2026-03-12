import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface VendorRfqVendor {
  id: number
  vendor_id: number
  vendor?: { id: number; name: string }
  status: 'pending' | 'quoted' | 'declined'
  quoted_amount_centavos: number | null
  quoted_at: string | null
  notes: string | null
}

export interface VendorRfq {
  id: number
  ulid: string
  rfq_reference: string
  title: string
  description: string | null
  status: 'draft' | 'sent' | 'closed' | 'cancelled'
  deadline: string | null
  vendors: VendorRfqVendor[]
  created_by_id: number
  created_by?: { id: number; name: string }
  created_at: string
}

interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

export function useVendorRfqs(params: { status?: string; page?: number } = {}) {
  return useQuery({
    queryKey: ['vendor-rfqs', params],
    queryFn: async () => {
      const res = await api.get<Paginated<VendorRfq>>('/procurement/rfqs', { params })
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useVendorRfq(ulid: string | null) {
  return useQuery({
    queryKey: ['vendor-rfqs', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: VendorRfq }>(`/procurement/rfqs/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
    staleTime: 30_000,
  })
}

export function useCreateVendorRfq() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { title: string; description?: string; deadline?: string; vendor_ids: number[] }) => {
      const res = await api.post<{ data: VendorRfq }>('/procurement/rfqs', payload)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-rfqs'] }) },
  })
}

export function useSendVendorRfq() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: VendorRfq }>(`/procurement/rfqs/${ulid}/send`)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-rfqs'] }) },
  })
}

export function useRecordQuote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, vendorId, payload }: { ulid: string; vendorId: number; payload: { quoted_amount: number; notes?: string } }) => {
      const res = await api.post(`/procurement/rfqs/${ulid}/vendors/${vendorId}/quote`, payload)
      return res.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-rfqs'] }) },
  })
}

export function useRecordDecline() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ ulid, vendorId }: { ulid: string; vendorId: number }) => {
      const res = await api.post(`/procurement/rfqs/${ulid}/vendors/${vendorId}/decline`)
      return res.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-rfqs'] }) },
  })
}

export function useCloseVendorRfq() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: VendorRfq }>(`/procurement/rfqs/${ulid}/close`)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-rfqs'] }) },
  })
}

export function useCancelVendorRfq() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ulid: string) => {
      const res = await api.post<{ data: VendorRfq }>(`/procurement/rfqs/${ulid}/cancel`)
      return res.data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vendor-rfqs'] }) },
  })
}
