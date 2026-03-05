import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import CurrencyAmount from '@/components/ui/CurrencyAmount'

// ---------------------------------------------------------------------------
// CurrencyAmount
// ---------------------------------------------------------------------------

describe('CurrencyAmount', () => {
  it('renders a positive amount in Philippine Peso format', () => {
    render(<CurrencyAmount centavos={1500000} />)
    // 1_500_000 centavos = ₱15,000.00
    const el = screen.getByText(/15,000/)
    expect(el).toBeInTheDocument()
    expect(el.tagName).toBe('SPAN')
  })

  it('renders with data-currency attribute', () => {
    render(<CurrencyAmount centavos={50000} />)
    const el = document.querySelector('[data-currency]')
    expect(el).toBeInTheDocument()
  })

  it('uses font-mono tabular-nums class for tabular numeral rendering', () => {
    render(<CurrencyAmount centavos={50000} />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).toContain('font-mono')
    expect(el?.className).toContain('tabular-nums')
  })

  it('applies text-red-600 class for negative amounts', () => {
    render(<CurrencyAmount centavos={-50000} />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).toContain('text-red-600')
  })

  it('does NOT apply text-red-600 class for positive amounts', () => {
    render(<CurrencyAmount centavos={50000} />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).not.toContain('text-red-600')
  })

  it('renders ₱0.00 for zero centavos', () => {
    render(<CurrencyAmount centavos={0} />)
    const el = document.querySelector('[data-currency]')
    expect(el?.textContent).toMatch(/0\.00/)
  })

  it('renders fractional centavo amounts correctly (e.g. 150 centavos = ₱1.50)', () => {
    render(<CurrencyAmount centavos={150} />)
    const el = document.querySelector('[data-currency]')
    expect(el?.textContent).toMatch(/1\.50/)
  })

  it('applies text-xs class for size="sm"', () => {
    render(<CurrencyAmount centavos={100} size="sm" />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).toContain('text-xs')
  })

  it('applies text-base class for size="lg"', () => {
    render(<CurrencyAmount centavos={100} size="lg" />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).toContain('text-base')
  })

  it('applies font-semibold class for size="xl"', () => {
    render(<CurrencyAmount centavos={100} size="xl" />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).toContain('font-semibold')
  })

  it('merges additional className prop', () => {
    render(<CurrencyAmount centavos={100} className="my-custom-class" />)
    const el = document.querySelector('[data-currency]')
    expect(el?.className).toContain('my-custom-class')
  })
})
