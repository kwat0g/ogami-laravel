import { test, expect } from '@playwright/test'

test('lightpanda blank test', async ({ page }) => {
    console.log('Navigating to about:blank...')
    await page.goto('about:blank')
    console.log('At about:blank')
})
