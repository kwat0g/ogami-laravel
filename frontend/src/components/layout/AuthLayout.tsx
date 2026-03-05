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
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <SkeletonLoader rows={3} />
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-gray-900">Ogami ERP</h1>
          <p className="text-sm text-gray-500 mt-1">Ogami Manufacturing Philippines Corp.</p>
        </div>
        <div className="bg-white shadow-sm rounded-xl p-8">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
