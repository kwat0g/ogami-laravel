/**
 * E2E-HR — HR Onboarding flows
 *
 * Covers:
 *  HR-01  HR Employees page loads with table
 *  HR-02  Create new employee form accessible
 *  HR-03  Form validation shows errors for missing required fields
 *  HR-04  Employee list is searchable / filterable
 *  HR-05  Employee detail page accessible from list
 *  HR-06  HR Departments page loads
 *  HR-07  Attendance list page loads
 *  HR-08  Leave balances page loads
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('HR Management', () => {
    // ── HR-01 ─────────────────────────────────────────────────────────────────
    test('HR-01 employees list page loads', async ({ page }) => {
        await page.goto(`${BASE}/hr/employees`)
        await expect(page).toHaveURL(/employees/, { timeout: 15_000 })

        // Page should contain a table or list of employees
        await expect(
            page.locator('table, [role="table"], [data-testid="employee-list"]').first(),
        ).toBeVisible({ timeout: 15_000 })
    })

    // ── HR-02 ─────────────────────────────────────────────────────────────────
    test('HR-02 new employee form is accessible from list page', async ({ page }) => {
        await page.goto(`${BASE}/hr/employees`)
        await expect(page).toHaveURL(/employees/, { timeout: 15_000 })

        // Find and click the "New Employee" / "Add Employee" button
        const addBtn = page.getByRole('link', { name: /new employee|add employee/i })
            .or(page.getByRole('button', { name: /new employee|add employee/i }))
        await expect(addBtn.first()).toBeVisible({ timeout: 10_000 })
        await addBtn.first().click()

        await expect(page).toHaveURL(/employees\/new|employees\/create/, { timeout: 10_000 })
        // Required fields should be present
        await expect(page.getByLabel(/first name/i)).toBeVisible()
        await expect(page.getByLabel(/last name/i)).toBeVisible()
    })

    // ── HR-03 ─────────────────────────────────────────────────────────────────
    test('HR-03 employee form shows validation errors on empty submit', async ({ page }) => {
        await page.goto(`${BASE}/hr/employees/new`)

        // Submit with no data
        await page.getByRole('button', { name: /save|create|submit/i }).first().click()

        // Validation errors should appear
        await expect(
            page.locator('text=/required|this field|must be/i').first(),
        ).toBeVisible({ timeout: 8_000 })
    })

    // ── HR-04 ─────────────────────────────────────────────────────────────────
    test('HR-04 employee list has search input', async ({ page }) => {
        await page.goto(`${BASE}/hr/employees`)
        await expect(page).toHaveURL(/employees/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')

        const searchInput = page.locator('input[type="search"], input[placeholder*="search" i], input[placeholder*="Search" i]').first()
        await expect(searchInput).toBeVisible({ timeout: 10_000 })
    })

    // ── HR-05 ─────────────────────────────────────────────────────────────────
    test('HR-05 clicking employee row opens detail page', async ({ page }) => {
        await page.goto(`${BASE}/hr/employees`)
        await page.waitForLoadState('networkidle')

        // Click first employee row link (if any employees exist)
        const firstRow = page.locator('table tbody tr, [data-testid="employee-row"]').first()
        const count = await firstRow.count()
        if (count > 0) {
            await firstRow.click()
            // Should navigate to employee detail
            await expect(page).toHaveURL(/employees\/\d+/, { timeout: 10_000 })
        } else {
            test.skip()  // No employees in test DB
        }
    })

    // ── HR-06 ─────────────────────────────────────────────────────────────────
    test('HR-06 departments page loads', async ({ page }) => {
        await page.goto(`${BASE}/hr/departments`)
        await expect(page).toHaveURL(/departments/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')
        // Page should not error
        await expect(page.locator('body')).not.toContainText('Error', { timeout: 5_000 })
    })

    // ── HR-07 ─────────────────────────────────────────────────────────────────
    test('HR-07 attendance list page loads', async ({ page }) => {
        await page.goto(`${BASE}/hr/attendance`)
        await expect(page).toHaveURL(/attendance/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')
        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
    })

    // ── HR-08 ─────────────────────────────────────────────────────────────────
    test('HR-08 leave balances page loads', async ({ page }) => {
        await page.goto(`${BASE}/hr/leave/balances`)
        await expect(page).toHaveURL(/balances/, { timeout: 15_000 })
        await page.waitForLoadState('networkidle')
        await expect(page.locator('body')).not.toContainText('500', { timeout: 5_000 })
    })
})
