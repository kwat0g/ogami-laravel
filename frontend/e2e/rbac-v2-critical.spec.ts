/**
 * E2E-RBAC-V2-CRITICAL — Critical RBAC v2 Permission Tests
 * 
 * Tests the specific permission bugs that were fixed:
 * 1. Production roles should NOT see Payroll module
 * 2. Warehouse Head should see Inventory (Categories/Locations)
 * 3. Accounting Officer should see Banking
 * 4. Production should NOT see Inventory Categories
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

// ── Critical Test Cases ───────────────────────────────────────────────────────

test.describe('Critical RBAC v2 Fixes', () => {
  
  test.beforeEach(async ({ page }) => {
    // Clear any existing session
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => localStorage.clear())
  })

  // ── FIX 1: Production should NOT see Payroll ────────────────────────────────
  
  test('PROD-001: Production Manager does NOT see Payroll in sidebar', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('prod.manager@ogamierp.local')
    await page.locator('input[type="password"]').fill('Manager@12345!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    // Get sidebar text
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // Should NOT contain "Payroll"
    expect(sidebarText).not.toContain('Payroll')
    
    // Should see Production
    expect(sidebarText).toContain('Production')
    
    // Try to access payroll URL directly
    await page.goto(`${BASE}/payroll/runs`)
    // Should show forbidden or redirect to dashboard
    await expect(page.locator('body')).toContainText(/forbidden|access denied|unauthorized/i, { timeout: 5000 })
      .catch(() => {
        // If no explicit forbidden message, check we're not on payroll page
        expect(page.url()).not.toContain('/payroll/')
      })
  })
  
  test('PROD-002: Production Head does NOT see Payroll', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('production.head@ogamierp.local')
    await page.locator('input[type="password"]').fill('Head@123456789!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    expect(sidebarText).not.toContain('Payroll')
    expect(sidebarText).toContain('Production')
  })
  
  test('PROD-003: Production Staff does NOT see Payroll', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('prod.staff@ogamierp.local')
    await page.locator('input[type="password"]').fill('Staff@123456789!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    expect(sidebarText).not.toContain('Payroll')
  })

  // ── FIX 2: Warehouse Head should see Inventory Categories ───────────────────
  
  test('WH-001: Warehouse Head sees Inventory with Categories', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('warehouse.head@ogamierp.local')
    await page.locator('input[type="password"]').fill('Head@123456789!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // Should see Inventory
    expect(sidebarText).toContain('Inventory')
    
    // Click to expand Inventory
    await page.click('text=Inventory')
    await page.waitForTimeout(500)
    
    // Should see Item Categories
    await expect(page.locator('text=Item Categories')).toBeVisible()
    // Should see Warehouse Locations
    await expect(page.locator('text=Warehouse Locations')).toBeVisible()
    // Should see Stock Balances
    await expect(page.locator('text=Stock Balances')).toBeVisible()
  })

  // ── FIX 3: Accounting Officer should see Banking ────────────────────────────
  
  test('ACCTG-001: Accounting Officer sees Banking menu', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('acctg.officer@ogamierp.local')
    await page.locator('input[type="password"]').fill('Officer@Test1234!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // Should see Banking
    expect(sidebarText).toContain('Banking')
    
    // Click to expand Banking
    await page.click('text=Banking')
    await page.waitForTimeout(500)
    
    // Should see Bank Accounts
    await expect(page.locator('text=Bank Accounts')).toBeVisible()
    // Should see Reconciliations
    await expect(page.locator('text=Reconciliations')).toBeVisible()
  })
  
  test('ACCTG-002: Accounting Officer does NOT see Payroll', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('acctg.officer@ogamierp.local')
    await page.locator('input[type="password"]').fill('Officer@Test1234!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // Officer should NOT see Payroll module (only own payslip via self-service)
    expect(sidebarText).not.toContain('Payroll')
  })

  // ── FIX 4: Production should NOT see Inventory Categories ───────────────────
  
  test('PROD-004: Production Manager does NOT see Inventory Categories', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('prod.manager@ogamierp.local')
    await page.locator('input[type="password"]').fill('Manager@12345!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // Should NOT see Inventory
    expect(sidebarText).not.toContain('Inventory')
  })
  
  test('PROD-005: Production cannot access Inventory Categories URL', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('prod.manager@ogamierp.local')
    await page.locator('input[type="password"]').fill('Manager@12345!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    // Try to access inventory categories directly
    await page.goto(`${BASE}/inventory/categories`)
    
    // Should be blocked
    const pageContent = await page.locator('body').innerText()
    const isBlocked = 
      pageContent.toLowerCase().includes('forbidden') ||
      pageContent.toLowerCase().includes('access denied') ||
      pageContent.toLowerCase().includes('unauthorized') ||
      !page.url().includes('/inventory/categories')
    
    expect(isBlocked).toBe(true)
  })

  // ── Additional HR Tests ─────────────────────────────────────────────────────
  
  test('HR-001: HR Manager sees Payroll', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('hr.manager@ogamierp.local')
    await page.locator('input[type="password"]').fill('Manager@Test1234!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // HR Manager should see Payroll
    expect(sidebarText).toContain('Payroll')
    // Should see Team Management
    expect(sidebarText).toContain('Team')
  })
  
  test('HR-002: HR Staff does NOT see Payroll module', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.locator('input[type="email"]').fill('hr.staff@ogamierp.local')
    await page.locator('input[type="password"]').fill('Staff@Test1234!')
    await page.getByRole('button', { name: /sign in/i }).click()
    
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    
    const sidebarText = await page.locator('aside, nav').first().innerText()
    
    // Staff should NOT see Payroll management
    expect(sidebarText).not.toContain('Payroll')
  })
})

// ── Summary Test ───────────────────────────────────────────────────────────────

test.describe('RBAC v2 Summary', () => {
  
  test('All critical permissions are correctly enforced', async ({ page }) => {
    const testResults: Record<string, boolean> = {}
    
    // Test each critical role
    const roles = [
      { name: 'prodManager', email: 'prod.manager@ogamierp.local', password: 'Manager@12345!', shouldSeePayroll: false },
      { name: 'whHead', email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!', shouldSeeInventory: true },
      { name: 'acctgOfficer', email: 'acctg.officer@ogamierp.local', password: 'Officer@Test1234!', shouldSeeBanking: true },
    ]
    
    for (const role of roles) {
      await page.goto(`${BASE}/login`)
      await page.locator('input[type="email"]').fill(role.email)
      await page.locator('input[type="password"]').fill(role.password)
      await page.getByRole('button', { name: /sign in/i }).click()
      
      await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
      
      const sidebarText = await page.locator('aside, nav').first().innerText()
      
      if ('shouldSeePayroll' in role) {
        testResults[role.name] = role.shouldSeePayroll 
          ? sidebarText.includes('Payroll')
          : !sidebarText.includes('Payroll')
      }
      
      if ('shouldSeeInventory' in role) {
        testResults[role.name] = role.shouldSeeInventory 
          ? sidebarText.includes('Inventory')
          : !sidebarText.includes('Inventory')
      }
      
      if ('shouldSeeBanking' in role) {
        testResults[role.name] = role.shouldSeeBanking 
          ? sidebarText.includes('Banking')
          : !sidebarText.includes('Banking')
      }
      
      // Logout
      await page.evaluate(() => localStorage.clear())
    }
    
    // All tests should pass
    expect(testResults.prodManager, 'Production Manager should NOT see Payroll').toBe(true)
    expect(testResults.whHead, 'Warehouse Head should see Inventory').toBe(true)
    expect(testResults.acctgOfficer, 'Accounting Officer should see Banking').toBe(true)
  })
})
