let authEpoch = 0

export function bumpAuthEpoch(): number {
  authEpoch += 1
  return authEpoch
}

export function getAuthEpoch(): number {
  return authEpoch
}
