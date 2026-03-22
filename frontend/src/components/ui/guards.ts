// Export all guard components and hooks
export { default as PermissionGuard } from './PermissionGuard'
export { default as DepartmentGuard } from './DepartmentGuard'
export { ActionGuard, ActionButton } from './ActionGuard'
export { default as SodActionButton } from './SodActionButton'

// Re-export hooks for convenience
export { useDepartmentGuard, checkDepartmentAccess, MODULE_DEPARTMENTS } from '@/hooks/useDepartmentGuard'
export { useSodCheck } from '@/hooks/useSodCheck'
