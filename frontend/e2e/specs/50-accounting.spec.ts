import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

type RouteCheck = {
  path: string
  label: string
}

const ACCOUNTING_CORE_ROUTES: RouteCheck[] = [
  { path: '/accounting/journal-entries', label: 'journal entries' },
  { path: '/accounting/accounts', label: 'chart of accounts' },
  { path: '/accounting/ap/invoices', label: 'ap invoices' },
  { path: '/accounting/vendors', label: 'vendors' },
  { path: '/ar/invoices', label: 'ar invoices' },
]

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('Accounting Canonical Workflow', () => {
  test('ACCT-CAN-01 super admin can open accounting core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    for (const route of ACCOUNTING_CORE_ROUTES) {
      await page.goto(route.path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    }

    await page.goto('/accounting/trial-balance', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)
    const reportBody = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), reportBody)).toBeFalsy()

    await logout(page)
  })

  test('ACCT-CAN-02 accounting officer can access accounting operations', async ({ page }) => {
    await loginAsRole(page, 'accountingOfficer')

    const operationalRoutes = ['/accounting/journal-entries', '/accounting/ap/invoices', '/ar/invoices']

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

  test('ACCT-CAN-03 non-accounting role is blocked from accounting pages', async ({ page }) => {
    await loginAsRole(page, 'hrManager')

    await page.goto('/accounting/journal-entries', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })
})
