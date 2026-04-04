// ---------------------------------------------------------------------------
// Recruitment Domain Types
// ---------------------------------------------------------------------------

import type { PaginatedMeta } from './hr'

// ── Status Types ─────────────────────────────────────────────────────────────

export type RequisitionStatus =
  | 'draft' | 'pending_approval' | 'approved' | 'rejected'
  | 'open' | 'on_hold' | 'closed' | 'cancelled'

export type PostingStatus = 'draft' | 'published' | 'closed' | 'expired'

export type ApplicationStatus =
  | 'new' | 'under_review' | 'shortlisted' | 'interviewed' | 'hired' | 'rejected' | 'withdrawn'

export type InterviewStatus =
  | 'scheduled' | 'in_progress' | 'completed' | 'cancelled' | 'no_show'

export type InterviewType =
  | 'panel' | 'one_on_one' | 'technical' | 'hr_screening' | 'final'

export type OfferStatus =
  | 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired' | 'withdrawn'

export type PreEmploymentStatus = 'pending' | 'in_progress' | 'completed' | 'waived'

export type RequirementStatus = 'pending' | 'submitted' | 'verified' | 'rejected' | 'waived'

export type HiringStatus =
  | 'pending'
  | 'pending_vp_approval'
  | 'hired'
  | 'rejected_by_vp'
  | 'failed_preemployment'

export type CandidateSource = 'referral' | 'walk_in' | 'job_board' | 'agency' | 'internal'

export type EmploymentType = 'regular' | 'contractual' | 'project_based' | 'part_time'

export type EvaluationRecommendation = 'endorse' | 'reject' | 'hold'

// ── Status badge color map ───────────────────────────────────────────────────

export const statusColors: Record<string, string> = {
  draft: 'gray',
  pending_approval: 'amber',
  approved: 'blue',
  rejected: 'red',
  open: 'green',
  on_hold: 'orange',
  closed: 'slate',
  cancelled: 'red',
  new: 'sky',
  under_review: 'amber',
  shortlisted: 'teal',
  interviewed: 'blue',
  withdrawn: 'gray',
  scheduled: 'blue',
  in_progress: 'amber',
  completed: 'green',
  no_show: 'red',
  sent: 'purple',
  accepted: 'green',
  expired: 'slate',
  pending: 'amber',
  submitted: 'blue',
  verified: 'green',
  waived: 'blue',
  hired: 'emerald',
  failed_preemployment: 'red',
  pending_vp_approval: 'amber',
  rejected_by_vp: 'red',
  endorse: 'green',
  hold: 'amber',
}

// ── Entities ─────────────────────────────────────────────────────────────────

