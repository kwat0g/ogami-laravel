import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { Ticket, TicketFilters, TicketMessage, Lead, LeadFilters, Opportunity, OpportunityFilters, PipelineSummary } from '@/types/crm'

// ── Queries ──────────────────────────────────────────────────────────────────

export function useTickets(filters: TicketFilters = {}) {
  return useQuery({
    queryKey: ['crm-tickets', filters],
    queryFn: async () => {
      // Backend returns a raw Laravel paginator (current_page / total at root level,
      // not nested under meta). Normalise to the standard { data, meta } shape.
      const { data } = await api.get<{
        data: Ticket[]
        current_page: number
        last_page: number
        per_page: number
        total: number
      }>('/crm/tickets', { params: filters })
      return {
        data: data.data,
        meta: {
          current_page: data.current_page,
          last_page: data.last_page,
          per_page: data.per_page,
          total: data.total,
        },
      }
    },
  })
}

export function useTicket(ulid: string) {
  return useQuery({
    queryKey: ['crm-ticket', ulid],
    queryFn: async () => {
      const { data } = await api.get<{ data: Ticket }>(`/crm/tickets/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

// ── Mutations ────────────────────────────────────────────────────────────────

export function useCreateTicket() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      subject: string
      description: string
      type: string
      priority?: string
      customer_id?: number | null
    }) => {
      const { data } = await api.post<{ data: Ticket }>('/crm/tickets', payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-tickets'] })
    },
  })
}

export function useReplyToTicket(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { body: string; is_internal?: boolean }) => {
      const { data } = await api.post<{ data: TicketMessage }>(`/crm/tickets/${ulid}/reply`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-ticket', ulid] })
    },
  })
}

export function useAssignTicket(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { assigned_to_id: number }) => {
      const { data } = await api.patch<{ data: Ticket }>(`/crm/tickets/${ulid}/assign`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-ticket', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-tickets'] })
    },
  })
}

export function useResolveTicket(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { resolution_note?: string }) => {
      const { data } = await api.patch<{ data: Ticket }>(`/crm/tickets/${ulid}/resolve`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-ticket', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-tickets'] })
    },
  })
}

export function useCloseTicket(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.patch<{ data: Ticket }>(`/crm/tickets/${ulid}/close`)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-ticket', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-tickets'] })
    },
  })
}

export function useReopenTicket(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { reason?: string }) => {
      const { data } = await api.patch<{ data: Ticket }>(`/crm/tickets/${ulid}/reopen`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-ticket', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-tickets'] })
    },
  })
}

// ── CRM Leads ──────────────────────────────────────────────────────────────

export function useLeads(filters: LeadFilters = {}) {
  return useQuery({
    queryKey: ['crm-leads', filters],
    queryFn: async () => {
      const { data } = await api.get<{
        data: Lead[]
        current_page: number
        last_page: number
        per_page: number
        total: number
      }>('/crm/leads', { params: filters })
      return {
        data: data.data,
        meta: { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total },
      }
    },
  })
}

export function useLead(ulid: string) {
  return useQuery({
    queryKey: ['crm-lead', ulid],
    queryFn: async () => {
      const { data } = await api.get<{ data: Lead }>(`/crm/leads/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateLead() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<Lead>) => {
      const { data } = await api.post<{ data: Lead }>('/crm/leads', payload)
      return data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['crm-leads'] }) },
  })
}

export function useUpdateLead(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<Lead>) => {
      const { data } = await api.put<{ data: Lead }>(`/crm/leads/${ulid}`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-lead', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-leads'] })
    },
  })
}

export function useConvertLead(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { create_opportunity?: boolean; opportunity_title?: string; expected_value_centavos?: number }) => {
      const { data } = await api.post(`/crm/leads/${ulid}/convert`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-lead', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-leads'] })
    },
  })
}

export function useDisqualifyLead(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { reason: string }) => {
      const { data } = await api.patch(`/crm/leads/${ulid}/disqualify`, payload)
      return data.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['crm-lead', ulid] })
      qc.invalidateQueries({ queryKey: ['crm-leads'] })
    },
  })
}

// ── CRM Opportunities ──────────────────────────────────────────────────────

export function useOpportunities(filters: OpportunityFilters = {}) {
  return useQuery({
    queryKey: ['crm-opportunities', filters],
    queryFn: async () => {
      const { data } = await api.get<{
        data: Opportunity[]
        current_page: number
        last_page: number
        per_page: number
        total: number
      }>('/crm/opportunities', { params: filters })
      return {
        data: data.data,
        meta: { current_page: data.current_page, last_page: data.last_page, per_page: data.per_page, total: data.total },
      }
    },
  })
}

export function useOpportunity(ulid: string) {
  return useQuery({
    queryKey: ['crm-opportunity', ulid],
    queryFn: async () => {
      const { data } = await api.get<{ data: Opportunity }>(`/crm/opportunities/${ulid}`)
      return data.data
    },
    enabled: !!ulid,
  })
}

export function useCreateOpportunity() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: Partial<Opportunity>) => {
      const { data } = await api.post<{ data: Opportunity }>('/crm/opportunities', payload)
      return data.data
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['crm-opportunities'] }) },
  })
}

export function usePipelineSummary() {
  return useQuery({
    queryKey: ['crm-pipeline'],
    queryFn: async () => {
      const { data } = await api.get<{ data: PipelineSummary[] }>('/crm/opportunities/pipeline')
      return data.data
    },
  })
}
