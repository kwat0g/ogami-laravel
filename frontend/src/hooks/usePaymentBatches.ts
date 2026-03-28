import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export interface PaymentBatch {
  id: number; ulid: string; batch_number: string; status: string
  payment_date: string; payment_method: string
  total_amount_centavos: number; payment_count: number
  notes: string | null; created_by?: { id: number; name: string }
  approved_by?: { id: number; name: string } | null; approved_at: string | null
  items?: PaymentBatchItem[]; created_at: string
}
export interface PaymentBatchItem {
  id: number; vendor_invoice_id: number; vendor_id: number
  vendor?: { id: number; name: string }; amount_centavos: number
  status: string; remarks: string | null
}

export function usePaymentBatches(filters: Record<string, unknown> = {}) {
  return useQuery({
    queryKey: ['payment-batches', filters],
    queryFn: async () => {
      const { data } = await api.get('/procurement/payment-batches', { params: filters })
      return { data: data.data, meta: { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total } }
    },
  })
}

export function useCreatePaymentBatch() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { payment_date: string; payment_method?: string; invoice_ids: number[]; notes?: string }) => {
      const { data } = await api.post('/procurement/payment-batches', payload)
      return data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['payment-batches'] }) },
  })
}

export function useSubmitPaymentBatch(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => { const { data } = await api.patch(`/procurement/payment-batches/${ulid}/submit`); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['payment-batches'] }) },
  })
}

export function useApprovePaymentBatch(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => { const { data } = await api.patch(`/procurement/payment-batches/${ulid}/approve`); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['payment-batches'] }) },
  })
}

export function useProcessPaymentBatch(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => { const { data } = await api.post(`/procurement/payment-batches/${ulid}/process`); return data.data },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['payment-batches'] }) },
  })
}
