import { lazy, Suspense } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { getLandingPath } from '@/lib/roleLanding'
import TimeClockWidget from '@/components/attendance/TimeClockWidget'

// Lazy load dashboard variants
// RBAC v2: 7 core roles + system roles
const EmployeeDashboard           = lazy(() => import('@/pages/dashboard/EmployeeDashboard'))
const ManagerDashboard            = lazy(() => import('@/pages/dashboard/ManagerDashboard'))
const HeadDashboard               = lazy(() => import('@/pages/dashboard/HeadDashboard'))
const OfficerDashboard            = lazy(() => import('@/pages/dashboard/OfficerDashboard'))
const PurchasingOfficerDashboard  = lazy(() => import('@/pages/dashboard/PurchasingOfficerDashboard'))
const VicePresidentDashboard      = lazy(() => import('@/pages/dashboard/VicePresidentDashboard'))
const AdminDashboard              = lazy(() => import('@/pages/dashboard/AdminDashboard'))
const ExecutiveDashboard          = lazy(() => import('@/pages/dashboard/ExecutiveDashboard'))

/**
 * Dashboard Router - RBAC v2
 * 
 * In the new role system, permissions are determined by:
 *   Role + Department Module = Effective Permissions
 * 
 * Example:
 *   - Manager in HR dept → HR Manager dashboard
 *   - Manager in ACCTG dept → Accounting Manager dashboard
 *   - Manager in PLANT dept → Plant Manager dashboard
 * 
 * The specific dashboard content is determined by the user's department module,
 * not by a specific role name.
 */
export default function Dashboard() {
  const { user, isLoading } = useAuth()
  const { hasRole, hasPermission } = useAuthStore()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  const landingPath = getLandingPath(user)
  if (landingPath !== '/dashboard') {
    return <Navigate to={landingPath} replace />
  }

  // Resolve which dashboard to render based on role priority
  const resolveDashboard = () => {
    if (hasRole('super_admin') || hasRole('admin')) return <AdminDashboard />
    if (hasRole('executive')) return <ExecutiveDashboard />
    if (hasRole('vice_president')) return <VicePresidentDashboard />
    if (hasRole('manager')) return <ManagerDashboard />

    if (hasRole('officer')) {
      const dept = user?.primary_department_code ?? ''
      if (dept === 'PURCH' || hasPermission(PERMISSIONS.procurement.purchase_order.create)) return <PurchasingOfficerDashboard />
      if (dept === 'ACCTG' || hasPermission(PERMISSIONS.journal_entries.post)) return <OfficerDashboard />
      return <ManagerDashboard />
    }

    if (hasRole('head')) return <HeadDashboard />
    return <EmployeeDashboard />
  }

  // Every dashboard gets the Time Clock widget at the top (if user has an employee record)
  return (
    <div className="space-y-6">
      {user?.employee_id && <TimeClockWidget />}
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        {resolveDashboard()}
      </Suspense>
    </div>
  )
}
