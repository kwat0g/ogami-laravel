/**
 * Playwright auth setup — runs once before all specs.
 *
 * Logs in via the API, then stores the resulting
 * Sanctum token + user in localStorage so every spec starts authenticated.
 * Saved state is written to e2e/.auth/admin.json.
 */
import { test as setup, expect } from '@playwright/test'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const __filename = fileURLToPath(import.meta.url)
const __dirname  = dirname(__filename)
const AUTH_FILE  = join(__dirname, '../.auth/admin.json')

setup('acquire admin session', async ({ page, request }) => {
    const baseUrl = 'http://localhost:5173'
    const apiBase = 'http://localhost:8000'

    // 1. Fetch CSRF cookie
    await request.get(`${apiBase}/sanctum/csrf-cookie`)

    // 2. Call login API
    const loginRes = await request.post(`${apiBase}/api/v1/auth/login`, {
        data: {
            email:    'admin@ogamierp.local',
            password: 'Admin@1234567890!',
        },
    })

    expect(loginRes.ok(), `Login failed: ${loginRes.status()} ${await loginRes.text()}`).toBeTruthy()
    const body = await loginRes.json()
    const token: string = body.token
    const user          = body.user

    expect(token, 'Expected a Sanctum token in login response').toBeTruthy()

    // 3. Navigate to the app and inject auth into localStorage
    await page.goto(baseUrl)
    await page.waitForLoadState('networkidle')

    await page.evaluate(
        ({ token, user }) => {
            localStorage.setItem('auth_token', token)
            localStorage.setItem(
                'ogami-auth',
                JSON.stringify({ state: { token, user }, version: 0 }),
            )
        },
        { token, user },
    )

    // 4. Reload and verify we land on the dashboard
    await page.goto(`${baseUrl}/dashboard`)
    await expect(page).toHaveURL(/dashboard/, { timeout: 15_000 })

    // 5. Persist storage state
    await page.context().storageState({ path: AUTH_FILE })
})
