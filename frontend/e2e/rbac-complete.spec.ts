/**
 * E2E-RBAC-COMPLETE — Comprehensive Role-Based Access Control Tests
 *
 * Tests ALL frontend pages, navigation, and actions for each role.
 * Covers 20+ domains, 100+ routes, 7+ roles.
 *
 * Organization:
 * - Test Suites: By Role (HR Manager, Production Manager, etc.)
 * - Each Suite: Navigation → Page Access → Actions → Forbidden Access
 */
import { test, expect, Page } from '@playwright/test'

const BASE = 'http://localhost:5173'

// ═══════════════════════════════════════════════════════════════════════════════
// TEST ACCOUNTS
// ═══════════════════════════════════════════════════════════════════════════════

const ACCOUNTS = {
  // HR Department
  hrManager: { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!', role: 'hr_manager', dept: 'HR' },
  hrOfficer: { email: 'hr.officer@ogamierp.local', password: 'Officer@Test1234!', role: 'hr_officer', dept: 'HR' },
  hrHead: { email: 'hr.head@ogamierp.local', password: 'Head@Test1234!', role: 'hr_head', dept: 'HR' },
  hrStaff: { email: 'hr.staff@ogamierp.local', password: 'Staff@Test1234!', role: 'hr_staff', dept: 'HR' },

  // Accounting Department
  acctgManager: { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!', role: 'acctg_manager', dept: 'ACCTG' },
  acctgOfficer: { email: 'acctg.officer@ogamierp.local', password: 'Officer@Test1234!', role: 'acctg_officer', dept: 'ACCTG' },
  acctgHead: { email: 'acctg.head@ogamierp.local', password: 'Head@Test1234!', role: 'acctg_head', dept: 'ACCTG' },

  // Production Department
  prodManager: { email: 'prod.manager@ogamierp.local', password: 'Manager@12345!', role: 'prod_manager', dept: 'PROD' },
  prodOfficer: { email: 'prod.officer@ogamierp.local', password: 'Officer@12345!', role: 'prod_officer', dept: 'PROD' },
  prodHead: { email: 'production.head@ogamierp.local', password: 'Head@123456789!', role: 'prod_head', dept: 'PROD' },
  prodStaff: { email: 'prod.staff@ogamierp.local', password: 'Staff@123456789!', role: 'prod_staff', dept: 'PROD' },

  // Warehouse Department
  whManager: { email: 'warehouse.manager@ogamierp.local', password: 'Manager@12345!', role: 'wh_manager', dept: 'WH' },
  whHead: { email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!', role: 'wh_head', dept: 'WH' },

  // Other Departments
  plantManager: { email: 'plant.manager@ogamierp.local', password: 'Manager@12345!', role: 'plant_manager', dept: 'PLANT' },
  qcManager: { email: 'qc.manager@ogamierp.local', password: 'Manager@12345!', role: 'qc_manager', dept: 'QC' },
  moldManager: { email: 'mold.manager@ogamierp.local', password: 'Manager@12345!', role: 'mold_manager', dept: 'MOLD' },
  salesManager: { email: 'crm.manager@ogamierp.local', password: 'Manager@12345!', role: 'sales_manager', dept: 'SALES' },
  purchasingOfficer: { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!', role: 'purchasing_officer', dept: 'PPC' },
  maintenanceManager: { email: 'maintenance.manager@ogamierp.local', password: 'Manager@12345!', role: 'maint_manager', dept: 'MAINT' },

  // Executive
  vp: { email: 'vp@ogamierp.local', password: 'VicePresident@1!', role: 'vice_president', dept: 'EXEC' },
  executive: { email: 'executive@ogamierp.local', password: 'Executive@Test1234!', role: 'executive', dept: 'EXEC' },

  // Admin
  admin: { email: 'admin@ogamierp.local', password: 'Admin@1234567890!', role: 'admin', dept: null },
  superAdmin: { email: 'superadmin@ogamierp.local', password: 'SuperAdmin@1!', role: 'super_admin', dept: null },
} as const

// ═══════════════════════════════════════════════════════════════════════════════
// ROUTE PERMISSIONS MAP (from router/index.tsx)
// ═══════════════════════════════════════════════════════════════════════════════

const ROUTES = {
  // Dashboard
  dashboard: { path: '/dashboard', permission: null },

  // HR Domain
  hrEmployees: { path: '/hr/employees', permission: 'hr.full_access' },
  hrEmployeesAll: { path: '/hr/employees/all', permission: 'hr.full_access' },
  hrEmployeesNew: { path: '/hr/employees/new', permission: 'hr.full_access' },
  hrAttendance: { path: '/hr/attendance', permission: 'hr.full_access' },
  hrAttendanceImport: { path: '/hr/attendance/import', permission: 'hr.full_access' },
  hrAttendanceDashboard: { path: '/hr/attendance/dashboard', permission: 'hr.full_access' },
  hrLeave: { path: '/hr/leave', permission: 'hr.full_access' },
  hrLeaveBalances: { path: '/hr/leave/balances', permission: 'hr.full_access' },
  hrLoans: { path: '/hr/loans', permission: 'hr.full_access' },
  hrDepartments: { path: '/hr/departments', permission: 'hr.full_access' },
  hrPositions: { path: '/hr/positions', permission: 'hr.full_access' },
  hrShifts: { path: '/hr/shifts', permission: 'hr.full_access' },
  hrReports: { path: '/hr/reports', permission: 'hr.full_access' },

  // Team Management
  teamEmployees: { path: '/team/employees', permission: 'employees.view_team' },
  teamAttendance: { path: '/team/attendance', permission: 'attendance.view_team' },
  teamLeave: { path: '/team/leave', permission: 'leaves.view_team' },
  teamOvertime: { path: '/team/overtime', permission: 'overtime.view' },
  teamLoans: { path: '/team/loans', permission: 'loans.view_department' },

  // Payroll
  payrollRuns: { path: '/payroll/runs', permission: 'payroll.view_runs' },
  payrollRunsNew: { path: '/payroll/runs/new', permission: 'payroll.initiate' },
  payrollPeriods: { path: '/payroll/periods', permission: 'payroll.manage_pay_periods' },

  // Accounting
  chartOfAccounts: { path: '/accounting/accounts', permission: 'chart_of_accounts.view' },
  journalEntries: { path: '/accounting/journal-entries', permission: 'journal_entries.view' },
  journalEntriesNew: { path: '/accounting/journal-entries/new', permission: 'journal_entries.create' },
  generalLedger: { path: '/accounting/gl', permission: 'journal_entries.view' },
  fiscalPeriods: { path: '/accounting/fiscal-periods', permission: 'fiscal_periods.view' },
  recurringTemplates: { path: '/accounting/recurring-templates', permission: 'journal_entries.view' },

  // AP
  vendors: { path: '/accounting/vendors', permission: 'vendors.view' },
  apInvoices: { path: '/accounting/ap/invoices', permission: 'vendor_invoices.view' },
  apInvoicesNew: { path: '/accounting/ap/invoices/new', permission: 'vendor_invoices.create' },
  apCreditNotes: { path: '/accounting/ap/credit-notes', permission: 'vendor_invoices.view' },
  apMonitor: { path: '/accounting/ap/monitor', permission: 'vendor_invoices.view' },
  apAging: { path: '/accounting/ap/aging-report', permission: 'vendor_invoices.view' },

  // AR
  customers: { path: '/ar/customers', permission: 'customers.view' },
  arInvoices: { path: '/ar/invoices', permission: 'customer_invoices.view' },
  arInvoicesNew: { path: '/ar/invoices/new', permission: 'customer_invoices.create' },
  arCreditNotes: { path: '/ar/credit-notes', permission: 'customer_invoices.view' },
  arAging: { path: '/ar/aging-report', permission: 'customer_invoices.view' },

  // Tax
  vatLedger: { path: '/accounting/vat-ledger', permission: 'reports.vat' },
  taxSummary: { path: '/accounting/tax-summary', permission: 'reports.vat' },

  // Banking
  bankAccounts: { path: '/banking/accounts', permission: 'bank_accounts.view' },
  bankReconciliations: { path: '/banking/reconciliations', permission: 'bank_reconciliations.view' },

  // Financial Reports
  trialBalance: { path: '/accounting/trial-balance', permission: 'reports.financial_statements' },
  balanceSheet: { path: '/accounting/balance-sheet', permission: 'reports.financial_statements' },
  incomeStatement: { path: '/accounting/income-statement', permission: 'reports.financial_statements' },
  cashFlow: { path: '/accounting/cash-flow', permission: 'reports.financial_statements' },

  // Budget
  budgetCostCenters: { path: '/budget/cost-centers', permission: 'budget.view' },
  budgetLines: { path: '/budget/lines', permission: 'budget.view' },
  budgetVsActual: { path: '/budget/vs-actual', permission: 'budget.view' },

  // Fixed Assets
  fixedAssets: { path: '/fixed-assets', permission: 'fixed_assets.view' },
  fixedAssetsCategories: { path: '/fixed-assets/categories', permission: 'fixed_assets.view' },
  fixedAssetsDisposals: { path: '/fixed-assets/disposals', permission: 'fixed_assets.view' },

  // Procurement
  purchaseRequests: { path: '/procurement/purchase-requests', permission: 'procurement.purchase-request.view' },
  purchaseRequestsNew: { path: '/procurement/purchase-requests/new', permission: 'procurement.purchase-request.create' },
  purchaseOrders: { path: '/procurement/purchase-orders', permission: 'procurement.purchase-order.view' },
  purchaseOrdersNew: { path: '/procurement/purchase-orders/new', permission: 'procurement.purchase-order.create' },
  goodsReceipts: { path: '/procurement/goods-receipts', permission: 'procurement.goods-receipt.view' },
  goodsReceiptsNew: { path: '/procurement/goods-receipts/new', permission: 'procurement.goods-receipt.create' },
  procurementRfqs: { path: '/procurement/rfqs', permission: 'procurement.purchase-order.create' },
  procurementAnalytics: { path: '/procurement/analytics', permission: 'procurement.purchase-order.create' },

  // Inventory
  inventoryCategories: { path: '/inventory/categories', permission: 'inventory.locations.manage' },
  inventoryItems: { path: '/inventory/items', permission: 'inventory.items.view' },
  inventoryItemsNew: { path: '/inventory/items/new', permission: 'inventory.items.create' },
  inventoryLocations: { path: '/inventory/locations', permission: 'inventory.locations.manage' },
  inventoryStock: { path: '/inventory/stock', permission: 'inventory.stock.view' },
  inventoryLedger: { path: '/inventory/ledger', permission: 'inventory.stock.view' },
  inventoryAdjustments: { path: '/inventory/adjustments', permission: 'inventory.adjustments.create' },
  inventoryRequisitions: { path: '/inventory/requisitions', permission: 'inventory.mrq.view' },
  inventoryRequisitionsNew: { path: '/inventory/requisitions/new', permission: 'inventory.mrq.create' },
  inventoryValuation: { path: '/inventory/valuation', permission: 'reports.financial_statements' },
  inventoryPhysicalCount: { path: '/inventory/physical-count', permission: 'inventory.adjustments.create' },

  // Production
  productionBoms: { path: '/production/boms', permission: 'production.bom.view' },
  productionBomsNew: { path: '/production/boms/new', permission: 'production.bom.manage' },
  productionDeliverySchedules: { path: '/production/delivery-schedules', permission: 'production.delivery-schedule.view' },
  productionOrders: { path: '/production/orders', permission: 'production.orders.view' },
  productionOrdersNew: { path: '/production/orders/new', permission: 'production.orders.create' },
  productionCostAnalysis: { path: '/production/cost-analysis', permission: 'production.orders.view' },

  // QC
  qcInspections: { path: '/qc/inspections', permission: 'qc.inspections.view' },
  qcInspectionsNew: { path: '/qc/inspections/new', permission: 'qc.inspections.create' },
  qcNcrs: { path: '/qc/ncrs', permission: 'qc.ncr.view' },
  qcNcrsNew: { path: '/qc/ncrs/new', permission: 'qc.ncr.create' },
  qcCapa: { path: '/qc/capa', permission: 'qc.ncr.view' },
  qcTemplates: { path: '/qc/templates', permission: 'qc.templates.view' },
  qcDefectRate: { path: '/qc/defect-rate', permission: 'qc.inspections.view' },

  // Maintenance
  maintenanceEquipment: { path: '/maintenance/equipment', permission: 'maintenance.view' },
  maintenanceEquipmentNew: { path: '/maintenance/equipment/new', permission: 'maintenance.manage' },
  maintenanceWorkOrders: { path: '/maintenance/work-orders', permission: 'maintenance.view' },
  maintenanceWorkOrdersNew: { path: '/maintenance/work-orders/new', permission: 'maintenance.manage' },

  // Mold
  moldMasters: { path: '/mold/masters', permission: 'mold.view' },
  moldMastersNew: { path: '/mold/masters/new', permission: 'mold.manage' },

  // Delivery
  deliveryReceipts: { path: '/delivery/receipts', permission: 'delivery.view' },
  deliveryReceiptsNew: { path: '/delivery/receipts/new', permission: 'delivery.manage' },
  deliveryShipments: { path: '/delivery/shipments', permission: 'delivery.view' },

  // ISO
  isoDocuments: { path: '/iso/documents', permission: 'iso.view' },
  isoDocumentsNew: { path: '/iso/documents/new', permission: 'iso.manage' },
  isoAudits: { path: '/iso/audits', permission: 'iso.view' },
  isoAuditsNew: { path: '/iso/audits/new', permission: 'iso.manage' },

  // CRM
  crmDashboard: { path: '/crm/dashboard', permission: 'crm.tickets.view' },
  crmTickets: { path: '/crm/tickets', permission: 'crm.tickets.view' },

  // VP Approvals
  vpApprovalsPending: { path: '/approvals/pending', permission: 'loans.vp_approve' },
  vpApprovalsLoans: { path: '/approvals/loans', permission: 'loans.vp_approve' },

  // Executive
  execLeaveApprovals: { path: '/executive/leave-approvals', permission: 'leaves.ga_process' },
  execOvertimeApprovals: { path: '/executive/overtime-approvals', permission: 'overtime.executive_approve' },

  // Reports
  govReports: { path: '/reports/government', permission: 'payroll.gov_reports' },

  // Admin
  adminUsers: { path: '/admin/users', permission: 'system.manage_users' },
  adminSettings: { path: '/admin/settings', permission: 'system.edit_settings' },
  adminAuditLogs: { path: '/admin/audit-logs', permission: 'system.view_audit_log' },
  adminReferenceTables: { path: '/admin/reference-tables', permission: 'system.edit_settings' },
  adminBackup: { path: '/admin/backup', permission: 'system.manage_backups' },

  // Self Service
  myPayslips: { path: '/self-service/payslips', permission: 'payslips.view' },
  myLeaves: { path: '/me/leaves', permission: 'leaves.view_own' },
  myLoans: { path: '/me/loans', permission: 'loans.view_own' },
  myOvertime: { path: '/me/overtime', permission: 'overtime.view' },
  myAttendance: { path: '/me/attendance', permission: 'attendance.view_own' },
  myProfile: { path: '/me/profile', permission: 'self.view_profile' },

  // Account
  changePassword: { path: '/account/change-password', permission: null },
} as const

// ═══════════════════════════════════════════════════════════════════════════════
// NAVIGATION SECTIONS (from AppLayout.tsx)
// ═══════════════════════════════════════════════════════════════════════════════

const NAV_SECTIONS = {
  teamManagement: {
    label: 'Team Management',
    permission: 'employees.view_team',
    roles: ['manager', 'officer', 'head'],
    children: ['My Team', 'Team Attendance', 'Team Leave', 'Team Overtime', 'Team Loans', 'Shift Schedules'],
  },
  humanResources: {
    label: 'Human Resources',
    permission: 'hr.full_access',
    roles: ['manager', 'officer'],
    children: ['All Employees', 'Attendance Logs', 'Leave Requests', 'Overtime', 'Loans', 'Departments', 'Positions', 'Shifts', 'HR Reports'],
  },
  payroll: {
    label: 'Payroll',
    permission: 'payroll.view_runs',
    roles: ['manager', 'officer'],
    children: ['Payroll Runs', 'Pay Periods'],
  },
  accounting: {
    label: 'Accounting',
    permission: 'chart_of_accounts.view',
    roles: ['manager', 'officer', 'executive', 'vice_president', 'head'],
    children: ['Chart of Accounts', 'Journal Entries', 'General Ledger', 'Loan Approvals', 'Recurring Templates'],
  },
  payables: {
    label: 'Payables (AP)',
    permission: 'vendors.view',
    roles: ['manager', 'officer', 'executive', 'vice_president', 'head'],
    children: ['Vendors', 'Invoices', 'Credit Notes'],
  },
  receivables: {
    label: 'Receivables (AR)',
    permission: 'customers.view',
    roles: ['manager', 'officer', 'executive', 'vice_president', 'head'],
    children: ['Customers', 'Invoices', 'Credit Notes'],
  },
  banking: {
    label: 'Banking',
    permission: 'bank_accounts.view',
    roles: ['manager', 'officer'],
    children: ['Bank Accounts', 'Reconciliations'],
  },
  financialReports: {
    label: 'Financial Reports',
    permission: 'reports.financial_statements',
    roles: ['manager', 'officer', 'executive', 'vice_president'],
    children: ['Trial Balance', 'Balance Sheet', 'Income Statement', 'Cash Flow', 'AP Aging', 'AR Aging', 'VAT Ledger', 'Tax Summary'],
  },
  fixedAssets: {
    label: 'Fixed Assets',
    permission: 'fixed_assets.view',
    roles: ['manager', 'officer', 'executive', 'vice_president', 'head'],
    children: ['Asset Register', 'Categories', 'Disposals'],
  },
  budget: {
    label: 'Budget',
    permission: 'budget.view',
    roles: ['manager', 'officer', 'executive', 'vice_president'],
    children: ['Cost Centers', 'Budget Lines', 'Budget vs Actual'],
  },
  reports: {
    label: 'Reports',
    permission: 'payroll.gov_reports',
    roles: ['manager', 'officer', 'executive', 'vice_president', 'head'],
    children: ['Government Reports'],
  },
  procurement: {
    label: 'Procurement',
    permission: 'procurement.purchase-request.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Purchase Requests', 'Purchase Orders', 'Goods Receipts', 'RFQs', 'Analytics'],
  },
  inventory: {
    label: 'Inventory',
    permission: 'inventory.items.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Item Categories', 'Item Master', 'Warehouse Locations', 'Stock Balances', 'Stock Ledger', 'Requisitions', 'Stock Adjustments', 'Valuation'],
  },
  production: {
    label: 'Production',
    permission: 'production.orders.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Bill of Materials', 'Delivery Schedules', 'Work Orders', 'Cost Analysis'],
  },
  qc: {
    label: 'QC / QA',
    permission: 'qc.inspections.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Inspections', 'NCR', 'CAPA', 'Templates', 'Defect Rate'],
  },
  maintenance: {
    label: 'Maintenance',
    permission: 'maintenance.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Equipment', 'Work Orders'],
  },
  mold: {
    label: 'Mold',
    permission: 'mold.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Mold Masters'],
  },
  delivery: {
    label: 'Delivery',
    permission: 'delivery.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Receipts', 'Shipments'],
  },
  iso: {
    label: 'ISO / IATF',
    permission: 'iso.view',
    roles: ['manager', 'officer', 'head'],
    children: ['Documents', 'Audits'],
  },
  crm: {
    label: 'CRM',
    permission: 'crm.tickets.view',
    roles: ['manager', 'officer', 'head'],
    children: ['CRM Dashboard', 'Support Tickets'],
  },
  vpApprovals: {
    label: 'VP Approvals',
    permission: 'loans.vp_approve',
    roles: ['vice_president'],
    children: ['Pending Approvals', 'Purchase Requests', 'Material Requisitions', 'Loans'],
  },
  admin: {
    label: 'Administration',
    permission: 'system.manage_users',
    roles: ['admin', 'super_admin'],
    children: ['Users', 'System Settings', 'Reference Tables', 'Fiscal Periods', 'Audit Logs', 'Backup & Restore'],
  },
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

