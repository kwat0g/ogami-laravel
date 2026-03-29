import { useState, useRef, useEffect } from 'react'
import { Navigate, NavLink, Outlet, useNavigate, useLocation } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'

// Uncodixified: Simple layout without excessive animations
import api from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { useAuthStore } from '@/stores/authStore'
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents'
import { disconnectEcho } from '@/lib/echo'
import { bumpAuthEpoch } from '@/lib/authEpoch'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { getPasswordChangePath } from '@/lib/roleLanding'
import NotificationBell from '@/components/layout/NotificationBell'
import { ColorModeButton } from '@/components/ui/ColorModeToggle'
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import {
  LayoutDashboard,
  Users,
  DollarSign,
  BookOpen,
  BarChart3,
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
  Landmark,
  Building,
  Search,
} from 'lucide-react'

interface NavChild {
  label: string
  href?: string
  permission?: string
  end?: boolean
  /** When true, renders as a non-clickable section header instead of a link. */
  divider?: boolean
  /** Additional path prefixes that keep this section active (e.g. detail pages reachable from here). */
  activePaths?: string[]
}

interface NavSection {
  label: string
  icon: React.ComponentType<{ className?: string }>
  permission?: string
  /** If provided, only users with at least one of these roles (or the admin role) can see this section. */
  roles?: string[]
  /** 
   * If provided, only users from these departments can see this section.
   * This enforces SoD - e.g., Accounting managers don't see Production modules.
   * Use 'ALL' to allow all departments.
   */
  departments?: string[]
  children: NavChild[]
}

const TOP_ITEMS = [
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, permission: null },
]

/**
 * Sidebar Sections - Combined Parent Modules with SoD Enforcement
 * 
 * Sub-modules grouped under parent modules with internal dividers:
 * 1. HR & Payroll (People)
 * 2. Financial Management (Money)
 * 3. Supply Chain (Materials)
 * 4. Production & Quality (Operations)
 * 5. Sales & Delivery (Fulfillment)
 * 6. Compliance & Governance
 */
