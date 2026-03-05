import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

// ═══════════════════════════════════════════════════════════════════════════════
// Types
// ═══════════════════════════════════════════════════════════════════════════════

export interface TaxBracket {
  id: number
  effective_date: string
  income_from: number
  income_to: number | null
  base_tax: number
  excess_rate: number
  notes: string | null
  created_at: string
  updated_at: string
}

export interface SssContribution {
  id: number
  effective_date: string
  salary_range_from: number
  salary_range_to: number | null
  monthly_salary_credit: number
  employee_contribution: number
  employer_contribution: number
  ec_contribution: number
  created_at: string
  updated_at: string
}

export interface PhilhealthContribution {
  id: number
  effective_date: string
  salary_floor: number | null
  salary_ceiling: number | null
  premium_rate: number
  min_monthly_premium: number
  max_monthly_premium: number
  legal_basis: string | null
  created_at: string
  updated_at: string
}

export interface PagibigContribution {
  id: number
  effective_date: string
  salary_threshold: number
  employee_rate_below: number
  employee_rate_above: number
  employee_cap_monthly: number
  employer_rate: number
  legal_basis: string | null
  created_at: string
  updated_at: string
}

export interface MinimumWageRate {
  id: number
  effective_date: string
  region: string
  daily_rate: number
  wage_order_reference: string | null
  created_at: string
  updated_at: string
}

export interface Holiday {
  id: number
  holiday_date: string
  year: number
  name: string
  type: 'REGULAR' | 'SPECIAL_NON_WORKING' | 'SPECIAL_WORKING'
  is_nationwide: boolean
  region: string | null
  proclamation_reference: string | null
  created_at: string
  updated_at: string
}

