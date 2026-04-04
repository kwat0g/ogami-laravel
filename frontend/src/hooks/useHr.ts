import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  Employee,
  EmployeeListItem,
  Department,
  Position,
  Paginated,
} from '@/types/hr'

// ── Reference Lookups ────────────────────────────────────────────────────────

export function useSalaryGrades() {
  return useQuery({
    queryKey: ['salary-grades'],
    queryFn: async () => {
      const res = await api.get<{ data: { id: number; code: string; name: string; basic_monthly_rate: number }[] }>('/hr/salary-grades')
      return res.data.data
    },
    staleTime: 300_000,
  })
}

export function useLeaveTypes() {
  return useQuery({
    queryKey: ['leave-types'],
    queryFn: async () => {
      const res = await api.get<{ data: { id: number; code: string; name: string; max_days: number }[] }>('/hr/leave-types')
      return res.data.data
    },
    staleTime: 300_000,
  })
}

export function useLoanTypes() {
  return useQuery({
    queryKey: ['loan-types'],
    queryFn: async () => {
      const res = await api.get<{ data: { id: number; code: string; name: string; interest_rate_annual: number }[] }>('/hr/loan-types')
      return res.data.data
    },
    staleTime: 300_000,
  })
}

// ── Employees ────────────────────────────────────────────────────────────────

export interface EmployeeFilters {
  search?: string
  department_id?: number
  employment_status?: string
  employment_type?: string
  per_page?: number
  page?: number
}

export function useEmployees(filters: EmployeeFilters = {}, enabled: boolean = true) {
  return useQuery({
    queryKey: ['employees', filters],
    enabled,
    queryFn: async () => {
      const res = await api.get<Paginated<EmployeeListItem>>('/hr/employees', { params: filters })
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useTeamEmployees(filters: EmployeeFilters = {}) {
  return useQuery({
    queryKey: ['employees-team', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<EmployeeListItem>>('/hr/employees/team', { params: filters })
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useEmployee(id: string | number | undefined, enabled: boolean = true) {
  return useQuery({
    queryKey: ['employees', id],
    queryFn: async () => {
      const res = await api.get<{ data: Employee }>(`/hr/employees/${id}`)
      return res.data.data
    },
    enabled: !!id,
    staleTime: 30_000,
  })
}

export function useCreateEmployee() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      api.post<{ data: Employee }>('/hr/employees', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['employees'] }),
  })
}

export function useUpdateEmployee() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string | number; payload: Record<string, unknown> }) =>
      api.patch<{ data: Employee }>(`/hr/employees/${id}`, payload),
    onSuccess: (_data, { id }) => {
      qc.invalidateQueries({ queryKey: ['employees'] })
      qc.invalidateQueries({ queryKey: ['employees', id] })
    },
  })
}

export function useTransitionEmployee() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, transition, remarks }: { id: string | number; transition: string; remarks?: string }) =>
      api.post(`/hr/employees/${id}/transition`, { transition, remarks }),
    onSuccess: (_data, { id }) => {
      qc.invalidateQueries({ queryKey: ['employees'] })
      qc.invalidateQueries({ queryKey: ['employees', id] })
    },
  })
}

// ── Departments ──────────────────────────────────────────────────────────────

export interface DepartmentFilters {
  search?: string
  is_active?: boolean
  page?: number
  per_page?: number
}

export function useDepartments(filters: DepartmentFilters = {}) {
  return useQuery({
    queryKey: ['departments', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<Department>>('/hr/departments', { params: filters })
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useCreateDepartment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { code: string; name: string; parent_department_id?: number; cost_center_code?: string }) =>
      api.post<{ data: Department }>('/hr/departments', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['departments'] }),
  })
}

export function useUpdateDepartment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      api.patch<{ data: Department }>(`/hr/departments/${id}`, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['departments'] }),
  })
}

export function useDeleteDepartment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/hr/departments/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['departments'] }),
  })
}

// ── Positions ────────────────────────────────────────────────────────────────

export interface PositionFilters {
  search?: string
  department_id?: number
  is_active?: boolean
  page?: number
  per_page?: number
}

export function usePositions(filters: PositionFilters = {}) {
  return useQuery({
    queryKey: ['positions', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<Position>>('/hr/positions', { params: filters })
      return res.data
    },
    staleTime: 60_000,
  })
}

export function useCreatePosition() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: { code: string; title: string; department_id?: number; pay_grade?: string; description?: string }) =>
      api.post<{ data: Position }>('/hr/positions', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['positions'] }),
  })
}

export function useUpdatePosition() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      api.patch<{ data: Position }>(`/hr/positions/${id}`, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['positions'] }),
  })
}

export function useDeletePosition() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/hr/positions/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['positions'] }),
  })
}
