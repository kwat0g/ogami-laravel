import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export interface SupplementaryKpis {
  cash_position: { total_balance: number; account_count: number; accounts: Array<{ bank: string; account: string; balance: number }> }
  ap_aging: { overdue_count: number; overdue_total: number; current_count: number; current_total: number }
  inventory_health: { total_value: number; dead_stock_items: number; low_stock_items: number }
  payroll_trend: Array<{ month: string; total_gross: number; total_net: number; run_count: number }>
}

export function useSupplementaryKpis(enabled = false) {
  return useQuery({
    queryKey: ['dashboard-kpis-supplementary'],
    queryFn: async () => { const { data } = await api.get('/dashboard/kpis/supplementary'); return data.data as SupplementaryKpis },
  })
}

export function useBudgetForecast(fiscalYear: number) {
  return useQuery({
    queryKey: ['budget-forecast', fiscalYear],
    queryFn: async () => { const { data } = await api.get('/budget/forecast/year-end', { params: { fiscal_year: fiscalYear } }); return data.data },
    enabled: !!fiscalYear,
  })
}

export function useLeaveCalendar(departmentId: number, startDate: string, endDate: string) {
  return useQuery({
    queryKey: ['leave-calendar', departmentId, startDate, endDate],
    queryFn: async () => { const { data } = await api.get('/leave/calendar', { params: { department_id: departmentId, start_date: startDate, end_date: endDate } }); return data.data },
    enabled: !!departmentId && !!startDate && !!endDate,
  })
}

export function useLeaveOverlaps(departmentId: number, startDate: string, endDate: string, maxConcurrent?: number) {
  return useQuery({
    queryKey: ['leave-overlaps', departmentId, startDate, endDate, maxConcurrent],
    queryFn: async () => { const { data } = await api.get('/leave/calendar/overlaps', { params: { department_id: departmentId, start_date: startDate, end_date: endDate, max_concurrent: maxConcurrent } }); return data.data },
    enabled: !!departmentId && !!startDate && !!endDate,
  })
}
