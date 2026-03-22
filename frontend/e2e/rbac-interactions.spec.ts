/**
 * E2E-RBAC-INTERACTIONS — Page Interaction Tests by Role
 *
 * Tests that users can perform CRUD actions on pages they have access to.
 * Verifies buttons, forms, and actions are available based on permissions.
 */
import { test, expect, Page } from '@playwright/test'

const BASE = 'http://localhost:5173'

const ACCOUNTS = {
  hrManager: { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!' },
  acctgManager: { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!' },
  prodManager: { email: 'prod.manager@ogamierp.local', password: 'Manager@12345!' },
  whHead: { email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!' },
  purchasingOfficer: { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!' },
  qcManager: { email: 'qc.manager@ogamierp.local', password: 'Manager@12345!' },
} as const

async function login(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
}

async function logout(page: Page) {
  await page.evaluate(() => localStorage.clear())
}

// ═══════════════════════════════════════════════════════════════════════════════
// HR MANAGER — Interactions
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('👤 HR Manager — Page Interactions', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
  })

  test('Can create new employee', async ({ page }) => {
    await page.goto(`${BASE}/hr/employees/new`)
    await page.waitForTimeout(1000)

    // Should see form
    await expect(page.locator('form')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()

    // Fill basic info
    await page.locator('input[name="first_name"]').fill('Test')
    await page.locator('input[name="last_name"]').fill('Employee')
    await page.locator('input[name="email"]').fill(`test${Date.now()}@example.com`)

    // Should have department dropdown
    await expect(page.locator('select[name="department_id"]')).toBeVisible()
  })

  test('Can navigate through employee list', async ({ page }) => {
    await page.goto(`${BASE}/hr/employees`)
    await page.waitForTimeout(1500)

    // Should see employee table
    await expect(page.locator('table, [role="table"]')).toBeVisible()

    // Should see "New Employee" button
    await expect(page.locator('a[href*="/new"], button:has-text("New")')).toBeVisible()
  })

  test('Can access payroll run creation', async ({ page }) => {
    await page.goto(`${BASE}/payroll/runs`)
    await page.waitForTimeout(1500)

    // Should see "New Run" button
    await expect(page.locator('a[href*="/new"], button:has-text("New")')).toBeVisible()
  })

  test('Can view attendance logs', async ({ page }) => {
    await page.goto(`${BASE}/hr/attendance`)
    await page.waitForTimeout(1500)

    // Should see attendance data
    await expect(page.locator('table, [role="table"], .data-grid')).toBeVisible()
  })

  test('Can manage leave requests', async ({ page }) => {
    await page.goto(`${BASE}/hr/leave`)
    await page.waitForTimeout(1500)

    // Should see leave list
    await expect(page.locator('table, [role="table"], .leave-list')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// ACCOUNTING MANAGER — Interactions
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('💰 Accounting Manager — Page Interactions', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
  })

  test('Can create journal entries', async ({ page }) => {
    await page.goto(`${BASE}/accounting/journal-entries/new`)
    await page.waitForTimeout(1000)

    // Should see journal entry form
    await expect(page.locator('form')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('Can manage vendors', async ({ page }) => {
    await page.goto(`${BASE}/accounting/vendors`)
    await page.waitForTimeout(1500)

    // Should see vendor list
    await expect(page.locator('table, [role="table"]')).toBeVisible()

    // Should be able to add vendor
    await expect(page.locator('a[href*="/new"], button:has-text("New")')).toBeVisible()
  })

  test('Can create AP invoices', async ({ page }) => {
    await page.goto(`${BASE}/accounting/ap/invoices/new`)
    await page.waitForTimeout(1000)

    // Should see invoice form
    await expect(page.locator('form')).toBeVisible()
  })

  test('Can access bank accounts', async ({ page }) => {
    await page.goto(`${BASE}/banking/accounts`)
    await page.waitForTimeout(1500)

    // Should see bank accounts
    await expect(page.locator('table, [role="table"], .bank-list')).toBeVisible()
  })

  test('Can view financial reports', async ({ page }) => {
    await page.goto(`${BASE}/accounting/trial-balance`)
    await page.waitForTimeout(2000)

    // Should see report
    await expect(page.locator('table, .report-content, [role="table"]')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PRODUCTION MANAGER — Interactions
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🏭 Production Manager — Page Interactions', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
  })

  test('Can create production orders', async ({ page }) => {
    await page.goto(`${BASE}/production/orders/new`)
    await page.waitForTimeout(1000)

    // Should see production order form
    await expect(page.locator('form')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('Can manage BOMs', async ({ page }) => {
    await page.goto(`${BASE}/production/boms`)
    await page.waitForTimeout(1500)

    // Should see BOM list
    await expect(page.locator('table, [role="table"]')).toBeVisible()

    // Should see Create BOM button
    await expect(page.locator('a[href*="/new"], button:has-text("New")')).toBeVisible()
  })

  test('Can view production orders list', async ({ page }) => {
    await page.goto(`${BASE}/production/orders`)
    await page.waitForTimeout(1500)

    // Should see orders table
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })

  test('Can manage delivery schedules', async ({ page }) => {
    await page.goto(`${BASE}/production/delivery-schedules`)
    await page.waitForTimeout(1500)

    // Should see schedules
    await expect(page.locator('table, [role="table"], .schedule-list')).toBeVisible()
  })

  test('CANNOT access payroll (interaction blocked)', async ({ page }) => {
    // Try to navigate to payroll
    await page.goto(`${BASE}/payroll/runs`)
    await page.waitForTimeout(1000)

    // Should be redirected or show forbidden
    const url = page.url()
    expect(url).not.toContain('/payroll/runs')
  })

  test('CANNOT access inventory categories', async ({ page }) => {
    await page.goto(`${BASE}/inventory/categories`)
    await page.waitForTimeout(1000)

    const url = page.url()
    expect(url).not.toContain('/inventory/categories')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// WAREHOUSE HEAD — Interactions
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📦 Warehouse Head — Page Interactions', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
  })

  test('Can manage item categories', async ({ page }) => {
    await page.goto(`${BASE}/inventory/categories`)
    await page.waitForTimeout(1500)

    // Should see categories list
    await expect(page.locator('table, [role="table"], .category-list')).toBeVisible()

    // Should have add button
    await expect(page.locator('button:has-text("Add"), button:has-text("New")')).toBeVisible()
  })

  test('Can manage warehouse locations', async ({ page }) => {
    await page.goto(`${BASE}/inventory/locations`)
    await page.waitForTimeout(1500)

    // Should see locations
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })

  test('Can view stock balances', async ({ page }) => {
    await page.goto(`${BASE}/inventory/stock`)
    await page.waitForTimeout(1500)

    // Should see stock data
    await expect(page.locator('table, [role="table"], .stock-list')).toBeVisible()
  })

  test('Can create stock adjustments', async ({ page }) => {
    await page.goto(`${BASE}/inventory/adjustments`)
    await page.waitForTimeout(1500)

    // Should see adjustment form/buttons
    await expect(page.locator('button:has-text("Adjust"), button:has-text("New")')).toBeVisible()
  })

  test('Can manage material requisitions', async ({ page }) => {
    await page.goto(`${BASE}/inventory/requisitions`)
    await page.waitForTimeout(1500)

    // Should see requisitions
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PURCHASING OFFICER — Interactions
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🛒 Purchasing Officer — Page Interactions', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
    await login(page, ACCOUNTS.purchasingOfficer.email, ACCOUNTS.purchasingOfficer.password)
  })

  test('Can create purchase requests', async ({ page }) => {
    await page.goto(`${BASE}/procurement/purchase-requests/new`)
    await page.waitForTimeout(1000)

    // Should see PR form
    await expect(page.locator('form')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('Can view purchase requests list', async ({ page }) => {
    await page.goto(`${BASE}/procurement/purchase-requests`)
    await page.waitForTimeout(1500)

    // Should see PR list
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })

  test('Can manage purchase orders', async ({ page }) => {
    await page.goto(`${BASE}/procurement/purchase-orders`)
    await page.waitForTimeout(1500)

    // Should see PO list
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })

  test('Can view goods receipts', async ({ page }) => {
    await page.goto(`${BASE}/procurement/goods-receipts`)
    await page.waitForTimeout(1500)

    // Should see GR list
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// QC MANAGER — Interactions
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔍 QC Manager — Page Interactions', () => {

  test.beforeEach(async ({ page }) => {
    await logout(page)
    await login(page, ACCOUNTS.qcManager.email, ACCOUNTS.qcManager.password)
  })

  test('Can create inspections', async ({ page }) => {
    await page.goto(`${BASE}/qc/inspections/new`)
    await page.waitForTimeout(1000)

    // Should see inspection form
    await expect(page.locator('form')).toBeVisible()
  })

  test('Can manage NCRs', async ({ page }) => {
    await page.goto(`${BASE}/qc/ncrs`)
    await page.waitForTimeout(1500)

    // Should see NCR list
    await expect(page.locator('table, [role="table"]')).toBeVisible()

    // Should see New NCR button
    await expect(page.locator('a[href*="/new"], button:has-text("New")')).toBeVisible()
  })

  test('Can view inspection list', async ({ page }) => {
    await page.goto(`${BASE}/qc/inspections`)
    await page.waitForTimeout(1500)

    // Should see inspections
    await expect(page.locator('table, [role="table"]')).toBeVisible()
  })

  test('Can access CAPA', async ({ page }) => {
    await page.goto(`${BASE}/qc/capa`)
    await page.waitForTimeout(1500)

    // Should see CAPA list
    await expect(page.locator('table, [role="table"], .capa-list')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// SIDEBAR NAVIGATION TESTS — Expanded State
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📋 Sidebar Navigation — Expanded State', () => {

  test('HR Manager sees correct menu structure', async ({ page }) => {
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)

    // Expand each section and verify children
    const sections = [
      { label: 'Team Management', children: ['My Team', 'Team Attendance', 'Team Leave'] },
      { label: 'Human Resources', children: ['All Employees', 'Attendance Logs', 'Leave Requests'] },
      { label: 'Payroll', children: ['Payroll Runs', 'Pay Periods'] },
    ]

    for (const section of sections) {
      // Click to expand
      await page.click(`text=${section.label}`)
      await page.waitForTimeout(300)

      // Check children exist
      for (const child of section.children) {
        await expect(page.locator(`text=${child}`)).toBeVisible()
      }
    }
  })

  test('Accounting Manager sees correct menu structure', async ({ page }) => {
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)

    const sections = [
      { label: 'Accounting', children: ['Chart of Accounts', 'Journal Entries', 'General Ledger'] },
      { label: 'Banking', children: ['Bank Accounts', 'Reconciliations'] },
      { label: 'Payables (AP)', children: ['Vendors', 'Invoices', 'Credit Notes'] },
      { label: 'Receivables (AR)', children: ['Customers', 'Invoices'] },
    ]

    for (const section of sections) {
      await page.click(`text=${section.label}`)
      await page.waitForTimeout(300)

      for (const child of section.children) {
        await expect(page.locator(`text=${child}`)).toBeVisible()
      }
    }
  })

  test('Production Manager sees correct menu structure', async ({ page }) => {
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)

    const sections = [
      { label: 'Production', children: ['Bill of Materials', 'Delivery Schedules', 'Work Orders'] },
      { label: 'QC / QA', children: ['Inspections', 'NCR', 'CAPA'] },
      { label: 'Maintenance', children: ['Equipment', 'Work Orders'] },
      { label: 'Mold', children: ['Mold Masters'] },
      { label: 'Delivery', children: ['Receipts', 'Shipments'] },
      { label: 'ISO / IATF', children: ['Documents', 'Audits'] },
    ]

    for (const section of sections) {
      await page.click(`text=${section.label}`)
      await page.waitForTimeout(300)

      for (const child of section.children) {
        await expect(page.locator(`text=${child}`)).toBeVisible()
      }
    }

    // CRITICAL: Verify NO Payroll section
    await expect(page.locator('text=Payroll')).not.toBeVisible()
  })

  test('Warehouse Head sees correct menu structure', async ({ page }) => {
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)

    // Expand Inventory
    await page.click('text=Inventory')
    await page.waitForTimeout(300)

    // Should see management options
    const managementOptions = ['Item Categories', 'Warehouse Locations', 'Item Master', 'Stock Balances']
    for (const option of managementOptions) {
      await expect(page.locator(`text=${option}`)).toBeVisible()
    }
  })
})
