import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { Paginated } from '@/types/hr'

// ---------------------------------------------------------------------------
// Admin Domain Types
// ---------------------------------------------------------------------------

export interface AdminUser {
  id:                    number
  name:                  string
  email:                 string
  department_id:         number | null
  last_login_at:         string | null
  created_at:            string
  locked_until:          string | null
  failed_login_attempts: number
  roles:                 Array<{ id: number; name: string }>
  employee?: {
    id:             number
    employee_code:  string
    first_name:     string
    last_name:      string
    department_id:  number | null
    department?: { id: number; name: string; code: string }
  } | null
}

export interface Role {
  id:         number
  name:       string
  users_count?: number
}

export interface SystemSetting {
  key:              string
  label:            string
  value:            unknown
  data_type:        'string' | 'integer' | 'decimal' | 'boolean' | 'json'
  group:            string
  editable_by_role: string
  is_sensitive:     boolean
}

export interface DashboardStats {
  total_employees:   number
  by_department:     Array<{ department: string; count: number }>
  hired_trend:       Array<{ month: string; count: number }>
  leave_by_status:   Record<string, number>
  pending_approvals: {
    leaves:          number
    loans:           number
    overtime:        number
    journal_entries: number
    invoices:        number
    total:           number
  }
  attendance_summary: {
    total_records:      number
    present:            number
    absent:             number
    total_late_minutes: number
  } | null
  payroll_trend: Array<{ month: string; total: number }>
  active_period: {
    id:        number
    name:      string
    date_from: string
    date_to:   string
    status:    string
  } | null
}

export interface UserFilters {
  search?: string
  role?:   string
  page?:   number
  per_page?: number
}

export interface CreateUserPayload {
  name:         string
  email:        string
  password:     string
  role:         string
  employee_id?: number | null
}

export interface AvailableEmployee {
  id:              number
  employee_code:   string
  first_name:      string
  last_name:       string
  department_id:   number
  department_name: string
}

export interface Department {
  id:   number
  name: string
  code: string
}

export interface UpdateUserPayload {
  name?:     string
  email?:    string
  password?: string
}

// ---------------------------------------------------------------------------
// Dashboard Stats
// ---------------------------------------------------------------------------

export function useDashboardStats() {
  return useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: async () => {
      const res = await api.get<DashboardStats>('/admin/dashboard/stats')
      return res.data
    },
    staleTime: 60_000,
    retry: 1,
  })
}

// ---------------------------------------------------------------------------
// Users
// ---------------------------------------------------------------------------

export function useAdminUsers(filters: UserFilters = {}) {
  return useQuery({
    queryKey: ['admin-users', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<AdminUser>>('/admin/users', { params: filters })
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useCreateAdminUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CreateUserPayload) => {
      const res = await api.post<{ data: AdminUser }>('/admin/users', payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })
}

export function useUpdateAdminUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: UpdateUserPayload & { id: number }) => {
      const res = await api.patch<{ data: AdminUser }>(`/admin/users/${id}`, payload)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })
}

export function useDeleteAdminUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/users/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })
}

export function useAssignRole() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ userId, role }: { userId: number; role: string }) => {
      const res = await api.post<{ data: AdminUser }>(`/admin/users/${userId}/roles`, { role })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })
}

