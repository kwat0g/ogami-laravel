import { useState, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { PayrollDetail } from '@/types/payroll'
import type { YtdSummary } from '@/types/reports'
import type { Employee } from '@/types/hr'
import type { ApiSuccess } from '@/types/api'

// ---------------------------------------------------------------------------
// Full payslip breakdown type
// ---------------------------------------------------------------------------

export interface PayslipBreakdown {
  payroll_run: {
    id: number
    reference_no: string
    pay_period_label: string
    cutoff_start: string
    cutoff_end: string
    pay_date: string
    run_type: string
    status: string
  }
  employee: {
    id: number
    employee_code: string
    first_name: string
    last_name: string
    middle_name: string | null
    employment_type: string
    department_name: string | null
    position_name: string | null
  }
  basic_monthly_rate_centavos: number
  daily_rate_centavos: number
  hourly_rate_centavos: number
  working_days_in_period: number
  pay_basis: string
  attendance: {
    days_worked: number
    days_absent: number
    days_late_minutes: number
    undertime_minutes: number
    leave_days_paid: number
    leave_days_unpaid: number
    regular_holiday_days: number
    special_holiday_days: number
  }
  overtime: {
    regular_minutes: number
    rest_day_minutes: number
    holiday_minutes: number
    night_diff_minutes: number
  }
  earnings: {
    basic_pay_centavos: number
    overtime_pay_centavos: number
    holiday_pay_centavos: number
    night_diff_pay_centavos: number
    gross_pay_centavos: number
  }
  deductions: {
    sss_ee_centavos: number
    sss_er_centavos: number
    philhealth_ee_centavos: number
    philhealth_er_centavos: number
    pagibig_ee_centavos: number
    pagibig_er_centavos: number
    withholding_tax_centavos: number
    loan_deductions_centavos: number
    loan_deduction_detail: Record<string, number> | null
    other_deductions_centavos: number
    total_deductions_centavos: number
  }
  summary: {
    gross_pay_centavos: number
    total_deductions_centavos: number
    net_pay_centavos: number
    is_below_min_wage: boolean
    has_deferred_deductions: boolean
  }
  ytd: {
    ytd_taxable_income_centavos: number
    ytd_tax_withheld_centavos: number
  }
}

// ---------------------------------------------------------------------------
// Paginated payslip list for the authenticated employee
// ---------------------------------------------------------------------------

interface PaginatedPayslips {
  data: PayrollDetail[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export function useMyPayslips(year: number, page = 1) {
  return useQuery<PaginatedPayslips>({
    queryKey: ['my-payslips', year, page],
    queryFn: async () => {
      const res = await api.get<PaginatedPayslips>('/employee/me/payslips', {
        params: { year: year > 0 ? year : undefined, page },
      })
      return res.data
    },
    staleTime: 30_000,
  })
}

// ---------------------------------------------------------------------------
// Full payslip breakdown
// ---------------------------------------------------------------------------

export function usePayslipDetail(detailId: number | null) {
  return useQuery<{ data: PayslipBreakdown }>({
    queryKey: ['payslip-detail', detailId],
    queryFn: async () => {
      const res = await api.get<{ data: PayslipBreakdown }>(`/employee/me/payslips/${detailId}`)
      return res.data
    },
    enabled: detailId !== null,
    staleTime: 5 * 60_000,
  })
}

// ---------------------------------------------------------------------------
// YTD summary
// ---------------------------------------------------------------------------

export function useMyYtdSummary(year: number) {
  return useQuery<YtdSummary>({
    queryKey: ['my-ytd', year],
    queryFn: async () => {
      const res = await api.get<YtdSummary>('/employee/me/ytd', {
        params: { year },
      })
      return res.data
    },
    staleTime: 60_000,
    enabled: year > 0,
  })
}

// ---------------------------------------------------------------------------
// Download own payslip PDF
// ---------------------------------------------------------------------------

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a   = document.createElement('a')
  a.href     = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

export function useDownloadMyPayslip(detailId: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get(
        `/employee/me/payslips/${detailId}/pdf`,
        { responseType: 'blob' },
      )
      triggerDownload(res.data as Blob, `payslip-${detailId}.pdf`)
    } finally {
      setIsLoading(false)
    }
  }, [detailId])

  return { download, isLoading }
}

// ---------------------------------------------------------------------------
// Own profile (read + limited update)
// ---------------------------------------------------------------------------

export function useMyProfile() {
  return useQuery<Employee>({
    queryKey: ['my-profile'],
    queryFn: async () => {
      const res = await api.get<ApiSuccess<Employee>>('/employee/me/profile')
      return res.data.data
    },
    staleTime: 5 * 60_000,
  })
}

export interface UpdateProfilePayload {
  personal_email?:  string | null
  personal_phone?:  string | null
  present_address?: string | null
  bank_name?:       string | null
  bank_account_no?: string | null
}

export function useUpdateMyProfile() {
  const qc = useQueryClient()
  return useMutation<Employee, Error, UpdateProfilePayload>({
    mutationFn: async (payload) => {
      const res = await api.patch<ApiSuccess<Employee>>('/employee/me/profile', payload)
      return res.data.data
    },
    onSuccess: (updated) => {
      qc.setQueryData<Employee>(['my-profile'], updated)
    },
  })
}
