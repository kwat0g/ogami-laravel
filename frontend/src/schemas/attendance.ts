import { z } from 'zod'

// ─── Attendance Import ────────────────────────────────────────────────────────

export const attendanceImportSchema = z.object({
  file: z
    .instanceof(FileList, { message: 'Select a file' })
    .refine((fl) => fl.length > 0, 'Select a file')
    .refine(
      (fl) =>
        ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'].includes(
          fl[0]?.type ?? '',
        ),
      'File must be a CSV or Excel spreadsheet',
    )
    .refine((fl) => (fl[0]?.size ?? 0) <= 10 * 1024 * 1024, 'File must be 10 MB or smaller'),
  cutoff_start: z
    .string()
    .min(1, 'Cutoff start is required')
    .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
  cutoff_end: z
    .string()
    .min(1, 'Cutoff end is required')
    .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
  overwrite_existing: z.boolean().default(false),
})

export type AttendanceImportFormValues = z.infer<typeof attendanceImportSchema>

// ─── Overtime Request / Approval ──────────────────────────────────────────────

export const overtimeFormSchema = z.object({
  employee_id: z.coerce.number({ invalid_type_error: 'Select an employee' }).positive(),
  work_date: z
    .string()
    .min(1, 'Work date is required')
    .refine((v) => !isNaN(Date.parse(v)), 'Enter a valid date'),
  overtime_type: z.enum(['regular_day', 'rest_day', 'holiday'], {
    errorMap: () => ({ message: 'Select overtime type' }),
  }),
  /** Overtime duration in minutes. */
  duration_minutes: z.coerce
    .number({ invalid_type_error: 'Enter minutes' })
    .int()
    .min(30, 'Minimum overtime is 30 minutes')
    .max(720, 'Maximum single-session overtime is 12 hours (720 minutes)'),
  remarks: z.string().trim().max(500).optional().default(''),
})

export type OvertimeFormValues = z.infer<typeof overtimeFormSchema>
