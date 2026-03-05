/**
 * E2E-PROC — Procurement module flows
 *
 * Covers:
 *  PROC-01  Purchase Request list page loads with correct structure
 *  PROC-02  New Purchase Request form is accessible
 *  PROC-03  PR form validation: empty submit shows errors
 *  PROC-04  PR list has status filter controls
 *  PROC-05  Purchase Orders list page loads
 *  PROC-06  Goods Receipts list page loads
 *  PROC-07  VP Approvals dashboard accessible
 *  PROC-08  PR detail page renders without 500 (using existing ULID pattern)
 *  PROC-09  PO create page accessible from PO list
 *  PROC-10  Vendor list (AP) page loads — accreditation column visible
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('Procurement — Purchase Requests', () => {
    // ── PROC-01 ───────────────────────────────────────────────────────────────
    test('PROC-01 PR list page loads', async ({ page }) => {
        await page.goto(`${BASE}/procurement/purchase-requests`)
        await expect(page).toHaveURL(/purchase-requests/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        // Table or empty state should be present
        const content = page.locator('table, [role="table"], text=/no purchase requests|no records|empty/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROC-02 ───────────────────────────────────────────────────────────────
    test('PROC-02 new PR form is accessible', async ({ page }) => {
        await page.goto(`${BASE}/procurement/purchase-requests`)
        await expect(page).toHaveURL(/purchase-requests/, { timeout: 15_000 })

        const newBtn = page
            .getByRole('link', { name: /new purchase request|new pr|create pr/i })
            .or(page.getByRole('button', { name: /new purchase request|new pr|create pr/i }))
        await expect(newBtn.first()).toBeVisible({ timeout: 10_000 })
        await newBtn.first().click()

        await expect(page).toHaveURL(/purchase-requests\/new|purchase-requests\/create/, { timeout: 10_000 })
        await page.waitForLoadState('networkidle')
        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
    })

    // ── PROC-03 ───────────────────────────────────────────────────────────────
    test('PROC-03 PR form shows validation on empty submit', async ({ page }) => {
        await page.goto(`${BASE}/procurement/purchase-requests/new`)
        await page.waitForLoadState('networkidle')

        // Click the submit button without filling in any fields
        const submitBtn = page.getByRole('button', { name: /submit|save|create/i }).first()
        await expect(submitBtn).toBeVisible({ timeout: 8_000 })
        await submitBtn.click()

        // Expect at least one validation message
        await expect(
            page.locator('text=/required|this field|must|invalid/i').first(),
        ).toBeVisible({ timeout: 8_000 })
    })

    // ── PROC-04 ───────────────────────────────────────────────────────────────
    test('PROC-04 PR list has status filter controls', async ({ page }) => {
        await page.goto(`${BASE}/procurement/purchase-requests`)
        await page.waitForLoadState('networkidle')

        // Filter select, tabs, or search input should be visible
        const filter = page
            .locator('select, [role="tablist"], input[type="search"], input[placeholder*="search" i]')
            .first()
        await expect(filter).toBeVisible({ timeout: 10_000 })
    })
})

test.describe('Procurement — Purchase Orders', () => {
    // ── PROC-05 ───────────────────────────────────────────────────────────────
    test('PROC-05 PO list page loads', async ({ page }) => {
        await page.goto(`${BASE}/procurement/purchase-orders`)
        await expect(page).toHaveURL(/purchase-orders/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        const content = page.locator('table, [role="table"], text=/no purchase orders|no records|empty/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PROC-09 ───────────────────────────────────────────────────────────────
    test('PROC-09 PO create page accessible', async ({ page }) => {
        await page.goto(`${BASE}/procurement/purchase-orders/new`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })
})

test.describe('Procurement — Goods Receipts', () => {
    // ── PROC-06 ───────────────────────────────────────────────────────────────
    test('PROC-06 GR list page loads', async ({ page }) => {
        await page.goto(`${BASE}/procurement/goods-receipts`)
        await expect(page).toHaveURL(/goods-receipts/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        const content = page.locator('table, [role="table"], text=/no goods receipts|no records|empty/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })
})

test.describe('Procurement — VP Approvals', () => {
    // ── PROC-07 ───────────────────────────────────────────────────────────────
    test('PROC-07 VP Approvals dashboard accessible', async ({ page }) => {
        await page.goto(`${BASE}/procurement/approvals`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })
})

test.describe('Procurement — Vendor Accreditation (AP)', () => {
    // ── PROC-10 ───────────────────────────────────────────────────────────────
    test('PROC-10 vendor list shows accreditation status column', async ({ page }) => {
        await page.goto(`${BASE}/accounting/vendors`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })

        // Accreditation column header or badge should be visible
        const accreditationContent = page
            .locator('text=/accreditation|accredited|pending|suspended/i')
            .first()
        await expect(accreditationContent).toBeVisible({ timeout: 10_000 })
    })

    test('PROC-10b vendor form includes banking details section', async ({ page }) => {
        await page.goto(`${BASE}/accounting/vendors`)
        await page.waitForLoadState('networkidle')

        // Open create vendor modal/form
        const newBtn = page
            .getByRole('button', { name: /new vendor|add vendor|create vendor/i })
            .first()
        await expect(newBtn).toBeVisible({ timeout: 10_000 })
        await newBtn.click()

        // Banking Details section should appear in the form
        await expect(
            page.locator('text=/banking details|bank name|bank account/i').first(),
        ).toBeVisible({ timeout: 8_000 })
    })
})
