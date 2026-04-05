import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import api from '@/lib/api'
import { deliveryApiPaths } from '@/lib/deliveryApiPaths'

export interface DisputeItem {
  id: number
  item_master_id: number
  expected_qty: number
  received_qty: number
  condition: 'good' | 'damaged' | 'missing' | 'wrong_item'
  notes: string | null
  resolution_action: 'replace' | 'credit' | 'accept' | null
  resolution_qty: number | null
  item_master: {
    id: number
    name: string
    sku: string
  } | null
}

export interface Dispute {
  id: number
  ulid: string
  dispute_reference: string
  delivery_schedule_id: number | null
  client_order_id: number | null
  customer_id: number
  delivery_receipt_id: number | null
  reported_by_id: number
  assigned_to_id: number | null
  status: 'open' | 'investigating' | 'pending_resolution' | 'resolved' | 'closed'
  resolution_type: 'replace_items' | 'credit_note' | 'partial_accept' | 'full_replacement' | null
  resolution_notes: string | null
  client_notes: string | null
  resolved_by_id: number | null
  resolved_at: string | null
  replacement_schedule_id: number | null
  credit_note_id: number | null
  ticket_id: number | null
  created_at: string
  updated_at: string
  items: DisputeItem[]
  customer: { id: number; name: string } | null
  client_order: { id: number; ulid: string; order_reference: string; status: string } | null
  reported_by: { id: number; name: string } | null
  assigned_to: { id: number; name: string } | null
  resolved_by: { id: number; name: string } | null
  credit_note: { id: number; cn_reference: string; amount_centavos: number; status: string } | null
  replacement_schedule: { id: number; ulid: string; ds_reference?: string; cds_reference?: string; status: string } | null
}

export function useDisputes(params?: Record<string, string | number>) {
  return useQuery<{ data: Dispute[]; meta: { current_page: number; last_page: number; total: number } }>({
    queryKey: ['delivery-disputes', params],
    queryFn: () => api.get(deliveryApiPaths.disputes, { params }).then(r => r.data),
    placeholderData: keepPreviousData,
  })
}

export function useDispute(ulid: string | null) {
  return useQuery<{ data: Dispute }>({
    queryKey: ['delivery-dispute', ulid],
    queryFn: () => api.get(deliveryApiPaths.disputeByUlid(ulid!)).then(r => r.data),
    enabled: !!ulid,
  })
}

export function useAssignDispute(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (assignedToId: number) =>
      api.patch(deliveryApiPaths.disputeAssign(ulid), { assigned_to_id: assignedToId }).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-dispute', ulid] })
      qc.invalidateQueries({ queryKey: ['delivery-disputes'] })
    },
  })
}

export interface ResolutionPayload {
  resolution_type: 'replace_items' | 'credit_note' | 'partial_accept' | 'full_replacement'
  resolution_notes?: string
  resolutions: Array<{ item_id: number; action: 'replace' | 'credit' | 'accept'; qty: number }>
}

export function useResolveDispute(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: ResolutionPayload) =>
      api.post(deliveryApiPaths.disputeResolve(ulid), payload).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-dispute', ulid] })
      qc.invalidateQueries({ queryKey: ['delivery-disputes'] })
    },
  })
}

export function useCloseDispute(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.patch(deliveryApiPaths.disputeClose(ulid)).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-dispute', ulid] })
      qc.invalidateQueries({ queryKey: ['delivery-disputes'] })
    },
  })
}

export function useDisputeCheck(clientOrderId: number | null) {
  return useQuery<{ has_open_disputes: boolean; disputes: Array<{ id: number; ulid: string; dispute_reference: string; status: string; created_at: string }> }>({
    queryKey: ['dispute-check', clientOrderId],
    queryFn: () => api.get(deliveryApiPaths.disputeCheck(clientOrderId!)).then(r => r.data),
    enabled: !!clientOrderId,
  })
}
