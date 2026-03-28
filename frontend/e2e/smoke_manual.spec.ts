import { test, expect, chromium } from '@playwright/test'

test('lightpanda console debug test', async () => {
    console.log('Connecting to Lightpanda CDP...')
    const browser = await chromium.connectOverCDP('ws://127.0.0.1:9222/')
    console.log('Connected!')
    
    // Lightpanda usually only supports the initial context
    const context = browser.contexts()[0] || await browser.newContext()
    const page = await context.pages()[0] || await context.newPage()
    
    page.on('console', msg => console.log(`[LIGHTPANDA] ${msg.type().toUpperCase()}: ${msg.text()}`))
    page.on('pageerror', err => console.log(`[LIGHTPANDA ERROR] ${err.message}`))
    page.on('requestfailed', req => console.log(`[LIGHTPANDA FAILED] ${req.url()}: ${req.failure()?.errorText}`))
    
    console.log('Navigating to http://127.0.0.1:5173/login ...')
    await page.goto('http://127.0.0.1:5173/login')
    
    console.log('Waiting for root to mount...')
    await page.waitForTimeout(10000)
    
    const rootHtml = await page.innerHTML('#root')
    console.log('Root HTML length:', rootHtml?.length ?? 0)
    
    await browser.close()
})
