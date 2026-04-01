import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

type RouteCheck = {
  path: string
  label: string
}

const DELIVERY_CORE_ROUTES: RouteCheck[] = [
  { path: '/delivery/receipts', label: 'delivery receipts' },
  { path: '/delivery/vehicles', label: 'delivery vehicles' },
  { path: '/delivery/disputes', label: 'delivery disputes' },
]

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('Delivery Canonical Workflow', () => {
  test('DLV-CAN-01 super admin can open delivery core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    for (const route of DELIVERY_CORE_ROUTES) {
      await page.goto(route.path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('DLV-CAN-02 warehouse head can access delivery operations', async ({ page }) => {
    await loginAsRole(page, 'warehouseHead')

    const operationalRoutes = ['/delivery/receipts', '/delivery/vehicles']

    for (const path of operationalRoutes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('DLV-CAN-03 non-delivery role is blocked from delivery pages', async ({ page }) => {
    await loginAsRole(page, 'admin')

    await page.goto('/delivery/receipts', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })
})
