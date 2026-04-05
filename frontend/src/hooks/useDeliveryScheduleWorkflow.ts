/**
 * Delivery Schedule Workflow Hooks
 *
 * Replaces useCombinedDeliverySchedules hooks with DS-based equivalents.
 * Keeps the same export names for backward compatibility with OrderReceiptPage.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import axios from 'axios'

const DS_API_BASE = '/production/delivery-schedules'

// Re-export as useCombinedDeliverySchedule for backward compat with OrderReceiptPage
export function useCombinedDeliverySchedule(ulid: string | null) {
  return useQuery({
    queryKey: ['delivery-schedules', ulid],
    queryFn: async () => {
      // Try DS endpoint first, fall back to CDS for old records
      try {
        const { data } = await api.get(`${DS_API_BASE}/${ulid}`)
        return data.data ?? data
      } catch (error) {
        const status = (error as { response?: { status?: number } })?.response?.status
        if (status !== 404) {
          throw error
        }
        // Fallback to CDS for backward compat with existing records
        const { data } = await api.get(`/production/combined-delivery-schedules/${ulid}`)
        return data.data ?? data
      }
    },
    enabled: !!ulid,
  })
}

export function useAcknowledgeReceipt(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      item_acknowledgments: Array<{
        item_id: number
        received_qty: number
        condition: string
        notes?: string
        photo_urls?: string[]
      }>
      general_notes?: string
    }) => {
      // Try DS endpoint first, fall back to CDS
      try {
        const { data } = await api.post(`${DS_API_BASE}/${ulid}/acknowledge`, payload)
        return data.data ?? data
      } catch (error) {
        if (!axios.isAxiosError(error) || error.response?.status !== 404) {
          throw error
        }
        const { data } = await api.post(`/production/combined-delivery-schedules/${ulid}/acknowledge`, payload)
        return data.data ?? data
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-schedules'] })
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules'] })
    },
  })
}

// ── New DS-native workflow hooks ────────────────────────────────────────────

export function useDeliverySchedules(filters: Record<string, unknown> = {}) {
  return useQuery({
    queryKey: ['delivery-schedules', filters],
    queryFn: async () => {
      const { data } = await api.get(DS_API_BASE, { params: filters })
      return data
    },
  })
}

export function useDeliverySchedule(ulid: string | null) {
  return useQuery({
    queryKey: ['delivery-schedules', ulid],
    queryFn: async () => {
      const { data } = await api.get(`${DS_API_BASE}/${ulid}`)
      return data.data ?? data
    },
    enabled: !!ulid,
  })
}

export function useDispatchSchedule(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { delivery_notes?: string }) => {
      const { data } = await api.post(`${DS_API_BASE}/${ulid}/dispatch`, payload)
      return data.data ?? data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-schedules'] })
    },
  })
}

export function useMarkScheduleDelivered(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { delivery_date: string }) => {
      const { data } = await api.post(`${DS_API_BASE}/${ulid}/delivered`, payload)
      return data.data ?? data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-schedules'] })
    },
  })
}

export function useNotifyMissingItems(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      missing_items: Array<{ item_id: number; reason: string }>
      expected_delivery_date?: string
      message?: string
    }) => {
      const { data } = await api.post(`${DS_API_BASE}/${ulid}/notify-missing`, payload)
      return data.data ?? data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['delivery-schedules'] })
    },
  })
}
