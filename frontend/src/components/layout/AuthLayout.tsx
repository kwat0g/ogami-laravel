import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

/**
 * Wraps unauthenticated routes (login).
 * Redirects to dashboard if user is already authenticated.
 */
export default function AuthLayout() {
  const { isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-neutral-50">
        <SkeletonLoader rows={3} />
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-neutral-50">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-xl font-semibold text-neutral-900">Ogami ERP</h1>
          <p className="text-sm text-neutral-500 mt-1">Ogami Manufacturing Philippines Corp.</p>
        </div>
        <div className="bg-white border border-neutral-200 rounded p-8">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
