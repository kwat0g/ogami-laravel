import { useAuthStore } from '@/stores/authStore'

/**
 * Role hierarchy levels for access control.
 * Higher number = higher access level (can see more).
 *
 * Standard hierarchy: Manager (highest operational) → Officer → Head → Staff (lowest)
 *
 * NOTE: In the module permission system (ModulePermissionSeeder), the hierarchy
 * within each module is: Manager > Officer > Head > Staff.
 * Manager has the broadest permissions, Staff has self-service only.
 */
export const ROLE_HIERARCHY: Record<string, number> = {
  'super_admin': 100,
  'admin': 90,
  'executive': 80,
  'vice_president': 70,
  'manager': 60,      // Department manager — broadest module access
  'officer': 50,      // Department operations — broad but narrower than manager
  'head': 40,         // Team lead / supervisor — first-level approvals
  'staff': 30,        // Regular employee — self-service only
}

/**
 * Check if user's role meets the minimum hierarchy level.
 * Uses reversed hierarchy: higher level = more access.
 * 
 * @param userRoles - Array of user's roles
 * @param minRole - Minimum required role level
 * @returns boolean - true if user has at least the minimum role level
 * 
 * Examples:
 * - isRoleAtLeast(['staff'], 'head') => false (staff below head)
 * - isRoleAtLeast(['head'], 'head') => true (head meets head)
 * - isRoleAtLeast(['manager'], 'head') => true (manager above head)
 * - isRoleAtLeast(['staff', 'head'], 'head') => true (has head role)
 */
export function isRoleAtLeast(userRoles: string[], minRole: string): boolean {
  const minLevel = ROLE_HIERARCHY[minRole] ?? 0
  
  // Super admin and admin always pass
  if (userRoles.includes('super_admin') || userRoles.includes('admin')) {
    return true
  }
  
  // Check if any of user's roles meets or exceeds the minimum level
  return userRoles.some(role => {
    const level = ROLE_HIERARCHY[role] ?? 0
    return level >= minLevel
  })
}

/**
 * Department-to-module mapping for Segregation of Duties (SoD).
 * Each module lists the department codes that should have access.
 * Use 'ALL' to allow all departments (typically for executives/VPs).
 */
