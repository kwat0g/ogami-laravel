import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  Vendor,
  VendorInvoice,
  VendorInvoiceFilters,
  CreateVendorPayload,
  CreateVendorInvoicePayload,
  RecordPaymentPayload,
} from '@/types/ap'

// ---------------------------------------------------------------------------
// Paginated list helper
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
// Vendors
// ===========================================================================

export function useVendors(params: {
  is_active?: boolean
  is_ewt_subject?: boolean
  search?: string
  per_page?: number
  page?: number
} = {}) {
  return useQuery({
    queryKey: ['vendors', params],
    queryFn: async () => {
      const res = await api.get<Paginated<Vendor>>('/accounting/vendors', { params })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useVendor(id: number | null) {
  return useQuery({
    queryKey: ['vendors', id],
    queryFn: async () => {
      const res = await api.get<{ data: Vendor }>(`/accounting/vendors/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useCreateVendor() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateVendorPayload) => {
      const res = await api.post<{ data: Vendor }>('/accounting/vendors', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendors'] })
    },
  })
}

export function useUpdateVendor(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<CreateVendorPayload>) => {
      const res = await api.put<{ data: Vendor }>(`/accounting/vendors/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendors'] })
    },
  })
}

export function useArchiveVendor(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.delete<{ message: string }>(`/accounting/vendors/${id}`)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendors'] })
    },
  })
}

export function useAccreditVendor(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (notes?: string) => {
      const res = await api.patch(`/accounting/vendors/${id}/accredit`, { notes })
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendors'] })
    },
  })
}

export function useSuspendVendor(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (reason: string) => {
      const res = await api.patch(`/accounting/vendors/${id}/suspend`, { reason })
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vendors'] })
    },
  })
}

// ===========================================================================
// AP Invoices
// ===========================================================================

export function useAPInvoices(filters: VendorInvoiceFilters = {}) {
  return useQuery({
    queryKey: ['ap-invoices', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<VendorInvoice>>('/accounting/ap/invoices', {
        params: filters,
      })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useAPInvoice(id: string | null) {
  return useQuery({
    queryKey: ['ap-invoices', id],
    queryFn: async () => {
      const res = await api.get<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

export function useAPInvoicesDueSoon(days = 7) {
  return useQuery({
    queryKey: ['ap-invoices-due-soon', days],
    queryFn: async () => {
      const res = await api.get<{ data: VendorInvoice[] }>('/accounting/ap/invoices/due-soon', {
        params: { days },
      })
      return res.data.data
    },
    staleTime: 60_000,
    refetchInterval: 60_000,        // auto-refresh every 60s for the monitor page
    refetchIntervalInBackground: false,
  })
}

export function useCreateAPInvoice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateVendorInvoicePayload) => {
      const res = await api.post<{ data: VendorInvoice }>('/accounting/ap/invoices', payload)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useSubmitAPInvoice(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}/submit`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useApproveAPInvoice(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}/approve`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useRejectAPInvoice(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (rejection_note: string) => {
      const res = await api.patch<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}/reject`, {
        rejection_note,
      })
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useHeadNoteAPInvoice(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}/head-note`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useManagerCheckAPInvoice(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}/manager-check`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useOfficerReviewAPInvoice(id: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<{ data: VendorInvoice }>(`/accounting/ap/invoices/${id}/officer-review`)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}

export function useRecordPayment(invoiceId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: RecordPaymentPayload) => {
      const res = await api.post(`/accounting/ap/invoices/${invoiceId}/payments`, payload)
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ap-invoices'] })
    },
  })
}
