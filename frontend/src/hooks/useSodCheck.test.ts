import { describe, it, expect, beforeEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useSodCheck } from '@/hooks/useSodCheck'
import { useAuthStore } from '@/stores/authStore'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

type AuthUser = ReturnType<typeof useAuthStore.getState>['user']

function setUser(user: Partial<NonNullable<AuthUser>> | null) {
  useAuthStore.setState({
    user: user
      ? ({
          id: 1,
          name: 'Test User',
          email: 'test@example.com',
          roles: ['head'],
          permissions: [],
          timezone: 'Asia/Manila',
          ...user,
        } as NonNullable<AuthUser>)
      : null,
  })
}

beforeEach(() => {
  // Reset to unauthenticated state before each test
  useAuthStore.setState({ user: null })
})

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useSodCheck', () => {
  it('scenario 1 — not blocked when initiatedById is null (no initiator, e.g. migrated data)', () => {
    setUser({ id: 5, roles: ['head'] })
    const { result } = renderHook(() => useSodCheck(null))
    expect(result.current.isBlocked).toBe(false)
    expect(result.current.reason).toBeNull()
  })

  it('scenario 2 — not blocked when initiatedById is undefined', () => {
    setUser({ id: 5, roles: ['head'] })
    const { result } = renderHook(() => useSodCheck(undefined as unknown as null))
    expect(result.current.isBlocked).toBe(false)
    expect(result.current.reason).toBeNull()
  })

  it('scenario 3 — not blocked when user is unauthenticated', () => {
    setUser(null)
    const { result } = renderHook(() => useSodCheck(42))
    expect(result.current.isBlocked).toBe(false)
    expect(result.current.reason).toBeNull()
  })

  it('scenario 4 — blocked when current user is the initiator (SoD violation)', () => {
    setUser({ id: 7, roles: ['head'] })
    const { result } = renderHook(() => useSodCheck(7))
    expect(result.current.isBlocked).toBe(true)
    expect(result.current.reason).toContain('Segregation of Duties')
  })

  it('scenario 5 — not blocked when current user is NOT the initiator', () => {
    setUser({ id: 7, roles: ['head'] })
    const { result } = renderHook(() => useSodCheck(99))
    expect(result.current.isBlocked).toBe(false)
    expect(result.current.reason).toBeNull()
  })

  it('scenario 6 — admin bypass: admin is never blocked even if they initiated', () => {
    setUser({ id: 7, roles: ['admin'] })
    const { result } = renderHook(() => useSodCheck(7))
    expect(result.current.isBlocked).toBe(false)
    expect(result.current.reason).toBeNull()
  })

  it('scenario 7 — manager: is blocked when they initiated (no SoD bypass for manager roles)', () => {
    setUser({ id: 7, roles: ['manager'] })
    const { result } = renderHook(() => useSodCheck(7))
    expect(result.current.isBlocked).toBe(true)
    expect(result.current.reason).not.toBeNull()
  })
})
