import { useState } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { useNavigate } from 'react-router-dom'
import { Plus, RefreshCw } from 'lucide-react'
import { usePayrollRuns } from '@/hooks/usePayroll'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import type { PayrollRunFilters, PayrollRunStatus } from '@/types/payroll'

const CURRENT_YEAR = new Date().getFullYear()
const YEARS = Array.from({ length: 5 }, (_, i) => CURRENT_YEAR - i)

const ALL_STATUSES: PayrollRunStatus[] = [
  'DRAFT', 'SCOPE_SET', 'PRE_RUN_CHECKED', 'PROCESSING', 'COMPUTED',
  'REVIEW', 'SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED', 'DISBURSED',
  'PUBLISHED', 'FAILED', 'RETURNED', 'REJECTED',
]

const ACCTG_STATUSES: PayrollRunStatus[] = ['SUBMITTED', 'HR_APPROVED', 'ACCTG_APPROVED', 'DISBURSED', 'PUBLISHED']

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

export default function PayrollRunListPage() {
  const navigate = useNavigate()
  const hasPermission = useAuthStore((s) => s.hasPermission)

  // Accounting Managers see only runs pending their action / already published.
  const isAcctgOnly =
    hasPermission('payroll.acctg_approve') &&
    !hasPermission('payroll.initiate') &&
    !hasPermission('payroll.hr_approve')

  const STATUSES = isAcctgOnly ? ACCTG_STATUSES : ALL_STATUSES

  const [filters, setFilters] = useState<PayrollRunFilters>({
    year: CURRENT_YEAR,
    per_page: 25,
  })

  const { data, isLoading, isError, refetch, isFetching } = usePayrollRuns(filters)

  if (isLoading) return <SkeletonLoader rows={8} />

  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
      <ExecutiveReadOnlyBanner />
        Failed to load payroll runs. Please try again.
      </div>
    )
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Payroll Runs</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {data?.meta?.total ?? 0} runs
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => void refetch()}
            disabled={isFetching}
            className="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors disabled:opacity-40"
            title="Refresh"
          >
            <RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          </button>
          {!isAcctgOnly && (
            <button
              onClick={() => navigate('/payroll/runs/new')}
              className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
            >
              <Plus className="h-4 w-4" />
              New Run
            </button>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        {/* Year */}
        <select
          value={filters.year ?? CURRENT_YEAR}
          onChange={(e) => setFilters((f) => ({
            ...f,
            year: Number(e.target.value),
            page: 1,
          }))}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
        >
          {YEARS.map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>

        {/* Status */}
        <select
          value={filters.status ?? ''}
          onChange={(e) => setFilters((f) => ({
            ...f,
            status: (e.target.value as PayrollRunStatus) || undefined,
            page: 1,
          }))}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
        >
          <option value="">All Statuses</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.charAt(0).toUpperCase() + s.slice(1)}
            </option>
          ))}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pay Date</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Employees</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Gross Pay</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Deductions</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Net Pay</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-3 py-10 text-center text-gray-400 text-sm">
                    No payroll runs found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((run) => (
                  <tr
                    key={run.id}
                    onClick={() => navigate(`/payroll/runs/${run.ulid}`)}
                    className="even:bg-slate-50 hover:bg-blue-50/60 cursor-pointer transition-colors"
                  >
                    <td className="px-3 py-2 font-mono text-xs text-gray-700 font-medium">
                      {run.reference_no}
                    </td>
                    <td className="px-3 py-2 text-gray-900">
                      <div className="font-medium">{run.pay_period_label}</div>
                      <div className="text-xs text-gray-400 mt-0.5">
                        {formatDate(run.cutoff_start)} – {formatDate(run.cutoff_end)}
                      </div>
                    </td>
                    <td className="px-3 py-2 text-gray-600">
                      {formatDate(run.pay_date)}
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge label={run.status} autoVariant />
                    </td>
                    <td className="px-4 py-3 text-right text-gray-700 tabular-nums">
                      {run.status === 'cancelled' ? <span className="text-gray-400">—</span> : run.total_employees}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {run.status === 'cancelled' || !(run.gross_pay_total > 0)
                        ? <span className="text-gray-400">—</span>
                        : <CurrencyAmount centavos={run.gross_pay_total} />
                      }
                    </td>
                    <td className="px-4 py-3 text-right">
                      {run.status === 'cancelled' || !(run.total_deductions > 0)
                        ? <span className="text-gray-400">—</span>
                        : <CurrencyAmount centavos={run.total_deductions} />
                      }
                    </td>
                    <td className="px-4 py-3 text-right">
                      {run.status === 'cancelled' || !(run.net_pay_total > 0)
                        ? <span className="text-gray-400">—</span>
                        : <CurrencyAmount centavos={run.net_pay_total} />
                      }
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {data?.meta && data.meta.last_page > 1 && (
          <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-600">
            <span>
              Page {data.meta.current_page} of {data.meta.last_page} &middot; {data.meta.total} total
            </span>
            <div className="flex gap-2">
              <button
                disabled={data.meta.current_page <= 1}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50 transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
