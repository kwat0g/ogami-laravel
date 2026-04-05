import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
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
  deleted_at:            string | null
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
  permissions_count?: number
}

export interface RoleDetail {
  id:                   number
  name:                 string
  guard_name:           string
  is_protected:         boolean
  users_count:          number
  permissions:          string[]
  default_permissions:  string[] | null
}

export interface GroupedPermissions {
  [module: string]: string[]
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

export interface AvailableVendor {
  id: number
  name: string
  email: string
  contact_person: string | null
  accreditation_status: string
}

export interface AvailableCustomer {
  id: number
  name: string
  email: string
  contact_person: string | null
}

export interface PortalAccountCredentials {
  user_id: number
  email: string
  password: string
  role: 'vendor' | 'client'
}

export interface ProvisionPortalAccountPayload {
  role: 'vendor' | 'client'
  targetId: number
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
    placeholderData: keepPreviousData,
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

export function useDisableAdminUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/admin/users/${id}/disable`)
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

export function useResetPassword() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (userId: number) => {
      const res = await api.post<{ message: string; password: string }>(`/admin/users/${userId}/reset-password`)
      return res.data
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

// RBAC v2 - 7 Core Roles + Portal Roles
// These are the only roles that should be assignable
const VALID_ROLES = [
  { id: 1, name: 'super_admin', label: 'Super Admin' },
  { id: 2, name: 'admin', label: 'System Admin' },
  { id: 3, name: 'executive', label: 'Executive' },
  { id: 4, name: 'vice_president', label: 'Vice President' },
  { id: 5, name: 'manager', label: 'Manager' },
  { id: 6, name: 'officer', label: 'Officer' },
  { id: 7, name: 'head', label: 'Department Head' },
  { id: 8, name: 'staff', label: 'Staff' },
  { id: 9, name: 'vendor', label: 'Vendor Portal' },
  { id: 10, name: 'client', label: 'Client Portal' },
]

export function useRoles() {
  return useQuery({
    queryKey: ['admin-roles'],
    queryFn: async () => {
      const res = await api.get<{ data: Role[] }>('/admin/roles')
      // Filter to only show valid RBAC v2 roles
      const apiRoles = res.data.data
      return VALID_ROLES.map(validRole => {
        const apiRole = apiRoles.find((r: Role) => r.name === validRole.name)
        return {
          ...validRole,
          id: apiRole?.id ?? validRole.id,
          users_count: apiRole?.users_count ?? 0,
          permissions_count: apiRole?.permissions_count ?? 0,
        }
      })
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

interface AvailableAccountFilters {
  search?: string
  limit?: number
}

/** Active accredited vendors with email and no linked user account. */
export function useAvailableVendors(filters: AvailableAccountFilters = {}, enabled = true) {
  return useQuery({
    queryKey: ['available-vendors', filters],
    queryFn: async () => {
      const res = await api.get<{ data: AvailableVendor[] }>('/admin/vendors/available', { params: filters })
      return res.data.data
    },
    enabled,
    staleTime: 30_000,
  })
}

/** Active customers with email and no linked user account. */
export function useAvailableCustomers(filters: AvailableAccountFilters = {}, enabled = true) {
  return useQuery({
    queryKey: ['available-customers', filters],
    queryFn: async () => {
      const res = await api.get<{ data: AvailableCustomer[] }>('/admin/customers/available', { params: filters })
      return res.data.data
    },
    enabled,
    staleTime: 30_000,
  })
}

/**
 * Provisions a portal account for a vendor or client record.
 * Uses existing AP/AR provisioning endpoints so password generation stays domain-consistent.
 */
export function useProvisionPortalAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: ProvisionPortalAccountPayload) => {
      const path = payload.role === 'vendor'
        ? `/accounting/vendors/${payload.targetId}/provision-account`
        : `/ar/customers/${payload.targetId}/provision-account`

      const res = await api.post<{ data: PortalAccountCredentials }>(path)
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
      void queryClient.invalidateQueries({ queryKey: ['available-vendors'] })
      void queryClient.invalidateQueries({ queryKey: ['available-customers'] })
      void queryClient.invalidateQueries({ queryKey: ['vendors'] })
      void queryClient.invalidateQueries({ queryKey: ['customers'] })
    },
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
  /** 'safety' = auto-created before a restore; 'regular' = scheduled or on-demand */
  type:       'safety' | 'regular'
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

// ---------------------------------------------------------------------------
// RBAC Permission Management
// ---------------------------------------------------------------------------

/** Fetch all permissions grouped by module prefix. */
export function usePermissionsList() {
  return useQuery({
    queryKey: ['admin-permissions'],
    queryFn: async () => {
      const res = await api.get<{ data: GroupedPermissions; meta: { total: number } }>('/admin/permissions')
      return res.data
    },
    staleTime: 300_000,
  })
}

/** Fetch a single role with its assigned permission names and seeder defaults. */
export function useRoleDetail(roleName: string | null) {
  return useQuery({
    queryKey: ['admin-role-detail', roleName],
    queryFn: async () => {
      const res = await api.get<{ data: RoleDetail }>(`/admin/roles/${roleName}`)
      return res.data.data
    },
    enabled: !!roleName,
    staleTime: 60_000,
  })
}

/** Bulk-sync permissions for a role. */
export function useUpdateRolePermissions() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ roleName, permissions }: { roleName: string; permissions: string[] }) => {
      const res = await api.put<{ message: string; data: { role: string; permissions_count: number; added: string[]; removed: string[] } }>(
        `/admin/roles/${roleName}/permissions`,
        { permissions },
      )
      return res.data
    },
    onSuccess: (_data: unknown, variables: { roleName: string; permissions: string[] }) => {
      void qc.invalidateQueries({ queryKey: ['admin-roles'] })
      void qc.invalidateQueries({ queryKey: ['admin-role-detail', variables.roleName] })
      // Refresh current user permissions in case they were affected
      void qc.invalidateQueries({ queryKey: ['auth-me'] })
    },
  })
}

/** Reset a role's permissions to seeder defaults. */
export function useResetRolePermissions() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (roleName: string) => {
      const res = await api.post<{ message: string; data: { role: string; permissions_count: number } }>(
        `/admin/roles/${roleName}/reset`,
      )
      return res.data
    },
    onSuccess: (_data: unknown, roleName: string) => {
      void qc.invalidateQueries({ queryKey: ['admin-roles'] })
      void qc.invalidateQueries({ queryKey: ['admin-role-detail', roleName] })
      void qc.invalidateQueries({ queryKey: ['auth-me'] })
    },
  })
}
