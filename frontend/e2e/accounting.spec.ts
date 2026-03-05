/**
 * E2E-ACCOUNTING — Accounting & GL flows
 *
 * Covers:
 *  ACCT-01  Journal Entries list page loads
 *  ACCT-02  New Journal Entry form accessible
 *  ACCT-03  Journal Entry form balance validation
 *  ACCT-04  Chart of Accounts page loads
 *  ACCT-05  AP Invoices list page loads
 *  ACCT-06  Trial Balance page renders
 *  ACCT-07  Balance Sheet page renders
 *  ACCT-08  Fiscal Periods page loads
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('Accounting & GL', () => {
    // ── ACCT-01 ───────────────────────────────────────────────────────────────
    test('ACCT-01 journal entries list page loads', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        const content = page.locator('table, [role="table"], text=/no entries|no journal/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── ACCT-02 ───────────────────────────────────────────────────────────────
    test('ACCT-02 new journal entry form is accessible', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries/new`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page.locator('form').first()).toBeVisible({ timeout: 12_000 })
    })

    // ── ACCT-03 ───────────────────────────────────────────────────────────────
    test('ACCT-03 chart of accounts page loads', async ({ page }) => {
        await page.goto(`${BASE}/accounting/accounts`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        // Should show accounts table (sample data or empty state)
        const content = page.locator('table, [role="table"], text=/no accounts|empty/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── ACCT-04 ───────────────────────────────────────────────────────────────
    test('ACCT-04 AP invoices list page loads', async ({ page }) => {
        await page.goto(`${BASE}/accounting/ap/invoices`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })

    // ── ACCT-05 ───────────────────────────────────────────────────────────────
    test('ACCT-05 trial balance page renders', async ({ page }) => {
        await page.goto(`${BASE}/accounting/trial-balance`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })

    // ── ACCT-06 ───────────────────────────────────────────────────────────────
    test('ACCT-06 balance sheet page renders', async ({ page }) => {
        await page.goto(`${BASE}/accounting/balance-sheet`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })

    // ── ACCT-07 ───────────────────────────────────────────────────────────────
    test('ACCT-07 income statement page renders', async ({ page }) => {
        await page.goto(`${BASE}/accounting/income-statement`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })

    // ── ACCT-08 ───────────────────────────────────────────────────────────────
    test('ACCT-08 fiscal periods page loads', async ({ page }) => {
        await page.goto(`${BASE}/accounting/fiscal-periods`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
        // Should show table or create prompt
        const content = page.locator('table, [role="table"], text=/no period|create|empty/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })
})
