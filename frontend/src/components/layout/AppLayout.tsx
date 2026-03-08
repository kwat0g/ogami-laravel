import { useState, useRef, useEffect } from 'react'
import { Navigate, NavLink, Outlet, useNavigate, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'

// Uncodixified: Simple layout without excessive animations
import api from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents'
import { disconnectEcho } from '@/lib/echo'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import NotificationBell from '@/components/layout/NotificationBell'
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import {
  LayoutDashboard,
  Users,
  DollarSign,
  BookOpen,
  BarChart3,
  Landmark,
  ChevronDown,
  ChevronRight,
  LogOut,
  UserCircle,
  Shield,
  FileText,
  Calendar,
  Wallet,
  KeyRound,
  Clock,
  TrendingUp,
  Menu,
  PanelLeft,
  PanelLeftClose,
  ClipboardList,
  ShoppingCart,
  Package,
  Factory,
  ClipboardCheck,
  Wrench,
  Settings,
  Truck,
  ShieldCheck,
} from 'lucide-react'

interface NavChild {
  label: string
  href: string
  permission?: string
  end?: boolean
}

interface NavSection {
  label: string
  icon: React.ComponentType<{ className?: string }>
  permission?: string
  /** If provided, only users with at least one of these roles (or the admin role) can see this section. */
  roles?: string[]
  children: NavChild[]
}

const TOP_ITEMS = [
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, permission: null },
]

