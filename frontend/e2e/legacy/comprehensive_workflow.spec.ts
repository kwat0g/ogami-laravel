import { test, expect, type Page } from '@playwright/test'

/**
 * Ogami ERP — Comprehensive Role-Based Workflow Test
 */

const BASE_URL = 'http://localhost:5173'

const USERS = {
  superadmin: { email: 'superadmin@ogamierp.local', password: 'SuperAdmin@12345!' },
  acctg_mgr:  { email: 'acctg.manager@ogamierp.local', password: 'Manager@Test1234!' },
  wh_mgr:     { email: 'wh.manager@ogamierp.local', password: 'Manager@Test1234!' },
  wh_staff:   { email: 'wh.staff@ogamierp.local',   password: 'Staff@Test1234!' },
  purch_mgr:  { email: 'purch.manager@ogamierp.local', password: 'Manager@Test1234!' },
  prod_staff: { email: 'prod.staff@ogamierp.local', password: 'Staff@Test1234!' },
  prod_head:  { email: 'prod.head@ogamierp.local', password: 'Head@Test1234!' },
  acctg_off:  { email: 'acctg.officer@ogamierp.local', password: 'Officer@Test1234!' },
  vp:         { email: 'vp@ogamierp.local', password: 'Vice_president@Test1234!' }
}

async function loginAs(page: Page, userKey: keyof typeof USERS) {
  const user = USERS[userKey]
  console.log(`Logging in as ${userKey} (${user.email})...`)
  
  await page.goto(`${BASE_URL}/login`)
  
  // Wait for login form to be visible
  await expect(page.locator('input[name="email"]')).toBeVisible({ timeout: 15000 })
  
  await page.fill('input[name="email"]', user.email)
  await page.fill('input[name="password"]', user.password)
  await page.click('button[type="submit"]')
  
  // Wait for navigation and dashboard element
  // We check for "Dashboard" text or breadcrumbs to ensure we are actually in.
  await expect(page).toHaveURL(/dashboard/, { timeout: 30000 })
  await expect(page.locator('text=Overview')).toBeVisible({ timeout: 15000 })
  console.log(`Logged in successfully as ${userKey}`)
}

async function logout(page: Page) {
  console.log('Logging out...')
  // Click user profile button in sidebar/header (simplified for now: go to login)
  await page.goto(`${BASE_URL}/login`) 
  // This is a hack to trigger logout if the app redirects auth users, 
  // but better to use the actual UI if possible.
  // In Ogami, visiting /login while auth usually redirects to /dashboard.
  // Let's try to find a logout button. It's usually in the sidebar bottom.
  const logoutBtn = page.locator('button:has-text("Logout"), button:has-text("Sign Out")')
  if (await logoutBtn.isVisible()) {
    await logoutBtn.click()
  } else {
    // Force logout via localStorage clear and refresh if UI is hard to find
    await page.evaluate(() => {
       // Assuming Sanctum cookie, so we might need a POST to /logout.
       // For E2E simplicity, we'll try to click the profile dropdown.
    })
    await page.goto(`${BASE_URL}/login`)
  }
}

