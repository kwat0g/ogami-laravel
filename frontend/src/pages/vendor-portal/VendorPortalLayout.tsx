import { Link, Navigate, Outlet, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Package, ShoppingCart, LayoutDashboard, ClipboardCheck, FileText, KeyRound, LogOut } from 'lucide-react'
import api from '@/lib/api'
import { bumpAuthEpoch } from '@/lib/authEpoch'
import { disconnectEcho } from '@/lib/echo'
import { getPasswordChangePath } from '@/lib/roleLanding'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const NAV_ITEMS = [
  { to: '/vendor-portal/dashboard',      label: 'Dashboard',       icon: LayoutDashboard, permission: 'vendor_portal.view_orders' },
  { to: '/vendor-portal/orders',         label: 'Purchase Orders', icon: ShoppingCart, permission: 'vendor_portal.view_orders' },
  { to: '/vendor-portal/goods-receipts', label: 'Goods Receipts',  icon: ClipboardCheck, permission: 'vendor_portal.view_receipts' },
  { to: '/vendor-portal/invoices',       label: 'Invoices',        icon: FileText, permission: 'vendor_portal.view_receipts' },
  { to: '/vendor-portal/items',          label: 'My Catalog',      icon: Package, permission: 'vendor_portal.manage_items' },
]

export default function VendorPortalLayout(): React.ReactElement {
  const location = useLocation()
  const queryClient = useQueryClient()
  const { isLoading } = useAuth()
  const { user, hasPermission, hasRole, clearAuth, mustChangePassword } = useAuthStore()

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

  if (!hasRole('vendor')) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex h-screen bg-neutral-50 dark:bg-neutral-950">
      {/* Sidebar */}
      <aside className="w-56 bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800 flex flex-col">
        <div className="px-4 py-5 border-b border-neutral-200 dark:border-neutral-800">
          <p className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Vendor Portal</p>
          <p className="text-sm font-semibold text-neutral-800 dark:text-neutral-200 mt-1 truncate">{user?.name ?? '—'}</p>
        </div>
        <nav className="flex-1 px-2 py-4 space-y-1">
          {NAV_ITEMS.filter((item) => hasPermission(item.permission)).map(({ to, label, icon: Icon }) => {
            const active = location.pathname.startsWith(to)
            return (
              <Link
                key={to}
                to={to}
                className={`flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                  active
                    ? 'bg-neutral-900 text-white'
                    : 'text-neutral-700 hover:bg-neutral-100'
                }`}
              >
                <Icon className="w-4 h-4" />
                {label}
              </Link>
            )
          })}
        </nav>
        <div className="px-3 py-4 border-t border-neutral-200 space-y-1">
          <Link
            to={getPasswordChangePath(user)}
            className="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-neutral-700 hover:bg-neutral-100"
          >
            <KeyRound className="w-4 h-4" />
            Change Password
          </Link>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-neutral-700 hover:bg-neutral-100"
          >
            <LogOut className="w-4 h-4" />
            Sign out
          </button>
        </div>
        <div className="px-4 py-4 border-t border-neutral-200">
          <p className="text-xs text-neutral-400">Ogami ERP — Vendor Portal</p>
        </div>
      </aside>

      {/* Main content */}
      <main className="flex-1 overflow-auto p-6">
        <Outlet />
      </main>
    </div>
  )
}