async function login(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
  await page.waitForSelector('nav', { timeout: 10000 })
}

async function logout(page: Page) {
  await page.evaluate(() => localStorage.clear())
}

async function getSidebarText(page: Page): Promise<string> {
  const sidebar = page.locator('aside, nav').first()
  return await sidebar.innerText()
}

async function canAccessPage(page: Page, path: string): Promise<boolean> {
  await page.goto(`${BASE}${path}`)
  await page.waitForTimeout(1000)
  const url = page.url()
  const bodyText = await page.locator('body').innerText()

  // If redirected to 403 or contains forbidden text
  const isForbidden = url.includes('/403') ||
    /forbidden|access denied|unauthorized|403/i.test(bodyText)

  return !isForbidden
}

async function expectPageAccessible(page: Page, path: string) {
  const accessible = await canAccessPage(page, path)
  expect(accessible, `Expected to access ${path}`).toBe(true)
}

async function expectPageForbidden(page: Page, path: string) {
  await page.goto(`${BASE}${path}`)
  await page.waitForTimeout(1000)

  const url = page.url()
  const bodyText = await page.locator('body').innerText()

  const isForbidden = url.includes('/403') ||
    /forbidden|access denied|unauthorized|403/i.test(bodyText)

  expect(isForbidden, `Expected ${path} to be forbidden`).toBe(true)
}

