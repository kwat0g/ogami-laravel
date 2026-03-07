import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  ItemCategory,
  ItemMaster,
  WarehouseLocation,
  StockBalance,
  StockLedger,
  MaterialRequisition,
  CreateItemMasterPayload,
  CreateMaterialRequisitionPayload,
  StockAdjustmentPayload,
  Paginated,
} from '@/types/inventory'

// ── Item Categories ──────────────────────────────────────────────────────────

export function useItemCategories() {
  return useQuery({
    queryKey: ['item-categories'],
    queryFn: async () => {
      const res = await api.get<{ data: ItemCategory[] }>('/inventory/items/categories')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useCreateItemCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { code: string; name: string; description?: string }) =>
      api.post('/inventory/items/categories', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['item-categories'] }),
  })
}

// ── Item Master ──────────────────────────────────────────────────────────────

export function useItems(params: {
  search?: string
  category_id?: number
  type?: string
  is_active?: boolean
  page?: number
  per_page?: number
  with_archived?: boolean
} = {}) {
  return useQuery({
    queryKey: ['items', params],
    queryFn: async () => {
      const res = await api.get<Paginated<ItemMaster>>('/inventory/items', { params })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useLowStockItems() {
  return useQuery({
    queryKey: ['items-low-stock'],
    queryFn: async () => {
      const res = await api.get<{ data: ItemMaster[] }>('/inventory/items/low-stock')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useItem(ulid: string | null) {
  return useQuery({
    queryKey: ['items', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: ItemMaster }>(`/inventory/items/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

export function useCreateItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateItemMasterPayload) =>
      api.post('/inventory/items', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['items'] }),
  })
}

export function useUpdateItem(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<CreateItemMasterPayload>) =>
      api.put(`/inventory/items/${ulid}`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['items'] })
    },
  })
}

export function useToggleItemActive(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.patch(`/inventory/items/${ulid}/toggle-active`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['items'] }),
  })
}

// ── Warehouse Locations ──────────────────────────────────────────────────────

export function useWarehouseLocations(params: {
  department_id?: number
  is_active?: boolean
  search?: string
} = {}) {
  return useQuery({
    queryKey: ['warehouse-locations', params],
    queryFn: async () => {
      const res = await api.get<{ data: WarehouseLocation[] }>('/inventory/locations', { params })
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useCreateLocation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: {
      code: string
      name: string
      zone?: string
      bin?: string
      department_id?: number
    }) => api.post('/inventory/locations', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['warehouse-locations'] }),
  })
}

export function useUpdateLocation(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { name?: string; zone?: string; bin?: string; is_active?: boolean }) =>
      api.put(`/inventory/locations/${id}`, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['warehouse-locations'] }),
  })
}

// ── Stock Balances ───────────────────────────────────────────────────────────

export function useStockBalances(params: {
  item_id?: number
  location_id?: number
  low_stock?: boolean
  search?: string
  page?: number
  per_page?: number
} = {}) {
  return useQuery({
    queryKey: ['stock-balances', params],
    queryFn: async () => {
      const res = await api.get<Paginated<StockBalance>>('/inventory/stock-balances', { params })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Stock Ledger ─────────────────────────────────────────────────────────────

export function useStockLedger(params: {
  item_id?: number
  location_id?: number
  transaction_type?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
} = {}) {
  return useQuery({
    queryKey: ['stock-ledger', params],
    queryFn: async () => {
      const res = await api.get<Paginated<StockLedger>>('/inventory/stock-ledger', { params })
      return res.data
    },
    staleTime: 30_000,
  })
}

// ── Stock Adjustment ─────────────────────────────────────────────────────────

export function useStockAdjust() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: StockAdjustmentPayload) =>
      api.post('/inventory/adjustments', payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['stock-balances'] })
      qc.invalidateQueries({ queryKey: ['stock-ledger'] })
    },
  })
}

// ── Material Requisitions ────────────────────────────────────────────────────

export function useMaterialRequisitions(params: {
  status?: string
  department_id?: number
  search?: string
  page?: number
  per_page?: number
  with_archived?: boolean
} = {}) {
  return useQuery({
    queryKey: ['material-requisitions', params],
    queryFn: async () => {
      const res = await api.get<Paginated<MaterialRequisition>>('/inventory/requisitions', { params })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useMaterialRequisition(ulid: string | null) {
  return useQuery({
    queryKey: ['material-requisitions', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: MaterialRequisition }>(`/inventory/requisitions/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

export function useCreateMRQ() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateMaterialRequisitionPayload) =>
      api.post<{ data: MaterialRequisition }>('/inventory/requisitions', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['material-requisitions'] }),
  })
}

// ── MRQ workflow mutations ───────────────────────────────────────────────────

function mrqWorkflowMutation(
  ulid: string,
  action: string,
  qc: ReturnType<typeof useQueryClient>,
) {
  return (payload?: { comments?: string; reason?: string }) =>
    api.patch(`/inventory/requisitions/${ulid}/${action}`, payload ?? {}).then(() => {
      qc.invalidateQueries({ queryKey: ['material-requisitions'] })
    })
}

export function useSubmitMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: () => mrqWorkflowMutation(ulid, 'submit', qc)() })
}

export function useNoteMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { comments?: string }) =>
      mrqWorkflowMutation(ulid, 'note', qc)(payload),
  })
}

export function useCheckMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { comments?: string }) =>
      mrqWorkflowMutation(ulid, 'check', qc)(payload),
  })
}

export function useReviewMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { comments?: string }) =>
      mrqWorkflowMutation(ulid, 'review', qc)(payload),
  })
}

export function useVpApproveMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { comments?: string }) =>
      mrqWorkflowMutation(ulid, 'vp-approve', qc)(payload),
  })
}

export function useRejectMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { reason: string }) =>
      mrqWorkflowMutation(ulid, 'reject', qc)(payload),
  })
}

export function useCancelMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: () => mrqWorkflowMutation(ulid, 'cancel', qc)() })
}

export function useFulfillMRQ(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { location_id: number }) =>
      api.patch(`/inventory/requisitions/${ulid}/fulfill`, payload).then(() => {
        qc.invalidateQueries({ queryKey: ['material-requisitions'] })
      }),
  })
}
