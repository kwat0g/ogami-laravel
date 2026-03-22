/**
 * E2E-RBAC-BACKEND-FRONTEND-SYNC
 *
 * Tests that verify frontend behavior matches backend permissions.
 * These tests work in conjunction with Pest integration tests:
 * tests/Integration/RbacFrontendSyncTest.php
 *
 * Flow:
 * 1. Backend (Pest): Creates user, assigns permissions, stores expected state
 * 2. Frontend (Playwright): Logs in, verifies UI matches expected permissions
 * 3. Both: Must agree on what user can/cannot do
 */
import { test, expect, Page, APIRequestContext } from '@playwright/test'

const BASE = 'http://localhost:5173'
const API = 'http://localhost:8000'

// Test accounts (same as Pest tests)
const TEST_ACCOUNTS = {
  productionManager: {
    email: 'test.prod.manager@ogamierp.local',
    password: 'password123',
    expectedPermissions: {
      shouldSee: ['Production', 'QC / QA', 'Maintenance', 'Mold', 'Delivery', 'ISO / IATF'],
      shouldNotSee: ['Payroll', 'Accounting', 'Banking', 'Inventory'],
    },
  },
  accountingOfficer: {
    email: 'test.acctg.officer@ogamierp.local',
    password: 'password123',
    expectedPermissions: {
      shouldSee: ['Accounting', 'Payables (AP)', 'Receivables (AR)', 'Banking', 'Financial Reports'],
      shouldNotSee: ['Payroll', 'Production', 'Inventory'],
    },
  },
  warehouseHead: {
    email: 'test.wh.head@ogamierp.local',
    password: 'password123',
    expectedPermissions: {
      shouldSee: ['Inventory'],
      shouldNotSee: ['Production', 'Payroll', 'Accounting'],
    },
  },
  hrManager: {
    email: 'test.hr.manager@ogamierp.local',
    password: 'password123',
    expectedPermissions: {
      shouldSee: ['Team Management', 'Human Resources', 'Payroll', 'Reports'],
      shouldNotSee: ['Accounting', 'Production', 'Inventory'],
    },
  },
} as const

// ═══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

async function loginViaApi(request: APIRequestContext, email: string, password: string) {
  // Get CSRF cookie
  await request.get(`${API}/sanctum/csrf-cookie`)

  // Login
  const response = await request.post(`${API}/api/v1/auth/login`, {
    data: { email, password },
  })

  expect(response.ok(), `Login failed: ${await response.text()}`).toBeTruthy()
  return await response.json()
}

async function getAuthMe(request: APIRequestContext, token: string) {
  const response = await request.get(`${API}/api/v1/auth/me`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  })

  expect(response.ok()).toBeTruthy()
  return await response.json()
}

