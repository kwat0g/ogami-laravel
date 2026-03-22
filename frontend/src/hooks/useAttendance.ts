import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  AttendanceLog,
  AttendanceFilters,
  OvertimeRequest,
  OvertimeFilters,
  ShiftSchedule,
  EmployeeShiftAssignment,
  Paginated,
} from '@/types/hr'

// ── Attendance logs ────────────────────────────────────────────────────────────

export function useAttendanceLogs(filters: AttendanceFilters = {}) {
  return useQuery({
    queryKey: ['attendance-logs', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<AttendanceLog>>('/attendance/logs', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Team Attendance logs (department-scoped) ───────────────────────────────────

export function useTeamAttendanceLogs(filters: AttendanceFilters = {}) {
  return useQuery({
    queryKey: ['team-attendance-logs', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<AttendanceLog>>('/attendance/logs/team', { params: filters })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useAttendanceLog(id: number | null) {
  return useQuery({
    queryKey: ['attendance-logs', id],
    queryFn: async () => {
      const res = await api.get<{ data: AttendanceLog }>(`/attendance/logs/${id}`)
      return res.data.data
    },
    enabled: id !== null,
    staleTime: 30_000,
  })
}

// ── Manual attendance entry ──────────────────────────────────────────────────

export function useCreateAttendanceLog() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      employee_id: number
      work_date: string
      time_in?: string
      time_out?: string
      remarks?: string
    }) => {
      const res = await api.post<{ data: AttendanceLog }>('/attendance/logs', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['attendance-logs'] })
    },
  })
}

export function useUpdateAttendanceLog() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({
      id,
      ...payload
    }: {
      id: number
      time_in?: string
      time_out?: string
      remarks?: string
    }) => {
      const res = await api.patch<{ data: AttendanceLog }>(`/attendance/logs/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['attendance-logs'] })
    },
  })
}

// ── Import attendance CSV ──────────────────────────────────────────────────────

export function useImportAttendance() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (file: File) => {
      const form = new FormData()
      form.append('file', file)
      const res = await api.post<{ data: { imported: number; failed: number; errors: string[] } }>(
        '/attendance/import',
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['attendance-logs'] })
    },
  })
}

// ── Overtime requests ─────────────────────────────────────────────────────────

export function useOvertimeRequests(filters: OvertimeFilters = {}) {
  return useQuery({
    queryKey: ['overtime-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<OvertimeRequest>>('/attendance/overtime-requests', {
        params: filters,
      })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Team Overtime requests (department-scoped) ────────────────────────────────

export function useTeamOvertimeRequests(filters: OvertimeFilters = {}) {
  return useQuery({
    queryKey: ['team-overtime-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<OvertimeRequest>>('/attendance/overtime-requests/team', {
        params: filters,
      })
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

export function useCreateOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      employee_id: number
      work_date: string
      ot_start_time: string
      ot_end_time: string
      reason: string
    }) => {
      const res = await api.post<{ data: OvertimeRequest }>('/attendance/overtime-requests', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

export function useApproveOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({
      id,
      approved_minutes,
      remarks,
    }: { id: number; approved_minutes: number; remarks?: string }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/approve`,
        { approved_minutes, remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

export function useRejectOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks: string }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/reject`,
        { remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

// ── Shift schedules ────────────────────────────────────────────────────────────

export function useShifts(activeOnly = false) {
  return useQuery({
    queryKey: ['shifts', { activeOnly }],
    queryFn: async () => {
      const res = await api.get<Paginated<ShiftSchedule>>('/attendance/shifts', {
        params: { active_only: activeOnly, per_page: 100 },
      })
      return res.data
    },
    staleTime: 5 * 60_000,
  })
}

export function useCreateShift() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Omit<ShiftSchedule, 'id' | 'is_night_shift' | 'created_at' | 'updated_at'>) => {
      const res = await api.post<ShiftSchedule>('/attendance/shifts', payload)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['shifts'] })
    },
  })
}

export function useUpdateShift() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...patch }: Partial<ShiftSchedule> & { id: number }) => {
      const res = await api.patch<ShiftSchedule>(`/attendance/shifts/${id}`, patch)
      return res.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['shifts'] })
    },
  })
}

export function useDeleteShift() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/attendance/shifts/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['shifts'] })
    },
  })
}

// ── Employee shift assignments ─────────────────────────────────────────────────

export function useEmployeeShiftAssignments(employeeUlid: string | null) {
  return useQuery({
    queryKey: ['shift-assignments', employeeUlid],
    queryFn: async () => {
      const res = await api.get<EmployeeShiftAssignment[]>(`/attendance/employees/${employeeUlid}/shift-assignments`)
      return res.data
    },
    enabled: employeeUlid !== null,
  })
}

export function useAssignShift() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      employee_ulid: string
      shift_schedule_id: number
      effective_from: string
      notes?: string
    }) => {
      const { employee_ulid, ...body } = payload
      const res = await api.post<EmployeeShiftAssignment>(
        `/attendance/employees/${employee_ulid}/shift-assignments`,
        body,
      )
      return res.data
    },
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: ['shift-assignments', variables.employee_ulid] })
      void queryClient.invalidateQueries({ queryKey: ['employee'] })
    },
  })
}

export function useDeleteShiftAssignment() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/attendance/shift-assignments/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['shift-assignments'] })
      void queryClient.invalidateQueries({ queryKey: ['employee'] })
    },
  })
}

// ── Attendance Dashboard ─────────────────────────────────────────────────────
export interface AttendanceDashboardData {
  anomaly_feed: Array<{
    id: number
    employee_id: number
    employee_name: string | null
    log_date: string
    type: 'tardy' | 'absent'
    minutes_late: number | null
  }>
  ot_queue: {
    data: Array<{
      id: number
      employee_id: number
      employee_name: string | null
      date: string
      requested_minutes: number
      status: string
    }>
    total: number
  }
  period_stats: {
    absent_count: number
    tardy_count: number
    total_overtime_minutes: number
  }
}

export function useAttendanceDashboard() {
  return useQuery({
    queryKey: ['attendance-dashboard'],
    queryFn: async () => {
      const res = await api.get<{ data: AttendanceDashboardData }>('/attendance/dashboard')
      return res.data.data
    },
    staleTime: 2 * 60_000,
    refetchInterval: 5 * 60_000,
    refetchIntervalInBackground: false,
  })
}

// ── Attendance Summary Report ─────────────────────────────────────────────────

export interface AttendanceSummaryRow {
  employee_id: number
  employee_code: string
  employee_name: string
  days_present: number
  days_absent: number
  days_rest: number
  days_holiday: number
  total_worked_min: number
  total_late_min: number
  total_ut_min: number
  total_ot_min: number
  total_nd_min: number
}

export function useAttendanceSummary(params: { from?: string; to?: string; department_id?: number } = {}) {
  return useQuery({
    queryKey: ['attendance-summary', params],
    queryFn: async () => {
      const res = await api.get<{ data: AttendanceSummaryRow[]; from: string; to: string }>('/attendance/summary', { params })
      return res.data
    },
    staleTime: 60_000,
  })
}

// ── DTR CSV export (triggers browser download) ────────────────────────────────

export async function downloadDtr(employeeId: number, from: string, to: string): Promise<void> {
  const res = await api.get('/attendance/dtr-export', {
    params: { employee_id: employeeId, from, to },
    responseType: 'blob',
  })
  const url = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `DTR_${employeeId}_${from}_to_${to}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// ── Attendance template download (Excel with pre-filled employee codes) ─────────

export async function downloadAttendanceTemplate(): Promise<void> {
  const res = await api.get('/attendance/template', {
    responseType: 'blob',
  })
  const url = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `attendance_template_${new Date().toISOString().split('T')[0]}.xlsx`
  a.click()
  URL.revokeObjectURL(url)
}
