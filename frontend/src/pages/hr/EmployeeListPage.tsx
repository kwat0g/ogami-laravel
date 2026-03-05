import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { useEmployees, useTeamEmployees } from '@/hooks/useEmployees'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import CurrencyAmount from '@/components/ui/CurrencyAmount'
import type { EmployeeFilters, EmploymentStatus, EmploymentType } from '@/types/hr'

const EMPLOYMENT_STATUSES: EmploymentStatus[] = [
  'draft', 'active', 'on_leave', 'suspended', 'resigned', 'terminated',
]

const EMPLOYMENT_TYPES: EmploymentType[] = [
  'regular', 'contractual', 'project_based', 'casual', 'probationary',
]

interface EmployeeListPageProps {
  view?: 'team' | 'all'
}

export default function EmployeeListPage({ view = 'all' }: EmployeeListPageProps) {
  const navigate = useNavigate()
  const { hasPermission } = useAuthStore()
  const canEdit = hasPermission('employees.update')
  const [filters, setFilters] = useState<EmployeeFilters>({ per_page: 25 })
  const [searchValue, setSearchValue] = useState('')

  const isTeamView = view === 'team'
  const employeesQuery = useEmployees(filters)
  const teamEmployeesQuery = useTeamEmployees(filters)
  const { data, isLoading, isError } = isTeamView ? teamEmployeesQuery : employeesQuery

  const handleSearch = () => {
    setFilters((f) => ({ ...f, search: searchValue || undefined, page: 1 }))
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') handleSearch()
  }

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Failed to load employees. Please try again.
      </div>
    )
  }

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isTeamView ? 'My Team' : 'All Employees'}
          </h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {data?.meta?.total ?? 0} records
            {isTeamView && (
              <span className="ml-2 text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                Department Only
              </span>
            )}
          </p>
        </div>
        <Link
          to="/hr/employees/new"
          className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
        >
          + Add Employee
        </Link>
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        {/* Search */}
        <div className="flex gap-2 flex-1 min-w-[200px]">
          <input
            type="text"
            placeholder="Search name or code…"
            value={searchValue}
            onChange={(e) => setSearchValue(e.target.value)}
            onKeyDown={handleKeyDown}
            className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
          />
          <button
            onClick={handleSearch}
            className="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 transition-colors"
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
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
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
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
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
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Grade</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Monthly Rate</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date Hired</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-3 py-10 text-center text-gray-400 text-sm">
                    No employees found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((emp) => (
                  <tr
                    key={emp.id}
                    onClick={() => navigate(`/hr/employees/${emp.ulid}`)}
                    className="even:bg-slate-50 hover:bg-blue-50/60 cursor-pointer transition-colors"
                  >
                    <td className="px-3 py-2 font-mono text-xs text-gray-600">
                      {emp.employee_code}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-900">{emp.full_name}</span>
                        {(!emp.has_sss_no || !emp.has_tin || !emp.has_philhealth_no || !emp.has_pagibig_no) && (
                          <span
                            title={`Employee is inactive due to missing government ID(s): ${[
                              !emp.has_sss_no && 'SSS',
                              !emp.has_tin && 'TIN',
                              !emp.has_philhealth_no && 'PhilHealth',
                              !emp.has_pagibig_no && 'Pag-IBIG',
                            ].filter(Boolean).join(', ')}`}
                          >
                            <AlertTriangle className="w-4 h-4 text-amber-500" />
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-3 py-2 text-gray-600 capitalize">
                      {emp.employment_type.replace('_', ' ')}
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge label={emp.employment_status} autoVariant />
                    </td>
                    <td className="px-3 py-2 text-gray-600">
                      {emp.salary_grade_code ?? '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <CurrencyAmount centavos={emp.basic_monthly_rate} />
                    </td>
                    <td className="px-3 py-2 text-gray-600">
                      {emp.date_hired
                        ? new Date(emp.date_hired).toLocaleDateString('en-PH', {
                            year: 'numeric', month: 'short', day: 'numeric',
                          })
                        : '—'}
                    </td>
                    <td className="px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => navigate(`/hr/employees/${emp.ulid}`)}
                          className="px-2.5 py-1 text-xs text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
                        >
                          View
                        </button>
                        {canEdit && (
                          <button
                            onClick={() => navigate(`/hr/employees/${emp.ulid}/edit`)}
                            className="px-2.5 py-1 text-xs text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors"
                          >
                            Edit
                          </button>
                        )}
                      </div>
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
