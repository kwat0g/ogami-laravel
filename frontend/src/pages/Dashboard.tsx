import { lazy, Suspense } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { getLandingPath } from '@/lib/roleLanding'

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

  // Priority order: most privileged → least privileged

  // Super Admin / Admin — System administration
  if (hasRole('super_admin') || hasRole('admin')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <AdminDashboard />
      </Suspense>
    )
  }

  // Executive — Chairman / President high-level overview
  if (hasRole('executive')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <ExecutiveDashboard />
      </Suspense>
    )
  }

  // Vice President — Executive approvals & financial KPIs
  if (hasRole('vice_president')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <VicePresidentDashboard />
      </Suspense>
    )
  }

  // Manager — Full department module access
  // The specific dashboard content depends on the department module
  if (hasRole('manager')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <ManagerDashboard />
      </Suspense>
    )
  }

  // Officer — route by department so each officer sees relevant KPIs
  if (hasRole('officer')) {
    const dept = user?.primary_department_code ?? ''

    // Purchasing dept officers
    if (dept === 'PURCH' || hasPermission('procurement.rfq.create')) {
      return (
        <Suspense fallback={<SkeletonLoader rows={8} />}>
          <PurchasingOfficerDashboard />
        </Suspense>
      )
    }

    // Accounting dept officers
    if (dept === 'ACCTG' || hasPermission('journal_entries.post')) {
      return (
        <Suspense fallback={<SkeletonLoader rows={8} />}>
          <OfficerDashboard />
        </Suspense>
      )
    }

    // HR, Production, QC, Warehouse, Sales, Maint, ISO, etc.
    // Use ManagerDashboard — it is already department-aware and shows
    // the correct team/HR metrics based on the user's department.
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <ManagerDashboard />
      </Suspense>
    )
  }

  // Head — Department head team oversight
  if (hasRole('head')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <HeadDashboard />
      </Suspense>
    )
  }

  // Staff / Employee — Personal information only (default fallback)
  return (
    <Suspense fallback={<SkeletonLoader rows={8} />}>
      <EmployeeDashboard />
    </Suspense>
  )
}
