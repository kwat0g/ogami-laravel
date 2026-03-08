import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTeamEmployees } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import type { EmployeeFilters, EmploymentStatus, EmploymentType } from '@/types/hr'

const EMPLOYMENT_STATUSES: EmploymentStatus[] = [
  'draft', 'active', 'on_leave', 'suspended', 'resigned', 'terminated',
]

const EMPLOYMENT_TYPES: EmploymentType[] = [
  'regular', 'contractual', 'project_based', 'seasonal', 'probationary',
]

export default function TeamEmployeeListPage() {
  const navigate = useNavigate()
  const [filters, setFilters] = useState<EmployeeFilters>({ per_page: 25 })
  const [searchValue, setSearchValue] = useState('')

  const { data, isLoading, isError } = useTeamEmployees(filters)

  const handleSearch = () => {
    setFilters((f) => ({ ...f, search: searchValue || undefined, page: 1 }))
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') handleSearch()
  }

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="text-neutral-600 text-sm mt-4">
        Failed to load team members. Please try again.
      </div>
    )
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-lg font-semibold text-neutral-900">My Team</h1>
      </div>
      <p className="text-sm text-neutral-500 mb-4">
        {data?.meta?.total ?? 0} members in your department
        <span className="ml-2 text-xs text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded">
          Department Only
        </span>
      </p>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        {/* Search */}
        <div className="flex gap-2 flex-1 min-w-[200px]">
          <input
            type="text"
            placeholder="Search name or code…"
            value={searchValue}
            onChange={(e) => setSearchValue(e.target.value)}
            onKeyDown={handleKeyDown}
            className="flex-1 border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none"
          />
          <button
            onClick={handleSearch}
            className="px-4 py-2 text-sm bg-neutral-900 hover:bg-neutral-800 rounded text-white transition-colors"
          >
            Search
          </button>
        </div>

        {/* Employment Status */}
        <select
          value={filters.employment_status ?? ''}
          onChange={(e) => setFilters((f) => ({
            ...f,
            employment_status: (e.target.value as EmploymentStatus) || undefined,
            page: 1,
          }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Statuses</option>
          {EMPLOYMENT_STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
            </option>
          ))}
        </select>

        {/* Employment Type */}
        <select
          value={filters.employment_type ?? ''}
          onChange={(e) => setFilters((f) => ({
            ...f,
            employment_type: (e.target.value as EmploymentType) || undefined,
            page: 1,
          }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Types</option>
          {EMPLOYMENT_TYPES.map((t) => (
            <option key={t} value={t}>
              {t.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
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
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Code</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Name</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Type</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Status</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Grade</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">Monthly Rate</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Date Hired</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-3 py-10 text-center text-neutral-400 text-sm">
                    No team members found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((emp) => (
                  <tr
                    key={emp.id}
                    onClick={() => navigate(`/team/employees/${emp.ulid}`)}
                    className="hover:bg-neutral-50 even:bg-neutral-100 cursor-pointer transition-colors"
                  >
                    <td className="px-3 py-2 font-mono text-xs text-neutral-600">
                      {emp.employee_code}
                    </td>
                    <td className="px-3 py-2 font-medium text-neutral-900">
                      {emp.full_name}
                    </td>
                    <td className="px-3 py-2 text-neutral-600 capitalize">
                      {emp.employment_type.replace('_', ' ')}
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge label={emp.employment_status} autoVariant />
                    </td>
                    <td className="px-3 py-2 text-neutral-600">
                      {emp.salary_grade_code ?? '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <CurrencyAmount centavos={emp.basic_monthly_rate} />
                    </td>
                    <td className="px-3 py-2 text-neutral-600">
                      {emp.date_hired
                        ? new Date(emp.date_hired).toLocaleDateString('en-PH', {
                            year: 'numeric', month: 'short', day: 'numeric',
                          })
                        : '—'}
                    </td>
                    <td className="px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                      <button
                        onClick={() => navigate(`/team/employees/${emp.ulid}`)}
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
                className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
                className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 hover:bg-neutral-50 transition-colors"
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
