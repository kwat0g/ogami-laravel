import type { z } from 'zod'

/**
 * Dev-mode API response validator.
 *
 * Wraps a Zod schema and logs a console warning (never throws) when the
 * response shape doesn't match.  In production builds this is a no-op
 * so there's zero runtime cost.
 *
 * Usage in a TanStack Query hook:
 * ```ts
 * import { paginatedLeaveRequestsSchema } from '@/schemas/responses'
 * import { validateResponse } from '@/lib/validateResponse'
 *
 * const validate = validateResponse(paginatedLeaveRequestsSchema, 'LeaveRequests')
 *
 * export function useLeaveRequests(filters) {
 *   return useQuery({
 *     queryKey: ['leave-requests', filters],
 *     queryFn: async () => {
 *       const res = await api.get('/leave/requests', { params: filters })
 *       return validate(res.data)   // warns in dev if shape is wrong
 *     },
 *   })
 * }
 * ```
 */
export function validateResponse<T extends z.ZodTypeAny>(
  schema: T,
  label: string,
): (data: unknown) => z.infer<T> {
  // In production, skip validation entirely
  if (import.meta.env.PROD) {
    return (data: unknown) => data as z.infer<T>
  }

  return (data: unknown): z.infer<T> => {
    const result = schema.safeParse(data)
    if (!result.success) {
      console.warn(
        `[validateResponse] ${label}: API response shape mismatch`,
        {
          issues: result.error.issues.map((i) => ({
            path: i.path.join('.'),
            code: i.code,
            message: i.message,
          })),
          data,
        },
      )
      // Return the original data anyway -- we never block the UI
      return data as z.infer<T>
    }
    return result.data
  }
}

/**
 * Convenience: validate a single-item `{ data: T }` response.
 */
export function validateSingleResponse<T extends z.ZodTypeAny>(
  itemSchema: T,
  label: string,
) {
  // Lazy import to avoid circular deps
  const wrappedSchema = (async () => {
    const { singleResponseSchema } = await import('@/schemas/responses')
    return singleResponseSchema(itemSchema)
  })()

  if (import.meta.env.PROD) {
    return (data: unknown) => data as { data: z.infer<T> }
  }

  return async (data: unknown) => {
    const schema = await wrappedSchema
    const result = schema.safeParse(data)
    if (!result.success) {
      console.warn(
        `[validateResponse] ${label}: single response shape mismatch`,
        {
          issues: result.error.issues.map((i) => ({
            path: i.path.join('.'),
            code: i.code,
            message: i.message,
          })),
          data,
        },
      )
    }
    return data as { data: z.infer<T> }
  }
}
