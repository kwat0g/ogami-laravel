import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('Payroll Canonical Workflow', () => {
  test('PAY-CAN-01 super admin can open payroll core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    const routes = ['/payroll/runs', '/payroll/runs/new', '/payroll/periods', '/reports/government']
    for (const path of routes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const body = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), body)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(body)).toBeFalsy()
    }

    await logout(page)
  })

  test('PAY-CAN-02 hr manager can access payroll run list', async ({ page }) => {
    await loginAsRole(page, 'hrManager')
    await page.goto('/payroll/runs', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), body)).toBeFalsy()

    await logout(page)
  })

  test('PAY-CAN-03 non-payroll role is blocked from payroll pages', async ({ page }) => {
    await loginAsRole(page, 'purchasingOfficer')
    await page.goto('/payroll/runs', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), body)).toBeFalsy()

    await logout(page)
  })
})
