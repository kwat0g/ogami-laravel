import api from '@/lib/api'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'

// ── Types ─────────────────────────────────────────────────────────────────

export interface VendorFulfillmentNote {
  id: number
  ulid: string
  purchase_order_id: number
  vendor_user_id: number
  note_type: 'in_transit' | 'delivered' | 'partial' | 'acknowledged' | 'change_requested' | 'change_accepted' | 'change_rejected'
  notes: string | null
  items: Array<{ po_item_id: number; qty_delivered: number }> | null
  created_at: string
  vendor_user?: { id: number; name: string }
}

export interface VendorPortalOrder {
  id: number
  ulid: string
  po_reference: string
  vendor_id: number
  status: string
  po_type: 'original' | 'split'
  po_date: string
  delivery_date: string
  payment_terms: string
  total_po_amount: string
  notes: string | null
  vendor_remarks: string | null
  negotiation_round: number
  change_requested_at: string | null
  change_reviewed_at: string | null
  change_review_remarks: string | null
  vendor_acknowledged_at: string | null
  items: VendorPortalOrderItem[]
  fulfillment_notes?: VendorFulfillmentNote[]
  parent_po?: {
    ulid: string
    po_reference: string
  } | null
  child_pos?: Array<{
    ulid: string
    po_reference: string
    status: string
    total_po_amount: string
  }>
}

export interface VendorPortalOrderItem {
  id: number
  item_description: string
  unit_of_measure: string
  quantity_ordered: string
  negotiated_quantity: string | null
  vendor_item_notes: string | null
  agreed_unit_cost: string
  total_cost: string
  quantity_received: string
  quantity_pending: string
}

export interface VendorPortalItem {
  id: number
  ulid: string
  item_code: string
  item_name: string
  description: string | null
  unit_of_measure: string
  unit_price: number
  is_active: boolean
}

// ── Hooks ─────────────────────────────────────────────────────────────────

export function useVendorOrders(status?: string) {
  return useQuery({
    queryKey: ['vendor-portal', 'orders', { status }],
    queryFn: () =>
      api.get<{ data: VendorPortalOrder[]; meta: { total: number; current_page: number; last_page: number } }>(
        '/vendor-portal/orders',
        { params: status ? { status } : {} }
      ).then((r) => r.data),
  })
}

export function useVendorOrder(ulid: string) {
  return useQuery({
    queryKey: ['vendor-portal', 'orders', ulid],
    queryFn: () =>
      api.get<{ data: VendorPortalOrder }>(`/vendor-portal/orders/${ulid}`).then((r) => r.data.data),
    enabled: !!ulid,
  })
}

export function useAcknowledgePO() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ ulid, notes }: { ulid: string; notes?: string }) =>
      api.post(`/vendor-portal/orders/${ulid}/acknowledge`, { notes }),
    onSuccess: (_data, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders', ulid] })
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders'] })
    },
  })
}

export interface ProposeChangesItem {
  po_item_id: number
  negotiated_quantity: number
  vendor_item_notes?: string
}

export function useProposeChanges() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ ulid, remarks, items }: { ulid: string; remarks: string; items: ProposeChangesItem[] }) =>
      api.post(`/vendor-portal/orders/${ulid}/propose-changes`, { remarks, items }),
    onSuccess: (_data, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders', ulid] })
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders'] })
    },
  })
}

export function useMarkInTransit() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ ulid, notes }: { ulid: string; notes?: string }) =>
      api.post(`/vendor-portal/orders/${ulid}/in-transit`, { notes }),
    onSuccess: (_data, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders', ulid] })
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders'] })
    },
  })
}

export interface MarkDeliveredResponse {
  message: string
  data: {
    note: VendorFulfillmentNote
    split_po: {
      id: number
      ulid: string
      reference: string
      total_amount: string
    } | null
  }
}

