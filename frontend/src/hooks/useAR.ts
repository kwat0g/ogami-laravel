import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  Customer,
  CustomerInvoice,
  CustomerInvoiceFilters,
  CreateCustomerPayload,
  CreateCustomerInvoicePayload,
  ReceivePaymentPayload,
  WriteOffPayload,
} from '@/types/ar'

// ---------------------------------------------------------------------------
// Paginated list helper (matches backend PaginatedResource)
// ---------------------------------------------------------------------------

interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// ===========================================================================
// Customers
// ===========================================================================

export function useCustomers(params: {
  search?: string
  is_active?: boolean
  per_page?: number
} = {}) {
  return useQuery({
    queryKey: ['customers', params],
    queryFn: async () => {
      const res = await api.get<Paginated<Customer>>('/ar/customers', { params })
      return res.data
    },
    staleTime: 30_000,
    placeholderData: keepPreviousData,
    refetchOnWindowFocus: true,
  })
}

export function useCustomer(id: number | null) {
  return useQuery({
    queryKey: ['customers', id],
    queryFn: async () => {
      const res = await api.get<{ data: Customer }>(`/ar/customers/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useCreateCustomer() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateCustomerPayload) => {
      const res = await api.post<{ data: Customer }>('/ar/customers', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customers'] })
    },
  })
}

export function useUpdateCustomer(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<CreateCustomerPayload>) => {
      const res = await api.put<{ data: Customer }>(`/ar/customers/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customers'] })
      qc.invalidateQueries({ queryKey: ['customers', id] })
    },
  })
}

export function useArchiveCustomer() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/ar/customers/${id}`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customers'] })
    },
  })
}

// ===========================================================================
// Customer Invoices
// ===========================================================================

export function useCustomerInvoices(filters: CustomerInvoiceFilters = {}) {
  return useQuery({
    queryKey: ['customer-invoices', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<CustomerInvoice>>('/ar/invoices', { params: filters })
      return res.data
    },
    staleTime: 15_000,
    refetchOnWindowFocus: true,
  })
}

export function useCustomerInvoice(id: string | null) {
  return useQuery({
    queryKey: ['customer-invoices', id],
    queryFn: async () => {
      const res = await api.get<{ data: CustomerInvoice }>(`/ar/invoices/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 15_000,
  })
}

export function useCreateCustomerInvoice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateCustomerInvoicePayload) => {
      const res = await api.post<{ data: CustomerInvoice }>('/ar/invoices', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customer-invoices'] })
    },
  })
}

export function useApproveCustomerInvoice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await api.patch<{ data: CustomerInvoice }>(`/ar/invoices/${id}/approve`)
      return res.data.data
    },
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: ['customer-invoices'] })
      qc.invalidateQueries({ queryKey: ['customer-invoices', id] })
    },
  })
}

export function useCancelCustomerInvoice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      await api.patch(`/ar/invoices/${id}/cancel`)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customer-invoices'] })
    },
  })
}

export function useReceivePayment(invoiceId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ReceivePaymentPayload) => {
      const res = await api.post(`/ar/invoices/${invoiceId}/payments`, payload)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customer-invoices'] })
      qc.invalidateQueries({ queryKey: ['customers'] })
    },
  })
}

export function useWriteOffInvoice(invoiceId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: WriteOffPayload) => {
      const res = await api.patch<{ data: CustomerInvoice }>(`/ar/invoices/${invoiceId}/write-off`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customer-invoices'] })
    },
  })
}

export function useDueSoonInvoices(days = 7) {
  return useQuery({
    queryKey: ['customer-invoices', 'due-soon', days],
    queryFn: async () => {
      const res = await api.get<Paginated<CustomerInvoice>>('/ar/invoices/due-soon', { params: { days } })
      return res.data
    },
    staleTime: 60_000,
  })
}

// ===========================================================================
// AR Aging Report
// ===========================================================================

export interface ArAgingRow {
  customer_id: number
  customer_name: string
  current: number
  '1_30': number
  '31_60': number
  '61_90': number
  over_90: number
  total: number
}

export function useArAgingReport(asOfDate?: string) {
  return useQuery({
    queryKey: ['ar-aging-report', asOfDate],
    queryFn: async () => {
      const res = await api.get<{ data: ArAgingRow[]; as_of_date: string }>('/ar/aging-report', {
        params: asOfDate ? { as_of_date: asOfDate } : {},
      })
      return res.data
    },
    staleTime: 60_000,
  })
}
