import { defineConfig, devices } from '@playwright/test'

/**
 * Ogami ERP — Playwright E2E Configuration
 *
 * Requires both servers running before executing tests:
 *   Terminal 1: php artisan serve  (port 8000)
 *   Terminal 2: pnpm dev           (port 5173, proxy → 8000)
 *
 * Quick run:
 *   pnpm e2e                     # headless
 *   pnpm e2e:ui                  # Playwright UI mode
 *   pnpm e2e -- --headed         # headed browser
 */
export default defineConfig({
    testDir:   './e2e',
    outputDir: './e2e/test-results',
    fullyParallel: false,   // tests share seeded data — run sequentially
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [
        ['list'],
        ['html', { outputFolder: './e2e/playwright-report', open: 'never' }],
    ],

    use: {
        baseURL:            'http://localhost:5173',
        storageState:       './e2e/.auth/admin.json',
        trace:              'on-first-retry',
        screenshot:         'only-on-failure',
        video:              'off',
        actionTimeout:      15_000,
        navigationTimeout:  20_000,
    },

    projects: [
        // ── Auth setup — runs first, saves cookies for subsequent specs ──────
        {
            name:    'setup',
            testDir: './e2e/setup',
            use:     { ...devices['Desktop Chrome'], storageState: undefined },
        },

        // ── Main test suite ──────────────────────────────────────────────────
        {
            name:         'chromium',
            use:          { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
        },
    ],
})