export interface Candidate {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  phone: string | null
  address: string | null
  source: CandidateSource
  source_label: string
  resume_path: string | null
  linkedin_url: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface JobRequisition {
  ulid: string
  requisition_number: string
  department: { id: number; code: string; name: string } | null
  position: { id: number; code: string; title: string } | null
  requester: { id: number; name: string } | null
  approver: { id: number; name: string } | null
  employment_type: EmploymentType
  employment_type_label: string
  headcount: number
  reason: string
  justification: string | null
  salary_grade_id: number | null
  salary_grade?: { id: number; grade?: number; name?: string; level?: number; min_monthly_rate?: number; max_monthly_rate?: number; amount?: number }
  target_start_date: string | null
  status: RequisitionStatus
  status_label: string
  status_color: string
  approved_at: string | null
  rejected_at: string | null
  rejection_reason: string | null
  hired_count: number
  postings?: JobPostingListItem[]
  approvals?: RequisitionApproval[]
  created_at: string
  updated_at: string
}

export interface RequisitionApproval {
  id: number
  user: { id: number; name: string }
  action: string
  remarks: string | null
  acted_at: string | null
}

export interface JobPostingListItem {
  id?: number
  ulid: string
  job_requisition_id?: number | null
  department?: { id: number; code: string; name: string } | null
  position?: { id: number; code: string; title: string } | null
  salary_grade?: {
    id: number
    code: string
    name: string
    level: number
    min_monthly_rate: number
    max_monthly_rate: number
  } | null
  headcount?: number | null
  posting_number: string
  title: string
  requirement_items?: string[]
  location: string | null
  employment_type: EmploymentType
  is_internal: boolean
  is_external: boolean
  status: PostingStatus
  status_label: string
  status_color: string
  published_at: string | null
  closes_at: string | null
  views_count: number
  applications_count?: number
  requisition?: {
    ulid: string | null
    requisition_number: string | null
    department: string | { id: number; code: string; name: string } | null
    position: string | { id: number; code: string; title: string } | null
  }
  created_at: string
}

export interface JobPosting extends JobPostingListItem {
  description: string
  requirements: string
  requirement_items?: string[]
  employment_type_label: string
  updated_at: string
}

export interface ApplicationListItem {
  ulid: string
  application_number: string
  candidate: { id: number; full_name: string; email: string } | null
  posting: {
    id?: number
    ulid: string
    posting_number?: string
    title: string
    salary_grade_id?: number | null
    salary_grade?: {
      id: number
      code: string
      name: string
      level: number
      min_monthly_rate: number
      max_monthly_rate: number
    } | null
    requisition?: {
      ulid?: string | null
      requisition_number?: string | null
      department?: string | null
      position?: string | null
    } | null
    department: string
    position: string
  } | null
  source: CandidateSource
  source_label: string
  status: ApplicationStatus
  status_label: string
  status_color: string
  application_date: string
  created_at: string
}

export interface InterviewDetail {
  id: number
  round: number
  type: InterviewType
  type_label: string
  scheduled_at: string
  duration_minutes: number
  location: string | null
  interviewer: { id: number; name: string }
  status: InterviewStatus
  status_label: string
  status_color: string
  evaluation: {
    overall_score: number
    recommendation: EvaluationRecommendation
    recommendation_label: string
    recommendation_color: string
    scorecard: ScorecardItem[]
    general_remarks: string | null
    submitted_at: string
  } | null
}

export interface RecruitmentInterviewerOption {
  id: number
  name: string
  position: {
    id: number
    code: string
    title: string
  } | null
  department: {
    id: number
    code: string
    name: string
  } | null
}

export interface ScorecardItem {
  criterion: string
  score: number
  comments: string | null
}

export interface JobOfferDetail {
  ulid: string
  offer_number: string
  application?: {
    ulid: string
    application_number: string
    candidate: { full_name: string; email: string } | null
  }
  offered_position: { id: number; title: string } | null
  offered_department: { id: number; name: string } | null
  offered_salary: number
  employment_type: EmploymentType
  employment_type_label: string
  start_date: string
  offer_letter_path: string | null
  status: OfferStatus
  status_label: string
  status_color: string
  sent_at: string | null
  expires_at: string | null
  responded_at: string | null
  rejection_reason: string | null
  preparer: { id: number; name: string } | null
  approver: { id: number; name: string } | null
  created_at: string
  updated_at: string
}

export interface PreEmploymentChecklist {
  id: number
  status: PreEmploymentStatus
  status_label: string
  status_color: string
  waiver_reason: string | null
  completed_at: string | null
  progress: { total: number; completed: number; percentage: number }
  requirements: PreEmploymentRequirementItem[]
  created_at: string
}

export interface PreEmploymentRequirementItem {
  id: number
  requirement_type: string
  label: string
  is_required: boolean
  status: RequirementStatus
  status_label: string
  status_color: string
  document_path: string | null
  submitted_at: string | null
  verified_at: string | null
  remarks: string | null
}

export interface Application extends ApplicationListItem {
  cover_letter: string | null
  resume_download_url: string | null
  reviewer: { id: number; name: string } | null
  reviewed_at: string | null
  rejection_reason: string | null
  withdrawn_reason: string | null
  interviews: InterviewDetail[]
  documents: { id: number; label: string; file_path: string; mime_type: string | null }[]
  offer: JobOfferDetail | null
  pre_employment: PreEmploymentChecklist | null
  hiring: {
    ulid: string
    status: HiringStatus
    hired_at: string | null
    start_date: string
    employee_id: number | null
    employee_ulid: string | null
  } | null
  updated_at: string
}

// ── Dashboard ────────────────────────────────────────────────────────────────

export interface RecruitmentDashboard {
  kpis: {
    active_postings: number
    active_applications: number
    interviews_this_week: number
    pending_offers: number
  }
  pipeline_funnel: {
    requisitions: number
    postings: number
    applications: number
    shortlisted: number
    interviewed: number
    offered: number
    hired: number
  }
  source_mix: { source: string; label: string; count: number }[]
  recent_requisitions: {
    ulid: string
    requisition_number: string
    department: string
    position: string
    status: string
    status_label: string
    status_color: string
    days_open: number
    created_at: string
  }[]
  upcoming_interviews: {
    id: number
    candidate_name: string
    position: string
    interviewer: string
    scheduled_at: string
    type: string
    round: number
  }[]
}

// ── Paginated response ───────────────────────────────────────────────────────

export interface Paginated<T> {
  data: T[]
  meta: PaginatedMeta
}