export const MODULE_DEPARTMENTS: Record<string, string[]> = {
  // HR Module — HR dept manages records; supervisors in other depts can view
  // their own team (team-scoped endpoints). This mirrors ModuleAccessMiddleware.
  'hr':         ['HR'],
  'employees':  ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
  'attendance': ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
  'leaves':     ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
  'overtime':   ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
  'loans':      ['HR', 'ACCTG', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'IT'],
  'payroll':    ['HR', 'ACCTG'],
  'departments': ['HR', 'IT', 'EXEC'],
  'positions':  ['HR'],
  'shifts':     ['HR', 'PURCH', 'PROD', 'PLANT', 'WH', 'QC', 'MAINT', 'SALES', 'ACCTG', 'IT'],
  // _none_ is used as a sentinel when ActionGuard/ActionButton call the hook
  // unconditionally but no module is specified — always grants access.
  '_none_':     ['ALL'],  
  
  // Accounting Module - Accounting department only
  'accounting': ['ACCTG', 'EXEC'],
  'journal_entries': ['ACCTG'],
  'chart_of_accounts': ['ACCTG'],
  'fiscal_periods': ['ACCTG'],
  'recurring_templates': ['ACCTG'],
  'general_ledger': ['ACCTG'],
  
  // Payables (AP) - Accounting and Purchasing
  'ap': ['ACCTG', 'PURCH'],
  'vendors': ['ACCTG', 'PURCH'], // Vendors shared between AP and Procurement
  'vendor_invoices': ['ACCTG'],
  'vendor_payments': ['ACCTG'],
  'vendor_credit_notes': ['ACCTG'],
  
  // Receivables (AR) - Sales, Accounting, and Purchasing (for vendor-customer management)
  'ar': ['SALES', 'ACCTG', 'PURCH'],
  'customers': ['SALES', 'ACCTG', 'PURCH'],
  'customer_invoices': ['SALES', 'ACCTG'],
  'customer_payments': ['SALES', 'ACCTG'],
  'customer_credit_notes': ['SALES', 'ACCTG'],
  
  // Tax - Accounting department only
  'tax': ['ACCTG'],
  'vat_ledger': ['ACCTG'],
  'bir_filing': ['ACCTG'],
  
  // Banking - Accounting department only
  'banking': ['ACCTG'],
  'bank_accounts': ['ACCTG'],
  'bank_reconciliation': ['ACCTG'],
  
  // Fixed Assets - Accounting department only
  'fixed_assets': ['ACCTG'],
  'asset_categories': ['ACCTG'],
  
  // Budget - Accounting and Executive
  'budget': ['ACCTG', 'EXEC'],
  'cost_centers': ['ACCTG'],
  'annual_budget': ['ACCTG'],
  
  // Procurement - Purchasing + departments that raise PRs or verify budget
  'procurement': ['PURCH', 'PROD', 'PLANT', 'ACCTG', 'WH'],
  'purchase_requests': ['PURCH', 'PROD', 'PLANT', 'ACCTG'],
  'purchase_orders': ['PURCH'],
  'goods_receipts': ['PURCH', 'WH'], // GR involves both Purchasing and Warehouse
  'rfqs': ['PURCH'],
  
  // Inventory - Warehouse, Purchasing, Production, Sales (for stock availability)
  'inventory': ['WH', 'PURCH', 'PROD', 'PLANT', 'SALES'],
  'items': ['WH', 'PURCH', 'PROD', 'SALES'],
  'stock': ['WH', 'PURCH', 'PROD', 'SALES'],
  'stock_ledger': ['WH', 'PURCH', 'PROD'],
  'requisitions': ['WH', 'PURCH', 'PROD'],
  'adjustments': ['WH'],
  'locations': ['WH'],
  
  // Production - Production department only
  'production': ['PROD', 'PLANT', 'PPC'],
  'work_orders': ['PROD', 'PLANT'],
  'boms': ['PROD', 'PLANT'],
  'delivery_schedules': ['PROD', 'PLANT', 'PPC'],
  
  // QC - Quality Control primarily, but Production and Warehouse need access for inspections
  'qc': ['QC', 'PROD', 'WH'],
  'inspections': ['QC', 'PROD', 'WH'],
  'ncr': ['QC', 'PROD', 'WH'],
  'capa': ['QC', 'PROD'],
  
  // Approvals - Executive and VP
  'approvals': ['EXEC'],
  'templates': ['QC'],
  
  // Maintenance - Maintenance and Production
  'maintenance': ['MAINT', 'PROD', 'PLANT'],
  'equipment': ['MAINT', 'PROD', 'PLANT'],
  'work_orders_maintenance': ['MAINT', 'PROD', 'PLANT'],
  
  // Mold - Mold and Production
  'mold': ['MOLD', 'PROD'],
  'mold_masters': ['MOLD', 'PROD'],
  'mold_shots': ['MOLD', 'PROD'],
  
  // Delivery - Warehouse, Sales, Production (coordination)
  'delivery': ['WH', 'SALES', 'PROD', 'PLANT'],
  'shipments': ['WH', 'SALES', 'PROD', 'PLANT'],
  'receipts': ['WH', 'SALES', 'PROD'],
  'vehicles': ['WH', 'MAINT'],
  
  // ISO — QC and ISO depts manage ISO/IATF
  'iso': ['ISO', 'QC'],
  'documents': ['QC'],
  'audits': ['QC'],
  'audit_findings': ['QC'],
  
  // CRM - Sales department only
  'crm': ['SALES'],
  'tickets': ['SALES'],
  'support_dashboard': ['SALES'],
  
  // Administration - IT and Executive only
  'admin': ['IT', 'EXEC'],
  'users': ['IT', 'EXEC'],
  'settings': ['IT', 'EXEC'],
  'audit_logs': ['IT', 'EXEC'],
  'backups': ['IT'],
  'reference_tables': ['IT', 'EXEC'],
  
  // Reports - All departments can view their own reports
  'reports': ['ALL'],
  
  // Dashboard - All departments
  'dashboard': ['ALL'],
  
  // Self-service - All employees
  'self_service': ['ALL'],
  'profile': ['ALL'],
  'payslips': ['ALL'],
  'my_attendance': ['ALL'],
  'my_leaves': ['ALL'],
  'my_overtime': ['ALL'],
}

export interface DepartmentGuardResult {
  /** True if the user has access to this module based on their department */
  hasAccess: boolean
  /** Human-readable explanation when access is denied */
  reason: string | null
  /** The user's primary department code */
  userDept: string | null
  /** Required departments for this module */
  requiredDepts: string[]
}

/**
 * Check if user has department-level access to a module.
 * This enforces SoD by preventing cross-department access.
 * 
 * @param moduleKey - The module key (e.g., 'accounting', 'hr', 'production')
 * @returns DepartmentGuardResult with access status and reason
 * 
 * Usage:
 * ```tsx
 * const { hasAccess, reason } = useDepartmentGuard('accounting')
 * <button disabled={!hasAccess} title={reason ?? undefined}>
 *   Create Entry
 * </button>
 * ```
 */
