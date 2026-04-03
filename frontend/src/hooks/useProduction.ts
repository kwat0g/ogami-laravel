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

export function useCreateReplenishmentOrder() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: {
      product_item_id: number
      target_stock_level: number
      min_batch_size?: number
      bom_id?: number
      target_start_date?: string
      target_end_date?: string
      notes?: string
    }) => api.post<{ data: ProductionOrder }>('/production/orders/replenishment', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['production-orders'] }),
  })
}

// ── Production Order Workflow ─────────────────────────────────────────────────

/**
 * M11 FIX: Added order detail invalidation to orderAction so the detail
 * page reflects the new status immediately after a state transition.
 * Previously only the list was invalidated, leaving the detail page stale.
 */
function orderAction(ulid: string, action: string, qc: ReturnType<typeof useQueryClient>) {
  return () =>
    api.patch(`/production/orders/${ulid}/${action}`).then(() => {
      void qc.invalidateQueries({ queryKey: ['production-orders'] })
      void qc.invalidateQueries({ queryKey: ['production-order', ulid] })
    })
}

export function useReleaseOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'release', qc) })
}

export function useApproveReleaseOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload?: { notes?: string }) =>
      api.patch(`/production/orders/${ulid}/approve-release`, payload ?? {}).then(() => {
        qc.invalidateQueries({ queryKey: ['production-orders'] })
        qc.invalidateQueries({ queryKey: ['production-order', ulid] })
      }),
  })
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

export function useCloseOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'close', qc) })
}

export function useHoldOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload?: { hold_reason?: string }) =>
      api.patch(`/production/orders/${ulid}/hold`, payload ?? {}).then(() => {
        qc.invalidateQueries({ queryKey: ['production-orders'] })
      }),
  })
}

export function useResumeOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: orderAction(ulid, 'resume', qc) })
}

export function useUpdateProductionOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { notes?: string; target_start_date?: string; target_end_date?: string }) =>
      api.put(`/production/orders/${ulid}`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['production-orders'] })
      qc.invalidateQueries({ queryKey: ['production-orders', ulid] })
    },
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

// ── Work Centers ─────────────────────────────────────────────────────────────

export interface WorkCenter {
  id: number
  ulid: string
  name: string
  code: string
  description?: string
  hourly_labor_rate?: number
  hourly_overhead_rate?: number
  capacity_hours_per_day?: number
  is_active: boolean
}

export function useWorkCenters() {
  return useQuery({
    queryKey: ['work-centers'],
    queryFn: async () => {
      const res = await api.get<{ data: WorkCenter[] }>('/production/work-centers')
      return res.data.data
    },
    staleTime: 30_000,
  })
}

export function useCreateWorkCenter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<WorkCenter>) =>
      api.post('/production/work-centers', payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['work-centers'] }) },
  })
}

export function useUpdateWorkCenter(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<WorkCenter>) =>
      api.put(`/production/work-centers/${id}`, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['work-centers'] }) },
  })
}

export function useDeleteWorkCenter() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/production/work-centers/${id}`),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['work-centers'] }) },
  })
}

// ── Routings ─────────────────────────────────────────────────────────────────

export interface Routing {
  id: number
  bom_id: number
  work_center_id: number
  step_number: number
  operation_name: string
  setup_time_minutes?: number
  run_time_minutes?: number
  description?: string
  work_center?: WorkCenter
}

export function useRoutings(bomId?: number) {
  return useQuery({
    queryKey: ['routings', bomId],
    queryFn: async () => {
      const url = bomId ? `/production/routings/bom/${bomId}` : '/production/routings'
      const res = await api.get<{ data: Routing[] }>(url)
      return res.data.data
    },
    staleTime: 30_000,
  })
}

export function useCreateRouting() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<Routing>) =>
      api.post('/production/routings', payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['routings'] }) },
  })
}

export function useUpdateRouting(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<Routing>) =>
      api.put(`/production/routings/${id}`, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['routings'] }) },
  })
}

export function useDeleteRouting() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/production/routings/${id}`),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['routings'] }) },
  })
}

// ── MRP (Material Requirements Planning) ─────────────────────────────────────

export interface MrpSummaryItem {
  item_id: number
  item_code: string
  item_name: string
  gross_requirement: number
  on_hand: number
  on_order: number
  net_requirement: number
  suggested_action: string
  lead_time_days?: number
}

export function useMrpSummary() {
  return useQuery({
    queryKey: ['mrp-summary'],
    queryFn: async () => {
      const res = await api.get<{ data: MrpSummaryItem[] }>('/production/mrp/summary')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useMrpExplode(productItemId: number | null, qty: number) {
  return useQuery({
    queryKey: ['mrp-explode', productItemId, qty],
    queryFn: async () => {
      const res = await api.get<{ data: MrpSummaryItem[] }>('/production/mrp/explode', {
        params: { product_item_id: productItemId, qty },
      })
      return res.data.data
    },
    enabled: productItemId !== null && qty > 0,
    staleTime: 30_000,
  })
}

export function useMrpTimePhased() {
  return useQuery({
    queryKey: ['mrp-time-phased'],
    queryFn: async () => {
      const res = await api.get<{ data: unknown[] }>('/production/mrp/time-phased')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

// ── Production Reports ───────────────────────────────────────────────────────

export function useProductionCostAnalysis(filters?: { date_from?: string; date_to?: string }) {
  return useQuery({
    queryKey: ['production-cost-analysis', filters],
    queryFn: async () => {
      const res = await api.get('/production/reports/cost-analysis', { params: filters })
      return res.data
    },
    staleTime: 60_000,
  })
}

// ── Where-Used Report ────────────────────────────────────────────────────────

export function useWhereUsed(itemId: number | null) {
  return useQuery({
    queryKey: ['bom-where-used', itemId],
    queryFn: async () => {
      const res = await api.get<{ data: unknown[] }>(`/production/bom/where-used/${itemId}`)
      return res.data.data
    },
    enabled: itemId !== null,
    staleTime: 60_000,
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
