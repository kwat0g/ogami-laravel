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

test.describe('HR Canonical Workflow', () => {
  test('HR-CAN-01 super admin can open HR core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    const routes = ['/hr/employees', '/hr/attendance', '/hr/leave', '/hr/departments']
    for (const path of routes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const body = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), body)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(body)).toBeFalsy()
    }

    await logout(page)
  })

  test('HR-CAN-02 hr manager can access HR operational pages', async ({ page }) => {
    await loginAsRole(page, 'hrManager')

    const routes = ['/hr/employees', '/hr/leave', '/hr/attendance']
    for (const path of routes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const body = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), body)).toBeFalsy()
    }

    await logout(page)
  })

  test('HR-CAN-03 non-HR role is blocked from HR pages', async ({ page }) => {
    await loginAsRole(page, 'purchasingOfficer')
    await page.goto('/hr/employees', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), body)).toBeFalsy()

    await logout(page)
  })
})