export function useDepartmentGuard(moduleKey: string): DepartmentGuardResult {
  const user = useAuthStore((s) => s.user)
  const hasRole = useAuthStore((s) => s.hasRole)
  
  // Not authenticated - no access
  if (!user) {
    return {
      hasAccess: false,
      reason: 'Authentication required',
      userDept: null,
      requiredDepts: MODULE_DEPARTMENTS[moduleKey] ?? ['ALL'],
    }
  }
  
  const requiredDepts = MODULE_DEPARTMENTS[moduleKey] ?? ['ALL']
  const userDept = user.primary_department_code
  
  // Bypass roles: super_admin, admin, executive, vice_president can access all
  if (hasRole('super_admin') || hasRole('admin') || hasRole('executive') || hasRole('vice_president')) {
    return {
      hasAccess: true,
      reason: null,
      userDept,
      requiredDepts,
    }
  }
  
  // Module allows all departments
  if (requiredDepts.includes('ALL')) {
    return {
      hasAccess: true,
      reason: null,
      userDept,
      requiredDepts,
    }
  }
  
  // Check if user's department is in the allowed list
  if (userDept && requiredDepts.includes(userDept)) {
    return {
      hasAccess: true,
      reason: null,
      userDept,
      requiredDepts,
    }
  }
  
  // Access denied - build reason message
  const deptList = requiredDepts.join(', ')
  return {
    hasAccess: false,
    reason: `Access restricted to ${deptList} department${requiredDepts.length > 1 ? 's' : ''}. Your department (${userDept ?? 'Unknown'}) does not have access to this module.`,
    userDept,
    requiredDepts,
  }
}

/**
 * Hook version for checking role hierarchy within the reversed hierarchy model.
 * 
 * @param minRole - Minimum required role level
 * @returns Object with hasAccess boolean and user's highest role level
 * 
 * Usage:
 * ```tsx
 * const { hasAccess, highestRole } = useRoleHierarchy('head')
 * if (hasAccess) {
 *   // User is head, manager, officer, or higher
 * }
 * ```
 */
export function useRoleHierarchy(minRole: string): { 
  hasAccess: boolean
  highestRole: string | null
  userRoles: string[]
} {
  const user = useAuthStore((s) => s.user)
  
  if (!user) {
    return { hasAccess: false, highestRole: null, userRoles: [] }
  }
  
  const userRoles = user.roles || []
  
  // Find the user's highest role level
  let highestLevel = 0
  let highestRole: string | null = null
  
  for (const role of userRoles) {
    const level = ROLE_HIERARCHY[role] ?? 0
    if (level > highestLevel) {
      highestLevel = level
      highestRole = role
    }
  }
  
  const hasAccess = isRoleAtLeast(userRoles, minRole)
  
  return { hasAccess, highestRole, userRoles }
}

/**
 * Direct function version for non-hook usage (e.g., in utility functions).
 * Requires user object to be passed in.
 */
export function checkDepartmentAccess(
  user: { primary_department_code: string | null; roles: string[] } | null,
  moduleKey: string
): { hasAccess: boolean; reason: string | null } {
  if (!user) {
    return { hasAccess: false, reason: 'Authentication required' }
  }
  
  const requiredDepts = MODULE_DEPARTMENTS[moduleKey] ?? ['ALL']
  
  // Bypass roles
  if (['super_admin', 'admin', 'executive', 'vice_president'].some(r => user.roles.includes(r))) {
    return { hasAccess: true, reason: null }
  }
  
  // Module allows all
  if (requiredDepts.includes('ALL')) {
    return { hasAccess: true, reason: null }
  }
  
  // Check department
  if (user.primary_department_code && requiredDepts.includes(user.primary_department_code)) {
    return { hasAccess: true, reason: null }
  }
  
  const deptList = requiredDepts.join(', ')
  return {
    hasAccess: false,
    reason: `Access restricted to ${deptList} department${requiredDepts.length > 1 ? 's' : ''}`,
  }
}

/**
 * Direct function version for checking role hierarchy.
 * Requires user roles array to be passed in.
 * 
 * @param userRoles - Array of user's roles
 * @param minRole - Minimum required role level
 * @returns boolean - true if user meets minimum role requirement
 */
export function checkRoleHierarchy(userRoles: string[], minRole: string): boolean {
  return isRoleAtLeast(userRoles, minRole)
}
