import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

// ============================================================================
// Types
// ============================================================================

export interface AgingBuckets {
  current: number
  bucket_31_60: number
  bucket_61_90: number
  bucket_91_120: number
  over_120: number
  grand_total: number
}

export interface AgingCustomerRow {
  customer_id: number
  customer_ulid: string | null
  customer_name: string
  current: number
  bucket_31_60: number
  bucket_61_90: number
  bucket_91_120: number
  over_120: number
  total_outstanding: number
}

export interface AgingDetailRow {
  invoice_id: number
  invoice_ulid: string | null
  invoice_number: string | null
  invoice_date: string
  due_date: string
  days_past_due: number
  bucket: string
  total_amount: number
  total_paid: number
  balance_due: number
}

export interface VarianceRow {
  cost_center_id: number
  cost_center_name: string
  cost_center_code: string
  account_id: number
  account_code: string
  account_name: string
  budgeted_centavos: number
  actual_centavos: number
  variance_centavos: number
  utilization_pct: number
  status: 'under_budget' | 'on_track' | 'warning' | 'critical' | 'over_budget'
}

export interface VarianceCostCenterRow {
  cost_center_id: number
  cost_center_name: string
  cost_center_code: string
  total_budgeted_centavos: number
  total_actual_centavos: number
  total_variance_centavos: number
  utilization_pct: number
  line_count: number
  over_budget_lines: number
}

export interface PipelineStage {
  status: string
  count: number
  total_value_centavos: number
}

export interface WinRate {
  total_decided: number
  won: number
  lost: number
  win_rate_pct: number
  avg_negotiation_rounds: number
}

export interface MonthlyRevenue {
  month: string
  order_count: number
  total_revenue_centavos: number
}

export interface QualityKpi {
  overall_pass_rate_pct: number
  open_ncrs: number
  inspections_this_month: number
  avg_defect_rate_pct: number
}

export interface MrpSummary {
  total_open_orders: number
  total_components_needed: number
  components_with_shortage: number
  components_fully_stocked: number
}

export interface HeadcountRow {
  department_id: number
  department_name: string
  headcount: number
  active: number
  on_leave: number
}

export interface TicketStats {
  total_open: number
  total_resolved: number
  avg_resolution_hours: number
  overdue_count: number
}

export interface ExecutiveDashboard {
  sales: {
    pipeline: PipelineStage[]
    win_rate: WinRate
    monthly_trend: MonthlyRevenue[]
    top_customers: { customer_id: number; customer_name: string; order_count: number; total_revenue_centavos: number }[]
  }
  ar_aging: AgingBuckets
  budget: VarianceCostCenterRow[]
  mrp_summary: MrpSummary
  quality_kpi: QualityKpi
  headcount: HeadcountRow[]
  tickets: TicketStats
}

export interface VendorScore {
  vendor_id: number
  vendor_name: string
  quality_score: number
  delivery_score: number
  price_score: number
  composite_score: number
  grade: string
  total_pos: number
  total_grs: number
  total_inspections: number
}

export interface AbcItem {
  item_id: number
  item_code: string
  item_name: string
  category: string
  annual_consumption_qty: number
  unit_cost: number
  annual_value: number
  cumulative_pct: number
  abc_class: 'A' | 'B' | 'C'
}

// ============================================================================
// Executive Dashboard
// ============================================================================

export function useExecutiveDashboard() {
  return useQuery({
    queryKey: ['dashboard', 'executive-analytics'],
    queryFn: async () => {
      const res = await api.get<{ data: ExecutiveDashboard }>('/dashboard/executive-analytics')
      return res.data.data
    },
    staleTime: 60_000,
  })
}

// ============================================================================
// AR Aging
// ============================================================================

export function useArAging(filters: { as_of?: string; customer_id?: number } = {}) {
  return useQuery({
    queryKey: ['ar', 'aging', filters],
    queryFn: async () => {
      const res = await api.get<{ data: AgingCustomerRow[]; totals: AgingBuckets; as_of: string }>(
        '/ar/reports/aging',
        { params: filters },
      )
      return res.data
    },
    staleTime: 30_000,
  })
}

export function useArAgingDetail(customerId: number | null, asOf?: string) {
  return useQuery({
    queryKey: ['ar', 'aging-detail', customerId, asOf],
    queryFn: async () => {
      const res = await api.get<{ data: AgingDetailRow[] }>(
        `/ar/reports/aging/${customerId}/detail`,
        { params: asOf ? { as_of: asOf } : {} },
      )
      return res.data.data
    },
    enabled: customerId !== null,
  })
}

// ============================================================================
// Budget Variance
// ============================================================================

export function useBudgetVariance(filters: { fiscal_year: number; cost_center_id?: number; department_id?: number }) {
  return useQuery({
    queryKey: ['budget', 'variance', filters],
    queryFn: async () => {
      const res = await api.get<{ data: VarianceRow[] }>('/budget/variance', { params: filters })
      return res.data.data
    },
    staleTime: 30_000,
  })
}

export function useBudgetVarianceByCostCenter(fiscalYear: number) {
  return useQuery({
    queryKey: ['budget', 'variance-by-cc', fiscalYear],
    queryFn: async () => {
      const res = await api.get<{ data: VarianceCostCenterRow[] }>(
        '/budget/variance/by-cost-center',
        { params: { fiscal_year: fiscalYear } },
      )
      return res.data.data
    },
    staleTime: 30_000,
  })
}

// ============================================================================
// Inventory ABC
// ============================================================================

export function useInventoryAbc(year?: number) {
  return useQuery({
    queryKey: ['inventory', 'abc', year],
    queryFn: async () => {
      const res = await api.get<{ data: AbcItem[] }>(
        '/inventory/analytics/abc',
        { params: year ? { year } : {} },
      )
      return res.data.data
    },
    staleTime: 60_000,
  })
}

// ============================================================================
// Vendor Scorecard
// ============================================================================

export function useVendorScores(year?: number) {
  return useQuery({
    queryKey: ['procurement', 'vendor-scores', year],
    queryFn: async () => {
      const res = await api.get<{ data: VendorScore[] }>(
        '/procurement/vendor-scores',
        { params: year ? { year } : {} },
      )
      return res.data.data
    },
    staleTime: 60_000,
  })
}

// ============================================================================
// Order Tracking
// ============================================================================

export interface OrderTrackingStage {
  stage: string
  label: string
  status: 'completed' | 'in_progress' | 'pending' | 'failed'
  date: string | null
  details: string | null
}

export interface OrderTracking {
  order_id: number
  order_number: string | null
  current_stage: string
  timeline: OrderTrackingStage[]
}

export function useOrderTracking(orderUlid: string | null) {
  return useQuery({
    queryKey: ['crm', 'order-tracking', orderUlid],
    queryFn: async () => {
      const res = await api.get<{ data: OrderTracking }>(`/crm/orders/${orderUlid}/tracking`)
      return res.data.data
    },
    enabled: orderUlid !== null,
  })
}
