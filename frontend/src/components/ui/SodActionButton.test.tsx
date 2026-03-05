import { describe, it, expect, beforeEach, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { SodActionButton } from '@/components/ui/SodActionButton'
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
          roles: ['supervisor'],
          permissions: [],
          timezone: 'Asia/Manila',
          ...user,
        } as NonNullable<AuthUser>)
      : null,
  })
}

beforeEach(() => {
  useAuthStore.setState({ user: null })
})

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SodActionButton', () => {
  it('scenario 1 — renders the label when not SoD-blocked', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    render(
      <SodActionButton
        initiatedById={99}
        label="Approve"
        onClick={() => undefined}
      />,
    )
    expect(screen.getByRole('button')).not.toBeDisabled()
    expect(screen.getByRole('button')).toHaveTextContent('Approve')
  })

  it('scenario 2 — button is disabled when user is the initiator (SoD violation)', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    render(
      <SodActionButton
        initiatedById={5}
        label="Approve"
        onClick={() => undefined}
      />,
    )
    expect(screen.getByRole('button')).toBeDisabled()
  })

  it('scenario 3 — blocked button shows "(SoD)" suffix in label', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    render(
      <SodActionButton
        initiatedById={5}
        label="Approve"
        onClick={() => undefined}
      />,
    )
    expect(screen.getByRole('button')).toHaveTextContent('(SoD)')
  })

  it('scenario 4 — tooltip div has SoD reason when blocked', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    const { container } = render(
      <SodActionButton
        initiatedById={5}
        label="Approve"
        onClick={() => undefined}
      />,
    )
    const wrapper = container.querySelector('[title]')
    expect(wrapper?.getAttribute('title')).toContain('Segregation of Duties')
  })

  it('scenario 5 — onClick is NOT called when button is SoD-blocked and clicked', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    const handleClick = vi.fn()
    render(
      <SodActionButton
        initiatedById={5}
        label="Approve"
        onClick={handleClick}
      />,
    )
    fireEvent.click(screen.getByRole('button'))
    expect(handleClick).not.toHaveBeenCalled()
  })

  it('scenario 6 — onClick IS called when not SoD-blocked', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    const handleClick = vi.fn()
    render(
      <SodActionButton
        initiatedById={99}
        label="Approve"
        onClick={handleClick}
      />,
    )
    fireEvent.click(screen.getByRole('button'))
    expect(handleClick).toHaveBeenCalledOnce()
  })

  it('scenario 7 — isLoading disables button with spinner and suppresses onClick', () => {
    setUser({ id: 5, roles: ['supervisor'] })
    const handleClick = vi.fn()
    render(
      <SodActionButton
        initiatedById={99}
        label="Approve"
        onClick={handleClick}
        isLoading
      />,
    )
    const btn = screen.getByRole('button')
    expect(btn).toBeDisabled()
    fireEvent.click(btn)
    expect(handleClick).not.toHaveBeenCalled()
  })

  it('scenario 8 — admin is never blocked even when they are the initiator', () => {
    setUser({ id: 5, roles: ['admin'] })
    render(
      <SodActionButton
        initiatedById={5}
        label="Approve"
        onClick={() => undefined}
      />,
    )
    expect(screen.getByRole('button')).not.toBeDisabled()
  })
})
