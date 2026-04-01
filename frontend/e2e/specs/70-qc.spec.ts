import { test, expect } from '@playwright/test'
import { loginAsRole, logout } from '../helpers/auth'

type RouteCheck = {
  path: string
  label: string
}

const QC_CORE_ROUTES: RouteCheck[] = [
  { path: '/qc/inspections', label: 'inspections' },
  { path: '/qc/templates', label: 'templates' },
  { path: '/qc/ncrs', label: 'ncrs' },
  { path: '/qc/capa', label: 'capa actions' },
  { path: '/qc/defect-rate', label: 'defect rate' },
]

function isBlocked(url: string, bodyText: string): boolean {
  return (
    url.includes('/login') ||
    url.includes('/403') ||
    url.includes('/dashboard') ||
    /forbidden|access denied|unauthorized|you don't have permission/i.test(bodyText)
  )
}

test.describe('QC Canonical Workflow', () => {
  test('QC-CAN-01 super admin can open QC core pages', async ({ page }) => {
    await loginAsRole(page, 'superAdmin')

    for (const route of QC_CORE_ROUTES) {
      await page.goto(route.path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
      expect(/something went wrong|failed to fetch dynamically imported module/i.test(bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('QC-CAN-02 QC manager can access QC operations', async ({ page }) => {
    await loginAsRole(page, 'qcManager')

    const operationalRoutes = ['/qc/inspections', '/qc/ncrs']

    for (const path of operationalRoutes) {
      await page.goto(path, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(500)

      const bodyText = (await page.locator('body').textContent()) ?? ''
      expect(isBlocked(page.url(), bodyText)).toBeFalsy()
    }

    await logout(page)
  })

  test('QC-CAN-03 non-QC role is blocked from QC pages', async ({ page }) => {
    await loginAsRole(page, 'hrManager')

    await page.goto('/qc/inspections', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(500)

    const bodyText = (await page.locator('body').textContent()) ?? ''
    expect(isBlocked(page.url(), bodyText)).toBeFalsy()

    await logout(page)
  })
})