const SECTIONS: NavSection[] = [
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 1: HR & PAYROLL (People Management)
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'HR & Payroll',
    icon: Users,
    permission: 'hr.full_access',
    roles: ['officer', 'manager', 'head', 'staff', 'executive', 'vice_president'],
    departments: ['HR', 'ACCTG', 'EXEC'],
    children: [
      // ── Human Resources ─────────────────────────────────────────────────────
      { divider: true, label: 'Human Resources' },
      { label: 'Employees', href: '/hr/employees/all', permission: 'hr.full_access' },
      { label: 'Departments', href: '/hr/departments', permission: 'hr.full_access', activePaths: ['/hr/positions', '/hr/shifts'] },
      { label: 'Attendance', href: '/hr/attendance', permission: 'hr.full_access' },
      { label: 'Leave', href: '/hr/leave', permission: 'hr.full_access', end: true },
      { label: 'Overtime', href: '/hr/overtime', permission: 'hr.full_access' },
      { label: 'Loans', href: '/hr/loans', permission: 'hr.full_access' },

      // ── Recruitment ───────────────────────────────────────────────────────
      { divider: true, label: 'Recruitment' },
      { label: 'Recruitment Hub', href: '/hr/recruitment', permission: 'hr.full_access|recruitment.requisitions.view', end: true },

      // ── Payroll ─────────────────────────────────────────────────────────────
      { divider: true, label: 'Payroll' },
      { label: 'Payroll Runs', href: '/payroll/runs', permission: 'payroll.view_runs' },
      { label: 'Government Reports', href: '/reports/government', permission: 'payroll.gov_reports' },
    ],
  },
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 1B: MY TEAM (visible to all dept officers/heads — not HR-dept-gated)
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'My Team',
    icon: Users,
    permission: 'employees.view_team',
    roles: ['officer', 'manager', 'head'],
    departments: ['ALL'],
    children: [
      { label: 'Team Members', href: '/team/employees', permission: 'employees.view_team' },
      { label: 'Team Attendance', href: '/team/attendance', permission: 'attendance.view_team' },
      { label: 'Team Leave', href: '/team/leave', permission: 'leaves.view_team' },
      { label: 'Team Overtime', href: '/team/overtime', permission: 'overtime.view' },
      { label: 'Team Loans', href: '/team/loans', permission: 'loans.view_department' },
      { divider: true, label: 'Hiring' },
      { label: 'Requisitions', href: '/hr/recruitment?tab=requisitions', permission: 'recruitment.requisitions.view|recruitment.requisitions.create' },
      { label: 'My Interviews', href: '/hr/recruitment?tab=interviews', permission: 'recruitment.interviews.evaluate' },
    ],
  },
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 2: FINANCIAL MANAGEMENT
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'Financial Management',
    icon: BookOpen,
    permission: 'chart_of_accounts.view',
    roles: ['officer', 'manager', 'head', 'staff'],
    departments: ['ACCTG', 'SALES', 'PURCH'],
    children: [
      // ── General Ledger ───────────────────────────────────────────────────────
      { divider: true, label: 'General Ledger' },
      { label: 'Chart of Accounts', href: '/accounting/accounts', permission: 'chart_of_accounts.view' },
      { label: 'Journal Entries', href: '/accounting/journal-entries', permission: 'journal_entries.view' },
      // ── Accounts Payable ────────────────────────────────────────────────────
      { divider: true, label: 'Accounts Payable' },
      { label: 'Vendors', href: '/accounting/vendors', permission: 'vendors.view' },
      { label: 'Vendor Invoices', href: '/accounting/ap/invoices', permission: 'vendor_invoices.view' },
      // ── Accounts Receivable ─────────────────────────────────────────────────
      { divider: true, label: 'Accounts Receivable' },
      { label: 'Customers', href: '/ar/customers', permission: 'customers.view' },
      { label: 'Customer Invoices', href: '/ar/invoices', permission: 'customer_invoices.view' },
      // ── Banking ─────────────────────────────────────────────────────────────
      { divider: true, label: 'Banking' },
      { label: 'Bank Accounts', href: '/banking/accounts', permission: 'bank_accounts.view' },
      // ── Budget ───────────────────────────────────────────────────────────────
      { divider: true, label: 'Budget' },
      { label: 'Department Budgets', href: '/budget/department-budgets', permission: 'budget.view' },
      // ── Reports ─────────────────────────────────────────────────────────────
      { divider: true, label: 'Reports' },
      { label: 'Trial Balance', href: '/accounting/trial-balance', permission: 'reports.financial_statements' },
    ],
  },
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 2B: FINANCE APPROVALS (Accounting dept only — budget & loan review)
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'Finance Approvals',
    icon: ClipboardCheck,
    permission: 'procurement.purchase-request.budget-check',
    roles: ['officer', 'manager', 'head'],
    departments: ['ACCTG'],
    children: [
      { label: 'Budget Verification', href: '/approvals/budget-verification', permission: 'procurement.purchase-request.budget-check' },
      { label: 'Loan Review', href: '/accounting/loans', permission: 'loans.accounting_approve' },
    ],
  },
  // Budget & Assets section removed — Department Budgets moved to Financial Management
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 3: SUPPLY CHAIN
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'Supply Chain',
    icon: Package,
    permission: 'procurement.purchase-request.view|inventory.items.view|production.orders.view',
    roles: ['officer', 'manager', 'head', 'staff', 'executive', 'vice_president'],
    departments: ['PURCH', 'PPC', 'PROD', 'PLANT', 'WH', 'SALES'],
    children: [
      // ── Procurement ─────────────────────────────────────────────────────────
      { divider: true, label: 'Procurement' },
      { label: 'Purchase Requests', href: '/procurement/purchase-requests', permission: 'procurement.purchase-request.view' },
      { label: 'Purchase Orders', href: '/procurement/purchase-orders', permission: 'procurement.purchase-order.view' },
      { label: 'Goods Receipts', href: '/procurement/goods-receipts', permission: 'procurement.goods-receipt.view' },
      // ── Inventory ────────────────────────────────────────────────────────────
      { divider: true, label: 'Inventory' },
      { label: 'Item Master', href: '/inventory/items', permission: 'inventory.items.view' },
      { label: 'Stock Balances', href: '/inventory/stock', permission: 'inventory.stock.view' },
      { label: 'Material Requisitions', href: '/inventory/requisitions', permission: 'inventory.mrq.view' },
    ],
  },
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 4: PRODUCTION & QUALITY
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'Production & Quality',
    icon: Factory,
    permission: 'production.orders.view',
    roles: ['officer', 'manager', 'head', 'staff', 'executive', 'vice_president'],
    departments: ['PROD', 'PLANT', 'PPC', 'QC', 'MAINT', 'MOLD'],
    children: [
      // ── Production ──────────────────────────────────────────────────────────
      { divider: true, label: 'Production' },
      { label: 'Production Orders', href: '/production/orders', permission: 'production.orders.view' },
      { label: 'Bill of Materials', href: '/production/boms', permission: 'production.bom.view' },
      { label: 'Delivery Schedules', href: '/production/delivery-schedules', permission: 'production.delivery-schedule.view' },
      // ── Quality Control ─────────────────────────────────────────────────────
      { divider: true, label: 'Quality Control' },
      { label: 'Inspections', href: '/qc/inspections', permission: 'qc.inspections.view' },
      // ── Maintenance ─────────────────────────────────────────────────────────
      { divider: true, label: 'Maintenance' },
      { label: 'Equipment', href: '/maintenance/equipment', permission: 'maintenance.view' },
      { label: 'Maintenance Work Orders', href: '/maintenance/work-orders', permission: 'maintenance.view' },
    ],
  },
  // ═════════════════════════════════════════════════════════════════════════════
  // SECTION 5: CRM & DELIVERY
  // ═════════════════════════════════════════════════════════════════════════════
  {
    label: 'CRM & Delivery',
    icon: Truck,
    permission: 'customers.view',
    roles: ['officer', 'manager', 'head', 'staff', 'executive', 'vice_president'],
    departments: ['SALES', 'WH', 'PROD', 'PLANT'],
    children: [
      { divider: true, label: 'CRM' },
      { label: 'Support Tickets', href: '/crm/tickets', permission: 'crm.tickets.view' },
      { divider: true, label: 'Sales' },
      { label: 'Client Orders', href: '/sales/client-orders', permission: 'sales.order_review' },
      { label: 'Price Quotations', href: '/sales/quotations', permission: 'sales.quotations.view' },
      { label: 'Order Processing', href: '/sales/orders', permission: 'sales.orders.view' },
      { divider: true, label: 'Delivery' },
      { label: 'Delivery Receipts', href: '/delivery/receipts', permission: 'delivery.view' },
      { label: 'Shipments', href: '/delivery/shipments', permission: 'delivery.view' },
      { label: 'Route Planning', href: '/delivery/routes', permission: 'delivery.routes.view' },
    ],
  },
  // Note: Executive users have a separate default dashboard at /approvals/pending
  // defined in EXECUTIVE_SECTION below - not mixed with operational modules
]

