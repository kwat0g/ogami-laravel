/**
 * Production Order — Full Lifecycle Workflow (E2E)
 * 
 * Real-life scenario: Production Manager creates an order,
 * adds BOM items, submits for approval, Production Head approves,
 * work begins, and order is completed.
 */
import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';

test.describe('🏭 Production Order — Full Lifecycle', () => {
  
  test.beforeEach(async ({ page }) => {
    await logout(page);
  });

  test('complete production order workflow', async ({ page }) => {
    // ── STEP 1: Production Manager creates order ─────────────────────────────
    await loginAs(page, 'prod_manager');
    
    await page.goto('/production/orders');
    await expect(page.locator('h1, h2')).toContainText('Production');
    
    // Click New Order
    await page.click('text=New Order, button:has-text("New")');
    await page.waitForURL(/production\/orders\/new/);
    
    // Fill form (discovered fields from production order form)
    await page.fill('[name="order_number"]', 'WO-' + Date.now());
    await page.fill('[name="product_name"]', 'Test Product');
    await page.fill('[name="quantity"]', '100');
    await page.selectOption('[name="priority"]', 'normal');
    
    // Submit
    await page.click('button[type="submit"]');
    
    // Should be created with draft status
    await page.waitForURL(/production\/orders\/\w+/);
    await expect(page.locator('[data-testid="status-badge"]')).toContainText('draft');
    
    const orderUrl = page.url();
    const orderId = orderUrl.split('/').pop();
    
    // ── STEP 2: Add BOM items ────────────────────────────────────────────────
    await page.click('text=Add BOM Item');
    await page.fill('[name="item_code"]', 'RAW-001');
    await page.fill('[name="quantity"]', '50');
    await page.click('button:has-text("Add")');
    
    // Should see BOM item in list
    await expect(page.locator('text=RAW-001')).toBeVisible();
    
    // ── STEP 3: Submit for approval ──────────────────────────────────────────
    await page.click('text=Submit for Approval');
    await page.click('button:has-text("Confirm")');
    
    await expect(page.locator('[data-testid="status-badge"]'))
          .toContainText('pending_approval');
    
    // ── STEP 4: Production Head approves ─────────────────────────────────────
    await logout(page);
    await loginAs(page, 'prod_head');
    
    await page.goto('/production/orders');
    
    // Find the order (by order number or status)
    await page.click(`a[href*="${orderId}"]`);
    
    await page.click('text=Approve');
    await page.fill('[name="approval_notes"]', 'Approved - proceed with production');
    await page.click('button:has-text("Confirm Approval")');
    
    await expect(page.locator('[data-testid="status-badge"]'))
          .toContainText('approved');
    
    // ── STEP 5: Start work ───────────────────────────────────────────────────
    await page.click('text=Start Work');
    await page.click('button:has-text("Confirm")');
    
    await expect(page.locator('[data-testid="status-badge"]'))
          .toContainText('in_progress');
    
    // ── STEP 6: Log output ───────────────────────────────────────────────────
    await page.click('text=Log Output');
    await page.fill('[name="quantity_produced"]', '100');
    await page.fill('[name="defects"]', '2');
    await page.click('button:has-text("Save")');
    
    // ── STEP 7: Complete order ───────────────────────────────────────────────
    await page.click('text=Complete Order');
    await page.click('button:has-text("Confirm")');
    
    await expect(page.locator('[data-testid="status-badge"]'))
          .toContainText('completed');
  });

  test('Production Head can reject order with reason', async ({ page }) => {
    await loginAs(page, 'prod_manager');
    
    // Create and submit order
    await page.goto('/production/orders/new');
    await page.fill('[name="order_number"]', 'WO-REJECT-' + Date.now());
    await page.fill('[name="product_name"]', 'Test Product');
    await page.click('button[type="submit"]');
    
    await page.waitForURL(/production\/orders\/\w+/);
    await page.click('text=Submit for Approval');
    await page.click('button:has-text("Confirm")');
    
    const orderUrl = page.url();
    
    // Reject as Production Head
    await logout(page);
    await loginAs(page, 'prod_head');
    
    await page.goto(orderUrl);
    await page.click('text=Reject');
    await page.fill('[name="rejection_reason"]', 'Insufficient materials in stock');
    await page.click('button:has-text("Confirm Rejection")');
    
    await expect(page.locator('[data-testid="status-badge"]'))
          .toContainText('rejected');
  });

  test('Production Staff cannot create orders', async ({ page }) => {
    await loginAs(page, 'prod_staff');
    
    await page.goto('/production/orders');
    
    // Should not see New Order button
    await expect(page.locator('text=New Order, button:has-text("New")')).toBeHidden();
    
    // Direct URL should also fail
    await page.goto('/production/orders/new');
    await page.waitForTimeout(1000);
    
    // Should be redirected or show 403
    expect(page.url()).not.toContain('/production/orders/new');
  });

  test('validation errors show for missing required fields', async ({ page }) => {
    await loginAs(page, 'prod_manager');
    
    await page.goto('/production/orders/new');
    
    // Submit empty form
    await page.click('button[type="submit"]');
    
    // Should see validation errors
    await expect(page.locator('text=Order number is required, text=required')).toBeVisible();
    await expect(page.locator('text=Product name is required, text=required')).toBeVisible();
  });

  test('cannot approve already approved order', async ({ page }) => {
    // Create and fully approve order
    await loginAs(page, 'prod_manager');
    await page.goto('/production/orders/new');
    await page.fill('[name="order_number"]', 'WO-DOUBLE-' + Date.now());
    await page.fill('[name="product_name"]', 'Test');
    await page.click('button[type="submit"]');
    
    await page.waitForURL(/production\/orders\/\w+/);
    const orderUrl = page.url();
    
    await page.click('text=Submit for Approval');
    await page.click('button:has-text("Confirm")');
    
    // Approve as Head
    await logout(page);
    await loginAs(page, 'prod_head');
    await page.goto(orderUrl);
    await page.click('text=Approve');
    await page.click('button:has-text("Confirm Approval")');
    
    // Try to approve again - should fail
    await expect(page.locator('text=Approve')).toBeHidden();
  });
});
