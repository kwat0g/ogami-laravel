import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { getLandingPath } from '@/lib/roleLanding'

/**
 * Wraps unauthenticated routes (login).
 * Redirects to dashboard if user is already authenticated.
 */
export default function AuthLayout() {
  const { isAuthenticated, isLoading, user } = useAuth()

  // Only redirect once we know for certain the user is authenticated.
  // Do NOT show a skeleton here — it unmounts <Outlet /> which wipes
  // the login form's react-hook-form state (typed email/password lost).
  if (!isLoading && isAuthenticated) {
    return <Navigate to={getLandingPath(user)} replace />
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-neutral-50 dark:bg-neutral-950">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Ogami ERP</h1>
          <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">Ogami Manufacturing Philippines Corp.</p>
        </div>
        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded p-8">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
