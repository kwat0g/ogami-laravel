import { Link, NavLink, Outlet, Navigate, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { KeyRound, LogOut } from 'lucide-react'
import api from '@/lib/api'
import { bumpAuthEpoch } from '@/lib/authEpoch'
import { disconnectEcho } from '@/lib/echo'
import { getPasswordChangePath } from '@/lib/roleLanding'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

export default function ClientPortalLayout() {
  const { isLoading } = useAuth()
  const user = useAuthStore(s => s.user)
  const hasPermission = useAuthStore(s => s.hasPermission)
  const clearAuth = useAuthStore(s => s.clearAuth)
  const mustChangePassword = useAuthStore(s => s.mustChangePassword)
  const queryClient = useQueryClient()
  const location = useLocation()

  const handleLogout = async () => {
    try {
      await api.post('/auth/logout')
    } catch {
      // ignore
    }
    disconnectEcho()
    queryClient.clear()
    clearAuth()
    bumpAuthEpoch()
  }

  if (isLoading) {
    return <SkeletonLoader rows={6} />
  }

  if (mustChangePassword()) {
    const passwordPath = getPasswordChangePath(user)
    if (location.pathname !== passwordPath) {
      return <Navigate to={passwordPath} replace />
    }
  }

  // Only client role users can access this portal
  if (!(user?.roles as string[] | undefined)?.includes('client') || !hasPermission('crm.tickets.view')) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex min-h-screen bg-neutral-50">
      {/* Sidebar */}
      <aside className="w-56 bg-white border-r flex flex-col">
        <div className="px-5 py-4 border-b">
          <div className="text-base font-bold text-neutral-900">Client Portal</div>
          <div className="text-xs text-neutral-500 mt-0.5 truncate">{user?.name}</div>
        </div>
        <nav className="flex-1 px-3 py-4 space-y-1">
          <NavLink
            to="/client-portal/tickets"
            className={({ isActive }) =>
              `flex items-center gap-2 px-3 py-2 rounded text-sm font-medium transition-colors ${
                isActive ? 'bg-neutral-100 text-neutral-700' : 'text-neutral-700 hover:bg-neutral-100'
              }`
            }
          >
            My Tickets
          </NavLink>
          <NavLink
            to="/client-portal/tickets/new"
            className={({ isActive }) =>
              `flex items-center gap-2 px-3 py-2 rounded text-sm font-medium transition-colors ${
                isActive ? 'bg-neutral-100 text-neutral-700' : 'text-neutral-700 hover:bg-neutral-100'
              }`
            }
          >
            Submit Ticket
          </NavLink>
        </nav>
        <div className="px-3 py-4 border-t space-y-1">
          <Link
            to={getPasswordChangePath(user)}
            className="flex items-center gap-2 px-3 py-2 rounded text-sm font-medium text-neutral-700 hover:bg-neutral-100"
          >
            <KeyRound className="w-4 h-4" />
            Change Password
          </Link>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2 px-3 py-2 rounded text-sm font-medium text-neutral-700 hover:bg-neutral-100"
          >
            <LogOut className="w-4 h-4" />
            Sign out
          </button>
        </div>
      </aside>

      {/* Main */}
      <main className="flex-1 overflow-auto">
        <Outlet />
      </main>
    </div>
  )
}
