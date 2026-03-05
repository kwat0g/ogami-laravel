import { useState } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useLoans } from '@/hooks/useLoans'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import { useAuthStore } from '@/stores/authStore'
import type { LoanFilters, LoanStatus } from '@/types/hr'

export default function LoanListPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { hasPermission } = useAuthStore()
  const canCreate = hasPermission('loans.create') || hasPermission('loans.apply')
  const isAccountingContext = location.pathname.startsWith('/accounting')
  const loanBasePath = isAccountingContext ? '/accounting/loans' : '/hr/loans'

  const ACCOUNTING_STATUSES = 'approved,ready_for_disbursement,active'
  const [filters, setFilters] = useState<LoanFilters>({
    per_page: 25,
    // Accounting view defaults to showing the post-HR-approval workflow stages
    status_in: isAccountingContext ? ACCOUNTING_STATUSES : undefined,
  })

  const { data, isLoading, isError } = useLoans(filters)

  if (isLoading) return <SkeletonLoader rows={10} />
  if (isError)   return <div className="text-red-600 text-sm mt-4">Failed to load loans.</div>

  const rows = data?.data ?? []

  return (
    <div>
      <ExecutiveReadOnlyBanner />
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Loans</h1>
          <p className="text-sm text-gray-500 mt-0.5">{data?.meta?.total ?? 0} records</p>
        </div>
        {loanBasePath === '/hr/loans' && canCreate && (
          <Link
            to="/hr/loans/new"
            className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            + New Loan
          </Link>
        )}
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={filters.status ?? ''}
          onChange={(e) => {
            const val = e.target.value as LoanStatus | ''
            if (isAccountingContext) {
              // Switch between single-status filter or default multi-status view
              setFilters((f) => ({
                ...f,
                status: val || undefined,
                status_in: val ? undefined : ACCOUNTING_STATUSES,
                page: 1,
              }))
            } else {
              setFilters((f) => ({ ...f, status: val || undefined, page: 1 }))
            }
          }}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
        >
          {isAccountingContext ? (
            <>
              <option value="">All Relevant</option>
              <option value="approved">Approved (awaiting accounting)</option>
              <option value="ready_for_disbursement">Ready for Disbursement</option>
              <option value="active">Active (disbursed)</option>
            </>
          ) : (
            <>
              <option value="">All Statuses</option>
              {(['pending', 'supervisor_approved', 'approved', 'ready_for_disbursement', 'active', 'fully_paid', 'written_off', 'rejected', 'cancelled'] as LoanStatus[]).map((s) => (
                <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
              ))}
            </>
          )}
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              {['Employee', 'Type', 'Principal', 'Term', 'Monthly Payment', 'Balance', 'Status', 'Actions'].map((h) => (
                <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {rows.length === 0 && (
              <tr><td colSpan={8} className="px-3 py-8 text-center text-gray-400">No loans found.</td></tr>
            )}
            {rows.map((row) => (
              <tr key={row.id} className="even:bg-slate-50 hover:bg-blue-50/60 cursor-pointer transition-colors" onClick={() => navigate(`${loanBasePath}/${row.ulid}`)}>
                <td className="px-3 py-2 font-medium text-gray-900">{row.employee?.full_name ?? `#${row.employee_id}`}</td>
                <td className="px-3 py-2 text-gray-600">{row.loan_type?.name ?? '—'}</td>
                <td className="px-3 py-2 text-gray-700"><CurrencyAmount centavos={row.principal_centavos} /></td>
                <td className="px-3 py-2 text-gray-600">{row.term_months} mos</td>
                <td className="px-3 py-2 text-gray-700"><CurrencyAmount centavos={row.monthly_amortization_centavos} /></td>
                <td className="px-3 py-2 text-gray-700"><CurrencyAmount centavos={row.outstanding_balance_centavos} /></td>
                <td className="px-3 py-2"><StatusBadge label={row.status} /></td>
                <td className="px-3 py-2" onClick={(e) => e.stopPropagation()}>
                  <button
                    onClick={() => navigate(`${loanBasePath}/${row.ulid}`)}
                    className="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1 rounded"
                  >
                    View
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {(data?.meta?.last_page ?? 1) > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
          <span>Page {data?.meta?.current_page} of {data?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={(filters.page ?? 1) <= 1} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
              className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40">Prev</button>
            <button disabled={(filters.page ?? 1) >= (data?.meta?.last_page ?? 1)} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
              className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40">Next</button>
          </div>
        </div>
      )}


    </div>
  )
}
