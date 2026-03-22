/**
 * Rate limit helpers for E2E tests
 * 
 * Laravel's default rate limiter allows 5 login attempts per minute.
 * These helpers manage delays to avoid hitting rate limits during tests.
 */

import { Page } from '@playwright/test'

// Track last login time to enforce delays
let lastLoginTime = 0
const MIN_DELAY_MS = 3000 // Minimum 3 seconds between logins for same IP/user

/**
 * Wait for rate limit window to clear before logging in
 */
export async function waitForRateLimit(page: Page): Promise<void> {
  const now = Date.now()
  const timeSinceLastLogin = now - lastLoginTime
  
  if (timeSinceLastLogin < MIN_DELAY_MS) {
    const delayNeeded = MIN_DELAY_MS - timeSinceLastLogin
    console.log(`⏳ Rate limit delay: ${delayNeeded}ms`)
    await page.waitForTimeout(delayNeeded)
  }
  
  lastLoginTime = Date.now()
}

/**
 * Clear rate limits by navigating to blank and waiting
 */
export async function clearRateLimitState(page: Page): Promise<void> {
  await page.goto('about:blank')
  await page.waitForTimeout(2000)
}

/**
 * Staggered delay for test batches
 * Use this when running multiple tests with manufacturing accounts
 */
export function getStaggeredDelay(index: number): number {
  // 3 seconds base + 500ms per test index
  return 3000 + (index * 500)
}
