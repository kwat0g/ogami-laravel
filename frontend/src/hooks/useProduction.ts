import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  Bom,
  DeliverySchedule,
  ProductionOrder,
  CreateBomPayload,
  CreateDeliverySchedulePayload,
  CreateProductionOrderPayload,
  LogProductionOutputPayload,
  Paginated,
  SmartDefaults,
} from '@/types/production'

// ── Bill of Materials ────────────────────────────────────────────────────────

export function useBoms(params: { product_item_id?: number; is_active?: boolean; per_page?: number; with_archived?: boolean } = {}) {
  return useQuery({
    queryKey: ['boms', params],
    queryFn: async () => {
      const res = await api.get<Paginated<Bom>>('/production/boms', { params })
      return res.data
    },
    staleTime: 30_000,
    placeholderData: keepPreviousData,
  })
}

export function useBom(ulid: string | null) {
  return useQuery({
    queryKey: ['boms', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: Bom }>(`/production/boms/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

export function useCreateBom() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateBomPayload) =>
      api.post<{ data: Bom }>('/production/boms', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['boms'] }),
  })
}

export function useUpdateBom(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<CreateBomPayload>) =>
      api.put(`/production/boms/${ulid}`, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['boms'] }),
  })
}

// ── Delivery Schedules ───────────────────────────────────────────────────────

export function useDeliverySchedules(params: {
  customer_id?: number
  status?: string
  type?: string
  date_from?: string
  date_to?: string
  per_page?: number
  page?: number
  with_archived?: boolean
} = {}) {
  return useQuery({
    queryKey: ['delivery-schedules', params],
    queryFn: async () => {
      const res = await api.get<Paginated<DeliverySchedule>>('/production/delivery-schedules', { params })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
    placeholderData: keepPreviousData,
  })
}

export function useDeliverySchedule(ulid: string | null) {
  return useQuery({
    queryKey: ['delivery-schedules', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: DeliverySchedule }>(`/production/delivery-schedules/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

export function useCreateDeliverySchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateDeliverySchedulePayload) =>
      api.post('/production/delivery-schedules', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-schedules'] }),
  })
}

export function useUpdateDeliverySchedule(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<CreateDeliverySchedulePayload & { status: string }>) =>
      api.put(`/production/delivery-schedules/${ulid}`, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['delivery-schedules'] }),
  })
}

export function useFulfillFromStock(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<{ data: DeliverySchedule }>(`/production/delivery-schedules/${ulid}/fulfill`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-schedules'] })
      qc.invalidateQueries({ queryKey: ['delivery-schedules', ulid] })
    },
  })
}

// ── Production Orders ────────────────────────────────────────────────────────

export function useProductionOrders(params: {
  status?: string
  product_item_id?: number
  per_page?: number
  page?: number
  with_archived?: boolean
} = {}) {
  return useQuery({
    queryKey: ['production-orders', params],
    queryFn: async () => {
      const res = await api.get<Paginated<ProductionOrder>>('/production/orders', { params })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
    placeholderData: keepPreviousData,
  })
}

export function useProductionOrder(ulid: string | null) {
  return useQuery({
    queryKey: ['production-orders', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: ProductionOrder }>(`/production/orders/${ulid}`)
      return res.data.data
    },
    enabled: ulid !== null,
  })
}

export function useCreateProductionOrder() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateProductionOrderPayload) =>
      api.post<{ data: ProductionOrder }>('/production/orders', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['production-orders'] }),
  })
}

// ── Production Order Workflow ─────────────────────────────────────────────────

function orderAction(ulid: string, action: string, qc: ReturnType<typeof useQueryClient>) {
  return () =>
    api.patch(`/production/orders/${ulid}/${action}`).then(() => {
      qc.invalidateQueries({ queryKey: ['production-orders'] })
    })
}

export function useReleaseOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'release', qc) })
}

export function useStartOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'start', qc) })
}

export function useCompleteOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'complete', qc) })
}

export function useCancelOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'cancel', qc) })
}

export function useVoidOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'void', qc) })
}

export function useLogOutput(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: LogProductionOutputPayload) =>
      api.post(`/production/orders/${ulid}/output`, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['production-orders'] }),
  })
}

export function useDeleteBom() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ulid: string) => api.delete(`/production/boms/${ulid}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['boms'] }),
  })
}

// ── PROD-001: Pre-release Stock Check ────────────────────────────────────────

export interface StockCheckItem {
  component_item_id: number
  item_name: string
  unit_of_measure: string
  required_qty: number
  available_qty: number
  sufficient: boolean
}

export function useStockCheck(ulid: string | null) {
  return useQuery({
    queryKey: ['stock-check', ulid],
    queryFn: async () => {
      const res = await api.get<{ data: StockCheckItem[] }>(`/production/orders/${ulid}/stock-check`)
      return res.data.data
    },
    enabled: false, // only fetch on demand
  })
}

// ── PROD-002: Force Release (QC Override) ────────────────────────────────────

export function useForceRelease(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () =>
      api.patch(`/production/orders/${ulid}/release`, { force_release: true }).then(() => {
        qc.invalidateQueries({ queryKey: ['production-orders'] })
      }),
  })
}

// ── Smart Defaults ───────────────────────────────────────────────────────────

export function useProductionSmartDefaults(productItemId: number | null, targetStartDate?: string) {
  return useQuery({
    queryKey: ['production-smart-defaults', productItemId, targetStartDate],
    queryFn: async () => {
      const params: { product_item_id: number; target_start_date?: string } = {
        product_item_id: productItemId!,
      }
      if (targetStartDate) {
        params.target_start_date = targetStartDate
      }
      const res = await api.get<{ data: SmartDefaults }>('/production/orders/smart-defaults', { params })
      return res.data.data
    },
    enabled: productItemId !== null,
    staleTime: 10_000,
  })
}
