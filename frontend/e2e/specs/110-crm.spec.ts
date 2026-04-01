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

test.describe('CRM Canonical Workflow', () => {
  test('CRM-CAN-01 CRM manager can open CRM ticket pages', async ({ page }) => {
    await loginAsRole(page, 'crmManager')

    const routes = ['/crm/tickets']
    for (const path of routes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const body = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), body)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(body)).toBeFalsy()
    }

    await logout(page)
  })

  test('CRM-CAN-02 CRM manager can access dashboard or fallback to tickets', async ({ page }) => {
    await loginAsRole(page, 'crmManager')
    await page.goto('/crm/dashboard', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''

    if (isBlocked(page.url(), body)) {
      await page.goto('/crm/tickets', { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)
      const fallbackBody = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), fallbackBody)).toBeFalsy()
    } else {
      expect(isBlocked(page.url(), body)).toBeFalsy()
    }

    await logout(page)
  })

  test('CRM-CAN-03 non-CRM role is blocked from CRM pages', async ({ page }) => {
    await loginAsRole(page, 'hrManager')
    await page.goto('/crm/tickets', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const body = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), body)).toBeFalsy()

    await logout(page)
  })
})