// ═════════════════════════════════════════════════════════════════════════════
// EXECUTIVE DASHBOARD SECTION - Default landing for VP/Executive users
// ═════════════════════════════════════════════════════════════════════════════
const EXECUTIVE_SECTION: NavSection = {
  label: 'Executive',
  icon: LayoutDashboard,
  permission: undefined,
  roles: ['vice_president', 'executive', 'super_admin'],
  departments: ['ALL'],
  children: [
    // ── Approvals ────────────────────────────────────────────────────────────
    { divider: true, label: 'Approvals' },
    { label: 'Pending Approvals', href: '/approvals/pending', permission: 'procurement.purchase-request.view', activePaths: ['/procurement/purchase-requests', '/hr/loans', '/inventory/requisitions', '/payroll/runs'] },
    // ── Financial Reports (exec read-only view) ───────────────────────────────
    { divider: true, label: 'Reports' },
    { label: 'Trial Balance', href: '/accounting/trial-balance', permission: 'reports.financial_statements' },
    { label: 'Financial Ratios', href: '/accounting/financial-ratios', permission: 'reports.financial_statements' },
    { divider: true, label: 'Analytics' },
    { label: 'Executive Analytics', href: '/dashboard/executive-analytics', permission: 'reports.financial_statements' },
  ],
}

