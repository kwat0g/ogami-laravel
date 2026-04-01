/**
 * Playwright auth setup — runs once before all specs.
 *
 * Uses UI login with fallback credentials and persists state to e2e/.auth/admin.json.
 */
import { test as setup, expect } from '@playwright/test'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'
import { TEST_ACCOUNTS } from '../helpers/auth'

const __filename = fileURLToPath(import.meta.url)
const __dirname  = dirname(__filename)
const AUTH_FILE  = join(__dirname, '../.auth/admin.json')

const FRONTEND_URL = process.env.FRONTEND_URL ?? 'http://localhost:5173'

async function tryUiLogin(page: Parameters<typeof setup>[1] extends (args: infer A) => unknown ? A extends { page: infer P } ? P : never : never, email: string, password: string): Promise<boolean> {
    await page.goto(`${FRONTEND_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 20_000 })

    const emailInput = page.locator('input[type="email"]').first()
    const passwordInput = page.locator('input[type="password"]').first()

    const visible = await emailInput.isVisible({ timeout: 8_000 }).catch(() => false)
    if (!visible) {
        return false
    }

    await emailInput.fill(email)
    await passwordInput.fill(password)
    await passwordInput.press('Enter')

    try {
        await page.waitForURL(/dashboard|change-password|vendor-portal|client-portal/, { timeout: 12_000 })
        return true
    } catch {
        return false
    }
}

setup('acquire admin session', async ({ page }) => {
    const candidates = [
        { email: TEST_ACCOUNTS.admin.email, password: TEST_ACCOUNTS.admin.password },
        ...(TEST_ACCOUNTS.admin.fallbacks ?? []),
        { email: TEST_ACCOUNTS.superAdmin.email, password: TEST_ACCOUNTS.superAdmin.password },
        { email: TEST_ACCOUNTS.hrManager.email, password: TEST_ACCOUNTS.hrManager.password },
        { email: TEST_ACCOUNTS.accountingOfficer.email, password: TEST_ACCOUNTS.accountingOfficer.password },
        { email: TEST_ACCOUNTS.purchasingOfficer.email, password: TEST_ACCOUNTS.purchasingOfficer.password },
        { email: TEST_ACCOUNTS.productionManager.email, password: TEST_ACCOUNTS.productionManager.password },
    ]

    let authenticated = false

    for (const credential of candidates) {
        if (await tryUiLogin(page, credential.email, credential.password)) {
            authenticated = true
            break
        }
    }

    if (!authenticated) {
        throw new Error('Setup API login failed for admin across all credential candidates.')
    }

    // 2. Verify login succeeded
    await page.goto(`${FRONTEND_URL}/dashboard`, { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/dashboard|change-password/, { timeout: 20_000 })

    // 3. Persist storage state
    await page.context().storageState({ path: AUTH_FILE })
})
