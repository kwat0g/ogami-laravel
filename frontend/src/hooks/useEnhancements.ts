import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

// ── Types ─────────────────────────────────────────────────────────────────────

interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

// Performance Appraisal
export interface PerformanceAppraisal {
  id: number
  ulid: string
  employee_id: number
  reviewer_id: number
  review_type: 'annual' | 'mid_year' | 'probationary' | 'project_based'
  review_period_start: string
  review_period_end: string
  status: 'draft' | 'submitted' | 'manager_reviewed' | 'hr_approved' | 'completed'
  overall_rating_pct: number | null
  employee_comments: string | null
  reviewer_comments: string | null
  hr_comments: string | null
  employee?: { id: number; first_name: string; last_name: string }
  reviewer?: { id: number; name: string }
  criteria?: AppraisalCriteria[]
}

export interface AppraisalCriteria {
  id: number
  criteria_name: string
  description: string | null
  weight_pct: number
  rating_pct: number | null
  comments: string | null
}

// Budget Amendment
export interface BudgetAmendment {
  id: number
  ulid: string
  cost_center_id: number
  fiscal_year: number
  amendment_type: 'reallocation' | 'increase' | 'decrease'
  source_account_id: number | null
  target_account_id: number
  amount_centavos: number
  justification: string
  status: 'draft' | 'submitted' | 'approved' | 'rejected'
  approval_remarks: string | null
  cost_center?: { id: number; code: string; name: string }
}

// Lead Score
export interface LeadScore {
  lead_id: number
  company_name: string
  contact_name: string
  score: number
  qualified: boolean
  status: string
  breakdown: Record<string, { points: number; max: number; detail: string }>
}

// Financial Ratio
export interface FinancialRatio {
  value: number
  formula: string
  status: string
  days_sales_outstanding?: number
  days_payable_outstanding?: number
}

// Capacity Planning
export interface WorkCenterUtilization {
  work_center_id: number
  code: string
  name: string
  total_capacity_hours: number
  required_hours: number
  available_hours: number
  utilization_pct: number
  status: 'available' | 'moderate' | 'near_capacity' | 'overloaded'
}

// Quarantine
export interface QuarantineEntry {
  quarantine_entry_id: number
  item_id: number
  item_code: string
  item_name: string
  quantity: number
  reason: string
  quarantined_at: string
  days_in_quarantine: number
}

// ── Performance Appraisals ────────────────────────────────────────────────────

export function usePerformanceAppraisals(filters: Record<string, unknown> = {}, enabled = false) {
  return useQuery({
    queryKey: ['performance-appraisals', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<PerformanceAppraisal>>('/hr/appraisals', { params: filters })
      return res.data
    },
  })
}

export function usePerformanceAppraisal(id: number | string) {
  return useQuery({
    queryKey: ['performance-appraisal', id],
    queryFn: async () => {
      const res = await api.get<{ data: PerformanceAppraisal }>(`/hr/appraisals/${id}`)
      return res.data.data
    },
    enabled: !!id,
  })
}

export function useCreateAppraisal() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => {
      const res = await api.post('/hr/appraisals', data)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['performance-appraisals'] }),
  })
}

export function useAppraisalAction() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action, data }: { id: number; action: string; data?: Record<string, unknown> }) => {
      const res = await api.patch(`/hr/appraisals/${id}/${action}`, data)
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['performance-appraisals'] })
      qc.invalidateQueries({ queryKey: ['performance-appraisal'] })
    },
  })
}

export function useDepartmentPerformanceSummary(year?: number, enabled = false) {
  return useQuery({
    queryKey: ['department-performance-summary', year],
    queryFn: async () => {
      const res = await api.get('/hr/appraisals/department-summary', { params: { year } })
      return res.data.data
    },
  })
}

// ── Budget Amendments ─────────────────────────────────────────────────────────

export function useBudgetAmendments(filters: Record<string, unknown> = {}, enabled = false) {
  return useQuery({
    queryKey: ['budget-amendments', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<BudgetAmendment>>('/budget/amendments', { params: filters })
      return res.data
    },
  })
}

export function useCreateBudgetAmendment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => {
      const res = await api.post('/budget/amendments', data)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['budget-amendments'] }),
  })
}

export function useBudgetAmendmentAction() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action, data }: { id: number; action: string; data?: Record<string, unknown> }) => {
      const res = await api.patch(`/budget/amendments/${id}/${action}`, data)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['budget-amendments'] }),
  })
}

// ── Lead Scoring ──────────────────────────────────────────────────────────────

export function useLeadScores(enabled = false) {
  return useQuery({
    queryKey: ['lead-scores'],
    queryFn: async () => {
      const res = await api.get<{ data: LeadScore[] }>('/crm/leads/scores')
      return res.data.data
    },
  })
}

export function useAutoQualifyLeads() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.post('/crm/leads/auto-qualify')
      return res.data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lead-scores'] })
      qc.invalidateQueries({ queryKey: ['leads'] })
    },
  })
}

// ── Financial Ratios ──────────────────────────────────────────────────────────

export function useFinancialRatios(year?: number, enabled = false) {
  return useQuery({
    queryKey: ['financial-ratios', year],
    queryFn: async () => {
      const res = await api.get<{ data: Record<string, FinancialRatio> & { fiscal_year: number } }>('/accounting/financial-ratios', { params: { year } })
      return res.data.data
    },
  })
}

