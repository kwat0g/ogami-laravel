const { chromium } = require('playwright')

async function run() {
    console.log('Connecting to Lightpanda...')
    const browser = await chromium.connectOverCDP('ws://127.0.0.1:9222/')
    console.log('Connected!')
    const context = await browser.newContext()
    const page = await context.newPage()
    await page.goto('http://localhost:5173/login')
    const title = await page.title()
    console.log('Page Title:', title)
    await browser.close()
}

run().catch(console.error)