export function useUnlockUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/admin/users/${id}/unlock`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
    },
  })
}

// ---------------------------------------------------------------------------
// Roles
// ---------------------------------------------------------------------------

export function useRoles() {
  return useQuery({
    queryKey: ['admin-roles'],
    queryFn: async () => {
      const res = await api.get<{ data: Role[] }>('/admin/roles')
      return res.data.data
    },
    staleTime: 300_000,
  })
}

// ---------------------------------------------------------------------------
// System Settings
// ---------------------------------------------------------------------------

export function useSystemSettings() {
  return useQuery({
    queryKey: ['system-settings'],
    queryFn: async () => {
      const res = await api.get<{ data: Record<string, SystemSetting[]> }>('/admin/settings')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

export function useUpdateSystemSetting() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ key, value }: { key: string; value: unknown }) => {
      const res = await api.patch<{ data: SystemSetting }>(`/admin/settings/${key}`, { value })
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['system-settings'] })
    },
  })
}

// ---------------------------------------------------------------------------
// Audit Logs
// ---------------------------------------------------------------------------

export interface AuditLog {
  id: number
  event: string
  auditable_type: string
  auditable_id: number
  old_values: string | null
  new_values: string | null
  ip_address: string | null
  user_agent: string | null
  url: string | null
  tags: string | null
  created_at: string
  user_name: string
  user_email: string
}

export interface AuditLogFilters {
  search?: string
  event?: string
  auditable_type?: string
  date_from?: string
  date_to?: string
  user_id?: number
  per_page?: number
  page?: number
}

interface PaginatedAuditLogs {
  data: AuditLog[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export function useAuditLogs(filters: AuditLogFilters = {}) {
  return useQuery({
    queryKey: ['audit-logs', filters],
    queryFn: async () => {
      const res = await api.get<PaginatedAuditLogs>('/admin/audit-logs', { params: filters })
      return res.data
    },
    staleTime: 60_000,
    gcTime: 5 * 60_000,
    placeholderData: (prev: PaginatedAuditLogs | undefined) => prev,
    refetchOnWindowFocus: false,
  })
}

// ---------------------------------------------------------------------------
// User-creation wizard helpers
// ---------------------------------------------------------------------------

/** All active departments — used in Step 1 of the user-creation wizard. */
export function useAdminDepartments() {
  return useQuery({
    queryKey: ['admin-departments'],
    queryFn: async () => {
      const res = await api.get<{ data: Department[] }>('/hr/departments')
      return res.data.data
    },
    staleTime: 300_000,
  })
}

/**
 * Employees in a given department that do NOT yet have a user account.
 * Used in Step 2 of the user-creation wizard.
 */
export function useEmployeesAvailable(departmentId: number | null) {
  return useQuery({
    queryKey: ['employees-available', departmentId],
    queryFn: async () => {
      const res = await api.get<{ data: AvailableEmployee[] }>(
        '/admin/employees/available',
        { params: departmentId ? { department_id: departmentId } : {} }
      )
      return res.data.data
    },
    enabled: departmentId !== null && departmentId > 0,
    staleTime: 30_000,
  })
}

// ---------------------------------------------------------------------------
// Backup Management
// ---------------------------------------------------------------------------

export interface BackupFile {
  filename:   string
  size_bytes: number
  size_human: string
  created_at: string
  age_days:   number
}

export interface BackupStatus {
  backup_count:  number
  latest_backup: {
    filename:   string
    size_human: string
    created_at: string
    age_days:   number
  } | null
}

/** List all backup archives (sorted newest first). */
export function useBackups() {
  return useQuery({
    queryKey: ['admin-backups'],
    queryFn: async () => {
      const res = await api.get<{ data: BackupFile[] }>('/admin/backups')
      return res.data.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  })
}

/** Lightweight status card: count + latest file info. */
export function useBackupStatus() {
  return useQuery({
    queryKey: ['admin-backups-status'],
    queryFn: async () => {
      const res = await api.get<{ data: BackupStatus }>('/admin/backups/status')
      return res.data.data
    },
    staleTime: 60_000,
    refetchOnWindowFocus: false,
  })
}

/** Trigger an on-demand backup. */
export function useTriggerBackup() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const res = await api.post<{ success: boolean; message: string; data: BackupFile | null }>(
        '/admin/backups/run'
      )
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-backups'] })
      qc.invalidateQueries({ queryKey: ['admin-backups-status'] })
    },
  })
}

/** Restore a selected backup archive to the production database. */
export function useRestoreBackup() {
  return useMutation({
    mutationFn: async ({ filename, confirm }: { filename: string; confirm: string }) => {
      const res = await api.post<{ success: boolean; message: string }>(
        '/admin/backups/restore',
        { filename, confirm }
      )
      return res.data
    },
  })
}

/** Returns a download URL for a backup archive (opens directly in browser). */
export function backupDownloadUrl(filename: string): string {
  return `/api/v1/admin/backups/download?file=${encodeURIComponent(filename)}`
}