export function useMarkDelivered() {
  const qc = useQueryClient()
  return useMutation<MarkDeliveredResponse, unknown, {
    ulid: string
    items: Array<{ po_item_id: number; qty_delivered: number }>
    notes?: string
    delivery_date?: string
  }>({
    mutationFn: async ({ ulid, items, notes, delivery_date }) => {
      const res = await api.post(`/vendor-portal/orders/${ulid}/deliver`, { items, notes, delivery_date })
      return res.data
    },
    onSuccess: (_data, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders', ulid] })
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders'] })
    },
  })
}

export function useVendorPortalItems(filters: { activeOnly?: boolean; search?: string } = {}) {
  const activeOnly = filters.activeOnly ?? true
  const search = filters.search?.trim()
  const searchParam = search ? search : undefined

  return useQuery({
    queryKey: ['vendor-portal', 'items', { activeOnly, search: searchParam }],
    queryFn: () =>
      api.get<{ data: VendorPortalItem[] }>('/vendor-portal/items', {
        params: {
          active_only: activeOnly ? 'true' : 'false',
          ...(searchParam ? { search: searchParam } : {}),
        },
      }).then((r) => r.data.data),
    placeholderData: (prev: VendorPortalItem[] | undefined) => prev,
  })
}

export function useCreateVendorPortalItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Omit<VendorPortalItem, 'id' | 'ulid'>) =>
      api.post<{ data: VendorPortalItem }>('/vendor-portal/items', data).then((r) => r.data.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'items'] })
    },
  })
}

export function useUpdateVendorPortalItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...data }: Partial<VendorPortalItem> & { id: number }) =>
      api.patch<{ data: VendorPortalItem }>(`/vendor-portal/items/${id}`, data).then((r) => r.data.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'items'] })
    },
  })
}

// ── Item Import ───────────────────────────────────────────────────────────

export function useImportVendorPortalItems() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (file: File) => {
      const formData = new FormData()
      formData.append('file', file)
      return api.post<{ message: string; data: { created: number; updated: number } }>(
        '/vendor-portal/items/import',
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      ).then((r) => r.data)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'items'] })
    },
  })
}

// ── Goods Receipts ────────────────────────────────────────────────────────

export interface VendorPortalGoodsReceipt {
  id: number
  gr_reference: string
  purchase_order_id: number
  status: string
  received_date: string
  three_way_match_passed: boolean
  ap_invoice_created: boolean
  created_at: string
  purchase_order?: { po_reference: string }
}

export function useVendorGoodsReceipts(status?: string) {
  return useQuery({
    queryKey: ['vendor-portal', 'goods-receipts', { status }],
    queryFn: () =>
      api.get<{ data: VendorPortalGoodsReceipt[]; meta: { total: number; current_page: number; last_page: number } }>(
        '/vendor-portal/goods-receipts',
        { params: status ? { status } : {} }
      ).then((r) => r.data),
  })
}

// ── Invoices ──────────────────────────────────────────────────────────────

export interface VendorPortalInvoice {
  id: number
  ulid: string
  vendor_id: number
  invoice_date: string
  due_date: string
  net_amount: string
  vat_amount: string
  ewt_amount: string
  status: string
  description: string | null
  or_number: string | null
  created_at: string
}

export function useVendorInvoices(status?: string) {
  return useQuery({
    queryKey: ['vendor-portal', 'invoices', { status }],
    queryFn: () =>
      api.get<{ data: VendorPortalInvoice[]; meta: { total: number; current_page: number; last_page: number } }>(
        '/vendor-portal/invoices',
        { params: status ? { status } : {} }
      ).then((r) => r.data),
  })
}

export function useCreateVendorInvoice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      goods_receipt_id: number
      invoice_date: string
      due_date: string
      net_amount: number
      vat_amount?: number
      or_number?: string
      description?: string
    }) => api.post<{ data: VendorPortalInvoice }>('/vendor-portal/invoices', data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'invoices'] })
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'goods-receipts'] })
    },
  })
}

