import { lazy, Suspense } from 'react'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

// Lazy load all dashboard variants
const EmployeeDashboard       = lazy(() => import('@/pages/dashboard/EmployeeDashboard'))
const ManagerDashboard        = lazy(() => import('@/pages/dashboard/ManagerDashboard'))
const PlantManagerDashboard      = lazy(() => import('@/pages/dashboard/PlantManagerDashboard'))
const ProductionManagerDashboard = lazy(() => import('@/pages/dashboard/ProductionManagerDashboard'))
const QcManagerDashboard         = lazy(() => import('@/pages/dashboard/QcManagerDashboard'))
const MoldManagerDashboard       = lazy(() => import('@/pages/dashboard/MoldManagerDashboard'))
const HeadDashboard           = lazy(() => import('@/pages/dashboard/HeadDashboard'))
const OfficerDashboard        = lazy(() => import('@/pages/dashboard/OfficerDashboard'))
const VicePresidentDashboard  = lazy(() => import('@/pages/dashboard/VicePresidentDashboard'))
const AdminDashboard          = lazy(() => import('@/pages/dashboard/AdminDashboard'))
const ExecutiveDashboard      = lazy(() => import('@/pages/dashboard/ExecutiveDashboard'))

export default function Dashboard() {
  const { user: _user, isLoading } = useAuth()
  const { hasRole } = useAuthStore()

  if (isLoading) {
    return <SkeletonLoader rows={8} />
  }

  // Priority order: most privileged → least privileged

  // Admin — System administration only
  if (hasRole('admin')) {
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

  // Officer — Accounting / Purchasing / ImpEx review dashboard
  if (hasRole('officer')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <OfficerDashboard />
      </Suspense>
    )
  }

  // Manager (HR Manager) — full HR and payroll dashboard
  if (hasRole('manager')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <ManagerDashboard />
      </Suspense>
    )
  }

  // Plant Manager — oversees ALL plant operations
  if (hasRole('plant_manager')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <PlantManagerDashboard />
      </Suspense>
    )
  }

  // Production Manager — supervises production activities only
  if (hasRole('production_manager')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <ProductionManagerDashboard />
      </Suspense>
    )
  }

  // QC/QA Manager — manages quality control and assurance
  if (hasRole('qc_manager')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <QcManagerDashboard />
      </Suspense>
    )
  }

  // Mold Manager — oversees mold department
  if (hasRole('mold_manager')) {
    return (
      <Suspense fallback={<SkeletonLoader rows={8} />}>
        <MoldManagerDashboard />
      </Suspense>
    )
  }

  // Head — Department head team oversight & notation dashboard
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
