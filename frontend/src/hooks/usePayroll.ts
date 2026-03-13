import { useState, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  PayrollRun,
  PayPeriod,
  PayrollDetail,
  PayrollRunFilters,
  CreatePayrollRunPayload,
  CreateAdjustmentPayload,
  ScopeFilters,
  ScopePreview,
  PreRunValidationResult,
  ComputationProgress,
  PayrollRunApproval,
  GlPreview,
  HrApprovePayload,
  AcctgApprovePayload,
  PublishPayload,
  PayrollRunExclusion,
  PayrollAdjustment,
} from '@/types/payroll'

// ---------------------------------------------------------------------------
// Date conflict / pre-creation validation (PR-001…PR-004)
// Used by CreatePayrollRunPage to catch conflicts before the run is saved.
// ---------------------------------------------------------------------------

export interface DateConflictCheck {
  code: string
  label: string
  status: 'pass' | 'block' | 'warn'
  message?: string
}

export interface DateConflictResult {
  can_proceed: boolean
  checks: DateConflictCheck[]
}

export function useRunDateConflictCheck(
  params: { cutoff_start: string; cutoff_end: string; pay_date: string; run_type: string } | null,
) {
  return useQuery({
    queryKey: ['payroll-date-conflict', params],
    queryFn: async () => {
      const res = await api.get<{ data: DateConflictResult }>('/payroll/runs/validate', { params })
      return res.data.data
    },
    enabled: params !== null,
    staleTime: 15_000,
    retry: false,
  })
}

// ---------------------------------------------------------------------------
// Paginated list
// ---------------------------------------------------------------------------

