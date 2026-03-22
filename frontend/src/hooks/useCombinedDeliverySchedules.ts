import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import type { CombinedDeliverySchedule, CombinedDeliveryScheduleFilters } from '@/types/production'

const API_BASE = '/production/combined-delivery-schedules'

export function useCombinedDeliverySchedules(filters: CombinedDeliveryScheduleFilters = {}) {
  return useQuery({
    queryKey: ['combined-delivery-schedules', filters],
    queryFn: async () => {
      const { data } = await api.get(API_BASE, { params: filters })
      return data
    },
  })
}

export function useCombinedDeliverySchedule(ulid: string | null) {
  return useQuery({
    queryKey: ['combined-delivery-schedules', ulid],
    queryFn: async () => {
      if (!ulid) return null
      const { data } = await api.get(`${API_BASE}/${ulid}`)
      return data.data as CombinedDeliverySchedule
    },
    enabled: !!ulid,
  })
}

export function useDispatchCombinedSchedule(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      vehicle_id?: number
      driver_name?: string
      delivery_notes?: string
    }) => {
      const { data } = await api.post(`${API_BASE}/${ulid}/dispatch`, payload)
      return data.data as CombinedDeliverySchedule
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules'] })
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules', ulid] })
    },
  })
}

export function useMarkDelivered(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      delivery_date: string
      received_by?: string
      delivery_receipt_number?: string
    }) => {
      const { data } = await api.post(`${API_BASE}/${ulid}/delivered`, payload)
      return data.data as CombinedDeliverySchedule
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules'] })
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules', ulid] })
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
      const { data } = await api.post(`${API_BASE}/${ulid}/notify-missing`, payload)
      return data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules'] })
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules', ulid] })
    },
  })
}

export function useAcknowledgeReceipt(ulid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: {
      item_acknowledgments: Array<{
        item_id: number
        received_qty: number
        condition: 'good' | 'damaged' | 'missing'
        notes?: string
      }>
      general_notes?: string
    }) => {
      const { data } = await api.post(`${API_BASE}/${ulid}/acknowledge`, payload)
      return data.data as CombinedDeliverySchedule
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules'] })
      qc.invalidateQueries({ queryKey: ['combined-delivery-schedules', ulid] })
    },
  })
}
