import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type {
  Application,
  ApplicationListItem,
  Candidate,
  JobOfferDetail,
  JobPosting,
  JobPostingListItem,
  JobRequisition,
  Paginated,
  RecruitmentDashboard,
} from '@/types/recruitment'

const KEYS = {
  dashboard: ['recruitment', 'dashboard'] as const,
  requisitions: ['recruitment', 'requisitions'] as const,
  requisition: (ulid: string) => ['recruitment', 'requisitions', ulid] as const,
  postings: ['recruitment', 'postings'] as const,
  posting: (ulid: string) => ['recruitment', 'postings', ulid] as const,
  applications: ['recruitment', 'applications'] as const,
  application: (ulid: string) => ['recruitment', 'applications', ulid] as const,
  interviews: ['recruitment', 'interviews'] as const,
  offers: ['recruitment', 'offers'] as const,
  offer: (ulid: string) => ['recruitment', 'offers', ulid] as const,
  candidates: ['recruitment', 'candidates'] as const,
  reports: ['recruitment', 'reports'] as const,
  hirings: ['recruitment', 'hirings'] as const,
}

// ── Dashboard ────────────────────────────────────────────────────────────────

export function useRecruitmentDashboard() {
  return useQuery({
    queryKey: KEYS.dashboard,
    queryFn: async () => {
      const { data } = await api.get<{ data: RecruitmentDashboard }>('/recruitment/dashboard')
      return data.data
    },
  })
}

// ── Requisitions ─────────────────────────────────────────────────────────────

export function useRequisitions(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.requisitions, params],
    queryFn: async () => {
      const { data } = await api.get<Paginated<JobRequisition>>('/recruitment/requisitions', { params })
      return data
    },
  })
}

export function useRequisition(ulid: string) {
  return useQuery({
    queryKey: KEYS.requisition(ulid),
    queryFn: async () => {
      const { data } = await api.get<{ data: JobRequisition }>(`/recruitment/requisitions/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateRequisition() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/recruitment/requisitions', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.requisitions }),
  })
}

export function useUpdateRequisition(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.put(`/recruitment/requisitions/${ulid}`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.requisition(ulid) })
      qc.invalidateQueries({ queryKey: KEYS.requisitions })
    },
  })
}

export function useRequisitionAction(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ action, payload }: { action: string; payload?: Record<string, unknown> }) =>
      api.post(`/recruitment/requisitions/${ulid}/${action}`, payload ?? {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.requisition(ulid) })
      qc.invalidateQueries({ queryKey: KEYS.requisitions })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

// ── Job Postings ─────────────────────────────────────────────────────────────

export function usePostings(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.postings, params],
    queryFn: async () => {
      const { data } = await api.get<Paginated<JobPostingListItem>>('/recruitment/postings', { params })
      return data
    },
  })
}

export function usePosting(ulid: string) {
  return useQuery({
    queryKey: KEYS.posting(ulid),
    queryFn: async () => {
      const { data } = await api.get<{ data: JobPosting }>(`/recruitment/postings/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreatePosting() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/recruitment/postings', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.postings }),
  })
}

export function usePostingAction(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ action }: { action: string }) =>
      api.post(`/recruitment/postings/${ulid}/${action}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.posting(ulid) })
      qc.invalidateQueries({ queryKey: KEYS.postings })
    },
  })
}

// ── Applications ─────────────────────────────────────────────────────────────

export function useApplications(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.applications, params],
    queryFn: async () => {
      const { data } = await api.get<Paginated<ApplicationListItem>>('/recruitment/applications', { params })
      return data
    },
  })
}

export function useApplication(ulid: string) {
  return useQuery({
    queryKey: KEYS.application(ulid),
    queryFn: async () => {
      const { data } = await api.get<{ data: Application }>(`/recruitment/applications/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateApplication() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/recruitment/applications', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.applications }),
  })
}

export function useApplicationAction(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ action, payload }: { action: string; payload?: Record<string, unknown> }) =>
      api.post(`/recruitment/applications/${ulid}/${action}`, payload ?? {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.application(ulid) })
      qc.invalidateQueries({ queryKey: KEYS.applications })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

// ── Interviews ───────────────────────────────────────────────────────────────

export function useInterviews(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.interviews, params],
    queryFn: async () => {
      const { data } = await api.get('/recruitment/interviews', { params })
      return data
    },
  })
}

export function useScheduleInterview() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/recruitment/interviews', payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.interviews })
      qc.invalidateQueries({ queryKey: KEYS.applications })
    },
  })
}

