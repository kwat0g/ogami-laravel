import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { OvertimeRequest, OvertimeFilters, Paginated } from '@/types/hr'

// ── List ──────────────────────────────────────────────────────────────────────

export function useOvertimeRequests(filters: OvertimeFilters = {}) {
  return useQuery({
    queryKey: ['overtime-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<OvertimeRequest>>(
        '/attendance/overtime-requests',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Team List (department-scoped) ─────────────────────────────────────────────

export function useTeamOvertimeRequests(filters: OvertimeFilters = {}) {
  return useQuery({
    queryKey: ['team-overtime-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<OvertimeRequest>>(
        '/attendance/overtime-requests/team',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Single ────────────────────────────────────────────────────────────────────

export function useOvertimeRequest(id: number | null) {
  return useQuery({
    queryKey: ['overtime-requests', id],
    queryFn: async () => {
      const res = await api.get<{ data: OvertimeRequest }>(`/attendance/overtime-requests/${id}`)
      return res.data.data
    },
    enabled: id !== null,
  })
}

// ── Approve ───────────────────────────────────────────────────────────────────

export function useApproveOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({
      id,
      approved_minutes,
      remarks,
    }: {
      id: number
      approved_minutes: number
      remarks?: string
    }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/approve`,
        { approved_minutes, remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

// ── Reject ────────────────────────────────────────────────────────────────────

export function useRejectOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks?: string }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/reject`,
        { remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

// ── Cancel ────────────────────────────────────────────────────────────────────

export function useCancelOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/attendance/overtime-requests/${id}`)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

// ── Supervisor Endorse ────────────────────────────────────────────────────────

export function useHeadEndorseOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks?: string }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/head-endorse`,
        { remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['team-overtime-requests'] })
    },
  })
}

// ── Pending Executive List ────────────────────────────────────────────────────

export function usePendingExecutiveOvertimeRequests(filters: OvertimeFilters = {}, enabled = true) {
  return useQuery({
    queryKey: ['executive-overtime-requests', filters],
    queryFn: async () => {
      const res = await api.get<Paginated<OvertimeRequest>>(
        '/attendance/overtime-requests/pending-executive',
        { params: filters },
      )
      return res.data
    },
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Executive Approve ─────────────────────────────────────────────────────────

export function useExecutiveApproveOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({
      id,
      approved_minutes,
      remarks,
    }: {
      id: number
      approved_minutes: number
      remarks?: string
    }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/executive-approve`,
        { approved_minutes, remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['executive-overtime-requests'] })
    },
  })
}

// ── Executive Reject ──────────────────────────────────────────────────────────

export function useExecutiveRejectOvertimeRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, remarks }: { id: number; remarks: string }) => {
      const res = await api.patch<{ data: OvertimeRequest }>(
        `/attendance/overtime-requests/${id}/executive-reject`,
        { remarks },
      )
      return res.data.data
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['overtime-requests'] })
      void queryClient.invalidateQueries({ queryKey: ['executive-overtime-requests'] })
    },
  })
}