interface PaginatedRuns {
  data: PayrollRun[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export function usePayrollRuns(filters: PayrollRunFilters = {}) {
  return useQuery({
    queryKey: ['payroll-runs', filters],
    queryFn: async () => {
      const res = await api.get<PaginatedRuns>('/payroll/runs', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ---------------------------------------------------------------------------
// Single run
// ---------------------------------------------------------------------------

export function usePayrollRun(id: string | null) {
  return useQuery({
    queryKey: ['payroll-runs', id],
    queryFn: async () => {
      const res = await api.get<{ data: PayrollRun }>(`/payroll/runs/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
    retry: (failureCount, error: unknown) => {
      const status = (error as { response?: { status?: number } })?.response?.status
      if (status === 404 || status === 403) return false
      return failureCount < 2
    },
    // Poll every 3 s while the run is being processed
    refetchInterval: (query) => {
      const data = query.state.data
      return (data?.status === 'processing' || data?.status === 'PROCESSING') ? 3_000 : false
    },
  })
}

// ---------------------------------------------------------------------------
// Paginated details for a run (payslips)
// ---------------------------------------------------------------------------

interface PaginatedDetails {
  data: PayrollDetail[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export function usePayrollDetails(runId: string | null, page = 1) {
  return useQuery({
    queryKey: ['payroll-details', runId, page],
    queryFn: async () => {
      const res = await api.get<PaginatedDetails>(
        `/payroll/runs/${runId}/details`,
        { params: { page, per_page: 50 } },
      )
      return res.data
    },
    enabled: runId !== null,
    staleTime: 30_000,
  })
}

// ---------------------------------------------------------------------------
// Single payslip detail
// ---------------------------------------------------------------------------

export function usePayrollDetail(runId: string, detailId: number) {
  return useQuery({
    queryKey: ['payroll-details', runId, detailId],
    queryFn: async () => {
      const res = await api.get<PayrollDetail>(
        `/payroll/runs/${runId}/details/${detailId}`,
      )
      return res.data
    },
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// Create run
// ---------------------------------------------------------------------------

export function useCreatePayrollRun() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: CreatePayrollRunPayload) => {
      const res = await api.post<{ data: PayrollRun }>('/payroll/runs', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ---------------------------------------------------------------------------
// Pay periods (for the run creation dropdown)
// ---------------------------------------------------------------------------

interface PaginatedPayPeriods {
  data: PayPeriod[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export function usePayPeriods(status?: string) {
  return useQuery({
    queryKey: ['pay-periods', status],
    queryFn: async () => {
      const res = await api.get<PaginatedPayPeriods>('/payroll/periods', {
        params: { status, per_page: 50 },
      })
      return res.data.data
    },
    staleTime: 60_000,
  })
}

// ---------------------------------------------------------------------------
// Begin computation — Step 3 → 4
// ---------------------------------------------------------------------------

interface BeginComputationResult {
  message: string
  batch_id: string
  total_jobs: number
  run: PayrollRun
}

export function useBeginComputation(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.post<BeginComputationResult>(`/payroll/runs/${runId}/compute`)
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
      void queryClient.invalidateQueries({ queryKey: ['payroll-progress', runId] })
    },
  })
}

// ---------------------------------------------------------------------------
// Lock a run → triggers batch computation
// ---------------------------------------------------------------------------

interface LockResult {
  message: string
  batch_id: string
  total_jobs: number
  run: PayrollRun
}

export function useLockPayrollRun(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<LockResult>(`/payroll/runs/${runId}/lock`)
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ---------------------------------------------------------------------------
// Approve a completed run
// ---------------------------------------------------------------------------

interface ApproveResult {
  message: string
  run: PayrollRun
}

export function useApprovePayrollRun(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const res = await api.patch<ApproveResult>(`/payroll/runs/${runId}/approve`)
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ---------------------------------------------------------------------------
// Cancel a run
// ---------------------------------------------------------------------------

export function useCancelPayrollRun(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      await api.patch(`/payroll/runs/${runId}/cancel`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

export function useArchivePayrollRun(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      await api.delete(`/payroll/runs/${runId}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ---------------------------------------------------------------------------
// Add / remove adjustments
// ---------------------------------------------------------------------------

export function usePayrollAdjustments(runId: string) {
  return useQuery({
    queryKey: ['payroll-adjustments', runId],
    queryFn: async () => {
      const res = await api.get<{ data: PayrollAdjustment[] }>(`/payroll/runs/${runId}/adjustments`)
      return res.data
    },
    enabled: !!runId,
  })
}

export function useCreateAdjustment(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: CreateAdjustmentPayload) => {
      const res = await api.post(`/payroll/runs/${runId}/adjustments`, payload)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-details', runId] })
    },
  })
}

export function useDeleteAdjustment(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (adjustmentId: number) => {
      await api.delete(`/payroll/adjustments/${adjustmentId}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-details', runId] })
    },
  })
}

// ---------------------------------------------------------------------------
// Downloads — PDF payslip, Excel register, BDO disbursement CSV
// ---------------------------------------------------------------------------

function triggerDownload(blob: Blob, filename: string, contentDisposition?: string) {
  // Try to extract filename from Content-Disposition header if provided
  let finalFilename = filename
  if (contentDisposition) {
    const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
    if (match && match[1]) {
      finalFilename = match[1].replace(/['"]/g, '')
    }
  }
  
  const url = URL.createObjectURL(blob)
  const a   = document.createElement('a')
  a.href     = url
  a.download = finalFilename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

export function useDownloadPayslip(runId: string, detailId: number) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get(
        `/payroll/runs/${runId}/details/${detailId}/payslip`,
        { responseType: 'blob' },
      )
      triggerDownload(
        res.data as Blob,
        `payslip-run${runId}-emp${detailId}.pdf`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [runId, detailId])

  return { download, isLoading }
}

export function useExportPayrollRegister(runId: string) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get(
        `/payroll/runs/${runId}/export/register`,
        { responseType: 'blob' },
      )
      triggerDownload(
        res.data as Blob,
        `payroll-register-run${runId}.xlsx`,
      )
    } finally {
      setIsLoading(false)
    }
  }, [runId])

  return { download, isLoading }
}

export function useExportDisbursement(runId: string, referenceNo?: string) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get(
        `/payroll/runs/${runId}/export/disbursement`,
        { responseType: 'blob' },
      )
      // Add timestamp to avoid browser adding @ prefix for duplicate filenames
      const timestamp = new Date().toISOString().slice(0, 10).replace(/-/g, '')
      const filename = referenceNo 
        ? `disbursement-bdo-${referenceNo}-${timestamp}.csv`
        : `disbursement-bdo-run-${runId}-${timestamp}.csv`
      triggerDownload(
        res.data as Blob,
        filename,
      )
    } finally {
      setIsLoading(false)
    }
  }, [runId, referenceNo])

  return { download, isLoading }
}

/**
 * Export comprehensive payroll breakdown — HR Manager and Accounting Manager.
 * Available from HR approval (Step 7) onwards, including DISBURSED and PUBLISHED.
 */
export function useExportPayrollBreakdown(runId: string) {
  const [isLoading, setIsLoading] = useState(false)

  const download = useCallback(async () => {
    setIsLoading(true)
    try {
      const res = await api.get(
        `/payroll/runs/${runId}/export/breakdown`,
        { responseType: 'blob' },
      )
      triggerDownload(
        res.data as Blob,
        `payroll-breakdown-run${runId}.xlsx`,
      )
    } catch (error: unknown) {
      // Show error message if not at HR_APPROVED or beyond
      const err = error as { response?: { status?: number } }
      if (err.response?.status === 422) {
        throw new Error('Export only available after HR approval (Step 7 onwards).')
      }
      throw error
    } finally {
      setIsLoading(false)
    }
  }, [runId])

  return { download, isLoading }
}

// ── Pre-run validation (legacy — Step 1 inline checks) ────────────────────────
export interface PreRunCheck {
  code: string
  label: string
  status: 'pass' | 'block' | 'warn'
  message?: string
}

export interface LegacyPreRunValidationResult {
  can_proceed: boolean
  checks: PreRunCheck[]
}

export function useValidatePreRun(params: {
  cutoff_start?: string
  cutoff_end?: string
  pay_date?: string
  run_type?: string
  enabled: boolean
}) {
  return useQuery({
    queryKey: ['payroll-pre-run-validate', params.cutoff_start, params.cutoff_end, params.pay_date, params.run_type],
    queryFn: async () => {
      const res = await api.get<{ data: LegacyPreRunValidationResult }>('/payroll/runs/validate', {
        params: {
          cutoff_start: params.cutoff_start,
          cutoff_end:   params.cutoff_end,
          pay_date:     params.pay_date,
          run_type:     params.run_type,
        },
      })
      return res.data.data
    },
    enabled: params.enabled && !!params.cutoff_start && !!params.cutoff_end,
    staleTime: 30_000,
  })
}

// ── Payroll Run Exceptions ────────────────────────────────────────────────────

export function usePayrollExceptions(runId: string | null) {
  return useQuery({
    queryKey: ['payroll-exceptions', runId],
    queryFn: async () => {
      const res = await api.get<{ data: unknown[]; total: number }>(`/payroll/runs/${runId}/exceptions`)
      return res.data
    },
    enabled: runId !== null,
    staleTime: 60_000,
  })
}

// =============================================================================
// WORKFLOW v1.0 HOOKS
// =============================================================================

// ── Step 2: Scope ─────────────────────────────────────────────────────────────

export function useScopePreview(runId: string, filters: Partial<ScopeFilters>, enabled = true) {
  return useQuery({
    queryKey: ['payroll-scope-preview', runId, filters],
    queryFn: async () => {
      const res = await api.get<{ data: ScopePreview }>(`/payroll/runs/${runId}/scope-preview`, {
        params: filters,
      })
      return res.data.data
    },
    enabled,
    staleTime: 10_000,
  })
}

export function useConfirmScope(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ScopeFilters) => {
      const res = await api.patch<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/scope`,
        payload,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

export function useAddExclusion(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { employee_id: number; reason: string }) => {
      const res = await api.post<{ data: PayrollRunExclusion }>(
        `/payroll/runs/${runId}/scope/exclusions`,
        payload,
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-scope-preview', runId] })
    },
  })
}

export function useRemoveExclusion(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (employeeId: number) => {
      await api.delete(`/payroll/runs/${runId}/scope/exclusions/${employeeId}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-scope-preview', runId] })
    },
  })
}

// ── Step 3: Pre-Run Checks ────────────────────────────────────────────────────

export function usePreRunChecks(runId: string | null, enabled = true) {
  return useQuery({
    queryKey: ['payroll-pre-run-checks', runId],
    queryFn: async () => {
      const res = await api.post<{ data: PreRunValidationResult }>(
        `/payroll/runs/${runId}/pre-run-checks`,
      )
      return res.data.data
    },
    enabled: enabled && runId !== null,
    staleTime: 0,          // always fresh (blocking checks may resolve after fix)
    refetchInterval: 10_000, // auto-refresh every 10 s to catch fixes
  })
}

export function useAcknowledgePreRun(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (acknowledgedWarnings: string[]) => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/acknowledge`,
        { acknowledged_warnings: acknowledgedWarnings },
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ── Step 4: Computation ───────────────────────────────────────────────────────

export function useComputationProgress(runId: string | null) {
  return useQuery({
    queryKey: ['payroll-progress', runId],
    queryFn: async () => {
      const res = await api.get<{ data: ComputationProgress }>(
        `/payroll/runs/${runId}/progress`,
      )
      return res.data.data
    },
    enabled: runId !== null,
    refetchInterval: (query) => {
      const data = query.state.data
      return (data?.status === 'PROCESSING' || data?.status === 'processing') ? 2_000 : false
    },
    staleTime: 0,
  })
}

// ── Step 5: Review & Breakdown ────────────────────────────────────────────────

interface BreakdownFilters {
  department_id?: number
  flag?: 'none' | 'flagged' | 'resolved'
  search?: string
  page?: number
  per_page?: number
}

interface PaginatedBreakdown {
  data: PayrollDetail[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  summary: {
    total_gross: number
    total_deductions: number
    total_net: number
    total_employees: number
  }
}

export function usePayrollBreakdown(runId: string | null, filters: BreakdownFilters = {}) {
  return useQuery({
    queryKey: ['payroll-breakdown', runId, filters],
    queryFn: async () => {
      const res = await api.get<PaginatedBreakdown>(
        `/payroll/runs/${runId}/breakdown`,
        { params: filters },
      )
      return res.data
    },
    enabled: runId !== null,
    staleTime: 60_000,
  })
}

export function usePayrollBreakdownDetail(runId: string, detailId: number) {
  return useQuery({
    queryKey: ['payroll-breakdown-detail', runId, detailId],
    queryFn: async () => {
      const res = await api.get<{ data: PayrollDetail }>(
        `/payroll/runs/${runId}/breakdown/${detailId}`,
      )
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useFlagEmployee(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (params: {
      detailId: number
      flag: 'none' | 'flagged' | 'resolved'
      review_note?: string
    }) => {
      const res = await api.patch<{ message: string }>(
        `/payroll/runs/${runId}/review/flag/${params.detailId}`,
        { flag: params.flag, review_note: params.review_note },
      )
      return res.data
    },
    onSuccess: (_, vars) => {
      void queryClient.invalidateQueries({ queryKey: ['payroll-breakdown', runId] })
      void queryClient.invalidateQueries({ queryKey: ['payroll-breakdown-detail', runId, vars.detailId] })
    },
  })
}

export function useSubmitForHrApproval(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/submit-for-hr`,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ── Step 6: HR Review ─────────────────────────────────────────────────────────

export function useHrApprove(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: HrApprovePayload) => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/hr-approve`,
        payload,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
      void queryClient.invalidateQueries({ queryKey: ['payroll-approvals', runId] })
    },
  })
}

// ── Step 7: Accounting Review ─────────────────────────────────────────────────

export function useGlPreview(runId: string | null) {
  return useQuery({
    queryKey: ['payroll-gl-preview', runId],
    queryFn: async () => {
      const res = await api.get<{ data: GlPreview }>(`/payroll/runs/${runId}/gl-preview`)
      return res.data.data
    },
    enabled: runId !== null,
    staleTime: 60_000,
  })
}

export function useAcctgApprove(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: AcctgApprovePayload) => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/acctg-approve`,
        payload,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
      void queryClient.invalidateQueries({ queryKey: ['payroll-approvals', runId] })
    },
  })
}

// ── Step 7b: VP Final Approval ────────────────────────────────────────────────

export interface VpApprovePayload {
  checkboxes_checked?: string[]
  comments?: string | null
}

export function useVpApprovePayroll(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: VpApprovePayload) => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/vp-approve`,
        payload,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
      void queryClient.invalidateQueries({ queryKey: ['payroll-approvals', runId] })
    },
  })
}

// ── Step 8: Disbursement & Publication ───────────────────────────────────────

export function useDisburse(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/disburse`,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

export function usePublish(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: PublishPayload) => {
      const res = await api.post<{ message: string; run: PayrollRun }>(
        `/payroll/runs/${runId}/publish`,
        payload,
      )
      return res.data
    },
    onSuccess: (data) => {
      queryClient.setQueryData(['payroll-runs', runId], data.run)
      void queryClient.invalidateQueries({ queryKey: ['payroll-runs'] })
    },
  })
}

// ── Approval history (all steps) ─────────────────────────────────────────────

export function usePayrollApprovals(runId: string | null) {
  return useQuery({
    queryKey: ['payroll-approvals', runId],
    queryFn: async () => {
      const res = await api.get<{ data: PayrollRunApproval[] }>(`/payroll/runs/${runId}/approvals`)
      return res.data.data
    },
    enabled: runId !== null,
    staleTime: 30_000,
  })
}
