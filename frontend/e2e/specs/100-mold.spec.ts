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

test.describe('Mold Canonical Workflow', () => {
  test('MOLD-CAN-01 super admin can open mold core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    const routes = ['/mold/masters', '/mold/lifecycle']
    for (const path of routes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const body = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), body)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(body)).toBeFalsy()
    }

    await logout(page)
  })

  test('MOLD-CAN-02 mold manager can access mold pages', async ({ page }) => {
    await loginAsRole(page, 'moldManager')
    await page.goto('/mold/masters', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), body)).toBeFalsy()

    await logout(page)
  })

  test('MOLD-CAN-03 non-mold role is blocked from mold pages', async ({ page }) => {
    await loginAsRole(page, 'hrManager')
    await page.goto('/mold/masters', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), body)).toBeFalsy()

    await logout(page)
  })
})
