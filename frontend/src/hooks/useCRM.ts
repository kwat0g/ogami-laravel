import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { Ticket, TicketFilters, TicketMessage } from '@/types/crm'

// ── Queries ──────────────────────────────────────────────────────────────────

export function useTickets(filters: TicketFilters = {}) {
  return useQuery({
    queryKey: ['crm-tickets', filters],
    queryFn: async () => {
      const { data } = await api.get<{ data: Ticket[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/crm/tickets', { params: filters })
      return data
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
