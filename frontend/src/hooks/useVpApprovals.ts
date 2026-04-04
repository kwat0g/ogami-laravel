import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type { PurchaseRequest, Paginated } from '@/types/procurement'
import type { Loan } from '@/types/hr'
import type { MaterialRequisition } from '@/types/inventory'
import type { PayrollRun } from '@/types/payroll'

export interface VpPrFilters {
  status?: string
  search?: string
  urgency?: string
  page?: number
  per_page?: number
}

export interface VpLoanFilters {
  status?: string
  search?: string
  page?: number
  per_page?: number
}

export interface VpMrqFilters {
  status?: string
  search?: string
  page?: number
  per_page?: number
}

export interface VpPayrollFilters {
  status?: string
  search?: string
  page?: number
  per_page?: number
}

// ── Pending counts (always fixed to the approval-stage status) ────────────────

export function useVpPendingCounts(enabled: { pr?: boolean; loan?: boolean; mrq?: boolean; payroll?: boolean } = {}) {
  const pr = useQuery({
    queryKey: ['vp-approvals', 'purchase-requests', { status: 'budget_verified', page: 1, per_page: 1 }],
    queryFn: async () => {
      const res = await api.get<Paginated<PurchaseRequest>>('/procurement/purchase-requests', {
        params: { status: 'budget_verified', per_page: 1 },
      })
      return res.data.meta?.total ?? 0
    },
    enabled: enabled.pr ?? false,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
  const loan = useQuery({
    queryKey: ['vp-approvals', 'loans', { status: 'officer_reviewed', page: 1, per_page: 1 }],
    queryFn: async () => {
      const res = await api.get<Paginated<{ id: number }>>('/loans', {
        params: { status: 'officer_reviewed', per_page: 1 },
      })
      return res.data.meta?.total ?? 0
    },
    enabled: enabled.loan ?? false,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
  const mrq = useQuery({
    queryKey: ['vp-approvals', 'mrqs', { status: 'reviewed', page: 1, per_page: 1 }],
    queryFn: async () => {
      const res = await api.get<Paginated<{ id: number }>>('/inventory/requisitions', {
        params: { status: 'reviewed', per_page: 1 },
      })
      return res.data.meta?.total ?? 0
    },
    enabled: enabled.mrq ?? false,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
  const payroll = useQuery({
    queryKey: ['vp-approvals', 'payroll-runs', { status: 'ACCTG_APPROVED', page: 1, per_page: 1 }],
    queryFn: async () => {
      const res = await api.get<{ data: unknown[]; meta?: { total: number } }>('/payroll/runs', {
        params: { status: 'ACCTG_APPROVED', per_page: 1 },
      })
      return res.data.meta?.total ?? 0
    },
    enabled: enabled.payroll ?? false,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
  return {
    pr:      pr.data ?? 0,
    loan:    loan.data ?? 0,
    mrq:     mrq.data ?? 0,
    payroll: payroll.data ?? 0,
  }
}

// ── Purchase Requests ─────────────────────────────────────────────────────────

export function useVpPurchaseRequests(filters: VpPrFilters = {}, enabled = true) {
  const params = {
    status: filters.status !== undefined ? (filters.status || undefined) : 'budget_verified',
    search: filters.search || undefined,
    urgency: filters.urgency || undefined,
    page: filters.page ?? 1,
    per_page: filters.per_page ?? 25,
  }
  return useQuery({
    queryKey: ['vp-approvals', 'purchase-requests', params],
    queryFn: async () => {
      const res = await api.get<Paginated<PurchaseRequest>>(
        '/procurement/purchase-requests',
        { params },
      )
      return res.data
    },
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Loans ─────────────────────────────────────────────────────────────────────

export function useVpLoans(filters: VpLoanFilters = {}, enabled = true) {
  const params = {
    status: filters.status !== undefined ? (filters.status || undefined) : 'officer_reviewed',
    search: filters.search || undefined,
    page: filters.page ?? 1,
    per_page: filters.per_page ?? 25,
  }
  return useQuery({
    queryKey: ['vp-approvals', 'loans', params],
    queryFn: async () => {
      const res = await api.get<Paginated<Loan>>('/loans', { params })
      return res.data
    },
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Material Requisitions ─────────────────────────────────────────────────────

export function useVpMrqs(filters: VpMrqFilters = {}, enabled = true) {
  const params = {
    status: filters.status !== undefined ? (filters.status || undefined) : 'reviewed',
    search: filters.search || undefined,
    page: filters.page ?? 1,
    per_page: filters.per_page ?? 25,
  }
  return useQuery({
    queryKey: ['vp-approvals', 'mrqs', params],
    queryFn: async () => {
      const res = await api.get<Paginated<MaterialRequisition>>(
        '/inventory/requisitions',
        { params },
      )
      return res.data
    },
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}

// ── Payroll Runs ──────────────────────────────────────────────────────────────

export function useVpPayrollRuns(filters: VpPayrollFilters = {}, enabled = true) {
  const params = {
    status: filters.status !== undefined ? (filters.status || undefined) : 'ACCTG_APPROVED',
    search: filters.search || undefined,
    page: filters.page ?? 1,
    per_page: filters.per_page ?? 25,
  }
  return useQuery({
    queryKey: ['vp-approvals', 'payroll-runs', params],
    queryFn: async () => {
      const res = await api.get<{ data: PayrollRun[]; meta?: { current_page: number; last_page: number; total: number } }>(
        '/payroll/runs',
        { params },
      )
      return res.data
    },
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  })
}
