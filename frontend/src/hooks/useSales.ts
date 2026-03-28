import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import api from '@/lib/api'
import type { Quotation, SalesOrder, PriceResolveResult } from '@/types/sales'

// ── Quotations ───────────────────────────────────────────────────────────────

export function useQuotations(filters: Record<string, unknown> = {}) {
  return useQuery({
    queryKey: ['sales-quotations', filters],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const { data } = await api.get<{
        data: Quotation[]
        current_page: number
        last_page: number
        per_page: number
        total: number
      }>('/sales/quotations', { params: filters })
      return {
        data: data.data,
        meta: { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total },
      }
    },
  })
}

export function useQuotation(ulid: string) {
  return useQuery({
    queryKey: ['sales-quotation', ulid],
    queryFn: async () => {
      const { data } = await api.get<{ data: Quotation }>(`/sales/quotations/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateQuotation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: Quotation }>('/sales/quotations', payload)
      return data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['sales-quotations'] }) },
  })
}

export function useSendQuotation(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.patch<{ data: Quotation }>(`/sales/quotations/${ulid}/send`)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sales-quotation', ulid] })
      qc.invalidateQueries({ queryKey: ['sales-quotations'] })
    },
  })
}

export function useAcceptQuotation(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.patch<{ data: Quotation }>(`/sales/quotations/${ulid}/accept`)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sales-quotation', ulid] })
      qc.invalidateQueries({ queryKey: ['sales-quotations'] })
    },
  })
}

export function useConvertQuotationToOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.post<{ data: SalesOrder }>(`/sales/quotations/${ulid}/convert-to-order`)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sales-quotations'] })
      qc.invalidateQueries({ queryKey: ['sales-orders'] })
    },
  })
}

// ── Sales Orders ─────────────────────────────────────────────────────────────

export function useSalesOrders(filters: Record<string, unknown> = {}) {
  return useQuery({
    queryKey: ['sales-orders', filters],
    queryFn: async () => {
      const { data } = await api.get<{
        data: SalesOrder[]
        current_page: number
        last_page: number
        per_page: number
        total: number
      }>('/sales/orders', { params: filters })
      return {
        data: data.data,
        meta: { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total },
      }
    },
  })
}

export function useSalesOrder(ulid: string) {
  return useQuery({
    queryKey: ['sales-order', ulid],
    queryFn: async () => {
      const { data } = await api.get<{ data: SalesOrder }>(`/sales/orders/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateSalesOrder() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: SalesOrder }>('/sales/orders', payload)
      return data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['sales-orders'] }) },
  })
}

export function useConfirmSalesOrder(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.patch<{ data: SalesOrder }>(`/sales/orders/${ulid}/confirm`)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sales-order', ulid] })
      qc.invalidateQueries({ queryKey: ['sales-orders'] })
    },
  })
}

// ── Pricing ──────────────────────────────────────────────────────────────────

export function useResolvePrice(itemId: number, quantity?: number, customerId?: number) {
  return useQuery({
    queryKey: ['sales-price', itemId, quantity, customerId],
    queryFn: async () => {
      const { data } = await api.get<{ data: PriceResolveResult }>('/sales/pricing/resolve', {
        params: { item_id: itemId, quantity, customer_id: customerId },
      })
      return data.data
    },
    enabled: !!itemId,
  })
}