export function useSubmitEvaluation(interviewId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      api.post(`/recruitment/interviews/${interviewId}/evaluation`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.interviews })
      qc.invalidateQueries({ queryKey: KEYS.applications })
    },
  })
}

// ── Offers ───────────────────────────────────────────────────────────────────

export function useOffers(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.offers, params],
    queryFn: async () => {
      const { data } = await api.get<Paginated<JobOfferDetail>>('/recruitment/offers', { params })
      return data
    },
  })
}

export function useOffer(ulid: string) {
  return useQuery({
    queryKey: KEYS.offer(ulid),
    queryFn: async () => {
      const { data } = await api.get<{ data: JobOfferDetail }>(`/recruitment/offers/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateOffer() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/recruitment/offers', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.offers }),
  })
}

export function useOfferAction(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ action, payload }: { action: string; payload?: Record<string, unknown> }) =>
      api.post(`/recruitment/offers/${ulid}/${action}`, payload ?? {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.offer(ulid) })
      qc.invalidateQueries({ queryKey: KEYS.offers })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

// ── Hiring ───────────────────────────────────────────────────────────────────

export function useHire(applicationUlid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      api.post(`/recruitment/hire/${applicationUlid}`, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.application(applicationUlid) })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

export function useVpApproveHiring() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ hiringUlid, notes }: { hiringUlid: string; notes?: string }) =>
      api.post(`/recruitment/hirings/${hiringUlid}/vp-approve`, { notes }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.applications })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

export function useVpRejectHiring() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ hiringUlid, reason }: { hiringUlid: string; reason: string }) =>
      api.post(`/recruitment/hirings/${hiringUlid}/vp-reject`, { reason }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.applications })
      qc.invalidateQueries({ queryKey: KEYS.dashboard })
    },
  })
}

// ── Pre-Employment ───────────────────────────────────────────────────────────

export function usePreEmployment(applicationUlid: string) {
  return useQuery({
    queryKey: ['recruitment', 'pre-employment', applicationUlid],
    queryFn: async () => {
      const { data } = await api.get(`/recruitment/pre-employment/${applicationUlid}`)
      return data.data
    },
    enabled: !!applicationUlid,
  })
}

export function useInitPreEmployment(applicationUlid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.post(`/recruitment/pre-employment/${applicationUlid}/init`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.application(applicationUlid) })
      qc.invalidateQueries({ queryKey: ['recruitment', 'pre-employment', applicationUlid] })
    },
  })
}

export function usePreEmploymentAction() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ requirementId, action, payload }: { requirementId: number; action: string; payload?: Record<string, unknown> }) =>
      api.post(`/recruitment/pre-employment/requirements/${requirementId}/${action}`, payload ?? {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment'] })
    },
  })
}

export function usePreEmploymentUpload() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ requirementId, file }: { requirementId: number; file: File }) => {
      const formData = new FormData()
      formData.append('document', file)
      return api.post(`/recruitment/pre-employment/requirements/${requirementId}/submit-document`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment'] })
    },
  })
}

export function useCompletePreEmployment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (checklistId: number) => api.post(`/recruitment/pre-employment/${checklistId}/complete`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment'] })
    },
  })
}

// ── Interview Actions ────────────────────────────────────────────────────────

export function useInterviewAction(interviewId: number | string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ action, payload }: { action: string; payload?: Record<string, unknown> }) =>
      api.post(`/recruitment/interviews/${interviewId}/${action}`, payload ?? {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.interviews })
      qc.invalidateQueries({ queryKey: ['recruitment', 'interviews', String(interviewId)] })
      qc.invalidateQueries({ queryKey: KEYS.applications })
    },
  })
}

// ── Candidates ───────────────────────────────────────────────────────────────

export function useCandidates(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.candidates, params],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Candidate>>('/recruitment/candidates', { params })
      return data
    },
  })
}

export function useCreateCandidate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => api.post('/recruitment/candidates', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.candidates }),
  })
}

// ── Reports ──────────────────────────────────────────────────────────────────

export function usePipelineReport(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.reports, 'pipeline', params],
    queryFn: async () => {
      const { data } = await api.get('/recruitment/reports/pipeline', { params })
      return data.data
    },
  })
}

export function useTimeToFillReport(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.reports, 'time-to-fill', params],
    queryFn: async () => {
      const { data } = await api.get('/recruitment/reports/time-to-fill', { params })
      return data.data
    },
  })
}

export function useSourceMixReport(params?: Record<string, string>) {
  return useQuery({
    queryKey: [...KEYS.reports, 'source-mix', params],
    queryFn: async () => {
      const { data } = await api.get('/recruitment/reports/source-mix', { params })
      return data.data
    },
  })
}
