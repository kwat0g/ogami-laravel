import { Outlet, Navigate } from 'react-router-dom'
import { Link, useLocation } from 'react-router-dom'
import { Package, ShoppingCart, LayoutDashboard, ClipboardCheck, FileText } from 'lucide-react'
import { useAuthStore } from '@/stores/authStore'

const NAV_ITEMS = [
  { to: '/vendor-portal/dashboard',      label: 'Dashboard',       icon: LayoutDashboard, permission: 'vendor_portal.view_orders' },
  { to: '/vendor-portal/orders',         label: 'My Orders',       icon: ShoppingCart, permission: 'vendor_portal.view_orders' },
  { to: '/vendor-portal/goods-receipts', label: 'Goods Receipts',  icon: ClipboardCheck, permission: 'vendor_portal.view_receipts' },
  { to: '/vendor-portal/invoices',       label: 'Invoices',        icon: FileText, permission: 'vendor_portal.view_receipts' },
  { to: '/vendor-portal/items',          label: 'My Catalog',      icon: Package, permission: 'vendor_portal.manage_items' },
]

export default function VendorPortalLayout(): React.ReactElement {
  const location = useLocation()
  const { user, hasPermission, hasRole } = useAuthStore()

  if (!hasRole('vendor')) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex h-screen bg-neutral-50">
      {/* Sidebar */}
      <aside className="w-56 bg-white border-r border-neutral-200 flex flex-col">
        <div className="px-4 py-5 border-b border-neutral-200">
          <p className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Vendor Portal</p>
          <p className="text-sm font-semibold text-neutral-800 mt-1 truncate">{user?.name ?? '—'}</p>
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
