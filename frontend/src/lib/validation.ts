/**
 * Validation Utilities
 * 
 * Centralized validation helpers for consistent form validation
 * across the application.
 */

import { z } from 'zod'

// Common validation patterns
export const validators = {
  /** Required string field */
  requiredString: (fieldName: string, minLength = 1) =>
    z.string().trim().min(minLength, `${fieldName} is required`),

  /** Optional string field */
  optionalString: () => z.string().trim().optional().or(z.literal('')),

  /** Email field */
  email: (required = false) => {
    const base = z.string().trim().email('Please enter a valid email address')
    return required 
      ? base.min(1, 'Email is required')
      : base.optional().or(z.literal(''))
  },

  /** Phone number (Philippines format) */
  phone: (required = false) => {
    const base = z.string().trim().regex(/^\+?[0-9\-\s()]+$/, 'Please enter a valid phone number')
    return required ? base.min(1, 'Phone number is required') : base.optional().or(z.literal(''))
  },

  /** TIN format (Philippines) */
  tin: (required = false) => {
    const base = z.string().trim().regex(/^\d{3}-?\d{3}-?\d{3}-?\d{3}$/, 'TIN format: 000-000-000-000')
    return required ? base.min(1, 'TIN is required') : base.optional().or(z.literal(''))
  },

  /** Positive number */
  positiveNumber: (fieldName: string) =>
    z.coerce.number({ invalid_type_error: `${fieldName} must be a number` })
      .positive(`${fieldName} must be greater than 0`),

  /** Non-negative number */
  nonNegativeNumber: (fieldName: string) =>
    z.coerce.number({ invalid_type_error: `${fieldName} must be a number` })
      .min(0, `${fieldName} cannot be negative`),

  /** Required date */
  requiredDate: (fieldName: string) =>
    z.string().trim().min(1, `${fieldName} is required`),

  /** Required ID (for foreign keys) */
  requiredId: (fieldName: string) =>
    z.coerce.number({ required_error: `${fieldName} is required` })
      .positive(`${fieldName} is required`),

  /** Optional ID */
  optionalId: () =>
    z.coerce.number().positive().optional(),

  /** Philippine mobile number */
  mobileNumber: (required = false) => {
    const base = z.string().trim().regex(/^(09|\+639)\d{9}$/, 'Please enter a valid Philippine mobile number (09XXXXXXXXX)')
    return required ? base.min(1, 'Mobile number is required') : base.optional().or(z.literal(''))
  },
}

/**
 * Helper to show validation errors in a toast
 * Returns the first error message from a Zod error
 */
export function getFirstZodError(error: z.ZodError): string {
  const firstIssue = error.issues[0]
  return firstIssue?.message ?? 'Validation failed'
}

/**
 * Format field errors for display
 */
export function formatZodErrors(error: z.ZodError): Record<string, string> {
  const errors: Record<string, string> = {}
  error.issues.forEach((issue) => {
    const path = issue.path.join('.')
    if (!errors[path]) {
      errors[path] = issue.message
    }
  })
  return errors
}

/**
 * Common validation error messages
 */
export const validationMessages = {
  required: (field: string) => `${field} is required`,
  minLength: (field: string, min: number) => `${field} must be at least ${min} characters`,
  maxLength: (field: string, max: number) => `${field} must not exceed ${max} characters`,
  minValue: (field: string, min: number) => `${field} must be at least ${min}`,
  maxValue: (field: string, max: number) => `${field} must not exceed ${max}`,
  email: 'Please enter a valid email address',
  phone: 'Please enter a valid phone number',
  tin: 'Please enter a valid TIN (format: 000-000-000-000)',
  sss: 'Please enter a valid SSS number',
  philhealth: 'Please enter a valid PhilHealth number',
  pagibig: 'Please enter a valid Pag-IBIG number',
  date: 'Please enter a valid date',
  number: 'Please enter a valid number',
  positive: 'Value must be greater than 0',
  nonNegative: 'Value cannot be negative',
  match: (field1: string, field2: string) => `${field1} must match ${field2}`,
  unique: (field: string) => `${field} already exists`,
}

/**
 * Schema refinements for common validations
 */
export const refinements = {
  /** Ensure due date is on or after invoice date */
  dueDateAfterInvoiceDate: <T extends { invoice_date: string; due_date: string }>(data: T) =>
    data.due_date >= data.invoice_date,

  /** Ensure end date is on or after start date */
  endDateAfterStartDate: <T extends { start_date: string; end_date: string }>(data: T) =>
    data.end_date >= data.start_date,

  /** Ensure password match */
  passwordsMatch: <T extends { password: string; password_confirmation: string }>(data: T) =>
    data.password === data.password_confirmation,
}
