// ---------------------------------------------------------------------------
// Attendance Domain Types
//
// Re-exports from hr.ts so callers can import from '@/types/attendance'
// without knowing which aggregate type file the interface lives in.
// ---------------------------------------------------------------------------

export type {
  AttendanceLog,
  AttendanceFilters,
  OvertimeRequest,
  OvertimeFilters,
  OvertimeStatus,
  ShiftSchedule,
} from './hr'
