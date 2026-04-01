import { test, expect } from '@playwright/test'
import { logout } from '../helpers/auth'

test.describe('Authentication', () => {
  test('admin can log in and access dashboard', async ({ page }) => {
    await page.goto('/dashboard')
    await expect(page).toHaveURL(/dashboard/, { timeout: 15000 })
    await expect(page.locator('main, [role="main"]').first()).toBeVisible()
  })

  test('invalid credentials are rejected', async ({ browser }) => {
    const context = await browser.newContext({ storageState: undefined })
    const page = await context.newPage()

    await page.goto('/login')
    await page.locator('input[type="email"]').fill('invalid@ogamierp.local')
    await page.locator('input[type="password"]').fill('WrongPassword123!')
    await page.getByRole('button', { name: /login|sign in/i }).click()

    await expect(page).toHaveURL(/login/, { timeout: 10000 })
    await expect(page.locator('body')).toContainText(/invalid|incorrect|unauthorized|failed/i)

    await context.close()
  })

  test('unauthenticated user is redirected to login for protected route', async ({ browser }) => {
    const context = await browser.newContext({ storageState: undefined })
    const page = await context.newPage()

    await page.goto('/dashboard')
    await expect(page).toHaveURL(/login/, { timeout: 10000 })

    await context.close()
  })

  test('payroll page route handling is stable for authenticated session', async ({ page }) => {
    await page.goto('/payroll/runs')
    await page.waitForLoadState('networkidle')

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    await logout(page)
  })
})
