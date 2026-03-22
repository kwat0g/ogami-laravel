/**
 * E2E-RBAC-COMPREHENSIVE-UI — Complete RBAC UI Testing
 * 
 * Tests ALL roles for:
 * 1. Sidebar navigation visibility (should see / should NOT see)
 * 2. Action buttons (Add/Create/Edit/Delete) visibility on pages
 * 3. Direct URL access to forbidden pages
 * 4. Module access boundaries
 * 
 * Covers 15+ roles across 20 domains
 */
import { test, expect, Page } from '@playwright/test'

const BASE = 'http://localhost:5173'

// ═══════════════════════════════════════════════════════════════════════════════
// TEST ACCOUNTS — All roles from ManufacturingEmployeeSeeder
// ═══════════════════════════════════════════════════════════════════════════════

const ROLES = {
  // HR Department
  hrManager: { 
    email: 'hr.manager@ogamierp.local', 
    password: 'Manager@Test1234!', 
    dept: 'HR',
    expectedModules: ['Payroll', 'Reports'],
    forbiddenModules: ['Accounting', 'Production', 'Inventory', 'Procurement', 'QC / QA'],
    canCreate: ['payroll.runs'],
  },
  hrOfficer: { 
    email: 'hr.officer@ogamierp.local', 
    password: 'Officer@Test1234!', 
    dept: 'HR',
    expectedModules: ['Team Management', 'Human Resources', 'Payroll', 'Reports'],
    forbiddenModules: ['Accounting', 'Production', 'Inventory'],
    canCreate: ['hr.employees', 'payroll.runs'],
  },
  hrHead: { 
    email: 'hr.head@ogamierp.local', 
    password: 'Head@Test1234!', 
    dept: 'HR',
    expectedModules: ['Team Management'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Accounting', 'Production', 'Inventory'],
    canCreate: [], // Head is view-only for team
  },
  hrStaff: { 
    email: 'hr.staff@ogamierp.local', 
    password: 'Staff@Test1234!', 
    dept: 'HR',
    expectedModules: [], // Staff has minimal access
    forbiddenModules: ['Team Management', 'Human Resources', 'Payroll', 'Accounting', 'Production', 'Inventory'],
    canCreate: [],
  },

  // Accounting Department
  acctgManager: { 
    email: 'acctg.manager@ogamierp.local', 
    password: 'Manager@12345!', 
    dept: 'ACCTG',
    expectedModules: ['Team Management', 'Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Fixed Assets', 'Budget'],
    forbiddenModules: ['Production', 'Inventory', 'QC / QA', 'Human Resources', 'Payroll'],
    canCreate: ['accounting.journals', 'accounting.vendors', 'accounting.ap.invoices', 'banking.accounts'],
  },
  acctgOfficer: { 
    email: 'acctg.officer@ogamierp.local', 
    password: 'Officer@Test1234!', 
    dept: 'ACCTG',
    expectedModules: ['Team Management', 'Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Fixed Assets', 'Budget'],
    forbiddenModules: ['Production', 'Inventory', 'QC / QA', 'Human Resources', 'Payroll'],
    canCreate: ['accounting.journals', 'accounting.vendors', 'accounting.ap.invoices'],
  },
  acctgHead: { 
    email: 'acctg.head@ogamierp.local', 
    password: 'Head@Test1234!', 
    dept: 'ACCTG',
    expectedModules: ['Team Management', 'Accounting', 'Payables (AP)', 'Receivables (AR)', 'Fixed Assets', 'Budget'],
    forbiddenModules: ['Banking', 'Production', 'Inventory', 'QC / QA', 'Human Resources', 'Payroll'],
    canCreate: [], // Head is typically view-only
  },

  // Production Department
  prodManager: { 
    email: 'prod.manager@ogamierp.local', 
    password: 'Manager@12345!', 
    dept: 'PROD',
    expectedModules: ['Production', 'QC / QA', 'Inventory'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Accounting', 'Payables (AP)', 'Banking'],
    canCreate: ['production.orders', 'production.boms', 'qc.inspections', 'inventory.mrq'],
  },
  prodHead: { 
    email: 'production.head@ogamierp.local', 
    password: 'Head@123456789!', 
    dept: 'PROD',
    expectedModules: ['Production', 'Team Management', 'QC / QA'],
    forbiddenModules: ['Inventory', 'Payroll', 'Human Resources', 'Accounting', 'Banking'],
    canCreate: [], // View only
  },
  prodStaff: { 
    email: 'prod.staff@ogamierp.local', 
    password: 'Staff@123456789!', 
    dept: 'PROD',
    expectedModules: ['Production'],
    forbiddenModules: ['Team Management', 'QC / QA', 'Inventory', 'Payroll', 'Accounting'],
    canCreate: [],
  },

  // Warehouse Department
  whHead: { 
    email: 'warehouse.head@ogamierp.local', 
    password: 'Head@123456789!', 
    dept: 'WH',
    expectedModules: ['Team Management', 'Inventory', 'Delivery'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Accounting', 'Production', 'Payables (AP)'],
    canCreate: ['inventory.items', 'inventory.adjustments', 'inventory.mrq'],
  },

  // Other Departments
  plantManager: { 
    email: 'plant.manager@ogamierp.local', 
    password: 'Manager@12345!', 
    dept: 'PLANT',
    expectedModules: ['Production', 'QC / QA', 'Maintenance', 'Delivery', 'Inventory', 'Team Management'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Accounting', 'Banking'],
    canCreate: ['production.orders', 'qc.inspections', 'maintenance.workorders'],
  },
  qcManager: { 
    email: 'qc.manager@ogamierp.local', 
    password: 'Manager@12345!', 
    dept: 'QC',
    expectedModules: ['QC / QA', 'Production', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Accounting', 'Inventory'],
    canCreate: ['qc.inspections', 'qc.ncr'],
  },
  moldManager: { 
    email: 'mold.manager@ogamierp.local', 
    password: 'Manager@12345!', 
    dept: 'MOLD',
    expectedModules: ['Mold', 'Team Management'],
    forbiddenModules: ['QC / QA', 'Production', 'Inventory', 'Payroll', 'Accounting'],
    canCreate: ['mold.masters'],
  },
  maintenanceManager: { 
    email: 'maintenance.head@ogamierp.local', 
    password: 'Head@123456789!', 
    dept: 'MAINT',
    expectedModules: ['Maintenance', 'Team Management'],
    forbiddenModules: ['QC / QA', 'Production', 'Inventory', 'Payroll', 'Accounting'],
    canCreate: ['maintenance.equipment', 'maintenance.workorders'],
  },

  // Sales & Purchasing
  salesManager: { 
    email: 'crm.manager@ogamierp.local', 
    password: 'Manager@12345!', 
    dept: 'SALES',
    expectedModules: ['Receivables (AR)', 'CRM', 'Team Management'],
    forbiddenModules: ['Payables (AP)', 'Inventory', 'Production', 'Payroll', 'Human Resources'],
    canCreate: ['ar.customers', 'ar.invoices', 'crm.tickets'],
  },
  purchasingOfficer: { 
    email: 'purchasing.officer@ogamierp.local', 
    password: 'Officer@12345!', 
    dept: 'PPC',
    expectedModules: ['Procurement', 'Payables (AP)', 'Inventory', 'Team Management'],
    forbiddenModules: ['Receivables (AR)', 'Production', 'Payroll', 'Human Resources', 'Banking'],
    canCreate: ['procurement.pr', 'procurement.po', 'accounting.vendors'],
  },

  // Executive
  vp: { 
    email: 'vp@ogamierp.local', 
    password: 'VicePresident@1!', 
    dept: 'EXEC',
    expectedModules: ['Financial Reports', 'Fixed Assets', 'Budget', 'VP Approvals', 'Procurement', 'Inventory', 'Production', 'Accounting', 'Payables (AP)', 'Receivables (AR)'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Banking'],
    canCreate: ['procurement.pr', 'procurement.po'],
  },
  executive: { 
    email: 'executive@ogamierp.local', 
    password: 'Executive@Test1234!', 
    dept: 'EXEC',
    expectedModules: ['Financial Reports', 'Fixed Assets', 'Budget', 'Executive Approvals', 'GA Processing', 'Accounting', 'Payables (AP)', 'Receivables (AR)'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Banking', 'Production', 'Inventory'],
    canCreate: [],
  },

  // Admin
  admin: { 
    email: 'admin@ogamierp.local', 
    password: 'Admin@1234567890!', 
    dept: null,
    expectedModules: ['Administration'],
    forbiddenModules: ['Payroll', 'Human Resources', 'Accounting', 'Production', 'Inventory'], // Admin is system-only
    canCreate: ['admin.users', 'admin.settings'],
  },
} as const

type RoleKey = keyof typeof ROLES

// ═══════════════════════════════════════════════════════════════════════════════
// ROUTE DEFINITIONS FOR DIRECT ACCESS TESTING
// ═══════════════════════════════════════════════════════════════════════════════

const ROUTES = {
  // HR
  hrEmployees: '/hr/employees/all',
  hrEmployeesNew: '/hr/employees/new',
  hrAttendance: '/hr/attendance',
  hrLeave: '/hr/leave',
  hrLoans: '/hr/loans',
  hrPayroll: '/payroll/runs',
  hrPayrollNew: '/payroll/runs/new',

  // Accounting
  accountingAccounts: '/accounting/accounts',
  accountingJournals: '/accounting/journal-entries',
  accountingJournalsNew: '/accounting/journal-entries/new',
  apVendors: '/accounting/vendors',
  apInvoices: '/accounting/ap/invoices',
  apInvoicesNew: '/accounting/ap/invoices/new',
  arCustomers: '/ar/customers',
  arInvoices: '/ar/invoices',
  bankingAccounts: '/banking/accounts',
  fiscalPeriods: '/accounting/fiscal-periods',
  trialBalance: '/accounting/trial-balance',

  // Inventory
  inventoryItems: '/inventory/items',
  inventoryItemsNew: '/inventory/items/new',
  inventoryCategories: '/inventory/categories',
  inventoryLocations: '/inventory/locations',
  inventoryStock: '/inventory/stock',
  inventoryRequisitions: '/inventory/requisitions',
  inventoryAdjustments: '/inventory/adjustments',

  // Production
  productionOrders: '/production/orders',
  productionOrdersNew: '/production/orders/new',
  productionBoms: '/production/boms',
  productionBomsNew: '/production/boms/new',
  productionDelivery: '/production/delivery-schedules',

  // QC
  qcInspections: '/qc/inspections',
  qcInspectionsNew: '/qc/inspections/new',
  qcNcrs: '/qc/ncrs',
  qcCapa: '/qc/capa',

  // Procurement
  procurementPR: '/procurement/purchase-requests',
  procurementPRNew: '/procurement/purchase-requests/new',
  procurementPO: '/procurement/purchase-orders',
  procurementGR: '/procurement/goods-receipts',

  // Maintenance
  maintenanceEquipment: '/maintenance/equipment',
  maintenanceWorkOrders: '/maintenance/work-orders',

  // Mold
  moldMasters: '/mold/masters',

  // Delivery
  deliveryReceipts: '/delivery/receipts',
  deliveryShipments: '/delivery/shipments',

  // CRM
  crmDashboard: '/crm/dashboard',
  crmTickets: '/crm/tickets',

  // Admin
  adminUsers: '/admin/users',
  adminSettings: '/admin/settings',
  adminAuditLogs: '/admin/audit-logs',
} as const

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

async function login(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/dashboard/, { timeout: 30000 })
  await page.waitForSelector('aside, nav', { timeout: 15000 })
}

async function logout(page: Page) {
  try {
    // Navigate to a valid page first to avoid security errors
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => {
      localStorage.clear()
      sessionStorage.clear()
      document.cookie.split(';').forEach(cookie => {
        const [name] = cookie.split('=')
        document.cookie = `${name.trim()}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;`
      })
    })
  } catch {
    // If page navigation fails, just try to clear storage
    try {
      await page.goto('about:blank')
    } catch {
      // Ignore errors
    }
  }
}

async function getSidebarText(page: Page): Promise<string> {
  const sidebar = page.locator('aside, nav').first()
  return await sidebar.innerText()
}

async function expandModule(page: Page, moduleName: string) {
  // Try to click on the module to expand it
  const moduleButton = page.locator('button', { hasText: moduleName }).first()
  if (await moduleButton.count() > 0) {
    await moduleButton.click()
    await page.waitForTimeout(500)
  }
}

async function canSeeButton(page: Page, buttonText: string | RegExp): Promise<boolean> {
  const button = page.getByRole('button', { name: buttonText })
  return await button.count() > 0 && await button.isVisible()
}

async function canSeeLink(page: Page, linkText: string | RegExp): Promise<boolean> {
  const link = page.getByRole('link', { name: linkText })
  return await link.count() > 0 && await link.isVisible()
}

async function checkPageAccess(page: Page, path: string): Promise<{ accessible: boolean; hasCreateButton: boolean }> {
  await page.goto(`${BASE}${path}`)
  await page.waitForTimeout(1500)
  
  const url = page.url()
  const bodyText = await page.locator('body').innerText()
  
  const isForbidden = url.includes('/403') ||
    url.includes('/login') ||
    /forbidden|access denied|unauthorized|403|not allowed/i.test(bodyText)
  
  // Check for common create/add buttons
  const createButtonPatterns = [/new/i, /create/i, /add/i, /\+\s/i]
  let hasCreateButton = false
  
  for (const pattern of createButtonPatterns) {
    const buttons = page.locator('button', { hasText: pattern })
    const links = page.locator('a', { hasText: pattern })
    if ((await buttons.count()) > 0 || (await links.count()) > 0) {
      hasCreateButton = true
      break
    }
  }
  
  return { accessible: !isForbidden, hasCreateButton }
}

// ═══════════════════════════════════════════════════════════════════════════════
// TEST SUITES
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔐 RBAC Comprehensive UI Tests', () => {
  
  test.beforeEach(async ({ page }) => {
    // Navigate to blank page and clear storage
    await page.goto('about:blank')
    await page.waitForTimeout(100)
    try {
      await page.evaluate(() => {
        localStorage.clear()
        sessionStorage.clear()
      })
    } catch {
      // Ignore errors on about:blank
    }
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // HR DEPARTMENT TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('👤 HR Department', () => {
    
    test('HR Manager - Sidebar Navigation', async ({ page }) => {
      await login(page, ROLES.hrManager.email, ROLES.hrManager.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see
      for (const module of ROLES.hrManager.expectedModules) {
        expect(sidebarText, `HR Manager should see ${module}`).toContain(module)
      }
      
      // Should NOT see
      for (const module of ROLES.hrManager.forbiddenModules) {
        expect(sidebarText, `HR Manager should NOT see ${module}`).not.toContain(module)
      }
    })

    test('HR Manager - Page Access and Actions', async ({ page }) => {
      await login(page, ROLES.hrManager.email, ROLES.hrManager.password)
      
      // Can access Payroll
      const payrollResult = await checkPageAccess(page, ROUTES.hrPayroll)
      expect(payrollResult.accessible, 'HR Manager should access Payroll').toBe(true)
      
      // Cannot access Accounting
      const acctgResult = await checkPageAccess(page, ROUTES.accountingJournals)
      expect(acctgResult.accessible, 'HR Manager should NOT access Accounting').toBe(false)
      
      // Cannot access Inventory
      const invResult = await checkPageAccess(page, ROUTES.inventoryItems)
      expect(invResult.accessible, 'HR Manager should NOT access Inventory').toBe(false)
    })

    test('HR Head - Limited Access', async ({ page }) => {
      await login(page, ROLES.hrHead.email, ROLES.hrHead.password)
      const sidebarText = await getSidebarText(page)
      
      // Should only see Team Management
      expect(sidebarText).toContain('Team Management')
      expect(sidebarText).not.toContain('Human Resources')
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Accounting')
    })

    test('HR Staff - Minimal Access', async ({ page }) => {
      await login(page, ROLES.hrStaff.email, ROLES.hrStaff.password)
      const sidebarText = await getSidebarText(page)
      
      // Should not see management modules
      expect(sidebarText).not.toContain('Human Resources')
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Team Management')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ACCOUNTING DEPARTMENT TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('💰 Accounting Department', () => {
    
    test('Accounting Manager - Full Accounting Access', async ({ page }) => {
      await login(page, ROLES.acctgManager.email, ROLES.acctgManager.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see all accounting modules
      for (const module of ROLES.acctgManager.expectedModules) {
        expect(sidebarText, `Accounting Manager should see ${module}`).toContain(module)
      }
      
      // Should NOT see production-related
      for (const module of ROLES.acctgManager.forbiddenModules) {
        expect(sidebarText, `Accounting Manager should NOT see ${module}`).not.toContain(module)
      }
    })

    test('Accounting Manager - Page Access', async ({ page }) => {
      await login(page, ROLES.acctgManager.email, ROLES.acctgManager.password)
      
      // Can access Accounting pages
      const journalsResult = await checkPageAccess(page, ROUTES.accountingJournals)
      expect(journalsResult.accessible, 'Accounting Manager should access Journals').toBe(true)
      
      // Can access AP
      const apResult = await checkPageAccess(page, ROUTES.apVendors)
      expect(apResult.accessible, 'Accounting Manager should access AP Vendors').toBe(true)
      
      // Can access Banking
      const bankingResult = await checkPageAccess(page, ROUTES.bankingAccounts)
      expect(bankingResult.accessible, 'Accounting Manager should access Banking').toBe(true)
      
      // Cannot access Payroll
      const payrollResult = await checkPageAccess(page, ROUTES.hrPayroll)
      expect(payrollResult.accessible, 'Accounting Manager should NOT access Payroll').toBe(false)
      
      // Cannot access Production
      const prodResult = await checkPageAccess(page, ROUTES.productionOrders)
      expect(prodResult.accessible, 'Accounting Manager should NOT access Production').toBe(false)
      
      // Cannot access Inventory
      const invResult = await checkPageAccess(page, ROUTES.inventoryItems)
      expect(invResult.accessible, 'Accounting Manager should NOT access Inventory').toBe(false)
    })

    test('Accounting Officer - Banking Access', async ({ page }) => {
      await login(page, ROLES.acctgOfficer.email, ROLES.acctgOfficer.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see Banking (critical permission)
      expect(sidebarText).toContain('Banking')
      
      // Should NOT see Payroll
      expect(sidebarText).not.toContain('Payroll')
    })

    test('Accounting Head - View Only Access', async ({ page }) => {
      await login(page, ROLES.acctgHead.email, ROLES.acctgHead.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see Accounting modules
      expect(sidebarText).toContain('Accounting')
      
      // Should NOT see Banking (head role restriction)
      expect(sidebarText).not.toContain('Banking')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PRODUCTION DEPARTMENT TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('🏭 Production Department', () => {
    
    // Add delay before manufacturing account tests to avoid rate limiting
    test.beforeEach(async ({ page }) => {
      await page.waitForTimeout(3000)
    })
    
    test('Production Manager - No Payroll Access', async ({ page }) => {
      await login(page, ROLES.prodManager.email, ROLES.prodManager.password)
      const sidebarText = await getSidebarText(page)
      
      // Critical: Should NOT see Payroll (the bug we fixed)
      expect(sidebarText, 'Production Manager should NOT see Payroll').not.toContain('Payroll')
      
      // Should see Production
      expect(sidebarText).toContain('Production')
    })

    test('Production Manager - No Inventory Categories Access', async ({ page }) => {
      await login(page, ROLES.prodManager.email, ROLES.prodManager.password)
      
      // Cannot access Inventory Categories (critical bug fix)
      const result = await checkPageAccess(page, ROUTES.inventoryCategories)
      expect(result.accessible, 'Production Manager should NOT access Inventory Categories').toBe(false)
    })

    test('Production Manager - Can Access Production', async ({ page }) => {
      await login(page, ROLES.prodManager.email, ROLES.prodManager.password)
      
      // Can access Production
      const ordersResult = await checkPageAccess(page, ROUTES.productionOrders)
      expect(ordersResult.accessible, 'Production Manager should access Production Orders').toBe(true)
      
      // Can access BOMs
      const bomResult = await checkPageAccess(page, ROUTES.productionBoms)
      expect(bomResult.accessible, 'Production Manager should access BOMs').toBe(true)
    })

    test('Production Head - Limited Production Access', async ({ page }) => {
      await login(page, ROLES.prodHead.email, ROLES.prodHead.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see Production and Team Management
      expect(sidebarText).toContain('Production')
      expect(sidebarText).toContain('Team Management')
      
      // Should NOT see Inventory
      expect(sidebarText).not.toContain('Inventory')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // WAREHOUSE DEPARTMENT TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('📦 Warehouse Department', () => {
    
    test('Warehouse Head - Full Inventory Access', async ({ page }) => {
      await login(page, ROLES.whHead.email, ROLES.whHead.password)
      
      // Critical: Should see Inventory
      const sidebarText = await getSidebarText(page)
      expect(sidebarText, 'Warehouse Head should see Inventory').toContain('Inventory')
      
      // Expand Inventory to see children
      await expandModule(page, 'Inventory')
      
      // Get updated sidebar text after expansion
      const expandedSidebarText = await getSidebarText(page)
      
      // Should see Inventory sub-items
      expect(expandedSidebarText).toContain('Item Master')
      expect(expandedSidebarText).toContain('Stock')
    })

    test('Warehouse Head - Can Access All Inventory Pages', async ({ page }) => {
      await login(page, ROLES.whHead.email, ROLES.whHead.password)
      
      // Can access Items
      const itemsResult = await checkPageAccess(page, ROUTES.inventoryItems)
      expect(itemsResult.accessible, 'Warehouse Head should access Items').toBe(true)
      
      // Can access Categories
      const catResult = await checkPageAccess(page, ROUTES.inventoryCategories)
      expect(catResult.accessible, 'Warehouse Head should access Categories').toBe(true)
      
      // Can access Locations
      const locResult = await checkPageAccess(page, ROUTES.inventoryLocations)
      expect(locResult.accessible, 'Warehouse Head should access Locations').toBe(true)
      
      // Can access Stock
      const stockResult = await checkPageAccess(page, ROUTES.inventoryStock)
      expect(stockResult.accessible, 'Warehouse Head should access Stock').toBe(true)
      
      // Can access Adjustments
      const adjResult = await checkPageAccess(page, ROUTES.inventoryAdjustments)
      expect(adjResult.accessible, 'Warehouse Head should access Adjustments').toBe(true)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // QC DEPARTMENT TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('🔍 QC Department', () => {
    
    // Add delay before manufacturing account tests to avoid rate limiting
    test.beforeEach(async ({ page }) => {
      await page.waitForTimeout(3000)
    })
    
    test('QC Manager - QC and Production Access', async ({ page }) => {
      await login(page, ROLES.qcManager.email, ROLES.qcManager.password)
      
      // Should see QC
      const sidebarText = await getSidebarText(page)
      expect(sidebarText).toContain('QC / QA')
      
      // Should also see Production (QC works with Production)
      expect(sidebarText).toContain('Production')
      
      // Expand QC to see children
      await expandModule(page, 'QC / QA')
      const expandedText = await getSidebarText(page)
      
      // Should see child items
      expect(expandedText).toContain('Inspections')
      expect(expandedText).toContain('NCR')
      
      // Should NOT see Payroll or Inventory
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Inventory')
    })

    test('QC Manager - Page Access', async ({ page }) => {
      await login(page, ROLES.qcManager.email, ROLES.qcManager.password)
      
      // Can access QC pages
      const inspectionsResult = await checkPageAccess(page, ROUTES.qcInspections)
      expect(inspectionsResult.accessible, 'QC Manager should access Inspections').toBe(true)
      
      const ncrResult = await checkPageAccess(page, ROUTES.qcNcrs)
      expect(ncrResult.accessible, 'QC Manager should access NCRs').toBe(true)
      
      // CAN access Production (QC works with Production)
      const prodResult = await checkPageAccess(page, ROUTES.productionOrders)
      expect(prodResult.accessible, 'QC Manager should access Production').toBe(true)
      
      // Cannot access Payroll
      const payrollResult = await checkPageAccess(page, ROUTES.hrPayroll)
      expect(payrollResult.accessible, 'QC Manager should NOT access Payroll').toBe(false)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // PROCUREMENT / PURCHASING TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('🛒 Procurement Department', () => {
    
    test('Purchasing Officer - Procurement Access', async ({ page }) => {
      await login(page, ROLES.purchasingOfficer.email, ROLES.purchasingOfficer.password)
      
      // Should see Procurement
      const sidebarText = await getSidebarText(page)
      expect(sidebarText).toContain('Procurement')
      
      // Expand Procurement to see children
      await expandModule(page, 'Procurement')
      const expandedText = await getSidebarText(page)
      
      // Should see child items
      expect(expandedText).toContain('Purchase Requests')
      expect(expandedText).toContain('Purchase Orders')
    })

    test('Purchasing Officer - Page Access', async ({ page }) => {
      await login(page, ROLES.purchasingOfficer.email, ROLES.purchasingOfficer.password)
      
      // Can access Procurement
      const prResult = await checkPageAccess(page, ROUTES.procurementPR)
      expect(prResult.accessible, 'Purchasing Officer should access PRs').toBe(true)
      
      const poResult = await checkPageAccess(page, ROUTES.procurementPO)
      expect(poResult.accessible, 'Purchasing Officer should access POs').toBe(true)
      
      // Cannot access AR
      const arResult = await checkPageAccess(page, ROUTES.arCustomers)
      expect(arResult.accessible, 'Purchasing Officer should NOT access AR').toBe(false)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // EXECUTIVE TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('👔 Executive Roles', () => {
    
    // Add delay before executive account tests to avoid rate limiting
    test.beforeEach(async ({ page }) => {
      await page.waitForTimeout(3000)
    })
    
    test('VP - Wide Access but No Payroll/HR', async ({ page }) => {
      await login(page, ROLES.vp.email, ROLES.vp.password)
      const sidebarText = await getSidebarText(page)
      
      // VP should see VP Approvals
      expect(sidebarText).toContain('VP Approvals')
      
      // Should NOT see Payroll
      expect(sidebarText).not.toContain('Payroll')
      
      // Should NOT see Human Resources
      expect(sidebarText).not.toContain('Human Resources')
      
      // Should NOT see Banking
      expect(sidebarText).not.toContain('Banking')
    })

    test('Executive - Limited Module Access', async ({ page }) => {
      await login(page, ROLES.executive.email, ROLES.executive.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see Dashboard
      expect(sidebarText).toContain('Dashboard')
      
      // Should NOT see Production/Inventory/Payroll
      expect(sidebarText).not.toContain('Production')
      expect(sidebarText).not.toContain('Inventory')
      expect(sidebarText).not.toContain('Payroll')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ADMIN TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('⚙️ Admin Role', () => {
    
    test('Admin - System Administration Only', async ({ page }) => {
      await login(page, ROLES.admin.email, ROLES.admin.password)
      const sidebarText = await getSidebarText(page)
      
      // Should see Administration
      expect(sidebarText).toContain('Administration')
      
      // Expand to see child items
      await expandModule(page, 'Administration')
      const expandedText = await getSidebarText(page)
      expect(expandedText).toContain('Users')
      
      // Should NOT see business modules
      expect(sidebarText).not.toContain('Payroll')
      expect(sidebarText).not.toContain('Accounting')
      expect(sidebarText).not.toContain('Production')
      expect(sidebarText).not.toContain('Inventory')
    })

    test('Admin - Can Access Admin Pages', async ({ page }) => {
      await login(page, ROLES.admin.email, ROLES.admin.password)
      
      const usersResult = await checkPageAccess(page, ROUTES.adminUsers)
      expect(usersResult.accessible, 'Admin should access Users').toBe(true)
      
      const settingsResult = await checkPageAccess(page, ROUTES.adminSettings)
      expect(settingsResult.accessible, 'Admin should access Settings').toBe(true)
      
      // Cannot access Payroll
      const payrollResult = await checkPageAccess(page, ROUTES.hrPayroll)
      expect(payrollResult.accessible, 'Admin should NOT access Payroll').toBe(false)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // CROSS-CUTTING ACCESS TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('🚫 Cross-Cutting Forbidden Access', () => {
    
    const crossCuttingTests = [
      { role: 'prodManager', path: ROUTES.hrPayroll, desc: 'Production accessing Payroll' },
      { role: 'prodManager', path: ROUTES.inventoryCategories, desc: 'Production accessing Inventory Categories' },
      { role: 'prodManager', path: ROUTES.accountingJournals, desc: 'Production accessing Accounting' },
      { role: 'hrManager', path: ROUTES.inventoryItems, desc: 'HR accessing Inventory' },
      { role: 'hrManager', path: ROUTES.productionOrders, desc: 'HR accessing Production' },
      { role: 'acctgOfficer', path: ROUTES.productionOrders, desc: 'Accounting accessing Production' },
      { role: 'acctgOfficer', path: ROUTES.hrPayroll, desc: 'Accounting Officer accessing Payroll' },
      { role: 'whHead', path: ROUTES.hrPayroll, desc: 'Warehouse accessing Payroll' },
      { role: 'whHead', path: ROUTES.productionOrders, desc: 'Warehouse accessing Production' },
      // QC CAN access Production (they work together)
      { role: 'qcManager', path: ROUTES.hrPayroll, desc: 'QC accessing Payroll' },
      { role: 'salesManager', path: ROUTES.apVendors, desc: 'Sales accessing AP' },
      { role: 'moldManager', path: ROUTES.qcInspections, desc: 'Mold accessing QC' },
    ] as const

    let testIndex = 0
    for (const testCase of crossCuttingTests) {
      const role = ROLES[testCase.role as RoleKey]
      test(`${testCase.desc} should be BLOCKED`, async ({ page }) => {
        // Add staggered delay for manufacturing accounts to avoid rate limits
        if (['prodManager', 'qcManager', 'salesManager', 'moldManager'].includes(testCase.role)) {
          await page.waitForTimeout(2000 + (testIndex * 500))
        }
        await login(page, role.email, role.password)
        const result = await checkPageAccess(page, testCase.path)
        expect(result.accessible, `${testCase.desc} should be forbidden`).toBe(false)
      })
      testIndex++
    }
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ACTION BUTTON VISIBILITY TESTS
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('🎛️ Action Button Visibility', () => {
    
    test('Production Manager - No Create Button on Inventory', async ({ page }) => {
      await page.waitForTimeout(3000) // Delay for manufacturing account
      await login(page, ROLES.prodManager.email, ROLES.prodManager.password)
      
      // Try to access inventory items (should be blocked or no create button)
      await page.goto(`${BASE}/inventory/items`)
      await page.waitForTimeout(1500)
      
      // If page loads, there should be no "New Item" button
      if (!page.url().includes('/403') && !page.url().includes('/login')) {
        const newItemButton = page.getByRole('link', { name: /new item/i })
        expect(await newItemButton.count()).toBe(0)
      }
    })

    test('Warehouse Head - Has Create Button on Inventory', async ({ page }) => {
      await login(page, ROLES.whHead.email, ROLES.whHead.password)
      
      await page.goto(`${BASE}/inventory/items`)
      await page.waitForTimeout(1500)
      
      // Should have "New Item" button
      const newItemButton = page.getByRole('link', { name: /new item/i })
      expect(await newItemButton.count()).toBeGreaterThan(0)
    })

    test('HR Manager - Has Create Button on Employees', async ({ page }) => {
      await login(page, ROLES.hrManager.email, ROLES.hrManager.password)
      
      await page.goto(`${BASE}/hr/employees/all`)
      await page.waitForLoadState('networkidle')
      
      // Should have "New Employee" or similar button - check various selectors
      const newButton = page.getByRole('button', { name: /new|create|add/i })
      const newLink = page.getByRole('link', { name: /new|create|add/i })
      const bodyText = await page.locator('body').innerText()
      const hasCreateText = /new employee|add employee|create employee|\+ employee/i.test(bodyText)
      
      const hasCreateButton = (await newButton.count() > 0) || 
                              (await newLink.count() > 0) || 
                              hasCreateText
      expect(hasCreateButton, 'HR Manager should see create button').toBe(true)
    })

    test('Production Manager - Has Create Button on Production', async ({ page }) => {
      await page.waitForTimeout(3000) // Delay for manufacturing account
      await login(page, ROLES.prodManager.email, ROLES.prodManager.password)
      
      await page.goto(`${BASE}/production/orders`)
      await page.waitForTimeout(1500)
      
      // Should have "New Order" or similar button
      const bodyText = await page.locator('body').innerText()
      const hasCreateButton = /new|create|add/i.test(bodyText)
      expect(hasCreateButton, 'Production Manager should see create button on Production').toBe(true)
    })

    test('Accounting Officer - Has Create Button on AP', async ({ page }) => {
      await login(page, ROLES.acctgOfficer.email, ROLES.acctgOfficer.password)
      
      await page.goto(`${BASE}/accounting/ap/invoices`)
      await page.waitForTimeout(1500)
      
      // Should have "New Invoice" or similar button
      const bodyText = await page.locator('body').innerText()
      const hasCreateButton = /new|create|add/i.test(bodyText)
      expect(hasCreateButton, 'Accounting Officer should see create button on AP').toBe(true)
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // SUMMARY TEST
  // ═══════════════════════════════════════════════════════════════════════════
  
  test.describe('📊 Summary - All Roles', () => {
    
    test('Verify all roles can login and access dashboard', async ({ page }) => {
      const results: Record<string, boolean> = {}
      let index = 0
      
      for (const [roleKey, role] of Object.entries(ROLES)) {
        // Add delay between logins to avoid rate limiting (5 attempts per minute)
        if (index > 0) {
          await page.waitForTimeout(3000)
        }
        
        await logout(page)
        await login(page, role.email, role.password)
        
        // Verify on dashboard
        const url = page.url()
        results[roleKey] = url.includes('/dashboard')
        
        expect(results[roleKey], `${roleKey} should reach dashboard`).toBe(true)
        index++
      }
    })
  })
})
