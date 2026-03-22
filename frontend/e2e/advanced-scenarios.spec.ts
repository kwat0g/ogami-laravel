/**
 * E2E-ADVANCED-SCENARIOS — Advanced Business Scenarios
 * 
 * Complex workflows and edge cases:
 * 1. Multi-level approval workflows (3+ levels)
 * 2. Multi-company/branch scenarios
 * 3. Batch operations
 * 4. Notification workflows
 * 5. Data import/export (CSV uploads)
 * 6. Advanced inventory (FIFO/LIFO, lot tracking, serial numbers)
 * 7. Manufacturing shop floor scenarios
 * 8. Integration scenarios (webhooks, external APIs)
 * 9. Performance/stress tests
 */
import { test, expect, Page, APIRequestContext } from '@playwright/test'

const BASE = 'http://localhost:5173'
const API_BASE = 'http://localhost:8000/api/v1'

const ACCOUNTS = {
  requestor: { email: 'prod.staff@ogamierp.local', password: 'Staff@123456789!' },
  deptHead: { email: 'production.head@ogamierp.local', password: 'Head@123456789!' },
  vp: { email: 'vp@ogamierp.local', password: 'VicePresident@1!' },
  executive: { email: 'executive@ogamierp.local', password: 'Executive@Test1234!' },
  purchasing: { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!' },
  whHead: { email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!' },
  qcManager: { email: 'qc.manager@ogamierp.local', password: 'Manager@12345!' },
  acctgManager: { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!' },
  hrManager: { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!' },
  plantManager: { email: 'plant.manager@ogamierp.local', password: 'Manager@12345!' },
  admin: { email: 'admin@ogamierp.local', password: 'Admin@1234567890!' },
}

async function login(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(password)
  await page.getByRole('button', { name: /sign in|login/i }).click()
  await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
  await page.waitForSelector('aside, nav', { timeout: 10000 })
}

async function logout(page: Page) {
  try {
    await page.goto('about:blank')
    await page.waitForTimeout(100)
  } catch {}
}

// 1. MULTI-LEVEL APPROVAL WORKFLOWS
test.describe('Multi-Level Approval Workflows', () => {
  test.beforeEach(async ({ page }) => { await logout(page) })

  test('4-Level Approval: Staff to Head to VP to Executive', async ({ page }) => {
    const timestamp = Date.now()
    
    // Level 1: Staff creates high-value PR
    await login(page, ACCOUNTS.requestor.email, ACCOUNTS.requestor.password)
    await page.goto(`${BASE}/procurement/purchase-requests/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="department_id"]', '1')
    await page.fill('input[name="justification"]', 'High-value equipment purchase')
    await page.click('button:has-text("Add Item")')
    await page.fill('input[name="items[0].description"]', 'CNC Machine')
    await page.fill('input[name="items[0].quantity"]', '1')
    await page.fill('input[name="items[0].estimated_unit_cost"]', '2500000')
    await page.selectOption('select[name="items[0].uom"]', 'unit')
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    const prNumber = await page.locator('text=/PR-[0-9]+/i').first().textContent() || `PR-${timestamp}`
    await page.click('button:has-text("Submit for Approval")')
    await page.waitForTimeout(1500)
    
    // Level 2: Department Head approves
    await logout(page)
    await login(page, ACCOUNTS.deptHead.email, ACCOUNTS.deptHead.password)
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    let prRow = page.locator('tr', { hasText: prNumber }).first()
    await prRow.locator('button:has-text("Review")').click()
    await page.waitForTimeout(1500)
    await page.click('button:has-text("Approve")')
    await page.waitForTimeout(1000)
    await page.fill('textarea[name="notes"]', 'Approved by Dept Head')
    await page.click('button:has-text("Confirm")')
    await page.waitForTimeout(2000)
    
    // Level 3: VP approves
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    prRow = page.locator('tr', { hasText: prNumber }).first()
    await prRow.locator('button:has-text("Review")').click()
    await page.waitForTimeout(1500)
    await page.click('button:has-text("Approve")')
    await page.waitForTimeout(1000)
    await page.fill('textarea[name="notes"]', 'VP Approval')
    await page.click('button:has-text("Confirm")')
    await page.waitForTimeout(2000)
    
    // Level 4: Executive approves (final)
    await logout(page)
    await login(page, ACCOUNTS.executive.email, ACCOUNTS.executive.password)
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    prRow = page.locator('tr', { hasText: prNumber }).first()
    await prRow.locator('button:has-text("Review")').click()
    await page.waitForTimeout(1500)
    await page.click('button:has-text("Final Approval")')
    await page.waitForTimeout(1000)
    await page.fill('textarea[name="notes"]', 'Executive approval granted')
    await page.click('button:has-text("Confirm")')
    await page.waitForTimeout(2000)
    
    // Verify PR is fully approved
    await logout(page)
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    await page.goto(`${BASE}/procurement/purchase-requests`)
    await page.waitForTimeout(1500)
    prRow = page.locator('tr', { hasText: prNumber }).first()
    const status = await prRow.locator('td.status').textContent()
    expect(status).toMatch(/approved|fully approved/i)
  })
})

// 2. BATCH OPERATIONS
test.describe('Batch Operations', () => {
  test.beforeEach(async ({ page }) => { await logout(page) })

  test('Bulk Employee Creation', async ({ page }) => {
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    await page.goto(`${BASE}/hr/employees/bulk-create`)
    await page.waitForTimeout(2000)
    
    const employees = [
      { code: 'EMP-B1', first: 'Batch1', last: 'Employee1', email: 'batch1@test.com' },
      { code: 'EMP-B2', first: 'Batch2', last: 'Employee2', email: 'batch2@test.com' },
      { code: 'EMP-B3', first: 'Batch3', last: 'Employee3', email: 'batch3@test.com' },
    ]
    
    for (let i = 0; i < employees.length; i++) {
      if (i > 0) {
        await page.click('button:has-text("Add Employee")')
        await page.waitForTimeout(300)
      }
      await page.fill(`input[name="employees[${i}].employee_code"]`, employees[i].code)
      await page.fill(`input[name="employees[${i}].first_name"]`, employees[i].first)
      await page.fill(`input[name="employees[${i}].last_name"]`, employees[i].last)
      await page.fill(`input[name="employees[${i}].email"]`, employees[i].email)
      await page.selectOption(`select[name="employees[${i}].department_id"]`, '1')
    }
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(5000)
    
    const successMsg = page.locator('.batch-success')
    expect(await successMsg.count()).toBeGreaterThan(0)
  })
})

// 3. NOTIFICATION WORKFLOWS
test.describe('Notification Workflows', () => {
  test.beforeEach(async ({ page }) => { await logout(page) })

  test('PR Approval Notification Chain', async ({ page }) => {
    await login(page, ACCOUNTS.requestor.email, ACCOUNTS.requestor.password)
    await page.goto(`${BASE}/procurement/purchase-requests/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="department_id"]', '1')
    await page.fill('input[name="justification"]', 'Notification test PR')
    await page.click('button:has-text("Add Item")')
    await page.fill('input[name="items[0].description"]', 'Test Item')
    await page.fill('input[name="items[0].quantity"]', '10')
    await page.fill('input[name="items[0].estimated_unit_cost"]', '1000')
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    const prNumber = await page.locator('text=/PR-[0-9]+/i').first().textContent() || 'PR-TEST'
    await page.click('button:has-text("Submit for Approval")')
    await page.waitForTimeout(1500)
    
    // Check notification sent
    await page.click('button[aria-label="Notifications"]')
    await page.waitForTimeout(500)
    const notification = page.locator('.notification-item', { hasText: 'submitted for approval' })
    expect(await notification.count()).toBeGreaterThan(0)
    
    // Login as approver and check notification
    await logout(page)
    await login(page, ACCOUNTS.deptHead.email, ACCOUNTS.deptHead.password)
    const notifBadge = page.locator('.notification-badge')
    expect(await notifBadge.count()).toBeGreaterThan(0)
  })
})

// 4. DATA IMPORT/EXPORT
test.describe('Data Import/Export', () => {
  test.beforeEach(async ({ page }) => { await logout(page) })

  test('Import Item Catalog from Vendor CSV', async ({ page }) => {
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    await page.goto(`${BASE}/procurement/vendor-catalog-import`)
    await page.waitForTimeout(2000)
    await page.selectOption('select[name="vendor_id"]', '1')
    
    // Upload CSV via file chooser
    const [fileChooser] = await Promise.all([
      page.waitForEvent('filechooser'),
      page.locator('input[type="file"]').click()
    ])
    
    await fileChooser.setFiles([{
      name: 'vendor_catalog.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from(`item_code,name,description,unit_price,uom
VENDOR-001,Item A,Description A,45.50,pcs
VENDOR-002,Item B,Description B,78.25,box`)
    }])
    
    await page.waitForTimeout(1500)
    await page.click('button:has-text("Preview Import")')
    await page.waitForTimeout(2000)
    
    const previewRows = page.locator('table.import-preview tbody tr')
    expect(await previewRows.count()).toBe(2)
  })
})

// 5. PERFORMANCE TESTS
test.describe('Performance & Stress Tests', () => {
  test('Large Dataset Pagination Performance', async ({ page }) => {
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    await page.goto(`${BASE}/hr/employees/all`)
    await page.waitForTimeout(2000)
    
    const startTime = Date.now()
    await page.selectOption('select[name="per_page"]', '100')
    await page.waitForTimeout(3000)
    
    const loadTime = Date.now() - startTime
    console.log(`Load time for 100 records: ${loadTime}ms`)
    expect(loadTime).toBeLessThan(5000)
  })

  test('Concurrent User Simulation', async ({ browser }) => {
    const contexts = await Promise.all([
      browser.newContext(),
      browser.newContext(),
      browser.newContext(),
    ])
    
    const users = [
      { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!' },
      { email: 'prod.manager@ogamierp.local', password: 'Manager@12345!' },
      { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!' },
    ]
    
    const loginStart = Date.now()
    
    await Promise.all(contexts.map(async (context, index) => {
      const page = await context.newPage()
      await page.goto(`${BASE}/login`)
      await page.locator('input[type="email"]').fill(users[index].email)
      await page.locator('input[type="password"]').fill(users[index].password)
      await page.getByRole('button', { name: /sign in|login/i }).click()
      await page.waitForURL(/dashboard/, { timeout: 20000 })
    }))
    
    const concurrentLoginTime = Date.now() - loginStart
    console.log(`Concurrent login time: ${concurrentLoginTime}ms`)
    expect(concurrentLoginTime).toBeLessThan(10000)
    
    await Promise.all(contexts.map(c => c.close()))
  })
})
