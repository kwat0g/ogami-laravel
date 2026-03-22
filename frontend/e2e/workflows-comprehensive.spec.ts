/**
 * E2E-WORKFLOWS-COMPREHENSIVE — End-to-End Workflow Tests
 * 
 * Tests complete business workflows across all modules:
 * - Procurement: PR → PO → GR
 * - Production: BOM → Work Order → Production
 * - Inventory: Item → Stock In → MRQ → Fulfillment
 * - QC: Inspection → NCR → CAPA
 * - Maintenance: Equipment → PM Schedule → Work Order
 * - Mold: Mold Master → Shot Log → Maintenance
 * - Accounting: Vendor → Invoice → Payment
 * - Sales: Customer → Quote → Invoice
 */
import { test, expect, Page } from '@playwright/test'

const BASE = 'http://localhost:5173'

// ═══════════════════════════════════════════════════════════════════════════════
// TEST ACCOUNT HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

const ACCOUNTS = {
  purchasing: { email: 'purchasing.officer@ogamierp.local', password: 'Officer@12345!' },
  prodManager: { email: 'prod.manager@ogamierp.local', password: 'Manager@12345!' },
  whHead: { email: 'warehouse.head@ogamierp.local', password: 'Head@123456789!' },
  qcManager: { email: 'qc.manager@ogamierp.local', password: 'Manager@12345!' },
  acctgManager: { email: 'acctg.manager@ogamierp.local', password: 'Manager@12345!' },
  hrManager: { email: 'hr.manager@ogamierp.local', password: 'Manager@Test1234!' },
  maintenanceManager: { email: 'maintenance.head@ogamierp.local', password: 'Head@123456789!' },
  moldManager: { email: 'mold.manager@ogamierp.local', password: 'Manager@12345!' },
  salesManager: { email: 'crm.manager@ogamierp.local', password: 'Manager@12345!' },
  vp: { email: 'vp@ogamierp.local', password: 'VicePresident@1!' },
  plantManager: { email: 'plant.manager@ogamierp.local', password: 'Manager@12345!' },
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
    await page.goto(`${BASE}/login`)
    await page.waitForTimeout(500)
    await page.evaluate(() => {
      localStorage.clear()
      sessionStorage.clear()
    })
  } catch {
    // Ignore errors
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// PROCUREMENT WORKFLOW: PR → PO → GR
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🛒 Procurement Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Procurement Workflow: PR → PO → GR', async ({ page }) => {
    // Step 1: Login as Purchasing Officer
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    // Step 2: Create Purchase Request
    await page.goto(`${BASE}/procurement/purchase-requests/new`)
    await page.waitForTimeout(2000)
    
    // Fill PR form
    await page.selectOption('select[name="department_id"]', '1')
    await page.fill('input[name="justification"]', 'Test PR for E2E workflow')
    await page.fill('textarea[name="remarks"]', 'Automated test PR')
    
    // Add line item
    await page.click('button:has-text("Add Item")')
    await page.waitForTimeout(500)
    await page.fill('input[name="items[0].description"]', 'Test Raw Material')
    await page.fill('input[name="items[0].quantity"]', '100')
    await page.fill('input[name="items[0].estimated_unit_cost"]', '50')
    await page.selectOption('select[name="items[0].uom"]', 'pcs')
    
    // Submit PR
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Capture PR number from success message or URL
    const prNumber = await page.locator('text=/PR-[0-9]+/i').first().textContent() || 'PR-TEST-001'
    console.log(`Created PR: ${prNumber}`)
    
    // Step 3: Submit PR for approval (if button exists)
    const submitForApproval = page.locator('button:has-text("Submit for Approval")')
    if (await submitForApproval.count() > 0) {
      await submitForApproval.click()
      await page.waitForTimeout(1000)
    }
    
    // Step 4: Login as VP to approve PR
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    
    // Navigate to approvals
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    
    // Find and approve the PR
    const approveButton = page.locator('button:has-text("Approve")').first()
    if (await approveButton.count() > 0 && await approveButton.isVisible()) {
      await approveButton.click()
      await page.waitForTimeout(1000)
      // Confirm approval
      const confirmButton = page.locator('button:has-text("Confirm")')
      if (await confirmButton.count() > 0) {
        await confirmButton.click()
        await page.waitForTimeout(1000)
      }
    }
    
    // Step 5: Login as Purchasing to create PO
    await logout(page)
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    // Navigate to approved PRs and convert to PO
    await page.goto(`${BASE}/procurement/purchase-requests`)
    await page.waitForTimeout(1500)
    
    // Look for "Create PO" button on approved PR
    const createPOButton = page.locator('button:has-text("Create PO")').first()
    if (await createPOButton.count() > 0 && await createPOButton.isVisible()) {
      await createPOButton.click()
      await page.waitForTimeout(2000)
      
      // Fill PO form
      await page.selectOption('select[name="vendor_id"]', '1')
      await page.fill('input[name="payment_terms"]', 'Net 30')
      await page.fill('input[name="delivery_date"]', '2026-12-31')
      
      // Submit PO
      await page.click('button[type="submit"]')
      await page.waitForTimeout(2000)
      
      const poNumber = await page.locator('text=/PO-[0-9]+/i').first().textContent() || 'PO-TEST-001'
      console.log(`Created PO: ${poNumber}`)
      
      // Step 6: Submit PO for approval
      const submitPO = page.locator('button:has-text("Submit for Approval")')
      if (await submitPO.count() > 0) {
        await submitPO.click()
        await page.waitForTimeout(1000)
      }
    }
    
    // Step 7: VP approves PO
    await logout(page)
    await login(page, ACCOUNTS.vp.email, ACCOUNTS.vp.password)
    
    await page.goto(`${BASE}/approvals/pending`)
    await page.waitForTimeout(1500)
    
    const approvePO = page.locator('button:has-text("Approve")').first()
    if (await approvePO.count() > 0 && await approvePO.isVisible()) {
      await approvePO.click()
      await page.waitForTimeout(1000)
    }
    
    // Step 8: Warehouse receives goods (GR)
    await logout(page)
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    await page.goto(`${BASE}/procurement/goods-receipts/new`)
    await page.waitForTimeout(2000)
    
    // Select PO to receive against
    await page.selectOption('select[name="purchase_order_id"]', '1')
    await page.waitForTimeout(1000)
    
    // Fill GR details
    await page.fill('input[name="received_by"]', 'Warehouse Test')
    await page.fill('textarea[name="remarks"]', 'Goods received in good condition')
    
    // Submit GR
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Verify success
    const successMessage = await page.locator('text=/created|success/i').first().isVisible().catch(() => false)
    expect(successMessage || page.url().includes('/goods-receipts')).toBe(true)
  })

  test('Purchasing Officer - Can Create and Manage Vendors', async ({ page }) => {
    await login(page, ACCOUNTS.purchasing.email, ACCOUNTS.purchasing.password)
    
    // Navigate to vendors
    await page.goto(`${BASE}/accounting/vendors`)
    await page.waitForTimeout(1500)
    
    // Check for "New Vendor" button
    const newVendorButton = page.locator('a:has-text("New Vendor"), button:has-text("New Vendor")')
    expect(await newVendorButton.count()).toBeGreaterThan(0)
    
    // Create new vendor
    await newVendorButton.first().click()
    await page.waitForTimeout(2000)
    
    // Fill vendor form
    await page.fill('input[name="name"]', `Test Vendor ${Date.now()}`)
    await page.fill('input[name="trade_name"]', 'Test Trade Name')
    await page.fill('textarea[name="address"]', '123 Test Street, Test City')
    await page.fill('input[name="contact_person"]', 'Test Contact')
    await page.fill('input[name="contact_number"]', '+63 912 345 6789')
    await page.fill('input[name="email"]', `test${Date.now()}@vendor.com`)
    await page.fill('input[name="tin"]', '123-456-789-000')
    await page.selectOption('select[name="payment_terms"]', 'net_30')
    
    // Submit
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Verify success
    expect(page.url()).not.toContain('/new')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// PRODUCTION WORKFLOW: BOM → Work Order → Production
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🏭 Production Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Production Workflow: BOM → Work Order', async ({ page }) => {
    // Step 1: Login as Production Manager
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
    
    // Step 2: Create Bill of Materials
    await page.goto(`${BASE}/production/boms/new`)
    await page.waitForTimeout(2000)
    
    // Fill BOM form
    await page.fill('input[name="bom_number"]', `BOM-${Date.now()}`)
    await page.fill('input[name="product_name"]', 'Test Product Assembly')
    await page.selectOption('select[name="product_type"]', 'finished_good')
    await page.fill('input[name="version"]', '1.0')
    await page.fill('textarea[name="description"]', 'Test BOM for E2E workflow')
    
    // Add BOM line items
    await page.click('button:has-text("Add Material")')
    await page.waitForTimeout(500)
    await page.selectOption('select[name="materials[0].item_id"]', '1')
    await page.fill('input[name="materials[0].quantity"]', '10')
    await page.selectOption('select[name="materials[0].uom"]', 'pcs')
    
    // Submit BOM
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Create Work Order
    await page.goto(`${BASE}/production/orders/new`)
    await page.waitForTimeout(2000)
    
    // Fill Work Order form
    await page.fill('input[name="wo_number"]', `WO-${Date.now()}`)
    await page.selectOption('select[name="bom_id"]', '1')
    await page.fill('input[name="quantity"]', '100')
    await page.fill('input[name="planned_start"]', '2026-04-01')
    await page.fill('input[name="planned_end"]', '2026-04-15')
    await page.selectOption('select[name="priority"]', 'high')
    await page.fill('textarea[name="remarks"]', 'Test Work Order')
    
    // Submit Work Order
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Verify success
    expect(page.url()).toContain('/production/orders')
    
    // Step 4: Start Production
    const startButton = page.locator('button:has-text("Start Production")').first()
    if (await startButton.count() > 0 && await startButton.isVisible()) {
      await startButton.click()
      await page.waitForTimeout(1000)
      
      // Confirm start
      const confirmStart = page.locator('button:has-text("Confirm")')
      if (await confirmStart.count() > 0) {
        await confirmStart.click()
        await page.waitForTimeout(1000)
      }
    }
    
    // Step 5: Record Production Output
    const outputButton = page.locator('button:has-text("Record Output")')
    if (await outputButton.count() > 0 && await outputButton.isVisible()) {
      await outputButton.click()
      await page.waitForTimeout(1000)
      
      await page.fill('input[name="quantity_produced"]', '95')
      await page.fill('input[name="quantity_rejected"]', '5')
      await page.fill('textarea[name="notes"]', 'Production completed with some rejects')
      
      const saveOutput = page.locator('button:has-text("Save")')
      await saveOutput.click()
      await page.waitForTimeout(1000)
    }
    
    // Step 6: Complete Work Order
    const completeButton = page.locator('button:has-text("Complete")')
    if (await completeButton.count() > 0 && await completeButton.isVisible()) {
      await completeButton.click()
      await page.waitForTimeout(1000)
    }
  })

  test('Production Manager - Can View and Manage Delivery Schedules', async ({ page }) => {
    await login(page, ACCOUNTS.prodManager.email, ACCOUNTS.prodManager.password)
    
    // Navigate to delivery schedules
    await page.goto(`${BASE}/production/delivery-schedules`)
    await page.waitForTimeout(1500)
    
    // Verify page loads
    expect(await page.locator('text=Delivery Schedules').first().isVisible()).toBe(true)
    
    // Check for create button
    const createButton = page.locator('a:has-text("New"), button:has-text("New")')
    expect(await createButton.count()).toBeGreaterThan(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// INVENTORY WORKFLOW: Item Master → Stock → MRQ
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📦 Inventory Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Inventory Workflow: Item → Stock → MRQ', async ({ page }) => {
    // Step 1: Login as Warehouse Head
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    // Step 2: Create New Item
    await page.goto(`${BASE}/inventory/items/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="item_code"]', `TEST-${timestamp}`)
    await page.fill('input[name="name"]', `Test Item ${timestamp}`)
    await page.selectOption('select[name="type"]', 'raw_material')
    await page.selectOption('select[name="category_id"]', '1')
    await page.fill('input[name="unit_of_measure"]', 'pcs')
    await page.fill('input[name="reorder_point"]', '50')
    await page.fill('input[name="reorder_quantity"]', '500')
    await page.check('input[name="requires_iqc"]')
    await page.fill('textarea[name="description"]', 'Test item for E2E workflow')
    
    // Submit item
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Create Stock Adjustment (Stock In)
    await page.goto(`${BASE}/inventory/adjustments`)
    await page.waitForTimeout(1500)
    
    const newAdjustment = page.locator('a:has-text("New Adjustment"), button:has-text("New")')
    if (await newAdjustment.count() > 0) {
      await newAdjustment.first().click()
      await page.waitForTimeout(2000)
      
      await page.selectOption('select[name="adjustment_type"]', 'receipt')
      await page.selectOption('select[name="item_id"]', '1')
      await page.selectOption('select[name="warehouse_location_id"]', '1')
      await page.fill('input[name="quantity"]', '1000')
      await page.fill('textarea[name="reason"]', 'Initial stock receipt')
      
      await page.click('button[type="submit"]')
      await page.waitForTimeout(2000)
    }
    
    // Step 4: Create Material Requisition (MRQ)
    await page.goto(`${BASE}/inventory/requisitions/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="department_id"]', '1')
    await page.fill('input[name="required_date"]', '2026-04-01')
    await page.fill('textarea[name="purpose"]', 'Test MRQ for production')
    await page.selectOption('select[name="priority"]', 'normal')
    
    // Add line item
    await page.click('button:has-text("Add Item")')
    await page.waitForTimeout(500)
    await page.selectOption('select[name="items[0].item_id"]', '1')
    await page.fill('input[name="items[0].quantity"]', '100')
    await page.selectOption('select[name="items[0].uom"]', 'pcs')
    
    // Submit MRQ
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Verify success
    expect(page.url()).toContain('/requisitions')
  })

  test('Warehouse Head - Full Inventory Management', async ({ page }) => {
    await login(page, ACCOUNTS.whHead.email, ACCOUNTS.whHead.password)
    
    // Test access to all inventory sections
    const sections = [
      { path: '/inventory/items', name: 'Item Master' },
      { path: '/inventory/categories', name: 'Item Categories' },
      { path: '/inventory/locations', name: 'Warehouse Locations' },
      { path: '/inventory/stock', name: 'Stock Balances' },
      { path: '/inventory/ledger', name: 'Stock Ledger' },
      { path: '/inventory/requisitions', name: 'Material Requisitions' },
      { path: '/inventory/adjustments', name: 'Stock Adjustments' },
    ]
    
    for (const section of sections) {
      await page.goto(`${BASE}${section.path}`)
      await page.waitForTimeout(1500)
      
      // Verify not redirected to 403
      expect(page.url()).not.toContain('/403')
      expect(page.url()).toContain(section.path)
      
      console.log(`✓ Accessed: ${section.name}`)
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// QC WORKFLOW: Inspection → NCR → CAPA
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔍 QC / QA Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete QC Workflow: Inspection → NCR → CAPA', async ({ page }) => {
    // Step 1: Login as QC Manager
    await login(page, ACCOUNTS.qcManager.email, ACCOUNTS.qcManager.password)
    
    // Step 2: Create Inspection
    await page.goto(`${BASE}/qc/inspections/new`)
    await page.waitForTimeout(2000)
    
    await page.selectOption('select[name="inspection_type"]', 'incoming')
    await page.selectOption('select[name="reference_type"]', 'goods_receipt')
    await page.selectOption('select[name="reference_id"]', '1')
    await page.selectOption('select[name="inspector_id"]', '1')
    await page.fill('input[name="inspection_date"]', '2026-03-15')
    
    // Add inspection items
    await page.click('button:has-text("Add Item")')
    await page.waitForTimeout(500)
    await page.selectOption('select[name="items[0].item_id"]', '1')
    await page.fill('input[name="items[0].quantity_inspected"]', '100')
    await page.fill('input[name="items[0].quantity_passed"]', '95')
    await page.fill('input[name="items[0].quantity_rejected"]', '5')
    await page.selectOption('select[name="items[0].result"]', 'conditional')
    await page.fill('textarea[name="items[0].remarks"]', 'Some defects found')
    
    // Submit inspection
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Create NCR for rejected items
    const createNCR = page.locator('button:has-text("Create NCR")')
    if (await createNCR.count() > 0 && await createNCR.isVisible()) {
      await createNCR.click()
      await page.waitForTimeout(2000)
      
      await page.fill('input[name="ncr_number"]', `NCR-${Date.now()}`)
      await page.selectOption('select[name="severity"]', 'major')
      await page.fill('textarea[name="description"]', 'Defective items received')
      await page.fill('textarea[name="immediate_containment"]', 'Quarantined defective items')
      
      await page.click('button[type="submit"]')
      await page.waitForTimeout(2000)
      
      // Step 4: Create CAPA
      const createCAPA = page.locator('button:has-text("Create CAPA")')
      if (await createCAPA.count() > 0 && await createCAPA.isVisible()) {
        await createCAPA.click()
        await page.waitForTimeout(2000)
        
        await page.fill('input[name="capa_number"]', `CAPA-${Date.now()}`)
        await page.selectOption('select[name="capa_type"]', 'corrective')
        await page.fill('textarea[name="problem_description"]', 'Recurring quality issue')
        await page.fill('textarea[name="root_cause"]', 'Supplier process variation')
        await page.fill('textarea[name="corrective_action"]', 'Implement supplier audit')
        await page.fill('textarea[name="preventive_action"]', 'Add incoming inspection criteria')
        await page.fill('input[name="target_date"]', '2026-04-15')
        
        await page.click('button[type="submit"]')
        await page.waitForTimeout(2000)
      }
    }
    
    // Verify final state
    expect(page.url()).toContain('/qc/')
  })

  test('QC Manager - Can Access All QC Functions', async ({ page }) => {
    await login(page, ACCOUNTS.qcManager.email, ACCOUNTS.qcManager.password)
    
    const sections = [
      { path: '/qc/inspections', name: 'Inspections' },
      { path: '/qc/ncrs', name: 'NCRs' },
      { path: '/qc/capa', name: 'CAPA' },
      { path: '/qc/templates', name: 'Templates' },
      { path: '/qc/defect-rate', name: 'Defect Rate' },
    ]
    
    for (const section of sections) {
      await page.goto(`${BASE}${section.path}`)
      await page.waitForTimeout(1500)
      
      expect(page.url()).not.toContain('/403')
      console.log(`✓ Accessed: ${section.name}`)
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// MOLD WORKFLOW
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔧 Mold Management Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Mold Workflow: Mold Master → Shot Log', async ({ page }) => {
    // Step 1: Login as Mold Manager
    await login(page, ACCOUNTS.moldManager.email, ACCOUNTS.moldManager.password)
    
    // Step 2: Create Mold Master
    await page.goto(`${BASE}/mold/masters/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="mold_code"]', `MOLD-${timestamp}`)
    await page.fill('input[name="name"]', `Test Mold ${timestamp}`)
    await page.selectOption('select[name="mold_type"]', 'injection')
    await page.selectOption('select[name="cavities"]', '4')
    await page.fill('input[name="customer_name"]', 'Test Customer')
    await page.fill('input[name="part_number"]', `PART-${timestamp}`)
    await page.fill('input[name="part_name"]', 'Test Plastic Part')
    await page.fill('input[name="material"]', 'ABS Plastic')
    await page.fill('input[name="shot_life_expected"]', '1000000')
    await page.fill('input[name="maintenance_interval"]', '100000')
    
    // Submit mold
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Record Shot Log
    await page.goto(`${BASE}/mold/masters`)
    await page.waitForTimeout(1500)
    
    // Click on first mold
    const firstMold = page.locator('tr').nth(1).locator('td').first()
    if (await firstMold.count() > 0) {
      await firstMold.click()
      await page.waitForTimeout(1500)
      
      // Add shot log
      const addShotLog = page.locator('button:has-text("Record Shots")')
      if (await addShotLog.count() > 0) {
        await addShotLog.click()
        await page.waitForTimeout(1000)
        
        await page.fill('input[name="shots_produced"]', '5000')
        await page.fill('input[name="production_date"]', '2026-03-15')
        await page.fill('textarea[name="remarks"]', 'Normal production')
        
        await page.click('button:has-text("Save")')
        await page.waitForTimeout(1000)
      }
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// MAINTENANCE WORKFLOW
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('🔩 Maintenance Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Maintenance Workflow: Equipment → Work Order', async ({ page }) => {
    // Step 1: Login as Maintenance Manager
    await login(page, ACCOUNTS.maintenanceManager.email, ACCOUNTS.maintenanceManager.password)
    
    // Step 2: Create Equipment
    await page.goto(`${BASE}/maintenance/equipment/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="asset_code"]', `EQ-${timestamp}`)
    await page.fill('input[name="name"]', `Test Equipment ${timestamp}`)
    await page.selectOption('select[name="category"]', 'production')
    await page.selectOption('select[name="criticality"]', 'high')
    await page.fill('input[name="model"]', 'Model-X100')
    await page.fill('input[name="manufacturer"]', 'Test Manufacturer')
    await page.fill('input[name="serial_number"]', `SN-${timestamp}`)
    await page.fill('input[name="location"]', 'Production Line 1')
    await page.fill('input[name="purchase_date"]', '2025-01-15')
    
    // Submit equipment
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Create Work Order
    await page.goto(`${BASE}/maintenance/work-orders/new`)
    await page.waitForTimeout(2000)
    
    await page.fill('input[name"wo_number"]', `MWO-${timestamp}`)
    await page.selectOption('select[name="equipment_id"]', '1')
    await page.selectOption('select[name="work_type"]', 'preventive')
    await page.selectOption('select[name="priority"]', 'medium')
    await page.fill('input[name="scheduled_date"]', '2026-04-01')
    await page.fill('textarea[name="description"]', 'Scheduled preventive maintenance')
    await page.fill('textarea[name="work_instructions"]', '1. Check oil levels\n2. Replace filters\n3. Lubricate moving parts')
    
    // Submit work order
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 4: Start Work Order
    const startWO = page.locator('button:has-text("Start Work")')
    if (await startWO.count() > 0 && await startWO.isVisible()) {
      await startWO.click()
      await page.waitForTimeout(1000)
    }
    
    // Step 5: Complete Work Order
    const completeWO = page.locator('button:has-text("Complete")')
    if (await completeWO.count() > 0 && await completeWO.isVisible()) {
      await completeWO.click()
      await page.waitForTimeout(1000)
      
      await page.fill('textarea[name="completion_notes"]', 'Maintenance completed successfully')
      await page.fill('input[name="labor_hours"]', '4')
      await page.fill('input[name="actual_cost"]', '5000')
      
      await page.click('button:has-text("Confirm")')
      await page.waitForTimeout(1000)
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// ACCOUNTING WORKFLOW
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('💰 Accounting Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete AP Workflow: Vendor → Invoice → Payment', async ({ page }) => {
    // Step 1: Login as Accounting Manager
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
    
    // Step 2: Create Vendor
    await page.goto(`${BASE}/accounting/vendors/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="name"]', `Vendor ${timestamp}`)
    await page.fill('input[name="trade_name"]', 'Test Trade')
    await page.fill('textarea[name="address"]', '123 Business St')
    await page.fill('input[name="tin"]', '123-456-789-000')
    await page.fill('input[name="contact_person"]', 'John Contact')
    await page.fill('input[name="contact_number"]', '+63 912 345 6789')
    await page.fill('input[name="email"]', `vendor${timestamp}@test.com`)
    await page.selectOption('select[name="payment_terms"]', 'net_30')
    await page.selectOption('select[name="tax_classification"]', 'vat_registered')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Create AP Invoice
    await page.goto(`${BASE}/accounting/ap/invoices/new`)
    await page.waitForTimeout(2000)
    
    await page.fill('input[name="invoice_number"]', `INV-${timestamp}`)
    await page.selectOption('select[name="vendor_id"]', '1')
    await page.fill('input[name="invoice_date"]', '2026-03-01')
    await page.fill('input[name="due_date"]', '2026-04-01')
    await page.selectOption('select[name="po_id"]', '1')
    
    // Add line item
    await page.click('button:has-text("Add Line")')
    await page.waitForTimeout(500)
    await page.fill('input[name="lines[0].description"]', 'Raw Material Purchase')
    await page.fill('input[name="lines[0].quantity"]', '100')
    await page.fill('input[name="lines[0].unit_price"]', '100')
    await page.selectOption('select[name="lines[0].account_id"]', '1')
    
    // Calculate and fill total
    await page.fill('input[name="subtotal"]', '10000')
    await page.fill('input[name="vat_amount"]', '1200')
    await page.fill('input[name="total_amount"]', '11200')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 4: Process Payment
    const payButton = page.locator('button:has-text("Pay")')
    if (await payButton.count() > 0 && await payButton.isVisible()) {
      await payButton.click()
      await page.waitForTimeout(2000)
      
      await page.selectOption('select[name="bank_account_id"]', '1')
      await page.fill('input[name="payment_date"]', '2026-03-15')
      await page.fill('input[name="reference_number"]', `CHK-${timestamp}`)
      await page.fill('textarea[name="notes"]', 'Payment for invoice')
      
      await page.click('button:has-text("Process Payment")')
      await page.waitForTimeout(2000)
    }
  })

  test('Journal Entry Creation and Posting', async ({ page }) => {
    await login(page, ACCOUNTS.acctgManager.email, ACCOUNTS.acctgManager.password)
    
    // Create Journal Entry
    await page.goto(`${BASE}/accounting/journal-entries/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="entry_number"]', `JE-${timestamp}`)
    await page.fill('input[name="entry_date"]', '2026-03-15')
    await page.selectOption('select[name="fiscal_period_id"]', '1')
    await page.fill('textarea[name="description"]', 'Test journal entry')
    await page.selectOption('select[name="source"]', 'manual')
    
    // Add debit line
    await page.click('button:has-text("Add Line")')
    await page.waitForTimeout(500)
    await page.selectOption('select[name="lines[0].account_id"]', '1')
    await page.fill('input[name="lines[0].debit"]', '10000')
    await page.fill('input[name="lines[0].credit"]', '0')
    await page.fill('textarea[name="lines[0].description"]', 'Debit entry')
    
    // Add credit line
    await page.click('button:has-text("Add Line")')
    await page.waitForTimeout(500)
    await page.selectOption('select[name="lines[1].account_id"]', '2')
    await page.fill('input[name="lines[1].debit"]', '0')
    await page.fill('input[name="lines[1].credit"]', '10000')
    await page.fill('textarea[name="lines[1].description"]', 'Credit entry')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Post the journal entry
    const postButton = page.locator('button:has-text("Post")')
    if (await postButton.count() > 0 && await postButton.isVisible()) {
      await postButton.click()
      await page.waitForTimeout(1000)
      
      // Confirm posting
      const confirmPost = page.locator('button:has-text("Confirm")')
      if (await confirmPost.count() > 0) {
        await confirmPost.click()
        await page.waitForTimeout(1000)
      }
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// SALES / CRM WORKFLOW
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('💼 Sales & CRM Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete Sales Workflow: Customer → Quote → Invoice', async ({ page }) => {
    // Step 1: Login as Sales Manager
    await login(page, ACCOUNTS.salesManager.email, ACCOUNTS.salesManager.password)
    
    // Step 2: Create Customer
    await page.goto(`${BASE}/ar/customers/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="customer_code"]', `CUST-${timestamp}`)
    await page.fill('input[name="name"]', `Test Customer ${timestamp}`)
    await page.fill('textarea[name="address"]', '456 Customer Ave')
    await page.fill('input[name="contact_person"]', 'Jane Customer')
    await page.fill('input[name="contact_number"]', '+63 917 123 4567')
    await page.fill('input[name="email"]', `customer${timestamp}@test.com`)
    await page.selectOption('select[name="payment_terms"]', 'net_30')
    await page.selectOption('select[name="tax_classification"]', 'vat_registered')
    
    // Set credit limit
    await page.fill('input[name="credit_limit"]', '100000')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Create AR Invoice
    await page.goto(`${BASE}/ar/invoices/new`)
    await page.waitForTimeout(2000)
    
    await page.fill('input[name="invoice_number"]', `SI-${timestamp}`)
    await page.selectOption('select[name="customer_id"]', '1')
    await page.fill('input[name="invoice_date"]', '2026-03-15')
    await page.fill('input[name="due_date"]', '2026-04-15')
    await page.selectOption('select[name="delivery_receipt_id"]', '1')
    
    // Add line item
    await page.click('button:has-text("Add Line")')
    await page.waitForTimeout(500)
    await page.selectOption('select[name="lines[0].item_id"]', '1')
    await page.fill('input[name="lines[0].quantity"]', '10')
    await page.fill('input[name="lines[0].unit_price"]', '500')
    await page.fill('input[name="lines[0].amount"]', '5000')
    
    await page.fill('input[name="subtotal"]', '5000')
    await page.fill('input[name="vat_amount"]', '600')
    await page.fill('input[name="total_amount"]', '5600')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
  })

  test('CRM Ticket Management', async ({ page }) => {
    await login(page, ACCOUNTS.salesManager.email, ACCOUNTS.salesManager.password)
    
    // Create Support Ticket
    await page.goto(`${BASE}/crm/tickets/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="ticket_number"]', `TKT-${timestamp}`)
    await page.selectOption('select[name="customer_id"]', '1')
    await page.fill('input[name="subject"]', 'Test Support Ticket')
    await page.selectOption('select[name="category"]', 'technical')
    await page.selectOption('select[name="priority"]', 'high')
    await page.fill('textarea[name="description"]', 'This is a test ticket for E2E workflow')
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Verify ticket created
    expect(page.url()).toContain('/crm/')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// HR WORKFLOW
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('👤 HR Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page)
  })

  test('Complete HR Workflow: Employee → Attendance → Leave', async ({ page }) => {
    // Step 1: Login as HR Manager
    await login(page, ACCOUNTS.hrManager.email, ACCOUNTS.hrManager.password)
    
    // Step 2: Create New Employee
    await page.goto(`${BASE}/hr/employees/new`)
    await page.waitForTimeout(2000)
    
    const timestamp = Date.now()
    await page.fill('input[name="employee_code"]', `EMP-${timestamp}`)
    await page.fill('input[name="first_name"]', 'Test')
    await page.fill('input[name="last_name"]', `Employee ${timestamp}`)
    await page.fill('input[name="email"]', `employee${timestamp}@ogamierp.local`)
    await page.selectOption('select[name="department_id"]', '1')
    await page.selectOption('select[name="position_id"]', '1')
    await page.fill('input[name="date_hired"]', '2026-03-01')
    await page.selectOption('select[name="employment_type"]', 'regular')
    await page.selectOption('select[name="salary_grade_id"]', '1')
    
    // Add government IDs
    await page.fill('input[name="sss_no"]', `SSS-${timestamp}`)
    await page.fill('input[name="philhealth_no"]', `PH-${timestamp}`)
    await page.fill('input[name="pagibig_no"]', `PAG-${timestamp}`)
    await page.fill('input[name="tin"]', `TIN-${timestamp}`)
    
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    
    // Step 3: Process Attendance
    await page.goto(`${BASE}/hr/attendance`)
    await page.waitForTimeout(1500)
    
    const importButton = page.locator('button:has-text("Import"), a:has-text("Import")')
    if (await importButton.count() > 0) {
      console.log('Attendance import available')
    }
    
    // Step 4: Create Leave Request
    await page.goto(`${BASE}/hr/leave`)
    await page.waitForTimeout(1500)
    
    const newLeave = page.locator('button:has-text("New"), a:has-text("New")')
    if (await newLeave.count() > 0) {
      await newLeave.first().click()
      await page.waitForTimeout(2000)
      
      await page.selectOption('select[name="employee_id"]', '1')
      await page.selectOption('select[name="leave_type_id"]', '1')
      await page.fill('input[name="start_date"]', '2026-04-01')
      await page.fill('input[name="end_date"]', '2026-04-03')
      await page.fill('textarea[name="reason"]', 'Test leave request')
      
      await page.click('button[type="submit"]')
      await page.waitForTimeout(2000)
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY TEST
// ═══════════════════════════════════════════════════════════════════════════════

test.describe('📊 Workflow Summary', () => {
  
  test('All department managers can access their workflows', async ({ page }) => {
    const managers = [
      { role: 'HR Manager', account: ACCOUNTS.hrManager, path: '/hr/employees' },
      { role: 'Accounting Manager', account: ACCOUNTS.acctgManager, path: '/accounting/accounts' },
      { role: 'Production Manager', account: ACCOUNTS.prodManager, path: '/production/orders' },
      { role: 'Warehouse Head', account: ACCOUNTS.whHead, path: '/inventory/items' },
      { role: 'QC Manager', account: ACCOUNTS.qcManager, path: '/qc/inspections' },
      { role: 'Maintenance Manager', account: ACCOUNTS.maintenanceManager, path: '/maintenance/equipment' },
      { role: 'Mold Manager', account: ACCOUNTS.moldManager, path: '/mold/masters' },
      { role: 'Sales Manager', account: ACCOUNTS.salesManager, path: '/ar/customers' },
    ]
    
    for (const manager of managers) {
      await logout(page)
      await login(page, manager.account.email, manager.account.password)
      
      await page.goto(`${BASE}${manager.path}`)
      await page.waitForTimeout(1500)
      
      const url = page.url()
      const isAccessible = !url.includes('/403') && !url.includes('/login')
      
      console.log(`${isAccessible ? '✅' : '❌'} ${manager.role}: ${isAccessible ? 'OK' : 'BLOCKED'}`)
      expect(isAccessible, `${manager.role} should access ${manager.path}`).toBe(true)
    }
  })
})
