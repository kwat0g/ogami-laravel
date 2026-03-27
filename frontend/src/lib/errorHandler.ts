import type { AxiosError } from 'axios'

// ---------------------------------------------------------------------------
// Structured error result
// ---------------------------------------------------------------------------

export interface ParsedApiError {
  /** Top-level human-readable message (always present). */
  message: string
  /** Field-level validation messages from a 422 response. */
  fieldErrors: Record<string, string[]>
  /** HTTP status code, if available. */
  status: number | null
  /** Laravel error_code string, if present (e.g. "VALIDATION_ERROR"). */
  errorCode: string | null
}

// ---------------------------------------------------------------------------
// Core parser — converts an Axios error into ParsedApiError
// ---------------------------------------------------------------------------

/**
 * Parse any thrown value into a structured {@link ParsedApiError}.
 *
 * Works for:
 *  - Axios `422 Unprocessable Content` — extracts `errors` field map
 *  - Axios `403 Forbidden` / `401 Unauthorized`
 *  - Generic Axios network errors
 *  - Non-Axios errors (plain Error, strings, unknown)
 *
 * Usage:
 * ```ts
 * try { await api.post(...) } catch (err) {
 *   const { message, fieldErrors } = parseApiError(err)
 *   toast.error(message)
 * }
 * ```
 */
export function parseApiError(err: unknown): ParsedApiError {
  // Axios error with a response
  if (isAxiosError(err) && err.response) {
    const { status, data }  = err.response
    const body              = data as Record<string, unknown>
    const message           = typeof body?.message === 'string'
      ? body.message
      : httpMessage(status)
    const fieldErrors       = extractFieldErrors(body?.errors)
    const errorCode         = typeof body?.error_code === 'string' ? body.error_code : null

    return { message, fieldErrors, status, errorCode }
  }

  // Axios network error (no response — timeout, DNS, CORS, etc.)
  if (isAxiosError(err)) {
    return {
      message:    'Network error — please check your connection and try again.',
      fieldErrors: {},
      status:     null,
      errorCode:  null,
    }
  }

  // Plain JS Error
  if (err instanceof Error) {
    return { message: err.message, fieldErrors: {}, status: null, errorCode: null }
  }

  // Unknown
  return {
    message:    'An unexpected error occurred.',
    fieldErrors: {},
    status:     null,
    errorCode:  null,
  }
}

// ---------------------------------------------------------------------------
// Convenience: first field error or the top-level message
// ---------------------------------------------------------------------------

/**
 * Returns the first field-level error message if available, otherwise the
 * top-level message.  Useful for toast notifications.
 */
export function firstErrorMessage(err: unknown, fallback?: string): string {
  const parsed = parseApiError(err)
  const firstField = Object.values(parsed.fieldErrors)[0]?.[0]
  return firstField ?? parsed.message ?? fallback ?? 'An unexpected error occurred'
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function isAxiosError(err: unknown): err is AxiosError {
  return typeof err === 'object' && err !== null && (err as AxiosError).isAxiosError === true
}

function extractFieldErrors(raw: unknown): Record<string, string[]> {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
    return {}
  }
  const result: Record<string, string[]> = {}
  for (const [field, messages] of Object.entries(raw as Record<string, unknown>)) {
    if (Array.isArray(messages) && messages.every((m) => typeof m === 'string')) {
      result[field] = messages as string[]
    } else if (typeof messages === 'string') {
      result[field] = [messages]
    }
  }
  return result
}

function httpMessage(status: number): string {
  const messages: Record<number, string> = {
    400: 'Bad request — please review your input.',
    401: 'Your session has expired. Please log in again.',
    403: 'You do not have permission to perform this action.',
    404: 'The requested resource was not found.',
    409: 'Conflict — this operation cannot be completed in the current state.',
    422: 'Validation failed — please review the highlighted fields.',
    429: 'Too many requests — please wait a moment and try again.',
    500: 'Server error — please try again shortly.',
    503: 'Service unavailable — the server is temporarily offline.',
  }
  return messages[status] ?? `Unexpected error (HTTP ${status}).`
}
