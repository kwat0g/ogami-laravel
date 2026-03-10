import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface VendorItem {
  id: number
  ulid: string
  vendor_id: number
  item_code: string
  item_name: string
  description: string | null
  unit_of_measure: string
  unit_price: number
  unit_price_formatted: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface VendorItemsPage {
  data: VendorItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface CreateVendorItemPayload {
  item_code: string
  item_name: string
  description?: string
  unit_of_measure?: string
  unit_price: number
  is_active?: boolean
}

export type UpdateVendorItemPayload = Partial<CreateVendorItemPayload>

export interface ImportVendorItemsPayload {
  items: Array<{
    item_code: string
    item_name: string
    description?: string
    unit_of_measure?: string
    unit_price: number
    is_active?: boolean
  }>
}

export interface ImportResult {
  success: boolean
  created: number
  updated: number
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

export function useVendorItems(vendorId: number | null, activeOnly = false) {
  return useQuery({
    queryKey: ['vendor-items', vendorId, { activeOnly }],
    queryFn: async () => {
      const res = await api.get<VendorItemsPage>(
        `/finance/vendors/${vendorId}/items`,
        { params: { active_only: activeOnly ? 1 : 0 } },
      )
      return res.data
    },
    enabled: vendorId !== null,
    staleTime: 30_000,
  })
}

export function useCreateVendorItem(vendorId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateVendorItemPayload) => {
      const res = await api.post<{ data: VendorItem }>(
        `/finance/vendors/${vendorId}/items`,
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-items', vendorId] })
    },
  })
}

export function useUpdateVendorItem(vendorId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ itemId, payload }: { itemId: number; payload: UpdateVendorItemPayload }) => {
      const res = await api.put<{ data: VendorItem }>(
        `/finance/vendors/${vendorId}/items/${itemId}`,
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-items', vendorId] })
    },
  })
}

export function useDeleteVendorItem(vendorId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (itemId: number) => {
      await api.delete(`/finance/vendors/${vendorId}/items/${itemId}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-items', vendorId] })
    },
  })
}

export function useImportVendorItems(vendorId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ImportVendorItemsPayload) => {
      const res = await api.post<ImportResult>(
        `/finance/vendors/${vendorId}/items/import`,
        payload,
      )
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-items', vendorId] })
    },
  })
}
