import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export interface VatReturnData {
  form: string; period: string; output_vat_centavos: number; input_vat_centavos: number
  vat_payable_centavos: number; excess_input_vat_centavos: number
  taxable_sales_centavos: number; zero_rated_sales_centavos: number; exempt_sales_centavos: number
}

export interface WithholdingTaxData {
  form: string; period: string; total_compensation_centavos: number
  tax_withheld_centavos: number; employee_count: number
}

export interface Form2307Data {
  form: string; quarter: number; year: number; vendor_name: string; tin: string | null
  total_payments_centavos: number; total_ewt_centavos: number
  payment_details: Array<{ date: string; invoice_number: string; amount_centavos: number; ewt_centavos: number }>
}

export function useVatReturn(month: number, year: number) {
  return useQuery({
    queryKey: ['bir-vat-return', month, year],
    queryFn: async () => { const { data } = await api.get('/tax/bir-forms/vat-return', { params: { month, year } }); return data.data as VatReturnData },
    enabled: !!month && !!year,
  })
}

export function useWithholdingTax(month: number, year: number) {
  return useQuery({
    queryKey: ['bir-withholding', month, year],
    queryFn: async () => { const { data } = await api.get('/tax/bir-forms/withholding-tax', { params: { month, year } }); return data.data as WithholdingTaxData },
    enabled: !!month && !!year,
  })
}

export function useForm2307(vendorId: number, quarter: number, year: number) {
  return useQuery({
    queryKey: ['bir-2307', vendorId, quarter, year],
    queryFn: async () => { const { data } = await api.get('/tax/bir-forms/form-2307', { params: { vendor_id: vendorId, quarter, year } }); return data.data as Form2307Data },
    enabled: !!vendorId && !!quarter && !!year,
  })
}
