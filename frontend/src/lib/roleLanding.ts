import type { AuthUser } from '@/types/api'

export function getPasswordChangePath(user: AuthUser | null): string {
  if (!user) return '/account/change-password'

  if (user.roles.includes('vendor')) {
    return '/vendor-portal/change-password'
  }

  if (user.roles.includes('client')) {
    return '/client-portal/change-password'
  }

  return '/account/change-password'
}

export function getLandingPath(user: AuthUser | null): string {
  if (!user) return '/dashboard'

  if (user.must_change_password) {
    return getPasswordChangePath(user)
  }

  if (user.roles.includes('vendor')) {
    return '/vendor-portal/dashboard'
  }

  if (user.roles.includes('client')) {
    return '/client-portal/tickets'
  }

  return '/dashboard'
}
