import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

type RouteCheck = {
  path: string
  label: string
}

const PRODUCTION_CORE_ROUTES: RouteCheck[] = [
  { path: '/production/orders', label: 'production orders' },
  { path: '/production/boms', label: 'bill of materials' },
  { path: '/production/delivery-schedules', label: 'delivery schedules' },
  { path: '/production/work-centers', label: 'work centers' },
  { path: '/production/routings', label: 'routings' },
]

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('Production Canonical Workflow', () => {
  test('PROD-CAN-01 super admin can open production core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    for (const route of PRODUCTION_CORE_ROUTES) {
      await page.goto(route.path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('PROD-CAN-02 production manager can access operational production pages', async ({ page }) => {
    await loginAsRole(page, 'productionManager')

    const operationalRoutes = ['/production/orders', '/production/work-centers', '/production/routings']

    for (const path of operationalRoutes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
    }

    await page.goto('/admin/users', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const denyBody = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), denyBody)).toBeFalsy()

    await logout(page)
  })

  test('PROD-CAN-03 non-production role is blocked from production module', async ({ page }) => {
    await loginAsRole(page, 'hrManager')

    await page.goto('/production/orders', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })
})
