import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  Loan,
  LoanScheduleEntry,
  LoanFilters,
  LoanType,
  CreateLoanPayload,
  Paginated,
} from '@/types/hr'

// ── Paginated loan list ───────────────────────────────────────────────────────

export function useLoans(filters: LoanFilters = {}) {
  return useQuery({
    queryKey: ['loans', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<Loan>>('/loans', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Team Loans (department-scoped) ────────────────────────────────────────────

export function useTeamLoans(filters: LoanFilters = {}) {
  return useQuery({
    queryKey: ['team-loans', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<Loan>>('/loans/team', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Single loan ───────────────────────────────────────────────────────────────

export function useLoan(id: string | null) {
  return useQuery({
    queryKey: ['loans', id],
    queryFn: async () => {
      const res = await api.get<{ data: Loan }>(`/loans/${id}`)
      return res.data.data
    },
    enabled: id !== null,
  })
}

// ── Amortization schedule ─────────────────────────────────────────────────────

export function useLoanSchedule(loanId: string | null) {
  return useQuery({
    queryKey: ['loans', loanId, 'schedule'],
    queryFn: async () => {
      const res = await api.get<{ data: LoanScheduleEntry[] }>(`/loans/${loanId}/schedule`)
      return res.data.data
    },
    enabled: loanId !== null,
    staleTime: 60_000,
  })
}

// ── Create loan application ───────────────────────────────────────────────────

export function useCreateLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateLoanPayload) => {
      const res = await api.post<{ data: Loan }>('/loans', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['team-loans'] })
    },
  })
}

// ── Employee Loan History ─────────────────────────────────────────────────────

export function useEmployeeLoanHistory(loanId: string | null) {
  return useQuery({
    queryKey: ['loans', loanId, 'employee-history'],
    queryFn: async () => {
      const res = await api.get<{ data: Loan[] }>(`/loans/${loanId}/employee-history`)
      return res.data.data
    },
    enabled: loanId !== null,
    staleTime: 60_000,
  })
}

// ── Approve ───────────────────────────────────────────────────────────────────

export function useApproveLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({
      id,
      remarks,
      first_deduction_date,
    }: {
      id: string
      remarks?: string
      first_deduction_date?: string
    }) => {
      const res = await api.patch<{ data: Loan }>(`/loans/${id}/approve`, {
        remarks,
        first_deduction_date,
      })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['team-loans'] })
    },
  })
}

// ── Reject ────────────────────────────────────────────────────────────────────

export function useRejectLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: string; remarks: string }) => {
      const res = await api.patch<{ data: Loan }>(`/loans/${id}/reject`, { remarks })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['team-loans'] })
    },
  })
}

// ── VP Approve ────────────────────────────────────────────────────────────────

export function useVpApproveLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: string; remarks?: string }) => {
      const res = await api.patch<{ data: Loan }>(`/loans/${id}/vp-approve`, { remarks })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['vp-approvals'] })
    },
  })
}

// ── Accounting Approve ────────────────────────────────────────────────────────

export function useAccountingApproveLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: string; remarks?: string }) => {
      const res = await api.patch<{ data: Loan }>(`/loans/${id}/accounting-approve`, { remarks })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['team-loans'] })
    },
  })
}

// ── Disburse ──────────────────────────────────────────────────────────────────

export function useDisburseLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      const res = await api.patch<{ data: Loan }>(`/loans/${id}/disburse`, {})
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['team-loans'] })
    },
  })
}

// ── Cancel ────────────────────────────────────────────────────────────────────

export function useCancelLoan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      await api.delete(`/loans/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['loans'] })
      void queryClient.invalidateQueries({ queryKey: ['team-loans'] })
    },
  })
}

// ── Loan types (reference) ────────────────────────────────────────────────────

export function useLoanTypes() {
  return useQuery({
    queryKey: ['loan-types'],
    queryFn: async () => {
      const res = await api.get<LoanType[]>('/hr/loan-types')
      return res.data
    },
    staleTime: 5 * 60_000,
  })
}
