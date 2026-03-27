import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { useEmployees, useTeamEmployees, useDepartments } from '@/hooks/useEmployees'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { DepartmentGuard, ActionButton } from '@/components/ui/guards'
import { ExportButton } from '@/components/ui/ExportButton'
import type { EmployeeFilters, EmploymentStatus, EmploymentType } from '@/types/hr'

const EMPLOYMENT_STATUSES: EmploymentStatus[] = [
  'draft',
  'active',
  'on_leave',
  'suspended',
  'resigned',
  'terminated',
]

const EMPLOYMENT_TYPES: EmploymentType[] = [
  'regular',
  'contractual',
  'project_based',
  'casual',
  'probationary',
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

  const { data: deptsData } = useDepartments()
  const departments = deptsData?.data ?? []

  // Calculate summary stats
  const employees = data?.data ?? []
  const activeCount = employees.filter((e) => e.employment_status === 'active').length
  const onLeaveCount = employees.filter((e) => e.employment_status === 'on_leave').length
  const totalPayroll = employees.reduce((sum, e) => sum + (e.basic_monthly_rate || 0), 0)

  const handleSearch = () => {
    setFilters((f) => ({ ...f, search: searchValue || undefined, page: 1 }))
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') handleSearch()
  }

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">Failed to load employees. Please try again.</div>
    )
  }

  return (
    <div>
      <PageHeader
        title="Employees"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={employees}
              columns={[
                { key: 'employee_code', label: 'Code' },
                { key: 'full_name', label: 'Name' },
                { key: 'department.name', label: 'Department' },
                { key: 'position.title', label: 'Position' },
                { key: 'employment_status', label: 'Status' },
                { key: 'employment_type', label: 'Type' },
                { key: 'date_hired', label: 'Date Hired' },
              ]}
              filename="employees"
            />
            <DepartmentGuard module="employees">
              <ActionButton
                label="+ Add Employee"
                permission="employees.create"
                module="employees"
                onClick={() => navigate('/hr/employees/new')}
                variant="primary"
              />
            </DepartmentGuard>
          </div>
        }
      />

      {/* Summary Stats */}
      {!isLoading && employees.length > 0 && (
        <div className="grid grid-cols-4 gap-4 mb-4">
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">
              Total Employees
            </p>
            <p className="text-2xl font-bold text-blue-700 mt-1">{employees.length}</p>
          </div>
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Active</p>
            <p className="text-2xl font-bold text-emerald-700 mt-1">{activeCount}</p>
          </div>
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">On Leave</p>
            <p className="text-2xl font-bold text-amber-700 mt-1">{onLeaveCount}</p>
          </div>
          <div className="bg-purple-50 border border-purple-200 rounded-xl p-4">
            <p className="text-xs font-medium text-purple-600 uppercase tracking-wide">
              Total Monthly Payroll
            </p>
            <p className="text-xl font-bold text-purple-700 font-mono mt-1">
              ₱{(totalPayroll / 100).toLocaleString(undefined, { minimumFractionDigits: 0 })}
            </p>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-4 flex flex-wrap gap-3">
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
            className="px-4 py-2 text-sm bg-neutral-100 hover:bg-neutral-200 rounded text-neutral-700 transition-colors"
          >
            Search
          </button>
        </div>

        {/* Employment Status */}
        <select
          value={filters.employment_status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              employment_status: (e.target.value as EmploymentStatus) || undefined,
              page: 1,
            }))
          }
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Statuses</option>
          {EMPLOYMENT_STATUSES.map((s) => (
            <option key={s} value={s}>
              {s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
            </option>
          ))}
        </select>

        {/* Department */}
        {!isTeamView && (
          <select
            value={filters.department_id ?? ''}
            onChange={(e) =>
              setFilters((f) => ({
                ...f,
                department_id: e.target.value ? Number(e.target.value) : undefined,
                page: 1,
              }))
            }
            className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
          >
            <option value="">All Departments</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        )}

        {/* Employment Type */}
        <select
          value={filters.employment_type ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              employment_type: (e.target.value as EmploymentType) || undefined,
              page: 1,
            }))
          }
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
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Code
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Name
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Department
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Position
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Type
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Grade
                </th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Monthly Rate
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Date Hired
                </th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(data?.data ?? []).length === 0 ? (
                <tr>
                  <td colSpan={10} className="px-3 py-10 text-center text-neutral-400 text-sm">
                    No employees found.
                  </td>
                </tr>
              ) : (
                (data?.data ?? []).map((emp) => (
                  <tr
                    key={emp.id}
                    onClick={() => navigate(`/hr/employees/${emp.ulid}`)}
                    className="hover:bg-neutral-50 even:bg-neutral-100 cursor-pointer transition-colors"
                  >
                    <td className="px-3 py-2 font-mono text-xs text-neutral-600">
                      {emp.employee_code}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-neutral-900">{emp.full_name}</span>
                        {(!emp.has_sss_no ||
                          !emp.has_tin ||
                          !emp.has_philhealth_no ||
                          !emp.has_pagibig_no) && (
                          <span
                            title={`Pending onboarding — missing government ID(s): ${[
                              !emp.has_sss_no && 'SSS',
                              !emp.has_tin && 'TIN',
                              !emp.has_philhealth_no && 'PhilHealth',
                              !emp.has_pagibig_no && 'Pag-IBIG',
                            ]
                              .filter(Boolean)
                              .join(
                                ', ',
                              )}. Employee is excluded from payroll runs until all IDs are recorded.`}
                          >
                            <AlertTriangle className="w-4 h-4 text-amber-500" />
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-3 py-2 text-neutral-600">
                      {emp.department?.name ?? <span className="text-neutral-300">—</span>}
                    </td>
                    <td className="px-3 py-2 text-neutral-600">
                      {emp.position?.title ?? <span className="text-neutral-300">—</span>}
                    </td>
                    <td className="px-3 py-2 text-neutral-600 capitalize">
                      {emp.employment_type?.replace('_', ' ') || '—'}
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge status={emp.employment_status}>
                        {emp.employment_status}
                      </StatusBadge>
                    </td>
                    <td className="px-3 py-2 text-neutral-600">{emp.salary_grade_code ?? '—'}</td>
                    <td className="px-3 py-2 text-right">
                      <span className="font-mono font-bold text-emerald-700">
                        ₱
                        {(emp.basic_monthly_rate / 100).toLocaleString(undefined, {
                          minimumFractionDigits: 2,
                        })}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-neutral-600">
                      {emp.date_hired
                        ? new Date(emp.date_hired).toLocaleDateString('en-PH', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                          })
                        : '—'}
                    </td>
                    <td className="px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1.5">
                        <DepartmentGuard module="employees">
                          {canEdit && (
                            <button
                              onClick={(e) => { e.stopPropagation(); navigate(`/hr/employees/${emp.ulid}/edit`) }}
                              className="px-2.5 py-1 text-xs text-neutral-700 bg-neutral-100 hover:bg-neutral-200 rounded transition-colors"
                            >
                              Edit
                            </button>
                          )}
                        </DepartmentGuard>
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
          <div className="px-4 py-3 border-t border-neutral-100 flex items-center justify-between text-sm text-neutral-600">
            <span>
              Page {data.meta.current_page} of {data.meta.last_page} &middot; {data.meta.total}{' '}
              total
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
