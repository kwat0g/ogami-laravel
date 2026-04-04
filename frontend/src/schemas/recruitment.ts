import { z } from 'zod'

// ── Job Requisition Schemas ─────────────────────────────────────────────────

export const jobRequisitionSchema = z.object({
  department_id: z.coerce.number({ required_error: 'Department is required' }).positive(),
  position_id: z.coerce.number({ required_error: 'Position is required' }).positive(),
  employment_type: z.enum(['regular', 'contractual', 'project_based', 'part_time'], {
    required_error: 'Employment type is required',
  }),
  number_of_positions: z.coerce.number().min(1, 'At least 1 position required').default(1),
  justification: z.string().trim().min(10, 'Justification must be at least 10 characters').max(2000),
  target_start_date: z.string().trim().min(1, 'Target start date is required'),
  salary_grade_id: z.coerce.number().positive().optional(),
  budget_approved: z.boolean().default(false),
  requirements: z.string().trim().max(5000).optional(),
  preferred_qualifications: z.string().trim().max(5000).optional(),
})

export type JobRequisitionFormValues = z.infer<typeof jobRequisitionSchema>

// ── Job Posting Schemas ─────────────────────────────────────────────────────

export const jobPostingSchema = z.object({
  requisition_id: z.coerce.number({ required_error: 'Requisition is required' }).positive(),
  title: z.string().trim().min(1, 'Title is required').max(200),
  description: z.string().trim().min(50, 'Description must be at least 50 characters').max(10000),
  requirements: z.string().trim().max(5000).optional(),
  salary_range_from: z.coerce.number().min(0).optional(),
  salary_range_to: z.coerce.number().min(0).optional(),
  posting_date: z.string().trim().min(1, 'Posting date is required'),
  closing_date: z.string().trim().min(1, 'Closing date is required'),
  is_internal: z.boolean().default(false),
})

export type JobPostingFormValues = z.infer<typeof jobPostingSchema>

// ── Application Schemas ─────────────────────────────────────────────────────

export const applicationSchema = z.object({
  posting_id: z.coerce.number({ required_error: 'Job posting is required' }).positive(),
  candidate_id: z.coerce.number().positive().optional(),
  first_name: z.string().trim().min(1, 'First name is required').max(100),
  last_name: z.string().trim().min(1, 'Last name is required').max(100),
  email: z.string().trim().email('Valid email is required'),
  phone: z.string().trim().max(30).optional(),
  source: z.enum(['referral', 'walk_in', 'job_board', 'agency', 'internal']).default('job_board'),
  cover_letter: z.string().trim().max(5000).optional(),
  expected_salary: z.coerce.number().min(0).optional(),
})

export type ApplicationFormValues = z.infer<typeof applicationSchema>

// ── Interview Schedule Schemas ──────────────────────────────────────────────

export const interviewScheduleSchema = z.object({
  application_id: z.coerce.number({ required_error: 'Application is required' }).positive(),
  type: z.enum(['panel', 'one_on_one', 'technical', 'hr_screening', 'final'], {
    required_error: 'Interview type is required',
  }),
  scheduled_at: z.string().trim().min(1, 'Date and time is required'),
  duration_minutes: z.coerce.number().min(15).max(480).default(60),
  location: z.string().trim().max(500).optional(),
  interviewer_id: z.coerce.number().positive().optional(),
  interviewer_department_id: z.coerce.number().positive().optional(),
  round: z.coerce.number().min(1).default(1),
  notes: z.string().trim().max(2000).optional(),
}).refine(
  (data) => data.interviewer_id !== undefined || data.interviewer_department_id !== undefined,
  { message: 'Either an interviewer or an interviewer department is required', path: ['interviewer_id'] },
)

export type InterviewScheduleFormValues = z.infer<typeof interviewScheduleSchema>

// ── Job Offer Schemas ───────────────────────────────────────────────────────

export const jobOfferSchema = z.object({
  application_id: z.coerce.number({ required_error: 'Application is required' }).positive(),
  position_id: z.coerce.number({ required_error: 'Position is required' }).positive(),
  department_id: z.coerce.number({ required_error: 'Department is required' }).positive(),
  salary_grade_id: z.coerce.number().positive().optional(),
  basic_monthly_rate: z.coerce.number().positive('Salary must be positive'),
  employment_type: z.enum(['regular', 'contractual', 'project_based', 'part_time']),
  start_date: z.string().trim().min(1, 'Start date is required'),
  probation_end_date: z.string().trim().optional(),
  remarks: z.string().trim().max(2000).optional(),
})

export type JobOfferFormValues = z.infer<typeof jobOfferSchema>

// ── Interview Evaluation Schemas ────────────────────────────────────────────

export const interviewEvaluationSchema = z.object({
  interview_schedule_id: z.coerce.number({ required_error: 'Interview is required' }).positive(),
  overall_score: z.coerce.number().min(0).max(5),
  recommendation: z.enum(['endorse', 'reject', 'hold'], {
    required_error: 'Recommendation is required',
  }),
  strengths: z.string().trim().max(2000).optional(),
  weaknesses: z.string().trim().max(2000).optional(),
  notes: z.string().trim().max(2000).optional(),
})

export type InterviewEvaluationFormValues = z.infer<typeof interviewEvaluationSchema>
