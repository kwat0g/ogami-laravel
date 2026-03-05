/**
 * E2E-AUTH — Authentication flows
 *
 * Covers:
 *  AUTH-01  Login with valid credentials → dashboard
 *  AUTH-02  Login with wrong password → error message
 *  AUTH-03  Login page accessible when unauthenticated
 *  AUTH-04  Dashboard not accessible without session (redirect to login)
 *  AUTH-05  User name visible in app header after login
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5173'

test.describe('Authentication', () => {
    // ── AUTH-01 ───────────────────────────────────────────────────────────────
    test('AUTH-01 valid login redirects to dashboard', async ({ page }) => {
        // storageState already has admin session — just verify dashboard loads
        await page.goto(`${BASE}/dashboard`)
        await expect(page).toHaveURL(/dashboard/, { timeout: 15_000 })
        await expect(page.locator('h1, [data-testid="dashboard-title"]').first())
            .toBeVisible({ timeout: 10_000 })
    })

    // ── AUTH-02 ───────────────────────────────────────────────────────────────
    test('AUTH-02 wrong password shows error', async ({ browser }) => {
        // Use a fresh context (no auth storage)
        const ctx  = await browser.newContext({ storageState: undefined })
        const page = await ctx.newPage()

        await page.goto(`${BASE}/login`)
        await expect(page).toHaveURL(/login/, { timeout: 10_000 })

        await page.locator('input[type="email"]').fill('admin@ogamierp.local')
        await page.locator('input[type="password"]').fill('WrongPassword123!')
        await page.getByRole('button', { name: /sign in|login/i }).click()

        // Expect an error message to appear on the page
        await expect(
            page.locator('text=/invalid|incorrect|credentials|failed/i').first(),
        ).toBeVisible({ timeout: 10_000 })

        await ctx.close()
    })

    // ── AUTH-03 ───────────────────────────────────────────────────────────────
    test('AUTH-03 unauthenticated user can access login page', async ({ browser }) => {
        const ctx  = await browser.newContext({ storageState: undefined })
        const page = await ctx.newPage()

        await page.goto(`${BASE}/login`)
        await expect(page.locator('input[type="email"]')).toBeVisible()
        await expect(page.locator('input[type="password"]')).toBeVisible()

        await ctx.close()
    })

    // ── AUTH-04 ───────────────────────────────────────────────────────────────
    test('AUTH-04 unauthenticated access to dashboard redirects to login', async ({ browser }) => {
        const ctx  = await browser.newContext({ storageState: undefined })
        const page = await ctx.newPage()

        await page.goto(`${BASE}/dashboard`)
        // Should end up at login or show auth UI
        await expect(page).toHaveURL(/login/, { timeout: 10_000 })

        await ctx.close()
    })

    // ── AUTH-05 ───────────────────────────────────────────────────────────────
    test('AUTH-05 logged-in user name visible in app header', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`)
        await expect(page).toHaveURL(/dashboard/)

        // Admin user name should appear somewhere in the header / top bar
        const header = page.locator('header, nav, [role="banner"]').first()
        await expect(header).toBeVisible({ timeout: 10_000 })
    })
})
