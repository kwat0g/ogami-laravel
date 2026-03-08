/**
 * CurrencyAmount - Minimalist design
 * Renders PHP amounts with clean typography
 */

interface CurrencyAmountProps {
  centavos: number
  size?: 'sm' | 'base' | 'lg' | 'xl'
  className?: string
  showSign?: boolean
}

const SIZE_CLASSES: Record<NonNullable<CurrencyAmountProps['size']>, string> = {
  sm: 'text-xs',
  base: 'text-sm',
  lg: 'text-base',
  xl: 'text-lg font-semibold',
}

export default function CurrencyAmount({ 
  centavos,
  size = 'base', 
  className = '',
  showSign = false 
}: CurrencyAmountProps) {
  const amount = (centavos ?? 0) / 100
  const isNegative = amount < 0
  const formatted = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    signDisplay: showSign ? 'always' : 'auto',
  }).format(amount)

  return (
    <span
      data-currency
      className={[
        'font-mono tabular-nums tracking-tight',
        SIZE_CLASSES[size],
        isNegative ? 'text-red-600' : 'text-neutral-900',
        className,
      ].join(' ').trim()}
    >
      {formatted}
    </span>
  )
}
