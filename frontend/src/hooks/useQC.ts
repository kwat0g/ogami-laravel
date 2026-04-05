import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import type {
  CapaAction,
  CreateInspectionPayload,
  CreateNcrPayload,
  Inspection,
  InspectionTemplate,
  IssueCapaPayload,
  NonConformanceReport,
  RecordResultsPayload,
} from '@/types/qc'
import type { Paginated } from '@/types/shared'

const KEYS = {
  templates:   ['qc', 'templates'] as const,
  inspections: ['qc', 'inspections'] as const,
  ncrs:        ['qc', 'ncrs'] as const,
}

// ── Inspection Templates ────────────────────────────────────────────────────

type TemplateParams = { stage?: string; is_active?: boolean; per_page?: number; with_archived?: boolean }

export function useInspectionTemplates(params?: TemplateParams) {
  const hasPermission = useAuthStore((s) => s.hasPermission)
  const canViewTemplates = hasPermission(
    `${PERMISSIONS.qc.templates.view}|${PERMISSIONS.qc.templates.manage}`,
  )

  return useQuery({
    queryKey: [...KEYS.templates, params],
    queryFn: async () => {
      const res = await api.get<{ data: InspectionTemplate[]; meta: { current_page: number; last_page: number; total: number } }>(
        '/qc/templates', { params }
      )
      return res.data
    },
    enabled: canViewTemplates,
    placeholderData: keepPreviousData,
  })
}

export function useCreateInspectionTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<InspectionTemplate> & { items?: Array<{ criterion: string; method?: string; acceptable_range?: string; sort_order?: number }> }) =>
      api.post<{ data: InspectionTemplate }>('/qc/templates', payload).then((r) => r.data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.templates }),
  })
}

export function useDeleteInspectionTemplate() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ulid: string) => api.delete(`/qc/templates/${ulid}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.templates }),
  })
}

// ── Inspections ─────────────────────────────────────────────────────────────

type InspectionParams = { stage?: string; status?: string; item_master_id?: number; per_page?: number; page?: number; with_archived?: boolean }

export function useInspections(params?: InspectionParams) {
  return useQuery({
    queryKey: [...KEYS.inspections, params],
    queryFn: async () => {
      const res = await api.get<Paginated<Inspection>>('/qc/inspections', { params })
      return res.data
    },
    placeholderData: keepPreviousData,
  })
}

export function useInspection(ulid: string | null) {
  return useQuery({
    queryKey: [...KEYS.inspections, ulid],
    enabled: !!ulid,
    queryFn: async () => {
      const res = await api.get<{ data: Inspection }>(`/qc/inspections/${ulid}`)
      return res.data.data
    },
  })
}

export function useCreateInspection() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateInspectionPayload) =>
      api.post<{ data: Inspection }>('/qc/inspections', payload).then((r) => r.data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.inspections }),
  })
}

export function useRecordResults(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: RecordResultsPayload) =>
      api.patch<{ data: Inspection }>(`/qc/inspections/${ulid}/results`, payload).then((r) => r.data.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.inspections, ulid] })
      qc.invalidateQueries({ queryKey: KEYS.inspections })
      qc.invalidateQueries({ queryKey: ['production-orders'] })
      qc.invalidateQueries({ queryKey: ['production-order'] })
    },
  })
}

export function useDeleteInspection() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ulid: string) => api.delete(`/qc/inspections/${ulid}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.inspections }),
  })
}

export function useCancelResults(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (reason: string) =>
      api.patch<{ data: Inspection }>(`/qc/inspections/${ulid}/cancel-results`, { reason }).then((r) => r.data.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.inspections, ulid] })
      qc.invalidateQueries({ queryKey: KEYS.inspections })
      qc.invalidateQueries({ queryKey: ['production-orders'] })
      qc.invalidateQueries({ queryKey: ['production-order'] })
    },
  })
}

// ── NCRs ────────────────────────────────────────────────────────────────────

type NcrParams = { status?: string; severity?: string; per_page?: number; page?: number }

export function useNcrs(params?: NcrParams) {
  return useQuery({
    queryKey: [...KEYS.ncrs, params],
    queryFn: async () => {
      const res = await api.get<Paginated<NonConformanceReport>>('/qc/ncrs', { params })
      return res.data
    },
    placeholderData: keepPreviousData,
  })
}

export function useNcr(ulid: string | null) {
  return useQuery({
    queryKey: [...KEYS.ncrs, ulid],
    enabled: !!ulid,
    queryFn: async () => {
      const res = await api.get<{ data: NonConformanceReport }>(`/qc/ncrs/${ulid}`)
      return res.data.data
    },
  })
}

export function useCreateNcr() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateNcrPayload) =>
      api.post<{ data: NonConformanceReport }>('/qc/ncrs', payload).then((r) => r.data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.ncrs }),
  })
}

export function useIssueCapa(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: IssueCapaPayload) =>
      api.patch<{ data: CapaAction }>(`/qc/ncrs/${ulid}/capa`, payload).then((r) => r.data.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEYS.ncrs, ulid] }),
  })
}

export function useCloseNcr(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.patch(`/qc/ncrs/${ulid}/close`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.ncrs, ulid] })
      qc.invalidateQueries({ queryKey: KEYS.ncrs })
    },
  })
}

export function useCapaActions(params?: { status?: string; per_page?: number }) {
  return useQuery({
    queryKey: ['qc', 'capa', params],
    queryFn: () => api.get<Paginated<CapaAction>>('/qc/capa', { params }).then(r => r.data),
  })
}

export function useCompleteCapaAction() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (capaUlid: string) => api.patch(`/qc/capa/${capaUlid}/complete`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEYS.ncrs })
      qc.invalidateQueries({ queryKey: ['qc', 'capa'] })
    },
  })
}