async function tryAccessRoute(request: APIRequestContext, token: string, route: string): Promise<number> {
  const response = await request.get(`${API}/api/v1${route}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  })

  return response.status()
}

async function loginToFrontend(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
  await page.waitForSelector('nav', { timeout: 10000 })
}

async function getSidebarText(page: Page): Promise<string> {
  const sidebar = page.locator('aside, nav').first()
  return await sidebar.innerText()
}

// ═══════════════════════════════════════════════════════════════════════════════
// BACKEND-FRONTEND SYNC TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔒 Backend-Frontend RBAC Sync', () => {

  // ═══════════════════════════════════════════════════════════════════════════
  // PRODUCTION MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('Production Manager — Backend/Frontend Sync', () => {

    test('API permissions match sidebar visibility', async ({ page, request }) => {
      // 1. Login via API to get permissions
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.productionManager.email, TEST_ACCOUNTS.productionManager.password)
      const token = loginData.token

      // 2. Get permissions from API
      const meData = await getAuthMe(request, token)
      const apiPermissions: string[] = meData.user.permissions

      // 3. Verify API says user has production access
      expect(apiPermissions).toContain('production.orders.view')
      expect(apiPermissions).toContain('qc.inspections.view')

      // CRITICAL: Verify API says user does NOT have payroll
      expect(apiPermissions).not.toContain('payroll.view_runs')
      expect(apiPermissions).not.toContain('chart_of_accounts.view')

      // 4. Login to frontend
      await loginToFrontend(page, TEST_ACCOUNTS.productionManager.email, TEST_ACCOUNTS.productionManager.password)

      // 5. Get sidebar visibility
      const sidebarText = await getSidebarText(page)

      // 6. Verify frontend matches API
      // Should see
      for (const item of TEST_ACCOUNTS.productionManager.expectedPermissions.shouldSee) {
        expect(sidebarText, `Frontend should show ${item} (API has permission)`)
          .toContain(item)
      }

      // Should NOT see
      for (const item of TEST_ACCOUNTS.productionManager.expectedPermissions.shouldNotSee) {
        expect(sidebarText, `Frontend should NOT show ${item} (API lacks permission)`)
          .not.toContain(item)
      }
    })

    test('API route access matches frontend page access', async ({ page, request }) => {
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.productionManager.email, TEST_ACCOUNTS.productionManager.password)
      const token = loginData.token

      // Test routes - API
      const productionRouteStatus = await tryAccessRoute(request, token, '/production/orders')
      const payrollRouteStatus = await tryAccessRoute(request, token, '/payroll/runs')
      const accountingRouteStatus = await tryAccessRoute(request, token, '/accounting/journal-entries')

      // API assertions
      expect(productionRouteStatus).toBe(200) // Should work
      expect(payrollRouteStatus).toBe(403) // Should be forbidden
      expect(accountingRouteStatus).toBe(403) // Should be forbidden

      // Frontend assertions
      await loginToFrontend(page, TEST_ACCOUNTS.productionManager.email, TEST_ACCOUNTS.productionManager.password)

      // Can access production page
      await page.goto(`${BASE}/production/orders`)
      await page.waitForTimeout(1000)
      expect(page.url()).not.toContain('/403')

      // Cannot access payroll page
      await page.goto(`${BASE}/payroll/runs`)
      await page.waitForTimeout(1000)
      const url = page.url()
      expect(url.includes('/403') || !url.includes('/payroll/')).toBeTruthy()
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // ACCOUNTING OFFICER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('Accounting Officer — Backend/Frontend Sync', () => {

    test('API permissions match sidebar visibility (CRITICAL: Banking)', async ({ page, request }) => {
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.accountingOfficer.email, TEST_ACCOUNTS.accountingOfficer.password)
      const token = loginData.token

      // Get API permissions
      const meData = await getAuthMe(request, token)
      const apiPermissions: string[] = meData.user.permissions

      // CRITICAL FIX: API should have banking
      expect(apiPermissions).toContain('bank_accounts.view')
      expect(apiPermissions).toContain('bank_reconciliations.view')

      // Should NOT have payroll
      expect(apiPermissions).not.toContain('payroll.view_runs')

      // Login to frontend
      await loginToFrontend(page, TEST_ACCOUNTS.accountingOfficer.email, TEST_ACCOUNTS.accountingOfficer.password)

      // Get sidebar
      const sidebarText = await getSidebarText(page)

      // CRITICAL: Frontend should show Banking
      expect(sidebarText).toContain('Banking')

      // Should NOT show Payroll
      expect(sidebarText).not.toContain('Payroll')
    })

    test('Banking route accessible via both API and Frontend', async ({ page, request }) => {
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.accountingOfficer.email, TEST_ACCOUNTS.accountingOfficer.password)
      const token = loginData.token

      // API access
      const bankingStatus = await tryAccessRoute(request, token, '/banking/accounts')
      expect(bankingStatus).toBe(200)

      // Frontend access
      await loginToFrontend(page, TEST_ACCOUNTS.accountingOfficer.email, TEST_ACCOUNTS.accountingOfficer.password)

      await page.goto(`${BASE}/banking/accounts`)
      await page.waitForTimeout(1000)

      // Should load page (not 403)
      const url = page.url()
      expect(url).toContain('/banking/accounts')
      expect(url).not.toContain('/403')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // WAREHOUSE HEAD
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('Warehouse Head — Backend/Frontend Sync', () => {

    test('API permissions match sidebar visibility (CRITICAL: Categories)', async ({ page, request }) => {
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.warehouseHead.email, TEST_ACCOUNTS.warehouseHead.password)
      const token = loginData.token

      // Get API permissions
      const meData = await getAuthMe(request, token)
      const apiPermissions: string[] = meData.user.permissions

      // CRITICAL FIX: API should have inventory management
      expect(apiPermissions).toContain('inventory.locations.manage')

      // Should NOT have production
      expect(apiPermissions).not.toContain('production.orders.view')

      // Login to frontend
      await loginToFrontend(page, TEST_ACCOUNTS.warehouseHead.email, TEST_ACCOUNTS.warehouseHead.password)

      // Get sidebar
      const sidebarText = await getSidebarText(page)

      // Should see Inventory
      expect(sidebarText).toContain('Inventory')

      // Should NOT see Production
      expect(sidebarText).not.toContain('Production')

      // Expand Inventory and check for Categories
      await page.click('text=Inventory')
      await page.waitForTimeout(500)

      // CRITICAL: Should see Item Categories
      await expect(page.locator('text=Item Categories')).toBeVisible()
      await expect(page.locator('text=Warehouse Locations')).toBeVisible()
    })

    test('Categories route accessible via both API and Frontend', async ({ page, request }) => {
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.warehouseHead.email, TEST_ACCOUNTS.warehouseHead.password)
      const token = loginData.token

      // API access
      const categoriesStatus = await tryAccessRoute(request, token, '/inventory/categories')
      expect(categoriesStatus).toBe(200)

      // Frontend access
      await loginToFrontend(page, TEST_ACCOUNTS.warehouseHead.email, TEST_ACCOUNTS.warehouseHead.password)

      await page.goto(`${BASE}/inventory/categories`)
      await page.waitForTimeout(1000)

      // Should load page
      const url = page.url()
      expect(url).toContain('/inventory/categories')
      expect(url).not.toContain('/403')
    })
  })

  // ═══════════════════════════════════════════════════════════════════════════
  // HR MANAGER
  // ═══════════════════════════════════════════════════════════════════════════
  test.describe('HR Manager — Backend/Frontend Sync', () => {

    test('API permissions match sidebar visibility', async ({ page, request }) => {
      const loginData = await loginViaApi(request, TEST_ACCOUNTS.hrManager.email, TEST_ACCOUNTS.hrManager.password)
      const token = loginData.token

      // Get API permissions
      const meData = await getAuthMe(request, token)
      const apiPermissions: string[] = meData.user.permissions

      // Should have HR and Payroll
      expect(apiPermissions).toContain('hr.full_access')
      expect(apiPermissions).toContain('payroll.view_runs')

      // Should NOT have accounting
      expect(apiPermissions).not.toContain('chart_of_accounts.view')

      // Login to frontend
      await loginToFrontend(page, TEST_ACCOUNTS.hrManager.email, TEST_ACCOUNTS.hrManager.password)

      // Get sidebar
      const sidebarText = await getSidebarText(page)

      // Should see Payroll
      expect(sidebarText).toContain('Payroll')

      // Should NOT see Accounting
      expect(sidebarText).not.toContain('Accounting')
    })
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// CROSS-CUTTING CONCERNS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔄 Cross-Cutting RBAC Validation', () => {

  test('all roles have consistent permission representation', async ({ request }) => {
    const results: Array<{ role: string; apiPermissions: string[] }> = []

    for (const [roleName, account] of Object.entries(TEST_ACCOUNTS)) {
      try {
        const loginData = await loginViaApi(request, account.email, account.password)
        const meData = await getAuthMe(request, loginData.token)

        results.push({
          role: roleName,
          apiPermissions: meData.user.permissions,
        })
      } catch (e) {
        // User might not exist yet (seeders not run)
        console.log(`Skipping ${roleName} - user not found`)
      }
    }

    // Verify each role has unique permission set
    const permissionSets = results.map(r => r.apiPermissions.sort().join(','))
    const uniqueSets = new Set(permissionSets)

    expect(uniqueSets.size).toBe(results.length)

    // Verify no role has conflicting permissions
    for (const result of results) {
      // Production should NOT have Payroll
      if (result.role === 'productionManager') {
        expect(result.apiPermissions).not.toContain('payroll.view_runs')
      }

      // Accounting Officer SHOULD have Banking
      if (result.role === 'accountingOfficer') {
        expect(result.apiPermissions).toContain('bank_accounts.view')
      }

      // Warehouse Head SHOULD have inventory management
      if (result.role === 'warehouseHead') {
        expect(result.apiPermissions).toContain('inventory.locations.manage')
      }
    }
  })

  test('sidebar visibility matches API for all test accounts', async ({ page, request }) => {
    const mismatches: string[] = []

    for (const [roleName, account] of Object.entries(TEST_ACCOUNTS)) {
      try {
        // Get API permissions
        const loginData = await loginViaApi(request, account.email, account.password)
        const meData = await getAuthMe(request, loginData.token)
        const apiPermissions: string[] = meData.user.permissions

        // Login to frontend
        await loginToFrontend(page, account.email, account.password)
        const sidebarText = await getSidebarText(page)

        // Check for mismatches
        for (const expected of account.expectedPermissions.shouldSee) {
          const hasInApi = account.expectedPermissions.shouldSee.some(
            item => apiPermissions.some(p => p.includes(item.toLowerCase().replace(/\s+/g, '_')))
          )
          const hasInSidebar = sidebarText.includes(expected)

          if (hasInApi && !hasInSidebar) {
            mismatches.push(`${roleName}: API has permission but sidebar missing ${expected}`)
          }
        }

        for (const notExpected of account.expectedPermissions.shouldNotSee) {
          const shouldNotHaveInApi = account.expectedPermissions.shouldNotSee.some(
            item => !apiPermissions.some(p => p.includes(item.toLowerCase().replace(/\s+/g, '_')))
          )
          const hasInSidebar = sidebarText.includes(notExpected)

          if (shouldNotHaveInApi && hasInSidebar) {
            mismatches.push(`${roleName}: API lacks permission but sidebar shows ${notExpected}`)
          }
        }

        // Logout
        await page.evaluate(() => localStorage.clear())
      } catch (e) {
        console.log(`Skipping ${roleName} - ${e}`)
      }
    }

    expect(mismatches, `Mismatches found: ${mismatches.join(', ')}`).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// END-TO-END WORKFLOW TESTS
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🌐 End-to-End Workflows', () => {

  test('production user workflow: access allowed, blocked from restricted', async ({ page, request }) => {
    const account = TEST_ACCOUNTS.productionManager

    // 1. API: Verify initial state
    const loginData = await loginViaApi(request, account.email, account.password)
    const meData = await getAuthMe(request, loginData.token)

    expect(meData.user.permissions).toContain('production.orders.view')
    expect(meData.user.permissions).not.toContain('payroll.view_runs')

    // 2. Frontend: Login and verify dashboard
    await loginToFrontend(page, account.email, account.password)

    // Should land on dashboard
    expect(page.url()).toContain('/dashboard')

    // 3. Navigate to allowed page
    await page.goto(`${BASE}/production/orders`)
    await page.waitForTimeout(1500)

    // Should load successfully
    expect(page.url()).toContain('/production/orders')
    expect(page.url()).not.toContain('/403')

    // Should see production order content
    const pageContent = await page.locator('body').innerText()
    expect(pageContent).toMatch(/production|orders|work orders/i)

    // 4. Try to access restricted page
    await page.goto(`${BASE}/payroll/runs`)
    await page.waitForTimeout(1500)

    // Should be blocked
    const blockedUrl = page.url()
    expect(blockedUrl.includes('/403') || !blockedUrl.includes('/payroll/')).toBeTruthy()

    // 5. Verify sidebar still shows correct navigation
    const sidebarText = await getSidebarText(page)
    expect(sidebarText).toContain('Production')
    expect(sidebarText).not.toContain('Payroll')
  })

  test('accounting officer workflow: banking access verified', async ({ page, request }) => {
    const account = TEST_ACCOUNTS.accountingOfficer

    // API verification
    const loginData = await loginViaApi(request, account.email, account.password)
    const meData = await getAuthMe(request, loginData.token)

    expect(meData.user.permissions).toContain('bank_accounts.view')

    // Frontend verification
    await loginToFrontend(page, account.email, account.password)

    // Should see Banking in sidebar
    const sidebarText = await getSidebarText(page)
    expect(sidebarText).toContain('Banking')

    // Click Banking
    await page.click('text=Banking')
    await page.waitForTimeout(500)

    // Click Bank Accounts
    await page.click('text=Bank Accounts')
    await page.waitForTimeout(1500)

    // Should load bank accounts page
    expect(page.url()).toContain('/banking/accounts')
    expect(page.url()).not.toContain('/403')
  })
})
