// ---------------------------------------------------------------------------
// Auth Domain Types
//
// Single-purpose re-export so callers can import from '@/types/auth' instead
// of '@/types/api'.  The canonical definitions remain in api.ts.
// ---------------------------------------------------------------------------

export type { AuthUser, LoginPayload, LoginResult, ApiSuccess, ApiError, ApiResponse } from './api'
