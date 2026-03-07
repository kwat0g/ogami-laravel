import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  LeaveRequest,
  LeaveBalance,
  LeaveFilters,
  CreateLeaveRequestPayload,
  Paginated,
} from '@/types/hr'

// ── Leave requests list ───────────────────────────────────────────────────────

export function useLeaveRequests(filters: LeaveFilters = {}) {
  return useQuery({
    queryKey: ['leave-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<LeaveRequest>>('/leave/requests', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Team Leave requests (department-scoped) ───────────────────────────────────

export function useTeamLeaveRequests(filters: LeaveFilters = {}) {
  return useQuery({
    queryKey: ['team-leave-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<LeaveRequest>>('/leave/requests/team', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Single leave request ──────────────────────────────────────────────────────

export function useLeaveRequest(id: number | null) {
  return useQuery({
    queryKey: ['leave-requests', id],
    queryFn: async () => {
      const res = await api.get<{ data: LeaveRequest }>(`/leave/requests/${id}`)
      return res.data.data
    },
    enabled: id !== null,
  })
}

// ── Leave balances ────────────────────────────────────────────────────────────

export interface CreateLeaveBalancePayload {
  employee_id: number
  leave_type_id: number
  year: number
  opening_balance: number
  accrued?: number
  adjusted?: number
  used?: number
}

export interface UpdateLeaveBalancePayload {
  opening_balance?: number
  accrued?: number
  adjusted?: number
  used?: number
}

export interface LeaveBalancesFilters {
  employee_id?: number
  year?: number
  department_id?: number
  search?: string
  per_page?: number
  page?: number
}

export interface EmployeeLeaveBalance {
  employee_id: number
  employee_name: string
  employee_code: string
  department_id: number | null
  department_name: string | null
  year: number
  balances: {
    leave_type_id: number
    leave_type_name: string
    leave_type_code: string
    has_balance: boolean
    balance: number
    opening_balance: number
    accrued: number
    adjusted: number
    used: number
    balance_id: number | null
  }[]
  total_balance: number
}

export function useLeaveBalances(filters: LeaveBalancesFilters = {}) {
  return useQuery({
    queryKey: ['leave-balances', filters],
    queryFn: async () => {
      const res = await api.get<{
        data: EmployeeLeaveBalance[]
        meta: {
          current_page: number
          last_page: number
          per_page: number
          total: number
        }
        leave_types: { id: number; name: string; code: string }[]
      }>('/leave/balances', {
        params: filters,
      })
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useCreateLeaveBalance() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateLeaveBalancePayload) => {
      const res = await api.post<{ data: LeaveBalance }>('/leave/balances', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
    },
  })
}

export function useUpdateLeaveBalance() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: UpdateLeaveBalancePayload & { id: number }) => {
      const res = await api.patch<{ data: LeaveBalance }>(`/leave/balances/${id}`, payload)
      return res.data.data
    },
    onSuccess: (_, { id }) => {
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-balances', id] })
    },
  })
}

// ── Create leave request ──────────────────────────────────────────────────────

export function useCreateLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateLeaveRequestPayload) => {
      const res = await api.post<{ data: LeaveRequest }>('/leave/requests', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Step 2 — Department Head Approval ────────────────────────────────────────

export function useHeadApproveLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks?: string }) => {
      const res = await api.patch<{ data: LeaveRequest }>(`/leave/requests/${id}/head-approve`, { remarks })
      return res.data.data
    },
    onSuccess: (_, { id }) => {
      queryClient.setQueryData(['leave-requests', id], (old: LeaveRequest | undefined) =>
        old ? { ...old, status: 'head_approved' as const } : old,
      )
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Step 3 — Plant Manager Check ─────────────────────────────────────────────

export function useManagerCheckLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks?: string }) => {
      const res = await api.patch<{ data: LeaveRequest }>(`/leave/requests/${id}/manager-check`, { remarks })
      return res.data.data
    },
    onSuccess: (_, { id }) => {
      queryClient.setQueryData(['leave-requests', id], (old: LeaveRequest | undefined) =>
        old ? { ...old, status: 'manager_checked' as const } : old,
      )
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Step 4 — GA Officer Process ───────────────────────────────────────────────

export interface GaProcessPayload {
  id: number
  action_taken: 'approved_with_pay' | 'approved_without_pay' | 'disapproved'
  remarks?: string
}

export function useGaProcessLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, action_taken, remarks }: GaProcessPayload) => {
      const res = await api.patch<{ data: LeaveRequest }>(`/leave/requests/${id}/ga-process`, {
        action_taken,
        remarks,
      })
      return res.data.data
    },
    onSuccess: (data, { id }) => {
      queryClient.setQueryData(['leave-requests', id], (old: LeaveRequest | undefined) =>
        old ? { ...old, status: data.status, action_taken: data.action_taken } : old,
      )
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Step 5 — VP Note ──────────────────────────────────────────────────────────

export function useVpNoteLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks?: string }) => {
      const res = await api.patch<{ data: LeaveRequest }>(`/leave/requests/${id}/vp-note`, { remarks })
      return res.data.data
    },
    onSuccess: (_, { id }) => {
      queryClient.setQueryData(['leave-requests', id], (old: LeaveRequest | undefined) =>
        old ? { ...old, status: 'approved' as const } : old,
      )
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Reject ────────────────────────────────────────────────────────────────────

export function useRejectLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks: string }) => {
      const res = await api.patch<{ data: LeaveRequest }>(`/leave/requests/${id}/reject`, { remarks })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Cancel (staff own request) ────────────────────────────────────────────────

export function useCancelLeaveRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/leave/requests/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-leave-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
      void queryClient.invalidateQueries({ queryKey: ['leave-calendar'] })
    },
  })
}

// ── Leave types (reference) ───────────────────────────────────────────────────

export interface LeaveType {
  id: number
  code: string
  name: string
  category: string
  is_paid: boolean
  max_days_per_year: number
  requires_approval: boolean
  requires_documentation: boolean
  monthly_accrual_days: number | null
  max_carry_over_days: number
  can_be_monetized: boolean
  deducts_absent_on_lwop: boolean
}

export function useLeaveTypes() {
  return useQuery({
    queryKey: ['leave-types'],
    queryFn: async () => {
      const res = await api.get<LeaveType[]>('/hr/leave-types')
      return res.data
    },
    staleTime: 5 * 60_000,
  })
}

// ── Leave Calendar ────────────────────────────────────────────────────────────
export interface LeaveCalendarEvent {
  id: number
  employee_id: number
  employee_name: string | null
  leave_type: string | null
  date_from: string
  date_to: string
  leave_days: number
  is_paid: boolean
}

export interface LeaveHoliday {
  date: string
  name: string
  type: string
}

export interface LeaveCalendarData {
  year: number
  month: number
  month_start: string
  month_end: string
  leave_events: LeaveCalendarEvent[]
  holidays: LeaveHoliday[]
}

export function useLeaveCalendar(year: number, month: number, departmentId?: number) {
  return useQuery({
    queryKey: ['leave-calendar', year, month, departmentId],
    queryFn: async () => {
      const res = await api.get<{ data: LeaveCalendarData }>('/leave/calendar', {
        params: { year, month, ...(departmentId ? { department_id: departmentId } : {}) },
      })
      return res.data.data
    },
    staleTime: 2 * 60_000,
  })
}