export interface SalaryGrade {
  id: number
  code: string
  name: string
  level: number
  min_monthly_rate: number
  max_monthly_rate: number
  employment_type: 'regular' | 'contractual' | 'project_based' | 'casual'
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface LoanType {
  id: number
  code: string
  name: string
  category: 'government' | 'company'
  description: string | null
  interest_rate_annual: number
  max_term_months: number
  max_amount_centavos: number | null
  min_amount_centavos: number
  subject_to_min_wage_protection: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface ChartOfAccount {
  id: number
  code: string
  name: string
  account_type: 'ASSET' | 'LIABILITY' | 'EQUITY' | 'REVENUE' | 'COGS' | 'OPEX' | 'TAX'
  parent_id: number | null
  parent?: ChartOfAccount
  children?: ChartOfAccount[]
  normal_balance: 'DEBIT' | 'CREDIT'
  is_active: boolean
  is_system: boolean
  description: string | null
  created_at: string
  updated_at: string
}

// ═══════════════════════════════════════════════════════════════════════════════
// Tax Brackets
// ═══════════════════════════════════════════════════════════════════════════════

export function useTaxBrackets(params?: { effective_date?: string }) {
  return useQuery({
    queryKey: ['tax-brackets', params],
    queryFn: async () => {
      const res = await api.get<{
        data: TaxBracket[]
        grouped: Record<string, TaxBracket[]>
        versions: string[]
      }>('/admin/tax-brackets', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useActiveTaxBrackets() {
  return useQuery({
    queryKey: ['tax-brackets', 'active'],
    queryFn: async () => {
      const res = await api.get<{
        effective_as_of: string
        data: TaxBracket[]
      }>('/admin/tax-brackets/active')
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateTaxBracket() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<TaxBracket, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: TaxBracket; message: string }>('/admin/tax-brackets', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tax-brackets'] })
    },
  })
}

export function useUpdateTaxBracket() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<TaxBracket> }) => {
      const res = await api.put<{ data: TaxBracket; message: string }>(`/admin/tax-brackets/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tax-brackets'] })
    },
  })
}

export function useDeleteTaxBracket() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/tax-brackets/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tax-brackets'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// SSS Contribution Table
// ═══════════════════════════════════════════════════════════════════════════════

export function useSssContributions(params?: { effective_date?: string }) {
  return useQuery({
    queryKey: ['sss-contributions', params],
    queryFn: async () => {
      const res = await api.get<{
        data: SssContribution[]
        grouped: Record<string, SssContribution[]>
        versions: string[]
      }>('/admin/sss-contributions', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useActiveSssContributions() {
  return useQuery({
    queryKey: ['sss-contributions', 'active'],
    queryFn: async () => {
      const res = await api.get<{
        effective_as_of: string
        total_brackets: number
        data: SssContribution[]
      }>('/admin/sss-contributions/active')
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateSssContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<SssContribution, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: SssContribution; message: string }>('/admin/sss-contributions', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sss-contributions'] })
    },
  })
}

export function useUpdateSssContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<SssContribution> }) => {
      const res = await api.put<{ data: SssContribution; message: string }>(`/admin/sss-contributions/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sss-contributions'] })
    },
  })
}

export function useDeleteSssContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/sss-contributions/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sss-contributions'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// PhilHealth Contribution Table
// ═══════════════════════════════════════════════════════════════════════════════

export function usePhilhealthContributions(params?: { effective_date?: string }) {
  return useQuery({
    queryKey: ['philhealth-contributions', params],
    queryFn: async () => {
      const res = await api.get<{
        data: PhilhealthContribution[]
        versions: string[]
      }>('/admin/philhealth-contributions', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useActivePhilhealthContribution() {
  return useQuery({
    queryKey: ['philhealth-contributions', 'active'],
    queryFn: async () => {
      const res = await api.get<{
        effective_as_of: string
        data: PhilhealthContribution
      }>('/admin/philhealth-contributions/active')
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreatePhilhealthContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<PhilhealthContribution, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: PhilhealthContribution; message: string }>('/admin/philhealth-contributions', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['philhealth-contributions'] })
    },
  })
}

export function useUpdatePhilhealthContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<PhilhealthContribution> }) => {
      const res = await api.put<{ data: PhilhealthContribution; message: string }>(`/admin/philhealth-contributions/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['philhealth-contributions'] })
    },
  })
}

export function useDeletePhilhealthContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/philhealth-contributions/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['philhealth-contributions'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// Pag-IBIG Contribution Table
// ═══════════════════════════════════════════════════════════════════════════════

export function usePagibigContributions(params?: { effective_date?: string }) {
  return useQuery({
    queryKey: ['pagibig-contributions', params],
    queryFn: async () => {
      const res = await api.get<{
        data: PagibigContribution[]
        versions: string[]
      }>('/admin/pagibig-contributions', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useActivePagibigContribution() {
  return useQuery({
    queryKey: ['pagibig-contributions', 'active'],
    queryFn: async () => {
      const res = await api.get<{
        effective_as_of: string
        data: PagibigContribution
      }>('/admin/pagibig-contributions/active')
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreatePagibigContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<PagibigContribution, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: PagibigContribution; message: string }>('/admin/pagibig-contributions', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pagibig-contributions'] })
    },
  })
}

export function useUpdatePagibigContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<PagibigContribution> }) => {
      const res = await api.put<{ data: PagibigContribution; message: string }>(`/admin/pagibig-contributions/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pagibig-contributions'] })
    },
  })
}

export function useDeletePagibigContribution() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/pagibig-contributions/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['pagibig-contributions'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// Minimum Wage Rates
// ═══════════════════════════════════════════════════════════════════════════════

export function useMinimumWageRates(params?: { region?: string; effective_date?: string }) {
  return useQuery({
    queryKey: ['minimum-wage-rates', params],
    queryFn: async () => {
      const res = await api.get<{
        data: MinimumWageRate[]
        by_region: Record<string, MinimumWageRate[]>
        regions: string[]
      }>('/admin/minimum-wages', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCurrentMinimumWagesByRegion() {
  return useQuery({
    queryKey: ['minimum-wage-rates', 'current-by-region'],
    queryFn: async () => {
      const res = await api.get<{
        effective_as_of: string
        data: Record<string, MinimumWageRate>
      }>('/admin/minimum-wages/current-by-region')
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateMinimumWageRate() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<MinimumWageRate, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: MinimumWageRate; message: string }>('/admin/minimum-wages', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['minimum-wage-rates'] })
    },
  })
}

export function useUpdateMinimumWageRate() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<MinimumWageRate> }) => {
      const res = await api.put<{ data: MinimumWageRate; message: string }>(`/admin/minimum-wages/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['minimum-wage-rates'] })
    },
  })
}

export function useDeleteMinimumWageRate() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/minimum-wages/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['minimum-wage-rates'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// Holiday Calendar
// ═══════════════════════════════════════════════════════════════════════════════

export function useHolidays(params?: { year?: number; type?: string; region?: string }) {
  return useQuery({
    queryKey: ['holidays', params],
    queryFn: async () => {
      const res = await api.get<{
        data: Holiday[]
        by_year: Record<string, Holiday[]>
        years: number[]
        types: string[]
      }>('/admin/holidays', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useHolidaysByYear(year: number) {
  return useQuery({
    queryKey: ['holidays', 'year', year],
    queryFn: async () => {
      const res = await api.get<{
        year: number
        count: number
        data: Holiday[]
      }>(`/admin/holidays/by-year/${year}`)
      return res.data
    },
    enabled: !!year,
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateHoliday() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<Holiday, 'id' | 'created_at' | 'updated_at' | 'year'>) => {
      const res = await api.post<{ data: Holiday; message: string }>('/admin/holidays', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['holidays'] })
    },
  })
}

export function useCreateHolidaysBulk() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ year, holidays }: { year: number; holidays: Omit<Holiday, 'id' | 'created_at' | 'updated_at' | 'year'>[] }) => {
      const res = await api.post<{ data: Holiday[]; message: string }>('/admin/holidays/bulk', { year, holidays })
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['holidays'] })
    },
  })
}

export function useUpdateHoliday() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<Holiday> }) => {
      const res = await api.put<{ data: Holiday; message: string }>(`/admin/holidays/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['holidays'] })
    },
  })
}