test.describe('E2E Procurement Workflow', () => {
  // Use serial to share state/data chronologically
  test.describe.configure({ mode: 'serial' })

  test.beforeAll(async () => {
    // Potentially run migrate:fresh here, but we'll assume it's pre-run for speed.
  })

  test('Scenario 0: Master Data Setup', async ({ page }) => {
    test.setTimeout(120000)
    
    // 0.1 Setup Financials as Acctg Manager
    await loginAs(page, 'acctg_mgr')
    
    await page.goto(`${BASE_URL}/accounting/vendors`)
    await page.click('text=New Vendor')
    await page.fill('input[name="name"]', 'Industrial Supplies Inc.')
    await page.fill('input[name="tin"]', '000-111-222-333')
    await page.fill('input[name="email"]', 'sales@industrial.com')
    await page.click('button:has-text("Save")')
    await expect(page.locator('text=Vendor created')).toBeVisible()

    await page.goto(`${BASE_URL}/ar/customers`)
    await page.click('text=New Customer')
    await page.fill('input[name="name"]', 'Metro Construction Co.')
    await page.fill('input[name="tax_identification_number"]', '123-456-789-000')
    await page.fill('button:has-text("Create Customer")').click()
    await expect(page.locator('text=Customer created')).toBeVisible()
    
    await logout(page)

    // 0.2 Setup Inventory as Superadmin (to ensure category exists)
    await loginAs(page, 'superadmin')
    await page.goto(`${BASE_URL}/inventory/items`)
    await page.click('button:has-text("Categories")')
    if (!(await page.getByText('Raw Metals').isVisible())) {
      await page.click('button:has-text("Add Category")')
      await page.fill('input[placeholder="e.g. Raw Materials"]', 'Raw Metals')
      await page.click('button:has-text("Save")')
    }
    await page.keyboard.press('Escape')

    await page.goto(`${BASE_URL}/inventory/items/new`)
    await page.fill('input[name="name"]', 'Steel Plate 10mm')
    await page.selectOption('select[name="type"]', 'raw_material')
    await page.selectOption('select[name="category_id"]', { label: 'Raw Metals' })
    await page.selectOption('select[name="unit_of_measure"]', 'pcs')
    await page.fill('input[name="standard_price"]', '1500')
    await page.fill('textarea[name="description"]', 'Durable steel plate')
    await page.click('button:has-text("Save")')
    await expect(page.locator('text=Item created')).toBeVisible()
    
    await logout(page)
  })

  test('Scenario 1 & 2: PR Creation and Full Approval Chain', async ({ page }) => {
    test.setTimeout(240000)

    // 1. Create PR as Prod Staff
    await loginAs(page, 'prod_staff')
    await page.goto(`${BASE_URL}/procurement/purchase-requests/new`)
    
    await page.selectOption('select[name="vendor_id"]', { label: 'Industrial Supplies Inc.' })
    await page.fill('textarea[name="justification"]', 'Need materials for Q3 production.')
    
    // Add Item to PR
    await page.click('button:has-text("Add Item")')
    await page.selectOption('select[name="item_id"]', { label: 'Steel Plate 10mm' })
    await page.fill('input[name="quantity"]', '50')
    await page.fill('input[name="estimated_unit_cost"]', '1500')
    await page.click('button:has-text("Add to List")')
    
    await page.click('button[type="submit"]') // Save as Draft
    await expect(page.locator('text=Purchase Request created')).toBeVisible()
    
    // Submit for Review
    await page.click('button:has-text("Submit for Review")')
    await expect(page.locator('text=submitted for review')).toBeVisible()
    
    const prRef = await page.locator('h1').innerText()
    console.log(`Created PR: ${prRef}`)
    await logout(page)

    // 2. Approvals
    const roles: (keyof typeof USERS)[] = ['purch_mgr', 'acctg_off', 'vp']
    const actions = ['Review & Approve', 'Verify Budget', 'Final Approve (VP)']

    for (let i = 0; i < roles.length; i++) {
      await loginAs(page, roles[i])
      await page.goto(`${BASE_URL}/approvals/pending`)
      // Find our PR in the list and click it
      await page.click(`text=${prRef}`)
      
      await page.click(`button:has-text("${actions[i]}")`)
      await page.fill('textarea[placeholder*="comments"]', `Approved by ${roles[i]}`)
      await page.click('button:has-text("Confirm")')
      await expect(page.locator('text=completed successfully')).toBeVisible()
      await logout(page)
    }
  })

  test('Scenario 3 & 4: PO Conversion and Goods Receipt', async ({ page }) => {
    test.setTimeout(120000)
    
    // 3. Convert to PO as Purch Manager
    await loginAs(page, 'purch_mgr')
    await page.goto(`${BASE_URL}/procurement/purchase-requests`)
    await page.click('text=Approved') // Filter for approved if possible, or just look for the row
    // Click the first approved PR (ours should be top)
    await page.locator('tr:has-text("Approved")').first().click()
    
    await page.click('button:has-text("Convert to PO")')
    await expect(page.locator('text=Purchase Order created')).toBeVisible()
    const poRef = await page.locator('h1').innerText()
    console.log(`Created PO: ${poRef}`)
    await logout(page)

    // 4. Goods Receipt as Warehouse Staff
    await loginAs(page, 'wh_staff')
    await page.goto(`${BASE_URL}/procurement/goods-receipts/new`)
    await page.selectOption('select[name="purchase_order_id"]', { label: poRef })
    
    await page.fill('input[name="reference_number"]', 'DR-99999')
    await page.click('button:has-text("Save Receipt")')
    await expect(page.locator('text=Goods receipt recorded')).toBeVisible()
    await logout(page)
  })
})
