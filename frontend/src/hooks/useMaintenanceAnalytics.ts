import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export interface EquipmentMetrics {
  equipment_id: number; equipment_code: string; equipment_name: string
  mtbf_hours: number; mttr_hours: number; total_failures: number
  total_repair_hours: number; availability_pct: number
  period_from: string; period_to: string
}

export interface EquipmentCost {
  equipment_id: number; equipment_code: string; equipment_name: string
  labor_cost_centavos: number; parts_cost_centavos: number
  total_cost_centavos: number; work_order_count: number
}

export function useEquipmentMetrics(equipmentId: number, fromDate?: string, toDate?: string) {
  return useQuery({
    queryKey: ['maintenance-metrics', equipmentId, fromDate, toDate],
    queryFn: async () => {
      const { data } = await api.get(`/maintenance/analytics/equipment/${equipmentId}`, { params: { from_date: fromDate, to_date: toDate } })
      return data.data as EquipmentMetrics
    },
    enabled: !!equipmentId,
  })
}

export function useAllEquipmentMetrics(fromDate?: string, toDate?: string) {
  return useQuery({
    queryKey: ['maintenance-metrics-all', fromDate, toDate],
    queryFn: async () => {
      const { data } = await api.get('/maintenance/analytics/all', { params: { from_date: fromDate, to_date: toDate } })
      return data.data as EquipmentMetrics[]
    },
  })
}

export function useEquipmentCosts() {
  return useQuery({
    queryKey: ['maintenance-costs'],
    queryFn: async () => {
      const { data } = await api.get('/maintenance/analytics/cost-per-equipment')
      return data.data as EquipmentCost[]
    },
  })
}
