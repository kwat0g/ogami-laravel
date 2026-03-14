import { create } from 'zustand'
import type { AppRole, AuthUser } from '@/types/api'

interface AuthState {
  user: AuthUser | null

  setAuth: (user: AuthUser) => void
  clearAuth: () => void

  // Permission / role helpers
  hasPermission: (permission: string) => boolean
  hasRole: (role: string) => boolean
  hasAnyRole: (roles: string[]) => boolean

  // RDAC helpers
  hasDepartmentAccess: (departmentId: number) => boolean
  primaryDepartmentId: () => number | null

  // Role helpers
  isManager:       () => boolean
  isHead:          () => boolean
  isOfficer:       () => boolean
  isVicePresident: () => boolean
  mustChangePassword: () => boolean
}

export const useAuthStore = create<AuthState>()(
  (set, get) => ({
    user: null,

    // Auth is now session-cookie based — no token stored in localStorage.
    setAuth: (user) => {
      set({ user })
    },

    clearAuth: () => {
      set({ user: null })
    },

    hasPermission: (permission) => {
      const user = get().user
      if (!user) return false
      // No wildcard for any role — permissions are checked strictly against
      // the Spatie permissions returned by the backend.
      // Admin's system.* permissions are seeded; they do NOT have HR/payroll
      // permissions (per ogami_role_permission_matrix.md).
      return user.permissions.includes(permission)
    },

    hasRole: (role) =>
      get().user?.roles.includes(role as AppRole) ?? false,

    hasAnyRole: (roles) =>
      roles.some((r) => get().user?.roles.includes(r as AppRole)) ?? false,

    // ---- RDAC -------------------------------------------------------
    /** True if the user has access to the given department (admin/executive bypass all). */
    hasDepartmentAccess: (departmentId) => {
      const user = get().user
      if (!user) return false
      if (user.roles.some((r: AppRole) => ['admin', 'super_admin', 'executive', 'vice_president'].includes(r))) return true
      return (user.department_ids ?? []).includes(departmentId)
    },

    primaryDepartmentId: () => get().user?.primary_department_id ?? null,

    // ---- Role helpers ------------------------------------------------
    isMemberOf: (roles: AppRole[]) => {
      const userRoles = get().user?.roles ?? []
      return roles.some(r => userRoles.includes(r))
    },

    isManager: () => {
      const roles = get().user?.roles ?? []
      return roles.some((r) => ['super_admin', 'manager', 'officer', 'vice_president', 'plant_manager', 'production_manager', 'qc_manager', 'mold_manager'].includes(r))
    },

    isHead: () => {
      const roles = get().user?.roles ?? []
      return roles.some((r) => ['head', 'warehouse_head', 'ppc_head'].includes(r))
    },

    isOfficer:       () => {
       const roles = get().user?.roles ?? []
       return roles.some((r) => ['officer', 'ga_officer', 'purchasing_officer', 'impex_officer'].includes(r))
    },

    isVicePresident: () => get().user?.roles.includes('vice_president') ?? false,
    mustChangePassword: () => get().user?.must_change_password ?? false,
  }),
)
