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

export function useVendors(
  params: {
    is_active?: boolean
    is_ewt_subject?: boolean
    accreditation_status?: string
    search?: string
    per_page?: number
    page?: number
  } = {},
) {
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

/** Fetch a vendor's item catalog for PR creation dropdown. */
export function useVendorItems(vendorId: number | null) {
  return useQuery({
    queryKey: ['vendor-items', vendorId],
    queryFn: async () => {
      const res = await api.get<{
        data: Array<{
          id: number
          item_code: string
          item_name: string
          unit_of_measure: string
          unit_price: number
          is_active: boolean
        }>
      }>(`/accounting/vendors/${vendorId}/items`, { params: { is_active: true, per_page: 500 } })
      return res.data.data
    },
    enabled: vendorId !== null && vendorId > 0,
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
    refetchInterval: 60_000, // auto-refresh every 60s for the monitor page
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
      const res = await api.patch<{ data: VendorInvoice }>(
        `/accounting/ap/invoices/${id}/head-note`,
      )
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
      const res = await api.patch<{ data: VendorInvoice }>(
        `/accounting/ap/invoices/${id}/manager-check`,
      )
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
      const res = await api.patch<{ data: VendorInvoice }>(
        `/accounting/ap/invoices/${id}/officer-review`,
      )
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

// ===========================================================================
// AP Aging Report
// ===========================================================================

export interface ApAgingRow {
  vendor_id: number
  vendor_name: string
  current: number
  '1_30': number
  '31_60': number
  '61_90': number
  over_90: number
  total: number
}

export function useApAgingReport(asOfDate?: string) {
  return useQuery({
    queryKey: ['ap-aging-report', asOfDate],
    queryFn: async () => {
      const res = await api.get<{ data: ApAgingRow[]; as_of_date: string }>(
        '/accounting/ap/aging-report',
        {
          params: asOfDate ? { as_of_date: asOfDate } : {},
        },
      )
      return res.data
    },
    staleTime: 60_000,
  })
}

// ===========================================================================
// Check Voucher
// ===========================================================================

export interface CheckVoucherData {
  voucher_number: string
  payment_date: string
  payment_method: string
  reference_number: string
  check_number: string | null
  amount: number
  vendor_name: string
  vendor_address: string
  vendor_tin: string
  invoice_number: string
  invoice_date: string | null
  description: string
  prepared_by: string
}

export function useCheckVoucher(paymentId: number | null) {
  return useQuery({
    queryKey: ['check-voucher', paymentId],
    queryFn: async () => {
      const res = await api.get<{ data: CheckVoucherData }>(
        `/accounting/ap/check-voucher/${paymentId}`,
      )
      return res.data.data
    },
    enabled: paymentId !== null,
  })
}
