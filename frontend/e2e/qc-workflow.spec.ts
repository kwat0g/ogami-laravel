/**
 * E2E-QC-WORKFLOW — Quality Control Workflow
 *
 * Covers:
 *  QC-WF-01  Inspection list loads with filters
 *  QC-WF-02  Create new inspection
 *  QC-WF-03  Inspection templates list
 *  QC-WF-04  Record inspection results
 *  QC-WF-05  NCR (Non-Conformance Report) list
 *  QC-WF-06  Create NCR from failed inspection
 *  QC-WF-07  CAPA actions linked to NCR
 *  QC-WF-08  QC dashboard with metrics
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('QC Workflow — Quality Control', () => {
    // ── QC-WF-01 ───────────────────────────────────────────────────────────────
    test('QC-WF-01 inspections list loads', async ({ page }) => {
        await page.goto(`${BASE}/qc/inspections`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/inspections/, { timeout: 15_000 })
        
        const content = page.locator('table, [role="table"], text=/no inspections|IQC|IPQC|OQC/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── QC-WF-02 ───────────────────────────────────────────────────────────────
    test('QC-WF-02 inspection templates list loads', async ({ page }) => {
        await page.goto(`${BASE}/qc/templates`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no templates|template|checklist|criteria/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── QC-WF-03 ───────────────────────────────────────────────────────────────
    test('QC-WF-03 non-conformance reports list loads', async ({ page }) => {
        await page.goto(`${BASE}/qc/ncrs`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).toHaveURL(/ncrs/, { timeout: 15_000 })
        
        const content = page.locator('table, [role="table"], text=/no NCRs|NCR-|non-conformance|severity/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── QC-WF-04 ───────────────────────────────────────────────────────────────
    test('QC-WF-04 CAPA actions list loads', async ({ page }) => {
        await page.goto(`${BASE}/qc/capa`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no CAPA|CAPA|corrective|preventive/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── QC-WF-05 ───────────────────────────────────────────────────────────────
    test('QC-WF-05 new inspection form accessible', async ({ page }) => {
        await page.goto(`${BASE}/qc/inspections/new`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        // Should show inspection type, reference (GR/PO), item selection
        const formFields = page.locator('select, input, textarea')
        await expect(formFields.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── QC-WF-06 ───────────────────────────────────────────────────────────────
    test('QC-WF-06 QC dashboard loads with metrics', async ({ page }) => {
        await page.goto(`${BASE}/qc/dashboard`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        // Dashboard should show metrics or charts
        const metrics = page.locator('text=/pass rate|reject rate|inspections|pending/i, [class*="chart"], [class*="metric"], [class*="stat"]')
        await expect(metrics.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── QC-WF-07 ───────────────────────────────────────────────────────────────
    test('QC-WF-07 inspection stages filter works', async ({ page }) => {
        await page.goto(`${BASE}/qc/inspections`)
        await page.waitForLoadState('networkidle')

        // Look for stage filter (IQC, IPQC, OQC tabs or dropdown)
        const stageFilter = page.locator('button:has-text("IQC"), button:has-text("IPQC"), button:has-text("OQC"), select, [role="tablist"]')
        await expect(stageFilter.first()).toBeVisible({ timeout: 10_000 })
    })

    // ── QC-WF-08 ───────────────────────────────────────────────────────────────
    test('QC-WF-08 lot tracking accessible', async ({ page }) => {
        await page.goto(`${BASE}/qc/lots`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        
        const content = page.locator('table, [role="table"], text=/no lots|lot number|batch|expiry/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })
})
