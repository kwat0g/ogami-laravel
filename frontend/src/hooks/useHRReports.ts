import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

// ── Headcount ────────────────────────────────────────────────────────────────

export interface HeadcountRow {
  department_id: number
  department_code: string
  department_name: string
  total: number
  active: number
  on_leave: number
  separated: number
}

export function useHeadcountReport() {
  return useQuery({
    queryKey: ['hr-reports', 'headcount'],
    queryFn: async () => {
      const res = await api.get<{ data: HeadcountRow[] }>('/hr/reports/headcount')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

// ── Turnover ─────────────────────────────────────────────────────────────────

export interface TurnoverMonth {
  month: string
  hires: number
  terminations: number
  net: number
}

export function useTurnoverReport() {
  return useQuery({
    queryKey: ['hr-reports', 'turnover'],
    queryFn: async () => {
      const res = await api.get<{ data: TurnoverMonth[]; turnover_rate_ytd: number }>('/hr/reports/turnover')
      return res.data
    },
    staleTime: 60_000,
  })
}

// ── Birthdays ────────────────────────────────────────────────────────────────

export interface BirthdayRow {
  id: number
  employee_code: string
  full_name: string
  birth_date: string
  department: string
  days_until: number
  age: number
}

export function useBirthdayReport(days = 30) {
  return useQuery({
    queryKey: ['hr-reports', 'birthdays', days],
    queryFn: async () => {
      const res = await api.get<{ data: BirthdayRow[] }>('/hr/reports/birthdays', { params: { days } })
      return res.data.data
    },
    staleTime: 120_000,
  })
}
