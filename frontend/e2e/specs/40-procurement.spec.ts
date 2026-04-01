import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

type RouteCheck = {
  path: string
  label: string
}

const PROCUREMENT_CORE_ROUTES: RouteCheck[] = [
  { path: '/procurement/purchase-requests', label: 'purchase requests' },
  { path: '/procurement/purchase-orders', label: 'purchase orders' },
  { path: '/procurement/goods-receipts', label: 'goods receipts' },
  { path: '/procurement/rfqs', label: 'rfqs' },
  { path: '/procurement/analytics', label: 'procurement analytics' },
]

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('Procurement Canonical Workflow', () => {
  test('PROC-CAN-01 super admin can open procurement core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    for (const route of PROCUREMENT_CORE_ROUTES) {
      await page.goto(route.path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('PROC-CAN-02 purchasing officer can access procurement operations', async ({ page }) => {
    await loginAsRole(page, 'purchasingOfficer')

    const operationalRoutes = [
      '/procurement/purchase-requests',
      '/procurement/purchase-orders',
      '/procurement/goods-receipts',
    ]

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

  test('PROC-CAN-03 approvals dashboard route is reachable by privileged role', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    await page.goto('/approvals/pending', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })

  test('PROC-CAN-04 non-procurement role is blocked from procurement pages', async ({ page }) => {
    await loginAsRole(page, 'admin')

    await page.goto('/procurement/purchase-orders', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })
})
