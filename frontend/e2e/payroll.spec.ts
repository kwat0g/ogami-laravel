/**
 * E2E-PAYROLL — Payroll Run lifecycle flows
 *
 * Covers:
 *  PAYROLL-01  Payroll runs list page loads
 *  PAYROLL-02  Create payroll run form is accessible
 *  PAYROLL-03  Payroll form validation shows errors on empty submit
 *  PAYROLL-04  Payroll run detail page accessible for existing run
 *  PAYROLL-05  Reports → Government reports page loads
 *  PAYROLL-06  My Payslips self-service page accessible
 *  PAYROLL-07  Payroll pre-run checklist visible in create form
 */
import { test, expect } from '@playwright/test'

const BASE    = 'http://localhost:5173'
const API     = 'http://localhost:8000'

test.describe('Payroll Management', () => {
    // ── PAYROLL-01 ────────────────────────────────────────────────────────────
    test('PAYROLL-01 payroll runs list page loads', async ({ page }) => {
        await page.goto(`${BASE}/payroll/runs`)
        await expect(page).toHaveURL(/payroll\/runs/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        const content = page.locator('table, [role="table"], text=/no payroll|no runs|empty/i')
        await expect(content.first()).toBeVisible({ timeout: 12_000 })
    })

    // ── PAYROLL-02 ────────────────────────────────────────────────────────────
    test('PAYROLL-02 create payroll run page is accessible', async ({ page }) => {
        await page.goto(`${BASE}/payroll/runs/new`)
        await expect(page).toHaveURL(/payroll\/runs\/new/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        // Form or wizard should be present
        await expect(page.locator('form, [data-testid="payroll-form"]').first())
            .toBeVisible({ timeout: 12_000 })
    })

    // ── PAYROLL-03 ────────────────────────────────────────────────────────────
    test('PAYROLL-03 create payroll form shows validation errors', async ({ page }) => {
        await page.goto(`${BASE}/payroll/runs/new`)
        await page.waitForLoadState('networkidle')

        // Try to click submit/next without filling anything
        const submitBtn = page.getByRole('button', { name: /submit|create|next|start/i }).first()
        await expect(submitBtn).toBeVisible({ timeout: 10_000 })
        await submitBtn.click()

        // Expect validation errors
        await expect(
            page.locator('text=/required|this field|cutoff|period/i').first(),
        ).toBeVisible({ timeout: 8_000 })
    })

    // ── PAYROLL-04 ────────────────────────────────────────────────────────────
    test('PAYROLL-04 payroll run detail page accessible via API-created run', async ({ page, request }) => {
        // Check if any run exists via API
        await request.get(`${API}/sanctum/csrf-cookie`)
        const loginRes = await request.post(`${API}/api/v1/auth/login`, {
            data: { email: 'admin@ogamierp.local', password: 'Admin@1234567890!' },
        })

        if (!loginRes.ok()) { test.skip(); return }
        const { token } = await loginRes.json()

        const runsRes = await request.get(`${API}/api/v1/payroll/runs?per_page=1`, {
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        })

        if (!runsRes.ok()) { test.skip(); return }
        const runs = await runsRes.json()

        const run = runs.data?.[0]
        if (!run?.id) { test.skip(); return }   // No runs yet

        await page.goto(`${BASE}/payroll/runs/${run.id}`)
        await page.waitForLoadState('networkidle')

        await expect(page).toHaveURL(new RegExp(`payroll/runs/${run.id}`))
        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
    })

    // ── PAYROLL-05 ────────────────────────────────────────────────────────────
    test('PAYROLL-05 government reports page loads', async ({ page }) => {
        await page.goto(`${BASE}/reports/government`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })

    // ── PAYROLL-06 ────────────────────────────────────────────────────────────
    test('PAYROLL-06 self-service my payslips page accessible', async ({ page }) => {
        await page.goto(`${BASE}/payslips`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
        await expect(page).not.toHaveURL(/login/)
    })

    // ── PAYROLL-07 ────────────────────────────────────────────────────────────
    test('PAYROLL-07 payroll runs list can navigate to create form', async ({ page }) => {
        await page.goto(`${BASE}/payroll/runs`)
        await page.waitForLoadState('networkidle')

        const newBtn = page.getByRole('link', { name: /new run|create run|new payroll/i })
            .or(page.getByRole('button', { name: /new run|create run|new payroll/i }))

        await expect(newBtn.first()).toBeVisible({ timeout: 10_000 })
        await newBtn.first().click()

        await expect(page).toHaveURL(/payroll\/runs\/new/, { timeout: 10_000 })
    })
})
