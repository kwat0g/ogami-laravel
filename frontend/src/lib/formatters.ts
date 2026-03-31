/**
 * Format a number as Philippine Peso currency.
 */
export function formatCurrency(
  value: number | string | undefined | null,
  currency: string = 'PHP',
  decimals: number = 2
): string {
  if (value === undefined || value === null) return '—'
  
  const num = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(num)) return '—'
  
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency,
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num)
}

/**
 * Format a number as a percentage.
 */
export function formatPercent(
  value: number | string | undefined | null,
  decimals: number = 2
): string {
  if (value === undefined || value === null) return '—'
  
  const num = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(num)) return '—'
  
  return new Intl.NumberFormat('en-PH', {
    style: 'percent',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num)
}

/**
 * Format a number with thousand separators.
 */
export function formatNumber(
  value: number | string | undefined | null,
  decimals: number = 0
): string {
  if (value === undefined || value === null) return '—'
  
  const num = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(num)) return '—'
  
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(num)
}

/**
 * Format a date to a readable string.
 * Handles date strings without timezone conversion issues.
 */
export function formatDate(
  value: string | Date | undefined | null,
  options: Intl.DateTimeFormatOptions = {}
): string {
  if (!value) return '—'
  
  // For string dates like "2023-01-01", treat as local date to avoid UTC conversion
  if (typeof value === 'string') {
    // If it's a simple date string (YYYY-MM-DD), format it directly
    const dateMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (dateMatch) {
      const [, year, month, day] = dateMatch
      const dateObj = new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
      if (!isNaN(dateObj.getTime())) {
        return new Intl.DateTimeFormat('en-PH', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          ...options,
        }).format(dateObj)
      }
    }
  }
  
  const date = typeof value === 'string' ? new Date(value) : value
  
  if (isNaN(date.getTime())) return '—'
  
  return new Intl.DateTimeFormat('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    ...options,
  }).format(date)
}

/**
 * Format a date for display in tables (YYYY-MM-DD format).
 */
export function formatDateSimple(value: string | Date | undefined | null): string {
  if (!value) return '—'
  
  // For string dates like "2023-01-01", return as-is
  if (typeof value === 'string') {
    const dateMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (dateMatch) {
      return value.substring(0, 10)
    }
  }
  
  const date = typeof value === 'string' ? new Date(value) : value
  if (isNaN(date.getTime())) return '—'
  
  return date.toISOString().split('T')[0]
}

/**
 * Format a datetime to a readable string.
 */
export function formatDateTime(
  value: string | Date | undefined | null
): string {
  return formatDate(value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// ============================================================================
// Peso Convenience Functions
// ============================================================================

/**
 * Format centavos as Philippine Peso (e.g. 150000 -> "₱1,500.00").
 * Use for amounts stored as centavos (integer) in the database.
 */
export function formatPeso(centavos: number | string | undefined | null): string {
  if (centavos === undefined || centavos === null) return '\u2014'
  const num = typeof centavos === 'string' ? parseFloat(centavos) : centavos
  if (isNaN(num)) return '\u2014'
  return '\u20B1' + (num / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/**
 * Format a raw peso amount (not centavos) with proper decimal formatting.
 * Use for amounts already in pesos (e.g. API responses returning decimal amounts).
 */
export function formatPesoAmount(amount: number | string | undefined | null): string {
  if (amount === undefined || amount === null) return '\u2014'
  const num = typeof amount === 'string' ? parseFloat(amount) : amount
  if (isNaN(num)) return '\u2014'
  return '\u20B1' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/**
 * Format a quantity value, trimming unnecessary trailing zeros.
 * Shows up to 2 decimal places (e.g. 10.5 -> "10.5", 10 -> "10", 10.50 -> "10.5").
 */
export function formatQty(value: number | string | undefined | null): string {
  if (value === undefined || value === null) return '\u2014'
  const num = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(num)) return '\u2014'
  // Use maximumFractionDigits: 2 to avoid showing 10.0000
  return num.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