export function useDeleteHoliday() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/holidays/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['holidays'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// Salary Grades
// ═══════════════════════════════════════════════════════════════════════════════

export function useSalaryGrades(params?: { employment_type?: string; is_active?: boolean }) {
  return useQuery({
    queryKey: ['salary-grades', params],
    queryFn: async () => {
      const res = await api.get<{
        data: SalaryGrade[]
        by_type: Record<string, SalaryGrade[]>
        types: string[]
      }>('/admin/salary-grades', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useSalaryGradesByType(type: string) {
  return useQuery({
    queryKey: ['salary-grades', 'type', type],
    queryFn: async () => {
      const res = await api.get<{
        employment_type: string
        data: SalaryGrade[]
      }>(`/admin/salary-grades/by-type/${type}`)
      return res.data
    },
    enabled: !!type,
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateSalaryGrade() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<SalaryGrade, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: SalaryGrade; message: string }>('/admin/salary-grades', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['salary-grades'] })
    },
  })
}

export function useUpdateSalaryGrade() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<SalaryGrade> }) => {
      const res = await api.put<{ data: SalaryGrade; message: string }>(`/admin/salary-grades/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['salary-grades'] })
    },
  })
}

export function useDeleteSalaryGrade() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/salary-grades/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['salary-grades'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// Loan Types
// ═══════════════════════════════════════════════════════════════════════════════

export function useLoanTypes(params?: { category?: string; is_active?: boolean }) {
  return useQuery({
    queryKey: ['loan-types', params],
    queryFn: async () => {
      const res = await api.get<{
        data: LoanType[]
        by_category: Record<string, LoanType[]>
        categories: string[]
      }>('/admin/loan-types', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useLoanTypesByCategory(category: string) {
  return useQuery({
    queryKey: ['loan-types', 'category', category],
    queryFn: async () => {
      const res = await api.get<{
        category: string
        data: LoanType[]
      }>(`/admin/loan-types/by-category/${category}`)
      return res.data
    },
    enabled: !!category,
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateLoanType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<LoanType, 'id' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<{ data: LoanType; message: string }>('/admin/loan-types', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['loan-types'] })
    },
  })
}

export function useUpdateLoanType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<LoanType> }) => {
      const res = await api.put<{ data: LoanType; message: string }>(`/admin/loan-types/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['loan-types'] })
    },
  })
}

export function useDeleteLoanType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/loan-types/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['loan-types'] })
    },
  })
}

// ═══════════════════════════════════════════════════════════════════════════════
// Chart of Accounts
// ═══════════════════════════════════════════════════════════════════════════════

export function useChartOfAccounts(params?: { 
  account_type?: string
  is_active?: boolean
  only_parents?: boolean
  only_leaves?: boolean
}) {
  return useQuery({
    queryKey: ['chart-of-accounts', params],
    queryFn: async () => {
      const res = await api.get<{
        data: ChartOfAccount[]
        tree: ChartOfAccount[]
        account_types: string[]
      }>('/admin/chart-of-accounts', { params })
      return res.data
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateChartOfAccount() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (data: Omit<ChartOfAccount, 'id' | 'created_at' | 'updated_at' | 'is_system'>) => {
      const res = await api.post<{ data: ChartOfAccount; message: string }>('/admin/chart-of-accounts', data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}

export function useUpdateChartOfAccount() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<ChartOfAccount> }) => {
      const res = await api.put<{ data: ChartOfAccount; message: string }>(`/admin/chart-of-accounts/${id}`, data)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}

export function useDeleteChartOfAccount() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.delete<{ message: string }>(`/admin/chart-of-accounts/${id}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}

export function useArchiveChartOfAccount() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      const res = await api.post<{ message: string }>(`/admin/chart-of-accounts/${id}/archive`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['chart-of-accounts'] })
    },
  })
}
