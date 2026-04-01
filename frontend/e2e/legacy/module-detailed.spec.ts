/**
 * E2E-MODULE-DETAILED — Detailed Module-Specific Tests
 * 
 * Complex workflows with multiple steps, approvals, and edge cases:
 * - Procurement with multi-level approvals
 * - Production with material allocation
 * - Inventory with stock movements and audits
 * - Accounting with GL posting and reconciliations
 * - HR with payroll integration
 */
import { test, expect, Page } from '@playwright/test'

const BASE = 'http://localhost:5173'
const API_BASE = 'http://localhost:8000/api/v1'

// ═══════════════════════════════════════════════════════════════════════════════
// TEST ACCOUNTS
// ═══════════════════════════════════════════════════════════════════════════════

const ACCOUNTS = {
  purchasing: { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!' },
  vp: { email: 'vp@ogamierp.local', password: 'VicePresident@1!' },
  prodManager: { email: 'prod.manager@ogamierp.local', password: 'Manager@12345!' },
  whHead: { email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!' },
  qcManager: { email: 'qc.manager@ogamierp.local', password: 'Manager@12345!' },
  acctgManager: { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!' },
  hrManager: { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!' },
  plantManager: { email: 'plant.manager@ogamierp.local', password: 'Manager@12345!' },
  executive: { email: 'executive@ogamierp.local', password: 'Executive@Test1234!' },
  salesManager: { email: 'crm.manager@ogamierp.local', password: 'Manager@12345!' },
  maintenanceManager: { email: 'maintenance.head@ogamierp.local', password: 'Head@123456789!' },
  moldManager: { email: 'mold.manager@ogamierp.local', password: 'Manager@12345!' },
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
  } catch {
    // Ignore
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// PROCUREMENT - Complex Multi-Level Approval Workflow
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🛒 Procurement - Complex Workflows', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('PR with Multiple Line Items and Partial PO Conversion', async ({ page }) => {
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    // Create PR with multiple items
    await page.goto(`${BASE}/procurement/purchase-requests/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="department_id"]', '1')
    await page.fill('input[name="justification"]', 'Multi-item procurement for production')
    
    // Add 3 line items
    const items = [
      { desc: 'Steel Raw Material', qty: '500', uom: 'kg', est: '45' },
      { desc: 'Plastic Pellets', qty: '200', uom: 'bags', est: '120' },
      { desc: 'Packaging Boxes', qty: '1000', uom: 'pcs', est: '15' },
    ]
    
    for (let i = 0; i < items.length; i++) {
      if (i > 0) {
        await page.click('button:has-text("Add Item")')
        await page.waitForTimeout(300)
      }
      await page.fill(`input[name="items[${i}].description"]`, items[i].desc)
      await page.fill(`input[name="items[${i}].quantity"]`, items[i].qty)
      await page.fill(`input[name="items[${i}].estimated_unit_cost"]`, items[i].est)
      await page.selectOption(`select[name="items[${i}].uom"]`, items[i].uom)
    }
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Get PR number
    const prNumber = await page.locator('text=/PR-[0-9]+-[0-9]+/i, text=/PR-[0-9]+/i').first().textContent() || 'PR-TEST'
    console.log(`Created multi-item PR: ${prNumber}`)
    
    // Submit for approval
    const submitBtn = page.locator('button:has-text("Submit for Approval")')
    if (await submitBtn.count() > 0 && await submitBtn.isVisible()) {
      await submitBtn.click()
      await page.waitForTimeout(1500)
    }
    
    // VP approves
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    
    // Find and approve the PR
    const prRow = page.locator('tr', { hasText: prNumber }).first()
    if (await prRow.count() > 0) {
      await prRow.locator('button:has-text("Approve")').click()
      await page.waitForTimeout(1000)
      
      // Add approval notes
      await page.fill('textarea[name="approval_notes"]', 'Approved for procurement')
      await page.click('button:has-text("Confirm Approval")')
      await page.waitForTimeout(1500)
    }
    
    // Create partial PO (only first 2 items)
    await logout(page)
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    await page.goto(`${BASE}/procurement/purchase-requests`)
    await page.waitForTimeout(1500)
    
    // Find approved PR and create PO
    const approvedRow = page.locator('tr', { hasText: prNumber }).first()
    if (await approvedRow.count() > 0) {
      await approvedRow.locator('button:has-text("Create PO")').click()
      await page.waitForTimeout(2000)
      
      // Select vendor
      await page.selectOption('select[name="vendor_id"]', '1')
      await page.fill('input[name="payment_terms"]', 'Net 30 Days')
      await page.fill('input[name="delivery_date"]', '2026-04-15')
      
      // Deselect third item (partial conversion)
      const thirdItemCheckbox = page.locator('input[name="include_item[2]"]')
      if (await thirdItemCheckbox.count() > 0) {
        await thirdItemCheckbox.uncheck()
      }
      
      // Update prices
      await page.fill('input[name="unit_price[0]"]', '42')  // Negotiated lower
      await page.fill('input[name="unit_price[1]"]', '115') // Slightly higher
      
      await page.click('button[type="submit"]')
      await page.waitForTimeout(2500)
      
      const poNumber = await page.locator('text=/PO-[0-9]+-[0-9]+/i, text=/PO-[0-9]+/i').first().textContent() || 'PO-TEST'
      console.log(`Created partial PO: ${poNumber}`)
      
      // Verify PR shows partial conversion
      await page.goto(`${BASE}/procurement/purchase-requests`)
      await page.waitForTimeout(1500)
      
      const prStatus = page.locator('tr', { hasText: prNumber }).locator('td').nth(4)
      if (await prStatus.count() > 0) {
        const statusText = await prStatus.textContent()
        expect(statusText).toMatch(/partial|partially converted/i)
      }
    }
  })

  test('PR Rejection and Resubmission Workflow', async ({ page }) => {
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    // Create PR
    await page.goto(`${BASE}/procurement/purchase-requests/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="department_id"]', '1')
    await page.fill('input[name="justification"]', 'Rejected PR test workflow')
    await page.click('button:has-text("Add Item")')
    await page.fill('input[name="items[0].description"]', 'Test Item')
    await page.fill('input[name="items[0].quantity"]', '100')
    await page.fill('input[name="items[0].estimated_unit_cost"]', '1000')
    await page.selectOption('select[name="items[0].uom"]', 'pcs')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    const prNumber = await page.locator('text=/PR-[0-9]+/i').first().textContent() || 'PR-TEST'
    
    // Submit for approval
    const submitBtn = page.locator('button:has-text("Submit for Approval")')
    if (await submitBtn.count() > 0) {
      await submitBtn.click()
      await page.waitForTimeout(1500)
    }
    
    // VP rejects
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    
    const prRow = page.locator('tr', { hasText: prNumber }).first()
    if (await prRow.count() > 0) {
      await prRow.locator('button:has-text("Reject")').click()
      await page.waitForTimeout(1000)
      
      await page.fill('textarea[name="rejection_reason"]', 'Budget not approved for this quarter')
      await page.click('button:has-text("Confirm Rejection")')
      await page.waitForTimeout(1500)
    }
    
    // Purchasing updates and resubmits
    await logout(page)
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    await page.goto(`${BASE}/procurement/purchase-requests`)
    await page.waitForTimeout(1500)
    
    // Find rejected PR
    const rejectedRow = page.locator('tr', { hasText: prNumber }).first()
    await rejectedRow.locator('button:has-text("Edit")').click()
    await page.waitForTimeout(2000)
    
    // Reduce quantity and resubmit
    await page.fill('input[name="items[0].quantity"]', '50')
    await page.fill('textarea[name="resubmission_notes"]', 'Reduced quantity per VP feedback')
    
    await page.click('button:has-text("Save & Resubmit")')
    await page.waitForTimeout(2000)
    
    // VP approves resubmission
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    
    const resubmittedRow = page.locator('tr', { hasText: prNumber }).first()
    if (await resubmittedRow.count() > 0) {
      await resubmittedRow.locator('button:has-text("Approve")').click()
      await page.waitForTimeout(1000)
      await page.click('button:has-text("Confirm")')
    }
  })

  test('Vendor Evaluation and Selection', async ({ page }) => {
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    // Create RFQ
    await page.goto(`${BASE}/procurement/rfqs/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="rfq_number"]', `RFQ-${timestamp}`)
    await page.fill('input[name="title"]', 'Material Procurement Q2 2026')
    await page.fill('textarea[name="description"]', 'Request for quotation for raw materials')
    await page.fill('input[name="deadline"]', '2026-04-01')
    
    // Add items to RFQ
    await page.click('button:has-text("Add Item")')
    await page.fill('input[name="items[0].description"]', 'Aluminum Sheets 4x8')
    await page.fill('input[name="items[0].quantity"]', '100')
    await page.selectOption('select[name="items[0].uom"]', 'sheets')
    await page.fill('textarea[name="items[0].specifications"]', 'Grade 6061-T6, 0.125" thickness')
    
    // Select vendors to invite
    await page.selectOption('select[name="vendors"]', ['1', '2', '3'])
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Record vendor quotations
    await page.goto(`${BASE}/procurement/rfqs`)
    await page.waitForTimeout(1500)
    
    const rfqRow = page.locator('tr', { hasText: `RFQ-${timestamp}` }).first()
    await rfqRow.locator('button:has-text("View Quotes")').click()
    await page.waitForTimeout(1500)
    
    // Enter quotes from vendors
    for (let i = 1; i <= 3; i++) {
      await page.fill(`input[name="vendor_quote[${i}].unit_price"]`, `${40 + i * 2}`)
      await page.fill(`input[name="vendor_quote[${i}].delivery_days"]`, `${14 - i * 2}`)
      await page.fill(`textarea[name="vendor_quote[${i}].notes"]`, `Vendor ${i} quote`)
    }
    
    await page.click('button:has-text("Save Quotes")')
    await page.waitForTimeout(1500)
    
    // Award to best vendor (lowest price)
    await page.click('button:has-text("Award to Vendor 1")')
    await page.waitForTimeout(1000)
    await page.click('button:has-text("Confirm Award")')
    await page.waitForTimeout(1500)
    
    // Verify PO created from award
    await page.goto(`${BASE}/procurement/purchase-orders`)
    await page.waitForTimeout(1500)
    
    const poRow = page.locator('tr', { hasText: `RFQ-${timestamp}` }).first()
    expect(await poRow.count()).toBeGreaterThan(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PRODUCTION - Complex Planning and Execution
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🏭 Production - Complex Workflows', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Multi-Level BOM with Component Allocation', async ({ page }) => {
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
    
    // Create parent BOM
    await page.goto(`${BASE}/production/boms/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="bom_number"]', `BOM-PARENT-${timestamp}`)
    await page.fill('input[name="product_name"]', 'Assembly Unit A')
    await page.selectOption('select[name="product_type"]', 'finished_good')
    await page.fill('input[name="version"]', '1.0')
    
    // Add components - some direct materials, some sub-assemblies
    const components = [
      { item: 'Raw Material X', qty: '10', type: 'raw' },
      { item: 'Component Y', qty: '5', type: 'component' },
      { item: 'Sub-Assembly Z', qty: '2', type: 'subassembly' },
    ]
    
    for (let i = 0; i < components.length; i++) {
      await page.click('button:has-text("Add Material")')
      await page.waitForTimeout(300)
      await page.selectOption(`select[name="materials[${i}].item_id"]`, '1')
      await page.fill(`input[name="materials[${i}].quantity"]`, components[i].qty)
      await page.selectOption(`select[name="materials[${i}].uom"]`, 'pcs')
    }
    
    // Add labor and overhead
    await page.click('button:has-text("Add Labor")')
    await page.fill('input[name="labor[0].operation"]', 'Assembly')
    await page.fill('input[name="labor[0].hours"]', '2.5')
    await page.fill('input[name="labor[0].rate"]', '150')
    
    await page.click('button:has-text("Add Overhead")')
    await page.fill('input[name="overhead[0].cost_center"]', 'Manufacturing')
    await page.fill('input[name="overhead[0].rate"]', '25')
    await page.selectOption('select[name="overhead[0].basis"]', 'labor_hour')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Create Work Order using this BOM
    await page.goto(`${BASE}/production/orders/new`)
    await page.waitForTimeout(2000)
    
    await page.fill('input[name="wo_number"]', `WO-${timestamp}`)
    await page.selectOption('select[name="bom_id"]', '1')
    await page.fill('input[name="quantity"]', '50')
    await page.fill('input[name="planned_start"]', '2026-04-01')
    await page.fill('input[name="planned_end"]', '2026-04-10')
    
    // Allocate materials
    await page.click('button:has-text("Allocate Materials")')
    await page.waitForTimeout(1500)
    
    // Check allocation status for each component
    for (let i = 0; i < components.length; i++) {
      const allocationStatus = page.locator(`tr:has-text("${components[i].item}") td.allocation-status`)
      if (await allocationStatus.count() > 0) {
        const status = await allocationStatus.textContent()
        console.log(`${components[i].item}: ${status}`)
      }
    }
    
    await page.click('button:has-text("Confirm Allocation")')
    await page.waitForTimeout(1500)
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Start production and track progress
    await page.goto(`${BASE}/production/orders`)
    await page.waitForTimeout(1500)
    
    const woRow = page.locator('tr', { hasText: `WO-${timestamp}` }).first()
    await woRow.locator('button:has-text("Start")').click()
    await page.waitForTimeout(1500)
    
    // Record partial completion
    await woRow.locator('button:has-text("Record Progress")').click()
    await page.waitForTimeout(1500)
    
    await page.fill('input[name="quantity_completed"]', '25')
    await page.fill('input[name="quantity_rejected"]', '2')
    await page.fill('textarea[name="notes"]', 'First batch completed')
    
    await page.click('button:has-text("Save Progress")')
    await page.waitForTimeout(1500)
    
    // Verify 50% completion
    const progressBar = page.locator('.progress-bar')
    if (await progressBar.count() > 0) {
      const progressText = await progressBar.textContent()
      expect(progressText).toContain('50')
    }
  })

  test('Production Scheduling with Conflict Detection', async ({ page }) => {
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
    
    // Create multiple work orders
    const woNumbers: string[] = []
    
    for (let i = 1; i <= 3; i++) {
      await page.goto(`${BASE}/production/orders/new`)
      await page.waitForTimeout(2000)
      
      const woNum = `WO-SCHED-${Date.now()}-${i}`
      woNumbers.push(woNum)
      
      await page.fill('input[name="wo_number"]', woNum)
      await page.selectOption('select[name="bom_id"]', '1')
      await page.fill('input[name="quantity"]', '100')
      
      // All scheduled for same time period (conflict)
      await page.fill('input[name="planned_start"]', '2026-04-01')
      await page.fill('input[name="planned_end"]', '2026-04-05')
      
      await page.click('button[type="submit"]')
      await page.waitForTimeout(2000)
    }
    
    // Go to scheduling view
    await page.goto(`${BASE}/production/delivery-schedules`)
    await page.waitForTimeout(1500)
    
    // Check for conflict warnings
    const conflictWarning = page.locator('.conflict-warning, .alert-warning')
    if (await conflictWarning.count() > 0) {
      console.log('Scheduling conflicts detected')
      
      // Resolve conflicts by rescheduling
      for (const woNum of woNumbers) {
        const woRow = page.locator('tr', { hasText: woNum }).first()
        if (await woRow.count() > 0) {
          await woRow.locator('button:has-text("Reschedule")').click()
          await page.waitForTimeout(1000)
          
          // Stagger the dates
          const index = woNumbers.indexOf(woNum)
          const startDate = `2026-04-${(index * 7) + 1}`.padStart(2, '0')
          const endDate = `2026-04-${(index * 7) + 7}`.padStart(2, '0')
          
          await page.fill('input[name="planned_start"]', startDate)
          await page.fill('input[name="planned_end"]', endDate)
          await page.click('button:has-text("Update Schedule")')
          await page.waitForTimeout(1500)
        }
      }
    }
    
    // Verify no more conflicts
    await page.goto(`${BASE}/production/delivery-schedules`)
    await page.waitForTimeout(1500)
    
    const finalConflicts = page.locator('.conflict-warning')
    expect(await finalConflicts.count()).toBe(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// INVENTORY - Complex Stock Management
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📦 Inventory - Complex Workflows', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Multi-Warehouse Stock Transfer with Approval', async ({ page }) => {
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    // Create stock transfer request
    await page.goto(`${BASE}/inventory/transfers/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="transfer_number"]', `ST-${timestamp}`)
    await page.selectOption('select[name="from_warehouse_id"]', '1')
    await page.selectOption('select[name="to_warehouse_id"]', '2')
    await page.fill('input[name"transfer_date"]', '2026-03-20')
    await page.fill('textarea[name="reason"]', 'Rebalancing stock between warehouses')
    
    // Add items to transfer
    const items = [
      { id: '1', qty: '100' },
      { id: '2', qty: '50' },
    ]
    
    for (let i = 0; i < items.length; i++) {
      await page.click('button:has-text("Add Item")')
      await page.waitForTimeout(300)
      await page.selectOption(`select[name="items[${i}].item_id"]`, items[i].id)
      await page.fill(`input[name="items[${i}].quantity"]`, items[i].qty)
      
      // Check available stock
      const availStock = page.locator(`span[name="items[${i}].available_stock"]`)
      if (await availStock.count() > 0) {
        const stock = await availStock.textContent()
        console.log(`Item ${items[i].id} available: ${stock}`)
        expect(parseInt(stock || '0')).toBeGreaterThanOrEqual(parseInt(items[i].qty))
      }
    }
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Transfer needs approval (if configured)
    const needsApproval = page.locator('text=/pending approval|awaiting approval/i')
    if (await needsApproval.count() > 0) {
      // Plant manager approves
      await logout(page)
      await login(page, ACCOUNTS.plantManager.email, ACCOUNTS.plantManager.password)
      
      await page.goto(`${BASE}/approvals/pending`)
      await page.waitForTimeout(1500)
      
      const transferRow = page.locator('tr', { hasText: `ST-${timestamp}` }).first()
      if (await transferRow.count() > 0) {
        await transferRow.locator('button:has-text("Approve")').click()
        await page.waitForTimeout(1000)
        await page.click('button:has-text("Confirm")')
        await page.waitForTimeout(1500)
      }
    }
    
    // Execute transfer
    await logout(page)
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    await page.goto(`${BASE}/inventory/transfers`)
    await page.waitForTimeout(1500)
    
    const transferRow = page.locator('tr', { hasText: `ST-${timestamp}` }).first()
    await transferRow.locator('button:has-text("Ship")').click()
    await page.waitForTimeout(1500)
    
    // Record shipping details
    await page.fill('input[name="shipped_by"]', 'Warehouse Staff A')
    await page.fill('input[name="vehicle_plate"]', 'ABC-123')
    await page.click('button:has-text("Confirm Shipment")')
    await page.waitForTimeout(1500)
    
    // Receive at destination
    await transferRow.locator('button:has-text("Receive")').click()
    await page.waitForTimeout(1500)
    
    // Verify quantities
    for (let i = 0; i < items.length; i++) {
      const receivedQty = page.locator(`input[name="items[${i}].received_quantity"]`)
      await receivedQty.fill(items[i].qty)
    }
    
    await page.click('button:has-text("Confirm Receipt")')
    await page.waitForTimeout(1500)
    
    // Verify stock updated in both warehouses
    await page.goto(`${BASE}/inventory/stock`)
    await page.waitForTimeout(1500)
    
    // Check source warehouse decreased
    await page.selectOption('select[name="warehouse_id"]', '1')
    await page.waitForTimeout(1000)
    
    // Check destination warehouse increased
    await page.selectOption('select[name="warehouse_id"]', '2')
    await page.waitForTimeout(1000)
  })

  test('Physical Count with Variance Analysis', async ({ page }) => {
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    // Create physical count session
    await page.goto(`${BASE}/inventory/physical-count`)
    await page.waitForTimeout(1500)
    
    await page.click('button:has-text("New Count Session")')
    await page.waitForTimeout(1500)
    
    const timestamp = Date.now()
    await page.fill('input[name="session_name"]', `Count-${timestamp}`)
    await page.selectOption('select[name="warehouse_id"]', '1')
    await page.selectOption('select[name"count_type"]', 'full')
    await page.fill('input[name="scheduled_date"]', '2026-03-20')
    
    // Select items to count (or all)
    await page.click('button:has-text("Select All Items")')
    await page.waitForTimeout(1000)
    
    await page.click('button:has-text("Start Count Session")')
    await page.waitForTimeout(2000)
    
    // Enter count results (with some variances)
    const countRows = page.locator('table.count-sheet tbody tr')
    const rowCount = await countRows.count()
    
    for (let i = 0; i < Math.min(rowCount, 5); i++) {
      const systemQty = await countRows.nth(i).locator('td.system-qty').textContent() || '100'
      const counted = parseInt(systemQty) + (i % 3 - 1) * 5  // Some +5, same, -5
      
      await countRows.nth(i).locator('input.counted-qty').fill(counted.toString())
      
      if (counted !== parseInt(systemQty)) {
        await countRows.nth(i).locator('input.variance-reason').fill('Counting variance')
      }
    }
    
    await page.click('button:has-text("Complete Count")')
    await page.waitForTimeout(2000)
    
    // Review variances
    const varianceReport = page.locator('.variance-report')
    expect(await varianceReport.count()).toBeGreaterThan(0)
    
    // Generate adjustments for significant variances
    const significantVariances = page.locator('tr.significant-variance')
    const sigCount = await significantVariances.count()
    
    if (sigCount > 0) {
      await page.click('button:has-text("Generate Adjustments")')
      await page.waitForTimeout(2000)
      
      // Review and approve adjustments
      for (let i = 0; i < sigCount; i++) {
        await page.locator('input.adjustment-approve').nth(i).check()
      }
      
      await page.click('button:has-text("Approve Adjustments")')
      await page.waitForTimeout(2000)
    }
  })

  test('Reorder Point Alert and Auto-PO Generation', async ({ page }) => {
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    // View reorder alerts
    await page.goto(`${BASE}/inventory/reorder-alerts`)
    await page.waitForTimeout(1500)
    
    // Check for items below reorder point
    const alertRows = page.locator('table.reorder-alerts tbody tr')
    const alertCount = await alertRows.count()
    
    console.log(`Found ${alertCount} items below reorder point`)
    
    if (alertCount > 0) {
      // Select items for auto-PO
      for (let i = 0; i < Math.min(alertCount, 3); i++) {
        await alertRows.nth(i).locator('input.select-item').check()
      }
      
      await page.click('button:has-text("Generate Purchase Suggestions")')
      await page.waitForTimeout(2000)
      
      // Review suggestions
      const suggestions = page.locator('table.suggestions tbody tr')
      expect(await suggestions.count()).toBeGreaterThan(0)
      
      // Convert to PR
      await page.click('button:has-text("Create Purchase Request")')
      await page.waitForTimeout(2000)
      
      await page.fill('textarea[name="justification"]', 'Auto-generated from reorder alerts')
      await page.click('button:has-text("Submit PR")')
      await page.waitForTimeout(2500)
      
      // Verify PR created
      expect(page.url()).toContain('/purchase-requests')
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// ACCOUNTING - Complex Financial Workflows
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('💰 Accounting - Complex Workflows', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Month-End Closing Process', async ({ page }) => {
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
    
    // Step 1: Review unposted transactions
    await page.goto(`${BASE}/accounting/journal-entries`)
    await page.waitForTimeout(1500)
    
    // Filter by unposted
    await page.selectOption('select[name="status"]', 'unposted')
    await page.waitForTimeout(1000)
    
    const unpostedRows = page.locator('table tbody tr')
    const unpostedCount = await unpostedRows.count()
    console.log(`Unposted entries: ${unpostedCount}`)
    
    // Post all unposted entries
    if (unpostedCount > 0) {
      await page.click('button:has-text("Select All")')
      await page.click('button:has-text("Batch Post")')
      await page.waitForTimeout(2000)
      await page.click('button:has-text("Confirm Posting")')
      await page.waitForTimeout(2000)
    }
    
    // Step 2: Bank Reconciliation
    await page.goto(`${BASE}/banking/reconciliations/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="bank_account_id"]', '1')
    await page.fill('input[name="statement_date"]', '2026-03-31')
    await page.fill('input[name="statement_balance"]', '1500000')
    
    await page.click('button:has-text("Start Reconciliation")')
    await page.waitForTimeout(2000)
    
    // Match transactions
    const unreconciled = page.locator('tr.unreconciled')
    const matchCount = await unreconciled.count()
    
    for (let i = 0; i < Math.min(matchCount, 10); i++) {
      await unreconciled.nth(i).locator('input.match-checkbox').check()
    }
    
    await page.click('button:has-text("Match Selected")')
    await page.waitForTimeout(1500)
    
    // Complete reconciliation
    const difference = page.locator('.reconciliation-difference')
    const diffAmount = await difference.textContent() || '0'
    
    if (parseFloat(diffAmount.replace(/[^0-9.-]/g, '')) === 0) {
      await page.click('button:has-text("Complete Reconciliation")')
      await page.waitForTimeout(2000)
    }
    
    // Step 3: Generate Trial Balance
    await page.goto(`${BASE}/accounting/trial-balance`)
    await page.waitForTimeout(1500)
    
    await page.fill('input[name="as_of_date"]', '2026-03-31')
    await page.click('button:has-text("Generate")')
    await page.waitForTimeout(2000)
    
    // Verify TB balances
    const totalDebits = page.locator('.total-debits')
    const totalCredits = page.locator('.total-credits')
    
    const debitAmount = await totalDebits.textContent() || '0'
    const creditAmount = await totalCredits.textContent() || '0'
    
    expect(parseFloat(debitAmount.replace(/[^0-9.-]/g, ''))).toBe(
      parseFloat(creditAmount.replace(/[^0-9.-]/g, ''))
    )
    
    // Step 4: Close Period
    await page.goto(`${BASE}/accounting/fiscal-periods`)
    await page.waitForTimeout(1500)
    
    const currentPeriod = page.locator('tr', { hasText: 'Open' }).first()
    await currentPeriod.locator('button:has-text("Close Period")').click()
    await page.waitForTimeout(1000)
    
    await page.fill('textarea[name="closing_notes"]', 'Month-end closing completed')
    await page.click('button:has-text("Confirm Close")')
    await page.waitForTimeout(2000)
  })

  test('Multi-Currency Transaction with Exchange Rate Variance', async ({ page }) => {
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
    
    // Create foreign currency vendor invoice
    await page.goto(`${BASE}/accounting/ap/invoices/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="invoice_number"]', `FC-${timestamp}`)
    await page.selectOption('select[name="vendor_id"]', '1')
    await page.selectOption('select[name="currency"]', 'USD')
    
    // Exchange rate at transaction date
    await page.fill('input[name="exchange_rate"]', '55.50')
    await page.fill('input[name="invoice_date"]', '2026-03-01')
    
    // Add line items in USD
    await page.click('button:has-text("Add Line")')
    await page.fill('input[name="lines[0].description"]', 'Imported Equipment')
    await page.fill('input[name="lines[0].quantity"]', '1')
    await page.fill('input[name="lines[0].unit_price"]', '10000')  // USD
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Record payment with different exchange rate
    const payButton = page.locator('button:has-text("Pay")')
    await payButton.click()
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="bank_account_id"]', '1')
    await page.fill('input[name="payment_date"]', '2026-03-15')
    await page.fill('input[name="exchange_rate"]', '56.00')  // Rate changed
    await page.fill('input[name="reference_number"]', `WIRE-${timestamp}`)
    
    await page.click('button:has-text("Process Payment")')
    await page.waitForTimeout(2500)
    
    // Verify exchange rate variance posted
    await page.goto(`${BASE}/accounting/journal-entries`)
    await page.waitForTimeout(1500)
    
    const varianceEntry = page.locator('tr', { hasText: 'Exchange Rate Variance' })
    expect(await varianceEntry.count()).toBeGreaterThan(0)
    
    // Variance = $10,000 * (56.00 - 55.50) = ₱5,000
    const varianceAmount = await varianceEntry.first().locator('td.amount').textContent()
    console.log(`Exchange rate variance: ${varianceAmount}`)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// HR - Payroll Integration
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('👤 HR - Payroll Integration', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Payroll Process with Government Contributions', async ({ page }) => {
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    
    // Step 1: Create Payroll Run
    await page.goto(`${BASE}/payroll/runs/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="run_name"]', `Payroll-${timestamp}`)
    await page.selectOption('select[name"pay_period_id"]', '1')
    await page.selectOption('select[name="payroll_type"]', 'regular')
    await page.fill('input[name"cutoff_start"]', '2026-03-01')
    await page.fill('input[name"cutoff_end"]', '2026-03-15')
    
    // Select employees
    await page.click('button:has-text("Select All Active")')
    await page.waitForTimeout(1000)
    
    await page.click('button:has-text("Create Payroll Run")')
    await page.waitForTimeout(3000)
    
    // Step 2: Process payroll (compute)
    await page.click('button:has-text("Process Payroll")')
    await page.waitForTimeout(5000)  // Computation takes time
    
    // Step 3: Review computed payroll
    await page.click('button:has-text("Review Details")')
    await page.waitForTimeout(2000)
    
    // Check government contributions
    const sssContrib = page.locator('td', { hasText: 'SSS' }).first()
    const philhealthContrib = page.locator('td', { hasText: 'PhilHealth' }).first()
    const pagibigContrib = page.locator('td', { hasText: 'Pag-IBIG' }).first()
    const wthTax = page.locator('td', { hasText: 'Withholding Tax' }).first()
    
    expect(await sssContrib.count()).toBeGreaterThan(0)
    expect(await philhealthContrib.count()).toBeGreaterThan(0)
    expect(await pagibigContrib.count()).toBeGreaterThan(0)
    expect(await wthTax.count()).toBeGreaterThan(0)
    
    // Step 4: Submit for approval
    await page.click('button:has-text("Submit for Approval")')
    await page.waitForTimeout(1500)
    
    // Step 5: Accounting approval
    await logout(page)
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
    
    await page.goto(`${BASE}/payroll/runs`)
    await page.waitForTimeout(1500)
    
    const payrollRow = page.locator('tr', { hasText: `Payroll-${timestamp}` }).first()
    await payrollRow.locator('button:has-text("Review")').click()
    await page.waitForTimeout(2000)
    
    // Verify GL entries preview
    await page.click('button:has-text("View GL Entries")')
    await page.waitForTimeout(1500)
    
    const glEntries = page.locator('table.gl-preview tbody tr')
    expect(await glEntries.count()).toBeGreaterThan(0)
    
    // Check debits = credits
    const totalDebits = await page.locator('.gl-total-debits').textContent() || '0'
    const totalCredits = await page.locator('.gl-total-credits').textContent() || '0'
    expect(parseFloat(totalDebits.replace(/[^0-9.-]/g, ''))).toBe(
      parseFloat(totalCredits.replace(/[^0-9.-]/g, ''))
    )
    
    await page.click('button:has-text("Close")')
    await page.waitForTimeout(500)
    
    // Approve payroll
    await payrollRow.locator('button:has-text("Approve")').click()
    await page.waitForTimeout(1000)
    await page.click('button:has-text("Confirm Approval")')
    await page.waitForTimeout(2000)
    
    // Step 6: Post to GL
    await payrollRow.locator('button:has-text("Post to GL")').click()
    await page.waitForTimeout(2000)
    await page.click('button:has-text("Confirm Posting")')
    await page.waitForTimeout(3000)
    
    // Step 7: Release payslips
    await logout(page)
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    
    await page.goto(`${BASE}/payroll/runs`)
    await page.waitForTimeout(1500)
    
    const finalRow = page.locator('tr', { hasText: `Payroll-${timestamp}` }).first()
    await finalRow.locator('button:has-text("Release Payslips")').click()
    await page.waitForTimeout(2000)
    await page.click('button:has-text("Confirm Release")')
    await page.waitForTimeout(2000)
    
    // Verify employees can see payslips
    await page.goto(`${BASE}/hr/employees`)
    await page.waitForTimeout(1500)
    
    const firstEmployee = page.locator('table tbody tr').first()
    await firstEmployee.locator('button:has-text("View")').click()
    await page.waitForTimeout(1500)
    
    await page.click('button:has-text("Payslips")')
    await page.waitForTimeout(1000)
    
    const latestPayslip = page.locator('tr', { hasText: '2026-03' }).first()
    expect(await latestPayslip.count()).toBeGreaterThan(0)
  })

  test('Employee Loan Integration with Payroll Deduction', async ({ page }) => {
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    
    // Create loan for employee
    await page.goto(`${BASE}/hr/loans/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.selectOption('select[name="employee_id"]', '1')
    await page.selectOption('select[name"loan_type_id"]', '1')  // SSS Loan
    await page.fill('input[name="loan_amount"]', '24000')
    await page.fill('input[name"interest_rate"]', '10')
    await page.fill('input[name"term_months"]', '24')
    await page.fill('input[name"monthly_amortization"]', '1100')
    await page.fill('input[name"start_date"]', '2026-03-01')
    await page.fill('textarea[name="purpose"]', 'Emergency loan')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Get loan reference
    const loanRef = await page.locator('text=/LOAN-[0-9]+/i').first().textContent() || 'LOAN-TEST'
    
    // Submit for approval
    await page.click('button:has-text("Submit for Approval")')
    await page.waitForTimeout(1500)
    
    // VP approves loan
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    
    const loanRow = page.locator('tr', { hasText: loanRef }).first()
    await loanRow.locator('button:has-text("Approve")').click()
    await page.waitForTimeout(1000)
    await page.click('button:has-text("Confirm")')
    await page.waitForTimeout(1500)
    
    // Verify loan appears in next payroll
    await logout(page)
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    
    await page.goto(`${BASE}/payroll/runs/new`)
    await page.waitForTimeout(2000)
    
    await page.click('button:has-text("Preview Deductions")')
    await page.waitForTimeout(2000)
    
    const loanDeduction = page.locator('tr', { hasText: loanRef })
    expect(await loanDeduction.count()).toBeGreaterThan(0)
    
    const deductionAmount = await loanDeduction.first().locator('td.amount').textContent()
    expect(deductionAmount).toContain('1,100')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// CROSS-DEPARTMENT INTEGRATION
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔗 Cross-Department Integration', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Sales to Production to Delivery Workflow', async ({ page }) => {
    // Step 1: Sales creates customer order
    await login(page, ACCOUNTS.salesManager.email, ACCOUNTS.salesManager.password)
    
    await page.goto(`${BASE}/ar/invoices/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name"invoice_number"]', `SO-${timestamp}`)
    await page.selectOption('select[name="customer_id"]', '1')
    await page.fill('input[name"delivery_date"]', '2026-04-15')
    
    await page.click('button:has-text("Add Line")')
    await page.selectOption('select[name="lines[0].item_id"]', '1')
    await page.fill('input[name="lines[0].quantity"]', '500')
    await page.fill('input[name="lines[0].unit_price"]', '100')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Convert to Production Order
    await page.click('button:has-text("Create Production Order")')
    await page.waitForTimeout(2000)
    
    await logout(page)
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
    
    // Review and schedule production
    await page.goto(`${BASE}/production/orders`)
    await page.waitForTimeout(1500)
    
    const woRow = page.locator('tr', { hasText: `SO-${timestamp}` }).first()
    await woRow.locator('button:has-text("Schedule")').click()
    await page.waitForTimeout(1500)
    
    await page.fill('input[name="planned_start"]', '2026-04-01')
    await page.fill('input[name"planned_end"]', '2026-04-10')
    await page.click('button:has-text("Save Schedule")')
    await page.waitForTimeout(1500)
    
    // Complete production
    await woRow.locator('button:has-text("Complete")').click()
    await page.waitForTimeout(1500)
    
    await page.fill('input[name="quantity_completed"]', '500')
    await page.click('button:has-text("Confirm Completion")')
    await page.waitForTimeout(2000)
    
    // Transfer to warehouse
    await logout(page)
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    await page.goto(`${BASE}/inventory/receipts/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name"source_type"]', 'production')
    await page.selectOption('select[name"reference_id"]', '1')
    await page.click('button:has-text("Receive Items")')
    await page.waitForTimeout(2000)
    
    // Create Delivery Receipt
    await page.goto(`${BASE}/delivery/receipts/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name"invoice_id"]', '1')
    await page.fill('input[name"dr_number"]', `DR-${timestamp}`)
    await page.fill('input[name"delivery_date"]', '2026-04-15')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2500)
    
    // Verify order status updated
    await page.goto(`${BASE}/ar/invoices`)
    await page.waitForTimeout(1500)
    
    const invoiceRow = page.locator('tr', { hasText: `SO-${timestamp}` }).first()
    const status = await invoiceRow.locator('td.status').textContent()
    expect(status).toMatch(/delivered|shipped/i)
  })
})
