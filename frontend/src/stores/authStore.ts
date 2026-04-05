import { create } from 'zustand'
import type { AppRole, AuthUser } from '@/types/api'

interface AuthState {
  user: AuthUser | null

  setAuth: (user: AuthUser) => void
  clearAuth: () => void

  // Permission / role helpers
  hasPermission: (permission?: string | null) => boolean
  hasRole: (role: string) => boolean
  hasAnyRole: (roles: string[]) => boolean

  // RDAC helpers
  hasDepartmentAccess: (departmentId: number) => boolean
  primaryDepartmentId: () => number | null
  primaryDepartmentCode: () => string | null

  // Role helpers (Reversed Hierarchy: Officer → Manager → Head → Staff)
  isOfficer:       () => boolean
  isManager:       () => boolean
  isHead:          () => boolean
  isStaff:         () => boolean
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
      if (!user || !permission || permission.trim() === '') return false
      // Super admin bypasses all permission checks
      if (user.roles.includes('super_admin')) return true
      // No wildcard for other roles — permissions are checked strictly against
      // the Spatie permissions returned by the backend.
      // Admin's system.* permissions are seeded; they do NOT have HR/payroll
      // permissions (per ogami_role_permission_matrix.md).
      // Supports pipe-separated OR syntax: 'perm.a|perm.b' means "has either"
      return permission.split('|').some(p => user.permissions.includes(p.trim()))
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
    primaryDepartmentCode: () => get().user?.primary_department_code ?? null,

    // ---- Role helpers ------------------------------------------------
    isMemberOf: (roles: AppRole[]) => {
      const userRoles = get().user?.roles ?? []
      return roles.some(r => userRoles.includes(r))
    },

    // Standard Hierarchy: Manager (broadest) → Officer → Head → Staff (self-service)
    // Each level has LESS access than the one above
    
    isOfficer: () => {
      const roles = get().user?.roles ?? []
      return roles.includes('officer')
    },

    isManager: () => {
      const roles = get().user?.roles ?? []
      return roles.includes('manager')
    },

    isHead: () => {
      const roles = get().user?.roles ?? []
      return roles.includes('head')
    },
    
    isStaff: () => {
      const roles = get().user?.roles ?? []
      return roles.includes('staff')
    },

    isVicePresident: () => get().user?.roles.includes('vice_president') ?? false,
    mustChangePassword: () => get().user?.must_change_password ?? false,
  }),
)
