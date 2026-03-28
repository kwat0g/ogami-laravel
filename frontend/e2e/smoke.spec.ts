import { test, expect } from '@playwright/test'

test('lightpanda smoke test', async ({ page }) => {
    await page.goto('http://127.0.0.1:5173/login')
    await expect(page).toHaveTitle(/Ogami ERP/)
    const h2Text = await page.textContent('h2')
    expect(h2Text).toContain('Sign in')
    console.log('Lightpanda connection successful!')
})