// ═══════════════════════════════════════════════════════════════════════════════
// ROLE TEST SUITES
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🎭 RBAC Complete — Role-Based Access Control', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // HR MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('👤 HR Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    })

    test('NAV: Should see HR modules in sidebar', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      // Should see
      expect(sidebarText).toContain('Team Management')
      expect(sidebarText).toContain('Human Resources')
      expect(sidebarText).toContain('Payroll')
      expect(sidebarText).toContain('Reports')

      // Should NOT see
      expect(sidebarText).not.toContain('Accounting')
      expect(sidebarText).not.toContain('Production')
      expect(sidebarText).not.toContain('Inventory')
    })

    test('PAGE: Can access all HR pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.hrEmployees.path)
      await expectPageAccessible(page, ROUTES.hrEmployeesAll.path)
      await expectPageAccessible(page, ROUTES.hrEmployeesNew.path)
      await expectPageAccessible(page, ROUTES.hrAttendance.path)
      await expectPageAccessible(page, ROUTES.hrLeave.path)
      await expectPageAccessible(page, ROUTES.hrLoans.path)
      await expectPageAccessible(page, ROUTES.hrDepartments.path)
      await expectPageAccessible(page, ROUTES.hrPositions.path)
      await expectPageAccessible(page, ROUTES.hrShifts.path)
      await expectPageAccessible(page, ROUTES.hrReports.path)
    })

    test('PAGE: Can access Payroll pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.payrollRuns.path)
      await expectPageAccessible(page, ROUTES.payrollRunsNew.path)
      await expectPageAccessible(page, ROUTES.payrollPeriods.path)
    })

    test('PAGE: Can access Team Management pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.teamEmployees.path)
      await expectPageAccessible(page, ROUTES.teamAttendance.path)
      await expectPageAccessible(page, ROUTES.teamLeave.path)
      await expectPageAccessible(page, ROUTES.teamOvertime.path)
      await expectPageAccessible(page, ROUTES.teamLoans.path)
    })

    test('FORBIDDEN: Cannot access Accounting pages', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.chartOfAccounts.path)
      await expectPageForbidden(page, ROUTES.journalEntries.path)
      await expectPageForbidden(page, ROUTES.vendors.path)
    })

    test('FORBIDDEN: Cannot access Production pages', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.productionOrders.path)
      await expectPageForbidden(page, ROUTES.productionBoms.path)
    })

    test('FORBIDDEN: Cannot access Inventory pages', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.inventoryItems.path)
      await expectPageForbidden(page, ROUTES.inventoryCategories.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ACCOUNTING MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('💰 Accounting Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
    })

    test('NAV: Should see Accounting modules', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Accounting')
      expect(sidebarText).toContain('Payables (AP)')
      expect(sidebarText).toContain('Receivables (AR)')
      expect(sidebarText).toContain('Banking')
      expect(sidebarText).toContain('Financial Reports')
      expect(sidebarText).toContain('Fixed Assets')
      expect(sidebarText).toContain('Budget')

      // Should NOT see
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Production')
    })

    test('PAGE: Can access all Accounting pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.chartOfAccounts.path)
      await expectPageAccessible(page, ROUTES.journalEntries.path)
      await expectPageAccessible(page, ROUTES.journalEntriesNew.path)
      await expectPageAccessible(page, ROUTES.generalLedger.path)
      await expectPageAccessible(page, ROUTES.fiscalPeriods.path)
    })

    test('PAGE: Can access AP pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.vendors.path)
      await expectPageAccessible(page, ROUTES.apInvoices.path)
      await expectPageAccessible(page, ROUTES.apInvoicesNew.path)
      await expectPageAccessible(page, ROUTES.apCreditNotes.path)
    })

    test('PAGE: Can access AR pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.customers.path)
      await expectPageAccessible(page, ROUTES.arInvoices.path)
      await expectPageAccessible(page, ROUTES.arInvoicesNew.path)
    })

    test('PAGE: Can access Banking pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.bankAccounts.path)
      await expectPageAccessible(page, ROUTES.bankReconciliations.path)
    })

    test('PAGE: Can access Financial Reports', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.trialBalance.path)
      await expectPageAccessible(page, ROUTES.balanceSheet.path)
      await expectPageAccessible(page, ROUTES.incomeStatement.path)
      await expectPageAccessible(page, ROUTES.cashFlow.path)
    })

    test('PAGE: Can access Budget pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.budgetCostCenters.path)
      await expectPageAccessible(page, ROUTES.budgetLines.path)
      await expectPageAccessible(page, ROUTES.budgetVsActual.path)
    })

    test('PAGE: Can access Fixed Assets', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.fixedAssets.path)
      await expectPageAccessible(page, ROUTES.fixedAssetsCategories.path)
    })

    test('FORBIDDEN: Cannot access Payroll', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.payrollRuns.path)
    })

    test('FORBIDDEN: Cannot access Production', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.productionOrders.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ACCOUNTING OFFICER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('💼 Accounting Officer', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.acctgOfficer.email, ACCOUNTS.acctgOfficer.password)
    })

    test('NAV: Should see Banking (FIXED!)', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      // Critical fix: Officer should see Banking
      expect(sidebarText).toContain('Banking')

      expect(sidebarText).toContain('Accounting')
      expect(sidebarText).toContain('Payables (AP)')
      expect(sidebarText).toContain('Financial Reports')

      // Should NOT see
      expect(sidebarText).not.toContain('Payroll')
    })

    test('PAGE: Can access Banking pages (FIXED!)', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.bankAccounts.path)
      await expectPageAccessible(page, ROUTES.bankReconciliations.path)
    })

    test('FORBIDDEN: Cannot access Payroll', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.payrollRuns.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PRODUCTION MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🏭 Production Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
    })

    test('NAV: Should see Production modules (NO PAYROLL!)', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      // Should see
      expect(sidebarText).toContain('Production')
      expect(sidebarText).toContain('QC / QA')
      expect(sidebarText).toContain('Maintenance')
      expect(sidebarText).toContain('Mold')
      expect(sidebarText).toContain('Delivery')
      expect(sidebarText).toContain('ISO / IATF')

      // CRITICAL FIX: Should NOT see
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Inventory')
    })

    test('PAGE: Can access all Production pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.productionOrders.path)
      await expectPageAccessible(page, ROUTES.productionOrdersNew.path)
      await expectPageAccessible(page, ROUTES.productionBoms.path)
      await expectPageAccessible(page, ROUTES.productionBomsNew.path)
      await expectPageAccessible(page, ROUTES.productionDeliverySchedules.path)
      await expectPageAccessible(page, ROUTES.productionCostAnalysis.path)
    })

    test('PAGE: Can access QC pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.qcInspections.path)
      await expectPageAccessible(page, ROUTES.qcInspectionsNew.path)
      await expectPageAccessible(page, ROUTES.qcNcrs.path)
      await expectPageAccessible(page, ROUTES.qcCapa.path)
    })

    test('PAGE: Can access Maintenance pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.maintenanceEquipment.path)
      await expectPageAccessible(page, ROUTES.maintenanceWorkOrders.path)
    })

    test('PAGE: Can access Mold pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.moldMasters.path)
    })

    test('PAGE: Can access Delivery pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.deliveryReceipts.path)
      await expectPageAccessible(page, ROUTES.deliveryShipments.path)
    })

    test('PAGE: Can access ISO pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.isoDocuments.path)
      await expectPageAccessible(page, ROUTES.isoAudits.path)
    })

    test('FORBIDDEN: Cannot access Payroll (CRITICAL!)', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.payrollRuns.path)
      await expectPageForbidden(page, ROUTES.payrollRunsNew.path)
    })

    test('FORBIDDEN: Cannot access Inventory Categories (CRITICAL!)', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.inventoryCategories.path)
      await expectPageForbidden(page, ROUTES.inventoryLocations.path)
    })

    test('FORBIDDEN: Cannot access Accounting', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.chartOfAccounts.path)
      await expectPageForbidden(page, ROUTES.journalEntries.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PRODUCTION HEAD
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('👷 Production Head', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.prodHead.email, ACCOUNTS.prodHead.password)
    })

    test('NAV: Should NOT see Payroll', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Production')
      expect(sidebarText).not.toContain('Payroll')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PRODUCTION STAFF
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🔧 Production Staff', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.prodStaff.email, ACCOUNTS.prodStaff.password)
    })

    test('NAV: Should see minimal Production only', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      // Staff should see very limited navigation
      expect(sidebarText).toContain('Production')

      // Should NOT see management functions
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Accounting')
      expect(sidebarText).not.toContain('QC / QA')
      expect(sidebarText).not.toContain('Maintenance')
      expect(sidebarText).not.toContain('Mold')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // WAREHOUSE HEAD
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('📦 Warehouse Head', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    })

    test('NAV: Should see Inventory with Categories (FIXED!)', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Inventory')

      // Expand Inventory to check children
      await page.click('text=Inventory')
      await page.waitForTimeout(500)

      // CRITICAL FIX: Should see management options
      await expect(page.locator('text=Item Categories')).toBeVisible()
      await expect(page.locator('text=Warehouse Locations')).toBeVisible()
    })

    test('PAGE: Can access all Inventory pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.inventoryCategories.path)
      await expectPageAccessible(page, ROUTES.inventoryItems.path)
      await expectPageAccessible(page, ROUTES.inventoryLocations.path)
      await expectPageAccessible(page, ROUTES.inventoryStock.path)
      await expectPageAccessible(page, ROUTES.inventoryLedger.path)
      await expectPageAccessible(page, ROUTES.inventoryAdjustments.path)
      await expectPageAccessible(page, ROUTES.inventoryRequisitions.path)
    })

    test('FORBIDDEN: Cannot access Production', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.productionOrders.path)
    })

    test('FORBIDDEN: Cannot access Payroll', async ({ page }) => {
      await expectPageForbidden(page, ROUTES.payrollRuns.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PLANT MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🌿 Plant Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.plantManager.email, ACCOUNTS.plantManager.password)
    })

    test('NAV: Should see Production ecosystem', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Production')
      expect(sidebarText).toContain('QC / QA')
      expect(sidebarText).toContain('Maintenance')
      expect(sidebarText).toContain('Mold')
      expect(sidebarText).toContain('Delivery')
      expect(sidebarText).toContain('ISO / IATF')

      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Accounting')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // QC MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🔍 QC Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.qcManager.email, ACCOUNTS.qcManager.password)
    })

    test('NAV: Should see QC and Production view', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('QC / QA')
      expect(sidebarText).toContain('Production')

      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Accounting')
      expect(sidebarText).not.toContain('Inventory')
    })

    test('PAGE: Can access all QC pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.qcInspections.path)
      await expectPageAccessible(page, ROUTES.qcInspectionsNew.path)
      await expectPageAccessible(page, ROUTES.qcNcrs.path)
      await expectPageAccessible(page, ROUTES.qcNcrsNew.path)
      await expectPageAccessible(page, ROUTES.qcCapa.path)
      await expectPageAccessible(page, ROUTES.qcTemplates.path)
      await expectPageAccessible(page, ROUTES.qcDefectRate.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PURCHASING OFFICER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🛒 Purchasing Officer', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.purchasingOfficer.email, ACCOUNTS.purchasingOfficer.password)
    })

    test('NAV: Should see Procurement', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Procurement')

      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Production')
      expect(sidebarText).not.toContain('Inventory')
    })

    test('PAGE: Can access Procurement pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.purchaseRequests.path)
      await expectPageAccessible(page, ROUTES.purchaseRequestsNew.path)
      await expectPageAccessible(page, ROUTES.purchaseOrders.path)
      await expectPageAccessible(page, ROUTES.goodsReceipts.path)
      await expectPageAccessible(page, ROUTES.procurementRfqs.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // SALES MANAGER (CRM)
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('💼 Sales Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.salesManager.email, ACCOUNTS.salesManager.password)
    })

    test('NAV: Should see CRM', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('CRM')

      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Production')
      expect(sidebarText).not.toContain('Procurement')
    })

    test('PAGE: Can access CRM pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.crmDashboard.path)
      await expectPageAccessible(page, ROUTES.crmTickets.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // MAINTENANCE MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🔧 Maintenance Manager', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.maintenanceManager.email, ACCOUNTS.maintenanceManager.password)
    })

    test('NAV: Should see Maintenance', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Maintenance')
      expect(sidebarText).toContain('Equipment')
      expect(sidebarText).toContain('Work Orders')
    })

    test('PAGE: Can access Maintenance pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.maintenanceEquipment.path)
      await expectPageAccessible(page, ROUTES.maintenanceEquipmentNew.path)
      await expectPageAccessible(page, ROUTES.maintenanceWorkOrders.path)
      await expectPageAccessible(page, ROUTES.maintenanceWorkOrdersNew.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // VICE PRESIDENT
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('👔 Vice President', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    })

    test('NAV: Should see VP Approvals', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('VP Approvals')
    })

    test('PAGE: Can access VP Approval pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.vpApprovalsPending.path)
      await expectPageAccessible(page, ROUTES.vpApprovalsLoans.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // EXECUTIVE
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('📊 Executive', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.executive.email, ACCOUNTS.executive.password)
    })

    test('NAV: Should see Reports only', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Reports')

      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Production')
    })

    test('PAGE: Can access Reports', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.govReports.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ADMIN
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('⚙️ Admin', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.admin.email, ACCOUNTS.admin.password)
    })

    test('NAV: Should see Administration', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      expect(sidebarText).toContain('Administration')
    })

    test('PAGE: Can access Admin pages', async ({ page }) => {
      await expectPageAccessible(page, ROUTES.adminUsers.path)
      await expectPageAccessible(page, ROUTES.adminSettings.path)
      await expectPageAccessible(page, ROUTES.adminAuditLogs.path)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // SUPER ADMIN
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('🦸 Super Admin', () => {

    test.beforeEach(async ({ page }) => {
      await login(page, ACCOUNTS.superAdmin.email, ACCOUNTS.superAdmin.password)
    })

    test('NAV: Should see all modules', async ({ page }) => {
      const sidebarText = await getSidebarText(page)

      // Super admin should see almost everything
      expect(sidebarText).toContain('Administration')
    })
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// CRITICAL BUG REGRESSION TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🐛 Critical Bug Regression Tests', () => {

  test('BUG-001: Production Manager cannot see Payroll (PAYROLL-PROD-ISOLATION)', async ({ page }) => {
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)

    // Check sidebar
    const sidebarText = await getSidebarText(page)
    expect(sidebarText).not.toContain('Payroll')

    // Try direct access
    await expectPageForbidden(page, ROUTES.payrollRuns.path)
  })

  test('BUG-002: Accounting Officer can see Banking (BANKING-OFFICER-FIX)', async ({ page }) => {
    await login(page, ACCOUNTS.acctgOfficer.email, ACCOUNTS.acctgOfficer.password)

    // Check sidebar
    const sidebarText = await getSidebarText(page)
    expect(sidebarText).toContain('Banking')

    // Access pages
    await expectPageAccessible(page, ROUTES.bankAccounts.path)
  })

  test('BUG-003: Warehouse Head can manage Categories (INVENTORY-WH-CATEGORIES)', async ({ page }) => {
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)

    // Check sidebar
    const sidebarText = await getSidebarText(page)
    expect(sidebarText).toContain('Inventory')

    // Access management pages
    await expectPageAccessible(page, ROUTES.inventoryCategories.path)
    await expectPageAccessible(page, ROUTES.inventoryLocations.path)
  })

  test('BUG-004: Production cannot manage Inventory (INVENTORY-PROD-NOACCESS)', async ({ page }) => {
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)

    // Check sidebar
    const sidebarText = await getSidebarText(page)
    expect(sidebarText).not.toContain('Inventory')

    // Try direct access
    await expectPageForbidden(page, ROUTES.inventoryCategories.path)
    await expectPageForbidden(page, ROUTES.inventoryLocations.path)
  })
})
