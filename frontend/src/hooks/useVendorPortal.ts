import api from '@/lib/api'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'

// ── Types ─────────────────────────────────────────────────────────────────

export interface VendorFulfillmentNote {
  id: number
  ulid: string
  purchase_order_id: number
  vendor_user_id: number
  note_type: 'in_transit' | 'delivered' | 'partial'
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
  po_date: string
  delivery_date: string
  payment_terms: string
  total_po_amount: string
  notes: string | null
  items: VendorPortalOrderItem[]
  fulfillment_notes?: VendorFulfillmentNote[]
}

export interface VendorPortalOrderItem {
  id: number
  item_description: string
  unit_of_measure: string
  quantity_ordered: string
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

export function useMarkDelivered() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      ulid,
      items,
      notes,
    }: {
      ulid: string
      items: Array<{ po_item_id: number; qty_delivered: number }>
      notes?: string
    }) => api.post(`/vendor-portal/orders/${ulid}/deliver`, { items, notes }),
    onSuccess: (_data, { ulid }) => {
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders', ulid] })
      qc.invalidateQueries({ queryKey: ['vendor-portal', 'orders'] })
    },
  })
}

export function useVendorPortalItems(activeOnly = true) {
  return useQuery({
    queryKey: ['vendor-portal', 'items', { activeOnly }],
    queryFn: () =>
      api.get<{ data: VendorPortalItem[] }>('/vendor-portal/items', {
        params: { active_only: activeOnly ? 'true' : 'false' },
      }).then((r) => r.data.data),
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
