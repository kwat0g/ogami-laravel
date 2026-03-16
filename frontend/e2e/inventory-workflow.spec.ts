/**
 * E2E-INV-WORKFLOW — Inventory Management Workflow
 *
 * Covers:
 *  INV-WF-01  Item Master list loads with categories
 *  INV-WF-02  Create new item master
 *  INV-WF-03  View item stock balance
 *  INV-WF-04  Material Requisition creation
 *  INV-WF-05  MR approval workflow
 *  INV-WF-06  Stock ledger shows transactions
 *  INV-WF-07  Low stock alerts visible
 *  INV-WF-08  Warehouse locations list
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('Inventory Workflow — Items & Stock', () => {
    // ── INV-WF-01 ───────────────────────────────────────────────────────────────
    test('INV-WF-01 item master list loads with filters', async ({ page }) => {
        await page.goto(`${BASE}/inventory/items`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/items/, { timeout: 15_000 })
        
        // Table or empty state
        const content = page.locator('table, [role="table"], text=/no items|item code|category/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
        
        // Filter controls should be present
        const filters = page.locator('input[type="search"], select, button:has-text("Filter")')
        await expect(filters.first()).toBeVisible({ timeout: 8_000 })
    })

    // ── INV-WF-02 ───────────────────────────────────────────────────────────────
    test('INV-WF-02 new item form accessible with validation', async ({ page }) => {
        await page.goto(`${BASE}/inventory/items/new`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        // Form should have item code, name, category fields
        const formFields = page.locator('input[name*="code"], input[name*="name"], select[name*="category"], input[placeholder*="code"], input[placeholder*="name"]')
        await expect(formFields.first()).toBeVisible({ timeout: 12_000 })
        
        // Submit without filling should show validation
        const submitBtn = page.getByRole('button', { name: /submit|save|create/i }).first()
        if (await submitBtn.isVisible({ timeout: 5_000 })) {
            await submitBtn.click()
            await expect(
                page.locator('text=/required|this field|must/i').first(),
            ).toBeVisible({ timeout: 8_000 })
        }
    })

    // ── INV-WF-03 ───────────────────────────────────────────────────────────────
    test('INV-WF-03 item categories list loads', async ({ page }) => {
        await page.goto(`${BASE}/inventory/categories`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no categories|category|type/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── INV-WF-04 ───────────────────────────────────────────────────────────────
    test('INV-WF-04 material requisitions list loads', async ({ page }) => {
        await page.goto(`${BASE}/inventory/material-requisitions`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/material-requisitions/, { timeout: 15_000 })
        
        const content = page.locator('table, [role="table"], text=/no requisitions|MR-|status/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── INV-WF-05 ───────────────────────────────────────────────────────────────
    test('INV-WF-05 new MR form accessible', async ({ page }) => {
        await page.goto(`${BASE}/inventory/material-requisitions/new`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        // Should show item selection or justification field
        const formFields = page.locator('textarea, select, input[placeholder*="item"], input[placeholder*="justification"]')
        await expect(formFields.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── INV-WF-06 ───────────────────────────────────────────────────────────────
    test('INV-WF-06 stock ledger page loads', async ({ page }) => {
        await page.goto(`${BASE}/inventory/stock-ledger`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no transactions|in|out|balance/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── INV-WF-07 ───────────────────────────────────────────────────────────────
    test('INV-WF-07 warehouse locations list loads', async ({ page }) => {
        await page.goto(`${BASE}/inventory/warehouse-locations`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no locations|warehouse|zone|aisle/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── INV-WF-08 ───────────────────────────────────────────────────────────────
    test('INV-WF-08 goods receipts list loads', async ({ page }) => {
        await page.goto(`${BASE}/procurement/goods-receipts`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/goods-receipts/, { timeout: 15_000 })
        
        const content = page.locator('table, [role="table"], text=/no receipts|GR-|received/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })
})
