import { Link, Navigate, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import {
  Package,
  ShoppingCart,
  LayoutDashboard,
  ClipboardCheck,
  FileText,
  KeyRound,
  LogOut,
  User,
  ChevronDown,
  Store,
} from 'lucide-react'
import { useState } from 'react'
import api from '@/lib/api'
import { bumpAuthEpoch } from '@/lib/authEpoch'
import { disconnectEcho } from '@/lib/echo'
import { getPasswordChangePath } from '@/lib/roleLanding'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const NAV_SECTIONS = [
  {
    label: 'Orders',
    items: [
      { to: '/vendor-portal/orders', label: 'Purchase Orders', icon: ShoppingCart, permission: 'vendor_portal.view_orders' },
      { to: '/vendor-portal/goods-receipts', label: 'Goods Receipts', icon: ClipboardCheck, permission: 'vendor_portal.view_receipts' },
      { to: '/vendor-portal/invoices', label: 'Invoices', icon: FileText, permission: 'vendor_portal.view_receipts' },
    ],
  },
  {
    label: 'Catalog',
    items: [
      { to: '/vendor-portal/items', label: 'My Catalog', icon: Package, permission: 'vendor_portal.manage_items' },
    ],
  },
]

export default function VendorPortalLayout(): React.ReactElement {
  const location = useLocation()
  const queryClient = useQueryClient()
  const { isLoading } = useAuth()
  const { user, hasPermission, hasRole, clearAuth, mustChangePassword } = useAuthStore()
  const [showUserMenu, setShowUserMenu] = useState(false)

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

  const isActive = (path: string) => location.pathname === path || location.pathname.startsWith(path + '/')

  return (
    <div className="min-h-screen bg-neutral-50 dark:bg-neutral-950 flex">
      {/* Sidebar */}
      <aside className="w-64 bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800 flex flex-col sticky top-0 h-screen">
        {/* Logo */}
        <div className="p-4 border-b border-neutral-100 dark:border-neutral-800">
          <Link to="/vendor-portal/dashboard" className="flex items-center gap-3">
            <div className="w-10 h-10 bg-neutral-900 dark:bg-neutral-100 rounded-xl flex items-center justify-center">
              <Store className="h-5 w-5 text-white dark:text-neutral-900" />
            </div>
            <div>
              <h1 className="font-bold text-neutral-900 dark:text-neutral-100 leading-tight">Vendor Portal</h1>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">Ogami ERP</p>
            </div>
          </Link>
        </div>

        {/* Navigation */}
        <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
          {/* Dashboard */}
          <NavLink
            to="/vendor-portal/dashboard"
            end
            className={() =>
              `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                isActive('/vendor-portal/dashboard') && location.pathname === '/vendor-portal/dashboard'
                  ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                  : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-neutral-100'
              }`
            }
          >
            <LayoutDashboard className="h-4 w-4" />
            Dashboard
          </NavLink>

          {NAV_SECTIONS.map((section) => {
            const visibleItems = section.items.filter((item) => hasPermission(item.permission))
            if (visibleItems.length === 0) return null
            return (
              <div key={section.label} className="pt-2">
                <p className="px-3 text-xs font-medium text-neutral-400 uppercase tracking-wider mb-2">
                  {section.label}
                </p>
                {visibleItems.map(({ to, label, icon: Icon }) => (
                  <NavLink
                    key={to}
                    to={to}
                    className={() =>
                      `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                        isActive(to)
                          ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                          : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-neutral-100'
                      }`
                    }
                  >
                    <Icon className="h-4 w-4" />
                    {label}
                  </NavLink>
                ))}
              </div>
            )
          })}
        </nav>

        {/* User Section */}
        <div className="p-3 border-t border-neutral-200 dark:border-neutral-800">
          <div className="relative">
            <button
              onClick={() => setShowUserMenu(!showUserMenu)}
              className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            >
              <div className="w-8 h-8 bg-neutral-200 dark:bg-neutral-700 rounded-full flex items-center justify-center">
                <User className="h-4 w-4 text-neutral-600 dark:text-neutral-300" />
              </div>
              <div className="flex-1 text-left min-w-0">
                <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">{user?.name}</p>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 truncate">{user?.email}</p>
              </div>
              <ChevronDown className="h-4 w-4 text-neutral-400" />
            </button>

            {showUserMenu && (
              <div className="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg py-1 z-50">
                <Link
                  to={getPasswordChangePath(user)}
                  className="flex items-center gap-2 px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                  onClick={() => setShowUserMenu(false)}
                >
                  <KeyRound className="h-4 w-4" />
                  Change Password
                </Link>
                <button
                  onClick={() => {
                    setShowUserMenu(false)
                    handleLogout()
                  }}
                  className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                >
                  <LogOut className="h-4 w-4" />
                  Sign out
                </button>
              </div>
            )}
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-auto">
        <div className="max-w-7xl mx-auto px-6 py-6">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
