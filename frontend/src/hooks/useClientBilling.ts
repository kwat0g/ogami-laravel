import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export interface ClientBillingInvoice {
  ulid: string
  invoice_number: string | null
  invoice_date: string
  due_date: string
  status: string
  total_amount: number
  total_paid: number
  balance_due: number
  is_overdue: boolean
  is_due_soon: boolean
}

export interface ClientBillingSummary {
  customer: {
    ulid: string
    name: string
    company_name: string | null
  }
  totals: {
    outstanding: number
    overdue: number
    due_soon: number
    open_invoices: number
    invoices_returned: number
  }
  invoices: ClientBillingInvoice[]
  as_of: string
}

export function useClientBillingSummary() {
  return useQuery({
    queryKey: ['client-billing-summary'],
    queryFn: async () => {
      const res = await api.get<{ data: ClientBillingSummary }>('/crm/client-billing/summary')
      return res.data.data
    },
  })
}
