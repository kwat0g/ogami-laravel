import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { ApiSuccess } from '@/types/api'
import type {
  Employee,
  EmployeeListItem,
  EmployeeFilters,
  CreateEmployeePayload,
  SalaryGrade,
  Department,
  Position,
  Paginated,
} from '@/types/hr'

// ── Paginated list ────────────────────────────────────────────────────────

interface PaginatedEmployees {
  data: EmployeeListItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export function useEmployees(filters: EmployeeFilters = {}) {
  return useQuery({
    queryKey: ['employees', filters],
    queryFn: async () => {
      const res = await api.get<PaginatedEmployees>('/hr/employees', { params: filters })
      return res.data
    },
    staleTime: 30_000,
  })
}

// ── Team employees (department-scoped) ──────────────────────────────────────

export function useTeamEmployees(filters: Omit<EmployeeFilters, 'department_id'> = {}) {
  return useQuery({
    queryKey: ['employees', 'team', filters],
    queryFn: async () => {
      const res = await api.get<PaginatedEmployees>('/hr/employees/team', { params: filters })
      return res.data
    },
    staleTime: 30_000,
  })
}

// ── Single employee detail ────────────────────────────────────────────────

export function useEmployee(
  id: string | null,
  options?: { staleTime?: number; refetchOnMount?: boolean | 'always' },
) {
  return useQuery({
    queryKey: ['employees', id],
    queryFn: async () => {
      const res = await api.get<{ data: Employee }>(`/hr/employees/${id}`)
      // EmployeeController::show() returns EmployeeResource → { data: {...} }
      return res.data.data
    },
    enabled: id !== null,
    staleTime: options?.staleTime ?? 60_000,
    refetchOnMount: options?.refetchOnMount ?? true,
  })
}

// ── Create ────────────────────────────────────────────────────────────────

export function useCreateEmployee() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: CreateEmployeePayload) => {
      const res = await api.post<ApiSuccess<Employee>>('/hr/employees', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['employees'] })
    },
  })
}

// ── Update ────────────────────────────────────────────────────────────────

export function useUpdateEmployee(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: Partial<CreateEmployeePayload>) => {
      const res = await api.patch<ApiSuccess<Employee>>(`/hr/employees/${id}`, payload)
      return res.data.data
    },
    onSuccess: (updated) => {
      // Update single-employee cache (keyed by ULID string, matching useEmployee)
      queryClient.setQueryData(['employees', id], updated)
      void queryClient.invalidateQueries({ queryKey: ['employees'] })
      // After update the employee may have been activated (all gov IDs provided),
      // which creates leave balances server-side. Invalidate the cache so the
      // profile view reflects the new balances without requiring a page reload.
      void queryClient.invalidateQueries({ queryKey: ['leave-balances'] })
    },
  })
}

// ── State transition ──────────────────────────────────────────────────────

export function useEmployeeTransition(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (toState: string) => {
      const res = await api.post<ApiSuccess<Employee>>(
        `/hr/employees/${id}/transition`,
        { to_state: toState },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['employees', id] })
      void queryClient.invalidateQueries({ queryKey: ['employees'] })
    },
  })
}

// ── Salary grades reference ───────────────────────────────────────────────

export function useSalaryGrades() {
  return useQuery({
    queryKey: ['salary-grades'],
    queryFn: async () => {
      const res = await api.get<SalaryGrade[]>('/hr/salary-grades')
      return res.data
    },
    staleTime: 10 * 60_000,  // 10 min — reference data
  })
}

// ── Departments ────────────────────────────────────────────────────────────

export function useDepartments(activeOnly = false) {
  return useQuery({
    queryKey: ['departments', { activeOnly }],
    queryFn: async () => {
      const res = await api.get<Paginated<Department>>('/hr/departments', {
        params: { active_only: activeOnly, per_page: 100 },
      })
      return res.data
    },
    staleTime: 5 * 60_000,
  })
}

export function useCreateDepartment() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<Department>) => {
      const res = await api.post<Department>('/hr/departments', payload)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['departments'] })
    },
  })
}

export function useUpdateDepartment() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...patch }: Partial<Department> & { id: number }) => {
      const res = await api.patch<Department>(`/hr/departments/${id}`, patch)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['departments'] })
    },
  })
}

export function useDeleteDepartment() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/hr/departments/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['departments'] })
    },
  })
}

// ── Positions ──────────────────────────────────────────────────────────────

export function usePositions(departmentId?: number) {
  return useQuery({
    queryKey: ['positions', { departmentId }],
    queryFn: async () => {
      const res = await api.get<Paginated<Position>>('/hr/positions', {
        params: { department_id: departmentId, per_page: 200 },
      })
      return res.data
    },
    staleTime: 5 * 60_000,
  })
}

export function useCreatePosition() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<Position>) => {
      const res = await api.post<Position>('/hr/positions', payload)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['positions'] })
    },
  })
}

export function useUpdatePosition() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...patch }: Partial<Position> & { id: number }) => {
      const res = await api.patch<Position>(`/hr/positions/${id}`, patch)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['positions'] })
    },
  })
}

export function useDeletePosition() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/hr/positions/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['positions'] })
    },
  })
}

// ── Employee Search (for autocomplete) ──────────────────────────────────────

import { useState, useEffect } from 'react'

export interface EmployeeSearchItem {
  id: number
  ulid: string
  employee_code: string
  full_name: string
  department?: { id: number; name: string }
  position?: { id: number; title: string }
}

// Simple debounce hook
function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value)
  
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedValue(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])
  
  return debouncedValue
}

export function useEmployeeSearch(query: string, enabled: boolean = true) {
  // Debounce query by 300ms to avoid API calls on every keystroke
  const debouncedQuery = useDebounce(query, 300)
  
  return useQuery({
    queryKey: ['employee-search', debouncedQuery],
    queryFn: async () => {
      const res = await api.get<PaginatedEmployees>('/hr/employees', {
        params: { search: debouncedQuery, per_page: 10 },
      })
      // Transform to simpler format for autocomplete
      // Note: EmployeeListResource returns full_name and nested department/position objects
      return res.data.data.map(emp => ({
        id: emp.id,
        ulid: emp.ulid,
        employee_code: emp.employee_code,
        full_name: emp.full_name,
        department: emp.department,    // { id, name }
        position: emp.position,        // { id, title }
      })) as EmployeeSearchItem[]
    },
    enabled: enabled && debouncedQuery.length >= 2,
    staleTime: 60_000, // Cache for 1 minute
  })
}
