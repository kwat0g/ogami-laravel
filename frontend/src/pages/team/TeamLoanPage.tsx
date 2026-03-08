import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTeamLoans } from '@/hooks/useLoans'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import type { LoanFilters, LoanStatus } from '@/types/hr'

const LOAN_STATUSES: LoanStatus[] = [
  'draft', 'pending', 'approved', 'rejected', 'disbursed', 'repaying', 'closed', 'cancelled'
]

export default function TeamLoanPage() {
  const navigate = useNavigate()
  const [filters, setFilters] = useState<LoanFilters>({ per_page: 25 })

  const { data, isLoading, isError } = useTeamLoans(filters)

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="text-neutral-600 text-sm mt-4">
        Failed to load team loans. Please try again.
      </div>
    )
  }

  const rows = data?.data ?? []

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">Team Loans</h1>
      </div>
      <p className="text-sm text-neutral-500 mb-4">
        {data?.meta?.total ?? 0} records
        <span className="ml-2 text-xs text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded">
          Department Only
        </span>
      </p>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              status: (e.target.value as LoanStatus) || undefined,
              page: 1,
            }))
          }
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none focus:border-neutral-400"
        >
          <option value="">All Statuses</option>
          {LOAN_STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
            </option>
          ))}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Employee</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Type</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">Amount</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Status</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">Balance</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-3 py-10 text-center text-neutral-400 text-sm">
                    No loan records found.
                  </td>
                </tr>
              ) : (
                rows.map((loan) => (
                  <tr
                    key={loan.id}
                    onClick={() => navigate(`/team/loans/${loan.ulid}`)}
                    className="hover:bg-neutral-50 even:bg-neutral-100 cursor-pointer transition-colors"
                  >
                    <td className="px-3 py-2 font-medium text-neutral-900">
                      {loan.employee?.full_name ?? `Employee #${loan.employee_id}`}
                    </td>
                    <td className="px-3 py-2 text-neutral-600">
                      {loan.loan_type?.name ?? '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <CurrencyAmount centavos={loan.principal_amount} />
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge status={loan.status}>{loan.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                    </td>
                    <td className="px-3 py-2 text-right">
                      <CurrencyAmount centavos={loan.outstanding_balance} />
                    </td>
                    <td className="px-3 py-2" onClick={(e) => e.stopPropagation()}>
                      <button
                        onClick={() => navigate(`/team/loans/${loan.ulid}`)}
                        className="px-2.5 py-1 text-xs text-neutral-600 bg-neutral-100 hover:bg-neutral-200 rounded transition-colors"
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {data?.meta && data.meta.last_page > 1 && (
          <div className="px-4 py-3 border-t border-neutral-100 flex items-center justify-between text-sm text-neutral-600">
            <span>
              Page {data.meta.current_page} of {data.meta.last_page} &middot; {data.meta.total} total
            </span>
            <div className="flex gap-2">
              <button
                disabled={data.meta.current_page <= 1}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
                className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
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
