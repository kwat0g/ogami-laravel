/**
 * Input formatters for Philippine government IDs and phone numbers
 * Provides auto-formatting as the user types
 */

/**
 * Formats TIN input: XXX-XXX-XXX-XXX (12 digits)
 * Auto-adds dashes as user types
 */
export function formatTIN(value: string): string {
  // Remove all non-digits
  const digits = value.replace(/\D/g, '').slice(0, 12)
  
  // Add dashes after every 3 digits
  const parts: string[] = []
  for (let i = 0; i < digits.length; i += 3) {
    parts.push(digits.slice(i, i + 3))
  }
  
  return parts.join('-')
}

/**
 * Formats SSS Number: XX-XXXXXXX-X (10 digits)
 * Pattern: 2 digits - 7 digits - 1 digit
 */
export function formatSSS(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 10)
  
  if (digits.length <= 2) return digits
  if (digits.length <= 9) {
    return `${digits.slice(0, 2)}-${digits.slice(2)}`
  }
  return `${digits.slice(0, 2)}-${digits.slice(2, 9)}-${digits.slice(9, 10)}`
}

/**
 * Formats PhilHealth Number: XX-XXXXXXXXX-X (12 digits)
 * Pattern: 2 digits - 9 digits - 1 digit
 */
export function formatPhilHealth(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 12)
  
  if (digits.length <= 2) return digits
  if (digits.length <= 11) {
    return `${digits.slice(0, 2)}-${digits.slice(2)}`
  }
  return `${digits.slice(0, 2)}-${digits.slice(2, 11)}-${digits.slice(11, 12)}`
}

/**
 * Formats Pag-IBIG Number: XXXX-XXXX-XXXX (12 digits)
 * Pattern: 4 digits - 4 digits - 4 digits
 */
export function formatPagIBIG(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 12)
  
  const parts: string[] = []
  for (let i = 0; i < digits.length; i += 4) {
    parts.push(digits.slice(i, i + 4))
  }
  
  return parts.join('-')
}

/**
 * Formats Philippine mobile number: 09XX XXX XXXX (11 digits)
 * Must start with 09
 */
export function formatPhoneNumber(value: string): string {
  // Remove all non-digits
  let digits = value.replace(/\D/g, '').slice(0, 11)
  
  // Ensure it starts with 09
  if (digits.length >= 2 && !digits.startsWith('09')) {
    // If user types something else, prepend 09
    if (digits.startsWith('9')) {
      digits = '0' + digits
    } else if (!digits.startsWith('0')) {
      digits = '09' + digits.slice(0, 9)
    }
  }
  
  // Format as 09XX XXX XXXX
  if (digits.length <= 4) return digits
  if (digits.length <= 7) {
    return `${digits.slice(0, 4)} ${digits.slice(4)}`
  }
  return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7, 11)}`
}

/**
 * Formats Philippine landline number: (XX) XXXX-XXXX
 */
export function formatLandline(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 10)
  
  if (digits.length <= 2) return digits
  if (digits.length <= 6) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  }
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6, 10)}`
}

/**
 * Returns raw digits only (for API submission)
 */
export function getRawDigits(value: string): string {
  return value.replace(/\D/g, '')
}

/**
 * Validation helpers
 */
export const validators = {
  tin: (value: string): boolean => {
    const digits = getRawDigits(value)
    return digits.length === 12
  },
  
  sss: (value: string): boolean => {
    const digits = getRawDigits(value)
    return digits.length === 10
  },
  
  philhealth: (value: string): boolean => {
    const digits = getRawDigits(value)
    return digits.length === 12
  },
  
  pagibig: (value: string): boolean => {
    const digits = getRawDigits(value)
    return digits.length === 12
  },
  
  phone: (value: string): boolean => {
    const digits = getRawDigits(value)
    return digits.length === 11 && digits.startsWith('09')
  },
  
  landline: (value: string): boolean => {
    const digits = getRawDigits(value)
    return digits.length === 10
  },
}

/**
 * Error messages for validation
 */
export const validationMessages = {
  tin: 'TIN must be 12 digits (format: XXX-XXX-XXX-XXX)',
  sss: 'SSS must be 10 digits (format: XX-XXXXXXX-X)',
  philhealth: 'PhilHealth must be 12 digits (format: XX-XXXXXXXXX-X)',
  pagibig: 'Pag-IBIG must be 12 digits (format: XXXX-XXXX-XXXX)',
  phone: 'Phone must be 11 digits starting with 09 (format: 09XX XXX XXXX)',
  landline: 'Landline must be 10 digits (format: (XX) XXXX-XXXX)',
}

/**
 * Strips all formatting from a value for API submission
 * Returns raw digits only
 */
export function stripFormatting(value: string | null | undefined): string | null {
  if (!value) return null
  const raw = getRawDigits(value)
  return raw || null
}

/**
 * Helper to normalize form data before API submission
 * Strips formatting from all government ID and phone fields
 */
export function normalizeGovIDs<T extends Record<string, unknown>>(data: T): T {
  const fieldsToStrip = [
    'tin', 'sss_no', 'philhealth_no', 'pagibig_no', 
    'personal_phone', 'phone', 'mobile', 'contact_number'
  ]
  
  const normalized = { ...data }
  
  for (const field of fieldsToStrip) {
    if (field in normalized) {
      const value = normalized[field]
      if (typeof value === 'string') {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (normalized as any)[field] = stripFormatting(value)
      }
    }
  }
  
  return normalized
}