const SECTIONS: NavSection[] = [
  {
    label: 'Team Management',
    icon: Users,
    permission: 'employees.view_team',
    roles: ['manager', 'head', 'ga_officer', 'plant_manager', 'production_manager', 'qc_manager', 'mold_manager'],
    children: [
      { label: 'My Team', href: '/team/employees', permission: 'employees.view_team' },
      { label: 'Team Attendance', href: '/team/attendance', permission: 'attendance.view_team' },
      { label: 'Team Leave', href: '/team/leave', permission: 'leaves.view_team', end: true },
      { label: 'Team Overtime', href: '/team/overtime', permission: 'overtime.view' },
      { label: 'Team Loans', href: '/team/loans', permission: 'loans.view_department' },
      { label: 'Shift Schedules', href: '/team/shifts', permission: 'attendance.manage_shifts' },
    ],
  },
  {
    label: 'Human Resources',
    icon: Users,
    permission: 'hr.full_access',
    roles: ['manager'],
    children: [
      { label: 'All Employees', href: '/hr/employees/all', permission: 'hr.full_access' },
      { label: 'Attendance Logs', href: '/hr/attendance', permission: 'hr.full_access' },
      { label: 'Leave Requests', href: '/hr/leave', permission: 'hr.full_access', end: true },
      { label: 'Overtime', href: '/hr/overtime', permission: 'hr.full_access' },
      { label: 'Loans', href: '/hr/loans', permission: 'hr.full_access' },
      { label: 'Departments', href: '/hr/departments', permission: 'hr.full_access' },
      { label: 'Positions', href: '/hr/positions', permission: 'hr.full_access' },
      { label: 'Shifts', href: '/hr/shifts', permission: 'hr.full_access' },
    ],
  },
  {
    label: 'Payroll',
    icon: DollarSign,
    permission: 'payroll.view_runs',
    roles: ['manager', 'officer'],
    children: [
      { label: 'Payroll Runs', href: '/payroll/runs', permission: 'payroll.view_runs' },
      { label: 'Pay Periods', href: '/payroll/periods', permission: 'payroll.manage_pay_periods' },
    ],
  },
  {
    label: 'Accounting',
    icon: BookOpen,
    permission: 'chart_of_accounts.view',
    roles: ['officer'],
    children: [
      { label: 'Chart of Accounts', href: '/accounting/accounts', permission: 'chart_of_accounts.view' },
      { label: 'Fiscal Periods', href: '/accounting/fiscal-periods', permission: 'fiscal_periods.view' },
      { label: 'Journal Entries', href: '/accounting/journal-entries', permission: 'journal_entries.view' },
      { label: 'AP Vendors', href: '/accounting/vendors', permission: 'vendors.view' },
      { label: 'AP Invoices', href: '/accounting/ap/invoices', permission: 'vendor_invoices.view' },
      { label: 'AP Due Monitor', href: '/accounting/ap/monitor', permission: 'vendor_invoices.view' },
      { label: 'AR Customers', href: '/ar/customers', permission: 'customers.view' },
      { label: 'AR Invoices', href: '/ar/invoices', permission: 'customer_invoices.view' },
      { label: 'Loan Approvals', href: '/accounting/loans', permission: 'loans.accounting_approve' },
      { label: 'VAT Ledger', href: '/accounting/vat-ledger', permission: 'reports.vat' },
      { label: 'Tax Summary', href: '/accounting/tax-summary', permission: 'reports.vat' },
    ],
  },
  {
    label: 'Financial Reports',
    icon: BarChart3,
    permission: 'reports.financial_statements',
    roles: ['officer', 'executive', 'vice_president'],
    children: [
      { label: 'General Ledger',    href: '/accounting/gl',               permission: 'journal_entries.view' },
      { label: 'Trial Balance',     href: '/accounting/trial-balance',     permission: 'reports.financial_statements' },
      { label: 'Balance Sheet',     href: '/accounting/balance-sheet',     permission: 'reports.financial_statements' },
      { label: 'Income Statement',  href: '/accounting/income-statement',  permission: 'reports.financial_statements' },
      { label: 'Cash Flow',         href: '/accounting/cash-flow',         permission: 'reports.financial_statements' },
    ],
  },
  {
    label: 'Banking',
    icon: Landmark,
    permission: 'bank_accounts.view',
    roles: ['officer'],
    children: [
      { label: 'Bank Accounts', href: '/banking/accounts', permission: 'bank_accounts.view' },
      { label: 'Reconciliations', href: '/banking/reconciliations', permission: 'bank_reconciliations.view' },
    ],
  },
  {
    label: 'Reports',
    icon: BarChart3,
    permission: 'payroll.gov_reports',
    roles: ['manager', 'officer', 'executive', 'vice_president'],
    children: [
      { label: 'Government Reports', href: '/reports/government', permission: 'payroll.gov_reports' },
    ],
  },
  {
    label: 'Executive',
    icon: Shield,
    permission: 'leaves.ga_process',
    roles: ['ga_officer', 'vice_president'],
    children: [
      { label: 'GA Leave Processing', href: '/executive/leave-approvals',    permission: 'leaves.ga_process' },
      { label: 'Overtime Approvals', href: '/executive/overtime-approvals', permission: 'overtime.executive_approve' },
    ],
  },
  {
    label: 'Procurement',
    icon: ShoppingCart,
    permission: 'procurement.purchase-request.view',
    children: [
      { label: 'Purchase Requests', href: '/procurement/purchase-requests', permission: 'procurement.purchase-request.view' },
      { label: 'Purchase Orders',   href: '/procurement/purchase-orders',   permission: 'procurement.purchase-order.view' },
      { label: 'Goods Receipts',    href: '/procurement/goods-receipts',    permission: 'procurement.goods-receipt.view' },
      { label: 'Vendors',           href: '/accounting/vendors',            permission: 'vendors.view' },
    ],
  },
  {
    label: 'Inventory',
    icon: Package,
    permission: 'inventory.items.view',
    children: [
      { label: 'Item Categories',     href: '/inventory/categories',     permission: 'inventory.items.view' },
      { label: 'Item Master',         href: '/inventory/items',          permission: 'inventory.items.view' },
      { label: 'Warehouse Locations', href: '/inventory/locations',       permission: 'inventory.locations.view' },
      { label: 'Stock Balances',      href: '/inventory/stock',           permission: 'inventory.stock.view' },
      { label: 'Stock Ledger',        href: '/inventory/ledger',          permission: 'inventory.stock.view' },
      { label: 'Requisitions',        href: '/inventory/requisitions',    permission: 'inventory.mrq.view' },
    ],
  },
  {
    label: 'Production',
    icon: Factory,
    permission: 'production.orders.view',
    children: [
      { label: 'Bill of Materials',   href: '/production/boms',                 permission: 'production.bom.view' },
      { label: 'Delivery Schedules',  href: '/production/delivery-schedules',   permission: 'production.delivery-schedule.view' },
      { label: 'Work Orders',         href: '/production/orders',               permission: 'production.orders.view' },
    ],
  },
  {
    label: 'QC / QA',
    icon: ClipboardCheck,
    permission: 'qc.inspections.view',
    children: [
      { label: 'Inspections', href: '/qc/inspections', permission: 'qc.inspections.view' },
      { label: 'NCR',         href: '/qc/ncrs',        permission: 'qc.ncr.view' },
      { label: 'Templates',   href: '/qc/templates',   permission: 'qc.templates.view' },
    ],
  },
  {
    label: 'Maintenance',
    icon: Wrench,
    permission: 'maintenance.view',
    children: [
      { label: 'Equipment',   href: '/maintenance/equipment',    permission: 'maintenance.view' },
      { label: 'Work Orders', href: '/maintenance/work-orders',  permission: 'maintenance.view' },
    ],
  },
  {
    label: 'Mold',
    icon: Settings,
    permission: 'mold.view',
    children: [
      { label: 'Mold Masters', href: '/mold/masters', permission: 'mold.view' },
    ],
  },
  {
    label: 'Delivery',
    icon: Truck,
    permission: 'delivery.view',
    children: [
      { label: 'Receipts',  href: '/delivery/receipts',  permission: 'delivery.view' },
      { label: 'Shipments', href: '/delivery/shipments', permission: 'delivery.view' },
    ],
  },
  {
    label: 'ISO / IATF',
    icon: ShieldCheck,
    permission: 'iso.view',
    children: [
      { label: 'Documents', href: '/iso/documents', permission: 'iso.view' },
      { label: 'Audits',    href: '/iso/audits',    permission: 'iso.view' },
    ],
  },
  {
    label: 'VP Approvals',
    icon: ClipboardList,
    permission: 'loans.vp_approve',
    roles: ['vice_president'],
    children: [
      { label: 'Pending Approvals',        href: '/approvals/pending',                     permission: 'loans.vp_approve' },
      { label: 'Purchase Requests',        href: '/procurement/purchase-requests',          permission: 'procurement.purchase-request.view' },
      { label: 'Material Requisitions',    href: '/inventory/requisitions',                permission: 'inventory.mrq.view' },
      { label: 'Loans',                    href: '/approvals/loans',                       permission: 'loans.vp_approve' },
    ],
  },
]

