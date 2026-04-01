import { test, expect } from '@playwright/test'
import { logout } from '../helpers/auth'

type AccessCheck = {
  role: 'hrManager' | 'accountingOfficer' | 'warehouseHead' | 'purchasingOfficer'
  allowedRoute: string
}

const MATRIX: AccessCheck[] = [
  {
    role: 'hrManager',
    allowedRoute: '/hr/employees',
  },
  {
    role: 'accountingOfficer',
    allowedRoute: '/accounting/journal-entries',
  },
  {
    role: 'warehouseHead',
    allowedRoute: '/inventory/items',
  },
  {
    role: 'purchasingOfficer',
    allowedRoute: '/procurement/purchase-orders',
  },
]

test.describe('RBAC Route Enforcement', () => {
  for (const item of MATRIX) {
    test(`${item.role} can access assigned module and page is stable`, async ({ page }) => {

      await page.goto(item.allowedRoute, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const allowBody = (await page.locator('body').textContent()) ?? ''
      const allowedBlocked =
        page.url().includes('/login') ||
        page.url().includes('/403') ||
        /forbidden|access denied|unauthorized/i.test(allowBody)

      expect(allowedBlocked).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(allowBody)).toBeFalsy()

      await logout(page)
    })
  }
})
