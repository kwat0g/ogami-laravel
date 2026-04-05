let authEpoch = 0
let loginGraceUntil = 0

export function bumpAuthEpoch(): number {
  authEpoch += 1
  return authEpoch
}

export function getAuthEpoch(): number {
  return authEpoch
}

/**
 * Set a grace period after login where 401s are suppressed.
 * This prevents false "Session expired" toasts when the session cookie
 * hasn't fully propagated to all concurrent requests yet.
 */
export function setLoginGrace(durationMs = 3000): void {
  loginGraceUntil = Date.now() + durationMs
}

/** True if we are within the post-login grace window. */
export function isInLoginGrace(): boolean {
  return Date.now() < loginGraceUntil
}
