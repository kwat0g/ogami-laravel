import { NavLink, Outlet, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'

export default function ClientPortalLayout() {
  const user = useAuthStore(s => s.user)
  const hasPermission = useAuthStore(s => s.hasPermission)

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
      </aside>

      {/* Main */}
      <main className="flex-1 overflow-auto">
        <Outlet />
      </main>
    </div>
  )
}
