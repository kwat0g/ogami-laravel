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
      get().user?.roles.includes(role) ?? false,

    hasAnyRole: (roles) =>
      roles.some((r) => get().user?.roles.includes(r)) ?? false,

    // ---- RDAC -------------------------------------------------------
    /** True if the user has access to the given department (admin/executive bypass all). */
    hasDepartmentAccess: (departmentId) => {
      const user = get().user
      if (!user) return false
      if (user.roles.some((r: AppRole) => ['admin', 'executive', 'vice_president'].includes(r))) return true
      return (user.department_ids ?? []).includes(departmentId)
    },

    primaryDepartmentId: () => get().user?.primary_department_id ?? null,

    // ---- Role helpers ------------------------------------------------
    isManager: () => {
      const roles = get().user?.roles ?? []
      return roles.some((r) => ['manager', 'officer', 'vice_president'].includes(r))
    },
    isHead:          () => get().user?.roles.includes('head')          ?? false,
    isOfficer:       () => get().user?.roles.includes('officer')       ?? false,
    isVicePresident: () => get().user?.roles.includes('vice_president') ?? false,
  }),
)