// ── Capacity Planning ─────────────────────────────────────────────────────────

export function useCapacityUtilization(from?: string, to?: string, enabled = false) {
  return useQuery({
    queryKey: ['capacity-utilization', from, to],
    queryFn: async () => {
      const res = await api.get<{ data: WorkCenterUtilization[] }>('/production/capacity', { params: { from, to } })
      return res.data.data
    },
  })
}

export function useTimePhasedMrp(enabled = false) {
  return useQuery({
    queryKey: ['time-phased-mrp'],
    queryFn: async () => {
      const res = await api.get('/production/mrp/time-phased')
      return res.data.data
    },
  })
}

// ── AP Early Payment Discounts ────────────────────────────────────────────────

export function usePaymentOptimization(days: number = 7, enabled = false) {
  return useQuery({
    queryKey: ['payment-optimization', days],
    queryFn: async () => {
      const res = await api.get('/ap/payment-optimization', { params: { days } })
      return res.data.data
    },
  })
}

export function useDiscountSummary(enabled = false) {
  return useQuery({
    queryKey: ['discount-summary'],
    queryFn: async () => {
      const res = await api.get('/ap/discount-summary')
      return res.data.data
    },
  })
}

// ── QC Quarantine ─────────────────────────────────────────────────────────────

export function useQuarantine(enabled = false) {
  return useQuery({
    queryKey: ['quarantine'],
    queryFn: async () => {
      const res = await api.get<{ data: QuarantineEntry[] }>('/qc/quarantine')
      return res.data.data
    },
  })
}

export function useQuarantineAction() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ entryId, action, data }: { entryId: number; action: 'release' | 'reject'; data: Record<string, unknown> }) => {
      const res = await api.post(`/qc/quarantine/${entryId}/${action}`, data)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['quarantine'] }),
  })
}

// ── Role-Based Dashboard ──────────────────────────────────────────────────────

export function useMyDashboard() {
  return useQuery({
    queryKey: ['my-dashboard'],
    queryFn: async () => {
      const res = await api.get('/dashboard/my')
      return res.data.data
    },
    staleTime: 60_000, // 1 minute
  })
}

// ── Loan Payoff ───────────────────────────────────────────────────────────────

export function useLoanPayoff(loanId: number) {
  return useQuery({
    queryKey: ['loan-payoff', loanId],
    queryFn: async () => {
      const res = await api.get(`/loans/${loanId}/payoff`)
      return res.data.data
    },
    enabled: !!loanId,
  })
}

export function useExecuteLoanPayoff() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (loanId: number) => {
      const res = await api.post(`/loans/${loanId}/payoff`)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['loans'] }),
  })
}

// ── Blanket POs ───────────────────────────────────────────────────────────────

export function useBlanketPOs(filters: Record<string, unknown> = {}, enabled = false) {
  return useQuery({
    queryKey: ['blanket-pos', filters],
    queryFn: async () => {
      const res = await api.get('/procurement/blanket-pos', { params: filters })
      return res.data
    },
  })
}

export function useCreateBlanketPO() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) => {
      const res = await api.post('/procurement/blanket-pos', data)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['blanket-pos'] }),
  })
}

// ── Tax Alphalist ─────────────────────────────────────────────────────────────

export function useAlphalist2316(year: number) {
  return useQuery({
    queryKey: ['alphalist-2316', year],
    queryFn: async () => {
      const res = await api.get('/tax/alphalist-2316', { params: { year } })
      return res.data.data
    },
  })
}

export function useAlphalist2307(year: number, quarter: number) {
  return useQuery({
    queryKey: ['alphalist-2307', year, quarter],
    queryFn: async () => {
      const res = await api.get('/tax/alphalist-2307', { params: { year, quarter } })
      return res.data.data
    },
  })
}

// ── Final Pay ─────────────────────────────────────────────────────────────────

export function useFinalPay(employeeId: number, lastWorkingDate?: string) {
  return useQuery({
    queryKey: ['final-pay', employeeId, lastWorkingDate],
    queryFn: async () => {
      const res = await api.get(`/payroll/final-pay/${employeeId}`, { params: { last_working_date: lastWorkingDate } })
      return res.data.data
    },
    enabled: !!employeeId,
  })
}

// ── Leave Conflict ────────────────────────────────────────────────────────────

export function useLeaveConflicts(requestId: number) {
  return useQuery({
    queryKey: ['leave-conflicts', requestId],
    queryFn: async () => {
      const res = await api.get(`/leave/requests/${requestId}/conflicts`)
      return res.data.data
    },
    enabled: !!requestId,
  })
}

// ── ISO Acknowledgments ───────────────────────────────────────────────────────

export function usePendingAcknowledgments(enabled = false) {
  return useQuery({
    queryKey: ['pending-acknowledgments'],
    queryFn: async () => {
      const res = await api.get('/iso/pending-acknowledgments')
      return res.data.data
    },
  })
}

export function useAcknowledgeDocument() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (distributionId: number) => {
      const res = await api.post(`/iso/acknowledge/${distributionId}`)
      return res.data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pending-acknowledgments'] }),
  })
}

// ── Inventory Valuation by Method ─────────────────────────────────────────────

export function useValuationByMethod(enabled = false) {
  return useQuery({
    queryKey: ['valuation-by-method'],
    queryFn: async () => {
      const res = await api.get('/inventory/valuation-by-method')
      return res.data.data
    },
  })
}
