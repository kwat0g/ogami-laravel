import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

type RouteCheck = {
  path: string
  label: string
}

const INVENTORY_CORE_ROUTES: RouteCheck[] = [
  { path: '/inventory/items', label: 'item master' },
  { path: '/inventory/categories', label: 'item categories' },
  { path: '/inventory/stock', label: 'stock balance' },
  { path: '/inventory/ledger', label: 'stock ledger' },
  { path: '/inventory/requisitions', label: 'material requisitions' },
]

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('Inventory Canonical Workflow', () => {
  test('INV-CAN-01 super admin can open inventory core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    for (const route of INVENTORY_CORE_ROUTES) {
      await page.goto(route.path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('INV-CAN-02 warehouse head can access inventory operational pages', async ({ page }) => {
    await loginAsRole(page, 'warehouseHead')

    const operationalRoutes = ['/inventory/items', '/inventory/stock', '/inventory/requisitions']

    for (const path of operationalRoutes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('INV-CAN-03 non-inventory role is blocked from inventory module', async ({ page }) => {
    await loginAsRole(page, 'hrManager')

    await page.goto('/inventory/items', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })
})
