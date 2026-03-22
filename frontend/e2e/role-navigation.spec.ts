/**
 * E2E-ROLE-NAV — Role-Based Navigation Tests
 *
 * Verifies that each role sees ONLY the modules they have permission for.
 * This ensures RBAC v2 is correctly implemented in the frontend.
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'
const API  = 'http://localhost:8000'

// ── Test Accounts ─────────────────────────────────────────────────────────────
const TEST_ACCOUNTS = {
  // HR Department
  hrManager: { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!', dept: 'HR', role: 'manager' },
  hrHead: { email: 'hr.head@ogamierp.local', password: 'Head@Test1234!', dept: 'HR', role: 'head' },
  hrStaff: { email: 'hr.staff@ogamierp.local', password: 'Staff@Test1234!', dept: 'HR', role: 'staff' },
  
  // Accounting Department
  acctgManager: { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!', dept: 'ACCTG', role: 'manager' },
  acctgOfficer: { email: 'acctg.officer@ogamierp.local', password: 'Officer@Test1234!', dept: 'ACCTG', role: 'officer' },
  acctgHead: { email: 'acctg.head@ogamierp.local', password: 'Head@Test1234!', dept: 'ACCTG', role: 'head' },
  
  // Production Department
  prodManager: { email: 'prod.manager@ogamierp.local', password: 'Manager@12345!', dept: 'PROD', role: 'manager' },
  prodHead: { email: 'production.head@ogamierp.local', password: 'Head@123456789!', dept: 'PROD', role: 'head' },
  prodStaff: { email: 'prod.staff@ogamierp.local', password: 'Staff@123456789!', dept: 'PROD', role: 'staff' },
  
  // Warehouse Department
  whHead: { email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!', dept: 'WH', role: 'head' },
  
  // Other Departments
  plantManager: { email: 'plant.manager@ogamierp.local', password: 'Manager@12345!', dept: 'PLANT', role: 'manager' },
  qcManager: { email: 'qc.manager@ogamierp.local', password: 'Manager@12345!', dept: 'QC', role: 'manager' },
  moldManager: { email: 'mold.manager@ogamierp.local', password: 'Manager@12345!', dept: 'MOLD', role: 'manager' },
  salesManager: { email: 'crm.manager@ogamierp.local', password: 'Manager@12345!', dept: 'SALES', role: 'manager' },
  purchasingOfficer: { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!', dept: 'PPC', role: 'officer' },
  
  // Executive
  vp: { email: 'vp@ogamierp.local', password: 'VicePresident@1!', dept: 'EXEC', role: 'vice_president' },
  executive: { email: 'executive@ogamierp.local', password: 'Executive@Test1234!', dept: 'EXEC', role: 'executive' },
  
  // Admin
  admin: { email: 'admin@ogamierp.local', password: 'Admin@1234567890!', dept: null, role: 'admin' },
}

// ── Expected Navigation by Role ───────────────────────────────────────────────
const EXPECTED_NAV = {
  // HR Manager should see: HR modules, Payroll
  hrManager: {
    shouldSee: ['Dashboard', 'Team Management', 'Payroll', 'Reports', 'Self Service'],
    shouldNotSee: ['Accounting', 'Production', 'QC / QA', 'Inventory'],
  },
  
  // Accounting Manager should see: Accounting, Banking, Payroll (approve only)
  acctgManager: {
    shouldSee: ['Dashboard', 'Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Financial Reports', 'Fixed Assets', 'Budget', 'Reports'],
    shouldNotSee: ['Payroll', 'Production', 'QC / QA', 'Inventory'],
  },
  
  // Accounting Officer should see: Accounting, Banking (NO Payroll runs)
  acctgOfficer: {
    shouldSee: ['Dashboard', 'Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Financial Reports', 'Fixed Assets', 'Budget'],
    shouldNotSee: ['Payroll', 'Production', 'QC / QA', 'Inventory'],
  },
  
  // Production Manager should see: Production, QC, Mold, Maintenance, Delivery, ISO (NO Payroll, NO Inventory categories)
  prodManager: {
    shouldSee: ['Dashboard', 'Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    shouldNotSee: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  
  // Production Head should see: Production modules only
  prodHead: {
    shouldSee: ['Dashboard', 'Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    shouldNotSee: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  
  // Production Staff should see: Minimal - Production orders only
  prodStaff: {
    shouldSee: ['Dashboard', 'Production'],
    shouldNotSee: ['Payroll', 'Accounting', 'Banking', 'Inventory', 'QC / QA', 'Maintenance', 'Mold'],
  },
  
  // Warehouse Head should see: Inventory (full), NO Production
  whHead: {
    shouldSee: ['Dashboard', 'Inventory'],
    shouldNotSee: ['Payroll', 'Accounting', 'Banking', 'Production', 'QC / QA'],
  },
  
  // Plant Manager should see: Production, QC, Maintenance, Mold, Delivery
  plantManager: {
    shouldSee: ['Dashboard', 'Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
    shouldNotSee: ['Payroll', 'Accounting', 'Banking'],
  },
  
  // QC Manager should see: QC, Production view
  qcManager: {
    shouldSee: ['Dashboard', 'QC / QA', 'Production'],
    shouldNotSee: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
  },
  
  // Purchasing Officer should see: Procurement, Vendors
  purchasingOfficer: {
    shouldSee: ['Dashboard', 'Procurement'],
    shouldNotSee: ['Payroll', 'Accounting', 'Production', 'Inventory'],
  },
  
  // Sales Manager should see: CRM, Customers
  salesManager: {
    shouldSee: ['Dashboard', 'CRM'],
    shouldNotSee: ['Payroll', 'Accounting', 'Production', 'Inventory', 'Procurement'],
  },
  
  // VP should see: VP Approvals, Reports
  vp: {
    shouldSee: ['Dashboard', 'VP Approvals'],
    shouldNotSee: ['Payroll', 'Accounting', 'Production'],
  },
  
  // Executive should see: Reports only (read-only)
  executive: {
    shouldSee: ['Dashboard', 'Reports'],
    shouldNotSee: ['Payroll', 'Accounting', 'Production', 'Procurement'],
  },
  
  // Admin should see: Administration
  admin: {
    shouldSee: ['Dashboard', 'Administration'],
    shouldNotSee: [],  // Admin sees limited but can access most
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

async function login(page: any, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
  // Wait for sidebar to load
  await page.waitForSelector('nav', { timeout: 10000 })
}

async function getVisibleNavItems(page: any): Promise<string[]> {
  const items = await page.locator('nav >> .group > button, nav >> a[href^="/"]').allInnerTexts()
  return items.map((i: string) => i.trim()).filter((i: string) => i.length > 0)
}

// ── Test Suite ─────────────────────────────────────────────────────────────────

test.describe('Role-Based Navigation (RBAC v2)', () => {
  
  test.describe('HR Department', () => {
    
    test('HR Manager sees Payroll and Team Management', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.hrManager.email, TEST_ACCOUNTS.hrManager.password)
      const visibleItems = await getVisibleNavItems(page)
      
      for (const item of EXPECTED_NAV.hrManager.shouldSee) {
        expect(visibleItems.some(v => v.includes(item)), `Should see ${item}`).toBe(true)
      }
      
      for (const item of EXPECTED_NAV.hrManager.shouldNotSee) {
        expect(visibleItems.some(v => v.includes(item)), `Should NOT see ${item}`).toBe(false)
      }
    })
    
    test('HR Head sees Team Management but NOT Payroll management', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.hrHead.email, TEST_ACCOUNTS.hrHead.password)
      const visibleItems = await getVisibleNavItems(page)
      
      // Head should see Team Management
      expect(visibleItems.some(v => v.includes('Team Management'))).toBe(true)
      // Head should NOT see Payroll Runs (only view own payslip via self-service)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
    })
    
    test('HR Staff sees minimal navigation', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.hrStaff.email, TEST_ACCOUNTS.hrStaff.password)
      const visibleItems = await getVisibleNavItems(page)
      
      // Staff should only see Self Service, not management modules
      expect(visibleItems.some(v => v.includes('Team Management'))).toBe(false)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
    })
  })
  
  test.describe('Accounting Department', () => {
    
    test('Accounting Manager sees Banking and Financial Reports', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.acctgManager.email, TEST_ACCOUNTS.acctgManager.password)
      const visibleItems = await getVisibleNavItems(page)
      
      for (const item of EXPECTED_NAV.acctgManager.shouldSee) {
        expect(visibleItems.some(v => v.includes(item)), `Should see ${item}`).toBe(true)
      }
      
      for (const item of EXPECTED_NAV.acctgManager.shouldNotSee) {
        expect(visibleItems.some(v => v.includes(item)), `Should NOT see ${item}`).toBe(false)
      }
    })
    
    test('Accounting Officer sees Banking', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.acctgOfficer.email, TEST_ACCOUNTS.acctgOfficer.password)
      const visibleItems = await getVisibleNavItems(page)
      
      // Officer should see Banking (fixed!)
      expect(visibleItems.some(v => v.includes('Banking')), 'Should see Banking menu').toBe(true)
      
      // Officer should NOT see Payroll
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
      
      // Officer should NOT see Production
      expect(visibleItems.some(v => v.includes('Production'))).toBe(false)
    })
    
    test('Accounting Head sees limited accounting view', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.acctgHead.email, TEST_ACCOUNTS.acctgHead.password)
      const visibleItems = await getVisibleNavItems(page)
      
      // Head should see Accounting view
      expect(visibleItems.some(v => v.includes('Accounting'))).toBe(true)
      // Head should see Banking
      expect(visibleItems.some(v => v.includes('Banking'))).toBe(true)
      // Head should NOT see Payroll
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
    })
  })
  
  test.describe('Production Department', () => {
    
    test('Production Manager sees Production modules but NOT Payroll', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.prodManager.email, TEST_ACCOUNTS.prodManager.password)
      const visibleItems = await getVisibleNavItems(page)
      
      for (const item of EXPECTED_NAV.prodManager.shouldSee) {
        expect(visibleItems.some(v => v.includes(item)), `Should see ${item}`).toBe(true)
      }
      
      // Critical: Production should NOT see Payroll
      expect(visibleItems.some(v => v.includes('Payroll')), 'Should NOT see Payroll').toBe(false)
      
      // Production should NOT see Inventory management
      expect(visibleItems.some(v => v.includes('Inventory')), 'Should NOT see Inventory').toBe(false)
    })
    
    test('Production Head sees Production modules', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.prodHead.email, TEST_ACCOUNTS.prodHead.password)
      const visibleItems = await getVisibleNavItems(page)
      
      expect(visibleItems.some(v => v.includes('Production'))).toBe(true)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
      expect(visibleItems.some(v => v.includes('Inventory'))).toBe(false)
    })
    
    test('Production Staff sees only Production', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.prodStaff.email, TEST_ACCOUNTS.prodStaff.password)
      const visibleItems = await getVisibleNavItems(page)
      
      expect(visibleItems.some(v => v.includes('Production'))).toBe(true)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
      expect(visibleItems.some(v => v.includes('Accounting'))).toBe(false)
    })
  })
  
  test.describe('Warehouse Department', () => {
    
    test('Warehouse Head sees Inventory management', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.whHead.email, TEST_ACCOUNTS.whHead.password)
      const visibleItems = await getVisibleNavItems(page)
      
      // WH Head should see Inventory
      expect(visibleItems.some(v => v.includes('Inventory')), 'Should see Inventory').toBe(true)
      
      // Expand Inventory and check for Categories
      const inventoryBtn = page.locator('button', { hasText: 'Inventory' })
      if (await inventoryBtn.count() > 0) {
        await inventoryBtn.click()
        await page.waitForTimeout(500)
        const pageContent = await page.content()
        expect(pageContent).toContain('Item Categories')
        expect(pageContent).toContain('Warehouse Locations')
      }
      
      // WH should NOT see Production
      expect(visibleItems.some(v => v.includes('Production'))).toBe(false)
      // WH should NOT see Payroll
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
    })
  })
  
  test.describe('Procurement & Sales', () => {
    
    test('Purchasing Officer sees Procurement', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.purchasingOfficer.email, TEST_ACCOUNTS.purchasingOfficer.password)
      const visibleItems = await getVisibleNavItems(page)
      
      expect(visibleItems.some(v => v.includes('Procurement'))).toBe(true)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
      expect(visibleItems.some(v => v.includes('Production'))).toBe(false)
    })
    
    test('Sales Manager sees CRM', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.salesManager.email, TEST_ACCOUNTS.salesManager.password)
      const visibleItems = await getVisibleNavItems(page)
      
      expect(visibleItems.some(v => v.includes('CRM'))).toBe(true)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
      expect(visibleItems.some(v => v.includes('Procurement'))).toBe(false)
    })
  })
  
  test.describe('Executive Roles', () => {
    
    test('VP sees VP Approvals dashboard', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.vp.email, TEST_ACCOUNTS.vp.password)
      const visibleItems = await getVisibleNavItems(page)
      
      expect(visibleItems.some(v => v.includes('VP Approvals'))).toBe(true)
    })
    
    test('Executive sees Reports only', async ({ page }) => {
      await login(page, TEST_ACCOUNTS.executive.email, TEST_ACCOUNTS.executive.password)
      const visibleItems = await getVisibleNavItems(page)
      
      expect(visibleItems.some(v => v.includes('Reports'))).toBe(true)
      expect(visibleItems.some(v => v.includes('Payroll'))).toBe(false)
      expect(visibleItems.some(v => v.includes('Accounting'))).toBe(false)
    })
  })
})

// ── Inventory Sub-Menu Tests ───────────────────────────────────────────────────

test.describe('Inventory Module - Permission Details', () => {
  
  test('Warehouse Head can see Item Categories and Warehouse Locations', async ({ page }) => {
    await login(page, TEST_ACCOUNTS.whHead.email, TEST_ACCOUNTS.whHead.password)
    
    // Click on Inventory to expand
    await page.click('text=Inventory')
    await page.waitForTimeout(500)
    
    // Should see Categories and Locations
    await expect(page.locator('text=Item Categories')).toBeVisible()
    await expect(page.locator('text=Warehouse Locations')).toBeVisible()
  })
  
  test('Production Manager CANNOT see Item Categories (read-only inventory)', async ({ page }) => {
    await login(page, TEST_ACCOUNTS.prodManager.email, TEST_ACCOUNTS.prodManager.password)
    
    // Production should NOT see Inventory menu at all
    const visibleItems = await getVisibleNavItems(page)
    expect(visibleItems.some(v => v.includes('Inventory'))).toBe(false)
    
    // Try to access inventory URL directly - should be blocked
    await page.goto(`${BASE}/inventory/categories`)
    // Should show forbidden or redirect
    await expect(page.locator('body')).toContainText(/forbidden|unauthorized|access denied/i)
  })
})
