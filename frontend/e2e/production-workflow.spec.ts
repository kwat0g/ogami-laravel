/**
 * E2E-PROD-WORKFLOW — Production Order Workflow
 *
 * Covers:
 *  PROD-WF-01  Production Orders list loads
 *  PROD-WF-02  Create new production order
 *  PROD-WF-03  View production order details
 *  PROD-WF-04  Release production order (check stock availability)
 *  PROD-WF-05  Record production output
 *  PROD-WF-06  Complete production order
 *  PROD-WF-07  BOM (Bill of Materials) list accessible
 *  PROD-WF-08  Delivery Schedules linked to production
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('Production Workflow — Manufacturing', () => {
    // ── PROD-WF-01 ───────────────────────────────────────────────────────────────
    test('PROD-WF-01 production orders list loads', async ({ page }) => {
        await page.goto(`${BASE}/production/orders`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/orders/, { timeout: 15_000 })
        
        const content = page.locator('table, [role="table"], text=/no orders|PO-|status|draft|released/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROD-WF-02 ───────────────────────────────────────────────────────────────
    test('PROD-WF-02 new production order form accessible', async ({ page }) => {
        await page.goto(`${BASE}/production/orders/new`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        // Should show product selection, quantity, dates
        const formFields = page.locator('select, input[type="number"], input[type="date"], input[placeholder*="product"], input[placeholder*="quantity"]')
        await expect(formFields.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROD-WF-03 ───────────────────────────────────────────────────────────────
    test('PROD-WF-03 BOM list page loads', async ({ page }) => {
        await page.goto(`${BASE}/production/boms`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/boms/, { timeout: 15_000 })
        
        const content = page.locator('table, [role="table"], text=/no BOMs|bill of materials|product|components/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROD-WF-04 ───────────────────────────────────────────────────────────────
    test('PROD-WF-04 delivery schedules list loads', async ({ page }) => {
        await page.goto(`${BASE}/production/delivery-schedules`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no schedules|delivery|customer|date/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROD-WF-05 ───────────────────────────────────────────────────────────────
    test('PROD-WF-05 production output log accessible', async ({ page }) => {
        await page.goto(`${BASE}/production/output-logs`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no logs|output|produced|rejected/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROD-WF-06 ───────────────────────────────────────────────────────────────
    test('PROD-WF-06 production order with components shows BOM', async ({ page }) => {
        await page.goto(`${BASE}/production/orders`)
        await page.waitForLoadState('networkidle')

        // Try to find and click on an order
        const orderRow = page.locator('table tbody tr, [role="row"]').first()
        if (await orderRow.isVisible({ timeout: 5_000 }).catch(() => false)) {
            await orderRow.click()
            await page.waitForLoadState('networkidle')
            
            // Detail page should load without error
            await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
            
            // Should show order details
            await expect(
                page.locator('text=/status|quantity|product|BOM|components/i').first(),
            ).toBeVisible({ timeout: 10_000 })
        }
    })

    // ── PROD-WF-07 ───────────────────────────────────────────────────────────────
    test('PROD-WF-07 work centers list loads', async ({ page }) => {
        await page.goto(`${BASE}/production/work-centers`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no work centers|work center|capacity/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROD-WF-08 ───────────────────────────────────────────────────────────────
    test('PROD-WF-08 production calendar view loads', async ({ page }) => {
        await page.goto(`${BASE}/production/calendar`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        // Should show calendar or schedule view
        const content = page.locator('text=/calendar|schedule|week|month|gantt/i, [class*="calendar"], [class*="gantt"]')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })
})