const ADMIN_SECTION: NavSection = {
  label: 'Administration',
  icon: Shield,
  permission: 'system.manage_users',
  children: [
    { label: 'Users', href: '/admin/users', permission: 'system.manage_users' },
    { label: 'System Settings', href: '/admin/settings', permission: 'system.edit_settings' },
    { label: 'Reference Tables', href: '/admin/reference-tables', permission: 'system.edit_settings' },
    { label: 'Audit Logs', href: '/admin/audit-logs', permission: 'system.view_audit_log' },
    { label: 'Backup & Restore', href: '/admin/backup', permission: 'system.manage_backups' },
  ],
}

// Minimalist link styles - Uncodixified
const linkStyle = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-3 px-3 py-2 rounded text-sm transition-colors ${isActive
    ? 'bg-neutral-100 text-neutral-900 font-medium'
    : 'text-neutral-600 hover:text-neutral-900 hover:bg-neutral-50'
  }`

// Compact link for collapsed sidebar
const compactLinkStyle = ({ isActive }: { isActive: boolean }) =>
  `flex items-center justify-center p-2 rounded transition-colors ${isActive
    ? 'bg-neutral-100 text-neutral-900'
    : 'text-neutral-500 hover:text-neutral-900 hover:bg-neutral-50'
  }`

function SectionNav({ section, hasPermission, hasRole }: { section: NavSection; hasPermission: (p: string) => boolean; hasRole: (r: string) => boolean }) {
  const { pathname } = useLocation()
  const Icon = section.icon

  const visibleChildren = section.children.filter(
    (c) => !c.permission || hasPermission(c.permission),
  )

  const isCurrentSection = visibleChildren.some((c) => pathname === c.href || pathname.startsWith(c.href + '/'))
  const [open, setOpen] = useState(isCurrentSection)

  if (section.permission && !hasPermission(section.permission)) return null
  if (section.roles && !hasRole('admin') && !hasRole('super_admin') && !section.roles.some((r) => hasRole(r))) return null
  if (visibleChildren.length === 0) return null

  return (
    <div className="mb-1">
      <button
        onClick={() => setOpen((o) => !o)}
        className="w-full flex items-center justify-between px-3 py-2 text-sm text-neutral-700 hover:text-neutral-900 hover:bg-neutral-50 rounded transition-colors"
      >
        <span className="flex items-center gap-3">
          <Icon className="h-4 w-4 text-neutral-500" />
          {section.label}
        </span>
        {open ? 
          <ChevronDown className="h-3.5 w-3.5 text-neutral-400" /> : 
          <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
        }
      </button>

      {open && (
        <div className="ml-4 mt-0.5 space-y-0.5">
          {visibleChildren.map((child) => (
            <NavLink key={child.href} to={child.href} end={child.end} className={linkStyle}>
              {child.label}
            </NavLink>
          ))}
        </div>
      )}
    </div>
  )
}

// Compact section nav for collapsed sidebar
function CompactSectionNav({ section, hasPermission, hasRole }: { section: NavSection; hasPermission: (p: string) => boolean; hasRole: (r: string) => boolean }) {
  const { pathname } = useLocation()
  const Icon = section.icon
  const [open, setOpen] = useState(false)

  if (section.permission && !hasPermission(section.permission)) return null
  if (section.roles && !hasRole('admin') && !hasRole('super_admin') && !section.roles.some((r) => hasRole(r))) return null

  const visibleChildren = section.children.filter(
    (c) => !c.permission || hasPermission(c.permission),
  )

  const isCurrentSection = visibleChildren.some((c) => pathname === c.href || pathname.startsWith(c.href + '/'))

  if (visibleChildren.length === 0) return null

  return (
    <div className="relative mb-1">
      <button
        onClick={() => setOpen(!open)}
        className={`w-full flex items-center justify-center p-2 rounded-md transition-all duration-150 ${
          isCurrentSection ? 'bg-neutral-100 text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 hover:bg-neutral-50'
        }`}
        title={section.label}
      >
        <Icon className="h-5 w-5" />
      </button>

      {open && (
        <div className="absolute left-full top-0 ml-2 w-52 bg-white rounded border border-neutral-200 shadow-md py-2 z-50">
          <p className="px-3 py-1.5 text-xs font-medium text-neutral-400">{section.label}</p>
          {visibleChildren.map((child) => (
            <NavLink
              key={child.href}
              to={child.href}
              end={child.end}
              className="block px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900"
              onClick={() => setOpen(false)}
            >
              {child.label}
            </NavLink>
          ))}
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// User menu dropdown (top-right)
// ---------------------------------------------------------------------------

const SELF_SERVICE_LINKS = [
  { label: 'My Profile', href: '/me/profile', icon: UserCircle },
  { label: 'My Payslips', href: '/self-service/payslips', icon: FileText },
  { label: 'My Attendance', href: '/me/attendance', icon: Clock },
  { label: 'My Leaves', href: '/me/leaves', icon: Calendar },
  { label: 'My Overtime', href: '/me/overtime', icon: TrendingUp },
  { label: 'My Loans', href: '/me/loans', icon: Wallet },
  { label: 'Change Password', href: '/account/change-password', icon: KeyRound },
]

function UserMenu({ user, onLogout }: { user: { name?: string; email?: string } | null; onLogout: () => void }) {
  const [open, setOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)
  const navigate = useNavigate()

  useEffect(() => {
    if (!open) return
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [open])

  return (
    <div className="relative" ref={menuRef}>
      <button
        onClick={() => setOpen(o => !o)}
        className="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-neutral-100 transition-colors"
      >
        <div className="h-8 w-8 rounded-full bg-neutral-200 text-neutral-700 flex items-center justify-center text-sm font-medium">
          {(user?.name ?? 'U').charAt(0).toUpperCase()}
        </div>
        <div className="text-left hidden sm:block">
          <div className="text-sm font-medium text-neutral-900">{user?.name ?? 'User'}</div>
          <div className="text-xs text-neutral-500">{user?.email}</div>
        </div>
        <ChevronDown className={`h-3.5 w-3.5 text-neutral-400 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-lg border border-neutral-200/80 shadow-lg py-1 z-50">
          <div className="px-4 py-2 border-b border-neutral-100 sm:hidden">
            <div className="text-sm font-medium text-neutral-900">{user?.name}</div>
            <div className="text-xs text-neutral-500">{user?.email}</div>
          </div>

          <div className="py-1">
            <p className="px-4 py-1 text-[10px] font-medium text-neutral-400 uppercase tracking-wider">Self Service</p>
            {SELF_SERVICE_LINKS.map(({ label, href, icon: Icon }) => (
              <button
                key={href}
                onClick={() => { navigate(href); setOpen(false) }}
                className="w-full flex items-center gap-3 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 transition-colors"
              >
                <Icon className="h-4 w-4 text-neutral-400" />
                {label}
              </button>
            ))}
          </div>

          <div className="border-t border-neutral-100 py-1">
            <button
              onClick={onLogout}
              className="w-full flex items-center gap-3 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 hover:text-red-600 transition-colors"
            >
              <LogOut className="h-4 w-4 text-neutral-400" />
              Sign out
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Layout
// ---------------------------------------------------------------------------