// ═════════════════════════════════════════════════════════════════════════════
// ADMINISTRATION SECTION - System Configuration
// ═════════════════════════════════════════════════════════════════════════════
const ADMIN_SECTION: NavSection = {
  label: 'Administration',
  icon: Shield,
  permission: 'system.manage_users',
  roles: ['admin', 'super_admin', 'executive', 'vice_president'],
  departments: ['IT', 'EXEC'],
  children: [
    { label: 'Users', href: '/admin/users', permission: 'system.manage_users' },
    { label: 'System Settings', href: '/admin/settings', permission: 'system.edit_settings' },
    { label: 'Reference Tables', href: '/admin/reference-tables', permission: 'system.edit_settings' },
    { label: 'Fiscal Periods', href: '/accounting/fiscal-periods', permission: 'system.edit_settings' },
    { label: 'Item Categories', href: '/inventory/categories', permission: 'system.edit_settings' },
    { label: 'Audit Logs', href: '/admin/audit-logs', permission: 'system.view_audit_log' },
    { label: 'Backup', href: '/admin/backup', permission: 'system.manage_backups' },
  ],
}

// Minimalist link styles - Uncodixified
const linkStyle = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-2.5 px-2.5 py-1.5 rounded text-sm transition-colors ${isActive
    ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-medium border-l-2 border-neutral-900 dark:border-neutral-300 -ml-[2px]'
    : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 hover:bg-neutral-50 dark:hover:bg-neutral-800 border-l-2 border-transparent'
  }`

// Compact link for collapsed sidebar
const compactLinkStyle = ({ isActive }: { isActive: boolean }) =>
  `flex items-center justify-center p-1.5 rounded transition-colors ${isActive
    ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100'
    : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 hover:bg-neutral-50 dark:hover:bg-neutral-800'
  }`

function SectionNav({ section, hasPermission, hasRole, userDept }: { section: NavSection; hasPermission: (p: string) => boolean; hasRole: (r: string) => boolean; userDept: string | null }) {
  const { pathname } = useLocation()
  const Icon = section.icon
  const isInitialMount = useRef(true)

  const withPermission = section.children.filter(
    (c) => c.divider || !c.permission || hasPermission(c.permission),
  )
  // Remove dividers that have no visible link items before the next divider (orphaned headers)
  const visibleChildren = withPermission.filter((child, index) => {
    if (!child.divider) return true
    for (let i = index + 1; i < withPermission.length; i++) {
      if (!withPermission[i].divider) return true
      break
    }
    return false
  })

  const isCurrentSection = visibleChildren
    .filter((c) => !c.divider)
    .some((c) =>
      pathname === c.href ||
      pathname.startsWith(c.href! + '/') ||
      (c.activePaths ?? []).some((p) => pathname === p || pathname.startsWith(p + '/')),
    )
  const [open, setOpen] = useState(isCurrentSection)

  // Sync open state with current route — open when entering, close when leaving
  useEffect(() => {
    if (isInitialMount.current) {
      isInitialMount.current = false
      return
    }
    setOpen(isCurrentSection)
  }, [isCurrentSection])

  // Permission check
  if (section.permission && !hasPermission(section.permission)) return null
  
  // Role check
  if (section.roles && !hasRole('super_admin') && !section.roles.some((r) => hasRole(r))) return null
  
  // Department check (SoD enforcement) - FAIL CLOSED
  // super_admin and admin bypass all dept checks; executive/vice_president are restricted to their own dept
  if (section.departments && !hasRole('super_admin') && !hasRole('admin')) {
    // Allow if explicitly ALL departments
    if (section.departments[0] === 'ALL') {
      // Continue to show
    }
    // Hide if no user department (fail closed for security)
    else if (!userDept) {
      return null
    }
    // Hide if user's department not in allowed list
    else if (!section.departments.includes(userDept)) {
      return null
    }
  }

  if (visibleChildren.length === 0) return null

  return (
    <div className="mb-0.5">
      <button
        onClick={() => setOpen((o) => !o)}
        className={`w-full flex items-center justify-between px-2.5 py-1.5 text-sm rounded transition-colors ${
          isCurrentSection
            ? 'bg-neutral-100 text-neutral-900 font-medium'
            : 'text-neutral-700 hover:text-neutral-900 hover:bg-neutral-50'
        }`}
      >
        <span className="flex items-center gap-3">
          <Icon className={`h-4 w-4 ${isCurrentSection ? 'text-neutral-700' : 'text-neutral-500'}`} />
          {section.label}
        </span>
        {open ? 
          <ChevronDown className="h-3.5 w-3.5 text-neutral-500" /> : 
          <ChevronRight className="h-3.5 w-3.5 text-neutral-400" />
        }
      </button>

      {open && (
        <div className="relative ml-4 mt-0.5 pl-3 border-l-2 border-neutral-200">
          {visibleChildren.map((child) =>
            child.divider ? (
              <p key={child.label} className="px-2.5 pt-2.5 pb-0.5 text-[10px] font-semibold text-neutral-400 uppercase tracking-wider">
                {child.label}
              </p>
            ) : (
              <NavLink key={child.href} to={child.href!} end={child.end} className={linkStyle}>
                {child.label}
              </NavLink>
            )
          )}
        </div>
      )}
    </div>
  )
}

// Compact section nav for collapsed sidebar
function CompactSectionNav({ section, hasPermission, hasRole, userDept }: { section: NavSection; hasPermission: (p: string) => boolean; hasRole: (r: string) => boolean; userDept: string | null }) {
  const { pathname } = useLocation()
  const Icon = section.icon
  const [open, setOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)

  // Close on outside click
  useEffect(() => {
    if (!open) return
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [open])

  // Permission check
  if (section.permission && !hasPermission(section.permission)) return null
  
  // Role check
  if (section.roles && !hasRole('super_admin') && !section.roles.some((r) => hasRole(r))) return null
  
  // Department check (SoD enforcement) - FAIL CLOSED
  if (section.departments && !hasRole('super_admin') && !hasRole('admin')) {
    // Allow if explicitly ALL departments
    if (section.departments[0] === 'ALL') {
      // Continue to show
    }
    // Hide if no user department (fail closed for security)
    else if (!userDept) {
      return null
    }
    // Hide if user's department not in allowed list
    else if (!section.departments.includes(userDept)) {
      return null
    }
  }

  const withPermission2 = section.children.filter(
    (c) => c.divider || !c.permission || hasPermission(c.permission),
  )
  const visibleChildren = withPermission2.filter((child, index) => {
    if (!child.divider) return true
    for (let i = index + 1; i < withPermission2.length; i++) {
      if (!withPermission2[i].divider) return true
      break
    }
    return false
  })

  const linkChildren = visibleChildren.filter((c) => !c.divider)
  const isCurrentSection = linkChildren.some((c) =>
    pathname === c.href ||
    pathname.startsWith(c.href! + '/') ||
    (c.activePaths ?? []).some((p) => pathname === p || pathname.startsWith(p + '/')),
  )

  if (linkChildren.length === 0) return null

  return (
    <div className="relative mb-1" ref={containerRef}>
      <button
        onClick={() => setOpen((o) => !o)}
        className={`w-full flex items-center justify-center p-2 rounded-md transition-all duration-150 ${
          isCurrentSection ? 'bg-neutral-100 text-neutral-900' : 'text-neutral-500 hover:text-neutral-900 hover:bg-neutral-50'
        }`}
        title={section.label}
      >
        <Icon className="h-5 w-5" />
      </button>

      {open && (
        <div className="absolute left-full top-0 ml-2 w-52 bg-white rounded border border-neutral-200 shadow-md py-1 z-50">
          <p className="px-3 py-2 text-xs font-semibold text-neutral-500 border-b border-neutral-100">{section.label}</p>
          {visibleChildren.map((child) =>
            child.divider ? (
              <p key={child.label} className="px-3 pt-2 pb-0.5 text-[10px] font-semibold text-neutral-400 uppercase tracking-wider">
                {child.label}
              </p>
            ) : (
              <NavLink
                key={child.href}
                to={child.href!}
                end={child.end}
                className="block px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900"
                onClick={() => setOpen(false)}
              >
                {child.label}
              </NavLink>
            )
          )}
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// User menu dropdown (top-right)
// ---------------------------------------------------------------------------

const SELF_SERVICE_LINKS: Array<{ label: string; href: string; icon: typeof UserCircle; permission?: string }> = [
  { label: 'My Profile', href: '/me/profile', icon: UserCircle, permission: 'self.view_profile' },
  { label: 'My Payslips', href: '/self-service/payslips', icon: FileText, permission: 'payroll.view_own_payslip' },
  { label: 'Time Clock', href: '/me/time-clock', icon: Clock, permission: 'self.view_attendance' },
  { label: 'My Attendance', href: '/me/attendance', icon: Clock, permission: 'self.view_attendance' },
  { label: 'My Leaves', href: '/me/leaves', icon: Calendar, permission: 'leaves.view_own' },
  { label: 'My Overtime', href: '/me/overtime', icon: TrendingUp, permission: 'overtime.view' },
  { label: 'My Loans', href: '/me/loans', icon: Wallet, permission: 'loans.view_own' },
  { label: 'Change Password', href: '/account/change-password', icon: KeyRound },
]

function UserMenu({
  user,
  onLogout,
  hasPermission,
}: {
  user: { name?: string; email?: string } | null
  onLogout: () => void
  hasPermission: (permission: string) => boolean
}) {
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

  // Auto-close on mouse leave with a small delay (prevents flicker)
  const hoverTimeout = useRef<ReturnType<typeof setTimeout> | null>(null)

  const handleMouseEnter = () => {
    if (hoverTimeout.current) clearTimeout(hoverTimeout.current)
    setOpen(true)
  }

  const handleMouseLeave = () => {
    hoverTimeout.current = setTimeout(() => setOpen(false), 200)
  }

  return (
    <div className="relative" ref={menuRef} onMouseEnter={handleMouseEnter} onMouseLeave={handleMouseLeave}>
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
            {SELF_SERVICE_LINKS.filter((item) => !item.permission || hasPermission(item.permission)).map(({ label, href, icon: Icon }) => (
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
  const location = useLocation()
  const appNavigate = useNavigate()
  const { clearAuth, hasPermission, hasRole } = useAuthStore()
  
  // Get user's primary department code for SoD filtering
  const userDept = user?.primary_department_code ?? null
  
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

  // Cmd+K / Ctrl+K keyboard shortcut for global search
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault()
        appNavigate('/search')
      }
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [appNavigate])

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
    return (
      <div className="min-h-screen flex items-center justify-center bg-neutral-50">
        <SkeletonLoader rows={6} />
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (user?.must_change_password) {
    const passwordPath = getPasswordChangePath(user)
    if (location.pathname !== passwordPath) {
      return <Navigate to={passwordPath} replace />
    }
  }

  return (
    <div className="flex h-screen overflow-hidden bg-neutral-50 dark:bg-neutral-950">

      {/* Desktop Sidebar */}
      <aside
        className="hidden lg:flex flex-shrink-0 bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800 flex-col relative z-20 transition-all duration-200"
        style={{ width: isSidebarExpanded ? 230 : 64 }}
        onMouseEnter={() => setIsHoveringSidebar(true)}
        onMouseLeave={() => setIsHoveringSidebar(false)}
      >
        {/* Logo */}
        <div className="h-14 flex items-center px-3 border-b border-neutral-200 dark:border-neutral-800 flex-shrink-0">
          {!isSidebarExpanded ? (
            <span className="font-semibold text-neutral-900 dark:text-neutral-100 text-lg mx-auto">
              O
            </span>
          ) : (
            <span className="font-semibold text-neutral-900 dark:text-neutral-100 text-base whitespace-nowrap">
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
              
              {/* Executive Dashboard for VP/Executive users */}
              {(hasRole('vice_president') || hasRole('executive')) && (
                <CompactSectionNav 
                  section={EXECUTIVE_SECTION} 
                  hasPermission={hasPermission} 
                  hasRole={hasRole} 
                  userDept={userDept} 
                />
              )}
              
              {SECTIONS.map((section) => (
                <CompactSectionNav
                  key={section.label}
                  section={section}
                  hasPermission={hasPermission}
                  hasRole={hasRole}
                  userDept={userDept}
                />
              ))}

              {(hasPermission('system.manage_users') || hasPermission('system.edit_settings') || hasPermission('system.view_audit_log')) && (
                <>
                  <div className="my-3 border-t border-neutral-200" />
                  <CompactSectionNav section={ADMIN_SECTION} hasPermission={hasPermission} hasRole={hasRole} userDept={userDept} />
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

              {/* Executive Dashboard - Primary for VP/Executive users */}
              {(hasRole('vice_president') || hasRole('executive')) && (
                <SectionNav 
                  section={EXECUTIVE_SECTION} 
                  hasPermission={hasPermission} 
                  hasRole={hasRole} 
                  userDept={userDept} 
                />
              )}

              {!hasRole('vice_president') && !hasRole('executive') && (
                <div className="pt-4 pb-2 flex items-center gap-2">
                  <div className="h-px flex-1 bg-neutral-200" />
                  <p className="px-2 text-[11px] font-medium text-neutral-400 uppercase tracking-wider">Modules</p>
                  <div className="h-px flex-1 bg-neutral-200" />
                </div>
              )}

              {SECTIONS.map((section) => (
                <SectionNav
                  key={section.label}
                  section={section}
                  hasPermission={hasPermission}
                  hasRole={hasRole}
                  userDept={userDept}
                />
              ))}

              {(hasPermission('system.manage_users') || hasPermission('system.edit_settings') || hasPermission('system.view_audit_log')) && (
                <>
                  <div className="pt-4 pb-2 flex items-center gap-2">
                    <div className="h-px flex-1 bg-neutral-200" />
                    <p className="px-2 text-[11px] font-medium text-neutral-400 uppercase tracking-wider">Administration</p>
                    <div className="h-px flex-1 bg-neutral-200" />
                  </div>
                  <SectionNav section={ADMIN_SECTION} hasPermission={hasPermission} hasRole={hasRole} userDept={userDept} />
                </>
              )}
            </>
          )}
        </nav>
      </aside>

      {/* Main Content */}
      <main className="flex-1 overflow-y-auto flex flex-col min-w-0">
        {/* Top bar — sticky so it stays visible when scrolling page content */}
        <header className="h-14 flex-shrink-0 sticky top-0 z-30 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between px-4 sm:px-6">
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
                <SheetContent side="left" className="w-[230px] p-0 bg-white border-r border-neutral-200/80">
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

                      {/* Executive Section - shown for VP/Executive on mobile */}
                    {(hasRole('vice_president') || hasRole('executive')) && (
                      <SectionNav
                        section={EXECUTIVE_SECTION}
                        hasPermission={hasPermission}
                        hasRole={hasRole}
                        userDept={userDept}
                      />
                    )}

                    <div className="pt-4 pb-2">
                      <p className="px-3 text-[10px] font-medium text-neutral-400 uppercase tracking-wider">Modules</p>
                    </div>

                    {SECTIONS.map((section) => (
                      <SectionNav
                        key={section.label}
                        section={section}
                        hasPermission={hasPermission}
                        hasRole={hasRole}
                        userDept={userDept}
                      />
                    ))}

                    {(hasPermission('system.manage_users') || hasPermission('system.edit_settings') || hasPermission('system.view_audit_log')) && (
                      <>
                        <div className="pt-4 pb-2">
                          <p className="px-3 text-[10px] font-medium text-neutral-400 uppercase tracking-wider">Administration</p>
                        </div>
                        <SectionNav section={ADMIN_SECTION} hasPermission={hasPermission} hasRole={hasRole} userDept={userDept} />
                      </>
                    )}
                  </nav>
                </SheetContent>
              </Sheet>
            </div>

            {/* Sidebar toggle button - desktop only */}
            <button
              onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
              className="hidden lg:flex p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 hover:border-neutral-300 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
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
            <NavLink
              to="/search"
              className="hidden sm:flex items-center gap-2 px-3 py-1.5 text-sm text-neutral-500 dark:text-neutral-400 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 hover:border-neutral-300 transition-colors"
              title="Search (Ctrl+K)"
            >
              <Search className="h-4 w-4" />
              <span className="text-xs">Search</span>
              <kbd className="hidden md:inline text-[10px] font-mono bg-neutral-200 text-neutral-500 px-1.5 py-0.5 rounded">⌘K</kbd>
            </NavLink>
            <ColorModeButton />
            <NotificationBell />
            <div className="h-5 w-px bg-neutral-200 dark:bg-neutral-700 hidden sm:block" />
            <UserMenu user={user} onLogout={handleLogout} hasPermission={hasPermission} />
          </div>
        </header>

        {/* Page content */}
        <div className="flex-1 w-full p-4 sm:p-6 dark:text-neutral-100">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