export default function AppLayout() {
  const { isAuthenticated, isLoading, user } = useAuth()
  const { clearAuth, hasPermission, hasRole } = useAuthStore()
  const queryClient = useQueryClient()
  
  // Sidebar collapse state - persist in localStorage
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
    const saved = localStorage.getItem('sidebar-collapsed')
    return saved ? JSON.parse(saved) : false
  })
  
  // Hover state for temporary expand
  const [isHoveringSidebar, setIsHoveringSidebar] = useState(false)
  
  // Determine effective width - expand on hover when collapsed
  const isSidebarExpanded = !sidebarCollapsed || isHoveringSidebar

  // Save collapse state
  useEffect(() => {
    localStorage.setItem('sidebar-collapsed', JSON.stringify(sidebarCollapsed))
  }, [sidebarCollapsed])

  useRealtimeEvents(user?.id)

  const handleLogout = async () => {
    try {
      await api.post('/auth/logout')
    } catch {
      // ignore
    }
    disconnectEcho()
    queryClient.clear()
    clearAuth()
  }

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-neutral-50">
        <SkeletonLoader rows={6} />
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return (
    <div className="flex h-screen overflow-hidden bg-neutral-50">
      {/* Desktop Sidebar */}
      <aside
        className="hidden lg:flex flex-shrink-0 bg-white border-r border-neutral-200 flex-col relative z-20 transition-all duration-200"
        style={{ width: isSidebarExpanded ? 260 : 72 }}
        onMouseEnter={() => setIsHoveringSidebar(true)}
        onMouseLeave={() => setIsHoveringSidebar(false)}
      >
        {/* Logo */}
        <div className="h-16 flex items-center px-4 border-b border-neutral-200 flex-shrink-0">
          {!isSidebarExpanded ? (
            <span className="font-semibold text-neutral-900 text-lg mx-auto">
              O
            </span>
          ) : (
            <span className="font-semibold text-neutral-900 text-base whitespace-nowrap">
              Ogami ERP
            </span>
          )}
        </div>

        {/* Navigation */}
        <nav className="flex-1 p-2 space-y-0.5 overflow-y-auto overflow-x-hidden">
          {!isSidebarExpanded ? (
            // Compact navigation (icons only)
            <>
              {TOP_ITEMS.filter((i) => i.permission === null || hasPermission(i.permission)).map(({ label, href, icon: Icon }) => (
                <NavLink
                  key={href}
                  to={href}
                  className={compactLinkStyle}
                  title={label}
                >
                  <Icon className="h-5 w-5" />
                </NavLink>
              ))}
              
              <div className="my-3 border-t border-neutral-200" />
              
              {SECTIONS.map((section) => (
                <CompactSectionNav
                  key={section.label}
                  section={section}
                  hasPermission={hasPermission}
                  hasRole={hasRole}
                />
              ))}

              {(hasPermission('system.manage_users') || hasPermission('system.edit_settings') || hasPermission('system.view_audit_log')) && (
                <>
                  <div className="my-3 border-t border-neutral-200" />
                  <CompactSectionNav section={ADMIN_SECTION} hasPermission={hasPermission} hasRole={hasRole} />
                </>
              )}
            </>
          ) : (
            // Full navigation
            <>
              {TOP_ITEMS.filter((i) => i.permission === null || hasPermission(i.permission)).map(({ label, href, icon: Icon }) => (
                <NavLink key={href} to={href} className={linkStyle}>
                  <Icon className="h-4 w-4 text-neutral-500" />
                  {label}
                </NavLink>
              ))}

              <div className="pt-4 pb-2">
                <p className="px-3 text-xs font-medium text-neutral-400">Modules</p>
              </div>

              {SECTIONS.map((section) => (
                <SectionNav
                  key={section.label}
                  section={section}
                  hasPermission={hasPermission}
                  hasRole={hasRole}
                />
              ))}

              {(hasPermission('system.manage_users') || hasPermission('system.edit_settings') || hasPermission('system.view_audit_log')) && (
                <>
                  <div className="pt-4 pb-2">
                    <p className="px-3 text-xs font-medium text-neutral-400">Administration</p>
                  </div>
                  <SectionNav section={ADMIN_SECTION} hasPermission={hasPermission} hasRole={hasRole} />
                </>
              )}
            </>
          )}
        </nav>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-y-auto flex flex-col min-w-0">
        {/* Top bar */}
        <header className="h-16 flex-shrink-0 bg-white border-b border-neutral-200 flex items-center justify-between px-4 sm:px-6">
          <div className="flex items-center gap-3">
            {/* Mobile hamburger menu */}
            <div className="lg:hidden">
              <Sheet>
                <SheetTrigger asChild>
                  <button
                    className="p-2 -ml-2 rounded-lg text-neutral-600 hover:bg-neutral-100 transition-colors"
                    aria-label="Open menu"
                  >
                    <Menu className="h-5 w-5" />
                  </button>
                </SheetTrigger>
                <SheetContent side="left" className="w-[260px] p-0 bg-white border-r border-neutral-200/80">
                  <SheetHeader className="border-b border-neutral-100 px-4 py-3">
                    <SheetTitle className="text-neutral-900 font-semibold tracking-tight">Ogami ERP</SheetTitle>
                  </SheetHeader>
                  <nav className="p-2 space-y-0.5 overflow-y-auto h-[calc(100vh-60px)]">
                    {TOP_ITEMS.filter((i) => i.permission === null || hasPermission(i.permission)).map(({ label, href, icon: Icon }) => (
                      <NavLink key={href} to={href} className={linkStyle}>
                        <Icon className="h-4 w-4 text-neutral-500" />
                        {label}
                      </NavLink>
                    ))}

                    <div className="pt-4 pb-2">
                      <p className="px-3 text-[10px] font-medium text-neutral-400 uppercase tracking-wider">Modules</p>
                    </div>

                    {SECTIONS.map((section) => (
                      <SectionNav
                        key={section.label}
                        section={section}
                        hasPermission={hasPermission}
                        hasRole={hasRole}
                      />
                    ))}

                    {(hasPermission('system.manage_users') || hasPermission('system.edit_settings') || hasPermission('system.view_audit_log')) && (
                      <>
                        <div className="pt-4 pb-2">
                          <p className="px-3 text-[10px] font-medium text-neutral-400 uppercase tracking-wider">Administration</p>
                        </div>
                        <SectionNav section={ADMIN_SECTION} hasPermission={hasPermission} hasRole={hasRole} />
                      </>
                    )}
                  </nav>
                </SheetContent>
              </Sheet>
            </div>

            {/* Sidebar toggle button - desktop only */}
            <button
              onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
              className="hidden lg:flex p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-colors"
              title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            >
              {sidebarCollapsed ? (
                <PanelLeft className="h-5 w-5" />
              ) : (
                <PanelLeftClose className="h-5 w-5" />
              )}
            </button>
          </div>
          
          <div className="flex items-center gap-2 sm:gap-3">
            <NotificationBell />
            <div className="h-5 w-px bg-neutral-200 hidden sm:block" />
            <UserMenu user={user} onLogout={handleLogout} />
          </div>
        </header>

        {/* Page content */}
        <div className="flex-1 w-full p-5 sm:p-8">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
