import { useState, useRef, useEffect } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import { useLeaveRequests } from '@/hooks/useLeave'
import { useDepartments } from '@/hooks/useEmployees'
import { useDebounce } from '@/hooks/useDebounce'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { PageHeader } from '@/components/ui/PageHeader'
import { ExportButton } from '@/components/ui/ExportButton'
import type { LeaveFilters } from '@/types/hr'
import { Scale, X, ChevronDown, ChevronUp, Search } from 'lucide-react'

const YEARS = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

export default function LeaveListPage() {
  const { hasPermission } = useAuthStore()
  const canFileOnBehalf = hasPermission('leaves.file_on_behalf')
  const [filters, setFilters] = useState<LeaveFilters>({ per_page: 15, page: 1 })
  const [expandedRow, setExpandedRow] = useState<number | null>(null)
  
  // Search with debounce
  const [searchValue, setSearchValue] = useState('')
  const debouncedSearch = useDebounce(searchValue, 400)
  const searchInputRef = useRef<HTMLInputElement>(null)
  const [isSearchFocused, setIsSearchFocused] = useState(false)

  // Update filters when debounced search changes
  useEffect(() => {
    setFilters((f) => ({ ...f, search: debouncedSearch || undefined, page: 1 }))
  }, [debouncedSearch])

  const { data, isLoading, isFetching, isError } = useLeaveRequests(filters)
  const { data: departmentsData, isLoading: deptLoading } = useDepartments()

  // Refocus search input after data loads
  useEffect(() => {
    if (!isFetching && isSearchFocused && searchInputRef.current) {
      const cursorPos = searchInputRef.current.selectionStart
      searchInputRef.current.focus()
      if (cursorPos !== null) {
        searchInputRef.current.setSelectionRange(cursorPos, cursorPos)
      }
    }
  }, [isFetching, isSearchFocused])

  if (isLoading || deptLoading) return <SkeletonLoader rows={10} />
  if (isError) return <div className="text-red-600 text-sm mt-4">Failed to load leave requests.</div>

  const rows = data?.data ?? []
  const meta = data?.meta

  // Format date for display
  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  }

  // Get status color for timeline
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
        return 'bg-green-500'
      case 'rejected':
        return 'bg-red-500'
      case 'cancelled':
        return 'bg-neutral-400'
      default:
        return 'bg-amber-500'
    }
  }

  return (
    <div>
      <ExecutiveReadOnlyBanner />
      
      <PageHeader
        title="Leave Requests"
        actions={
          <div className="flex items-center gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'employee.full_name', label: 'Employee' },
                { key: 'leave_type.name', label: 'Leave Type' },
                { key: 'date_from', label: 'From' },
                { key: 'date_to', label: 'To' },
                { key: 'total_days', label: 'Days' },
                { key: 'status', label: 'Status' },
              ]}
              filename="leave-requests"
            />
            <Link
              to="/hr/leave/calendar"
              className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-3 py-2 rounded transition-colors"
            >
              Calendar
            </Link>
            <Link
              to="/hr/leave/balances"
              className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-4 py-2 rounded transition-colors"
            >
              <Scale className="h-4 w-4" />
              Leave Balances
            </Link>
            {canFileOnBehalf && (
              <Link
                to="/hr/leave/new"
                className="bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                + File Leave
              </Link>
            )}
          </div>
        }
      />

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-4 flex flex-wrap gap-3">
        {/* Search */}
        <div className="flex gap-2 flex-1 min-w-[200px] max-w-md relative">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
            <input
              ref={searchInputRef}
              type="text"
              placeholder="Search employee name..."
              value={searchValue}
              onChange={(e) => {
                setSearchValue(e.target.value)
                setFilters((f) => ({ ...f, page: 1 }))
              }}
              onFocus={() => setIsSearchFocused(true)}
              onBlur={() => setIsSearchFocused(false)}
              className="w-full border border-neutral-300 rounded pl-9 pr-9 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none"
            />
            {searchValue && (
              <button
                onClick={() => {
                  setSearchValue('')
                  setFilters((f) => ({ ...f, search: undefined, page: 1 }))
                  searchInputRef.current?.focus()
                }}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
              >
                <X className="h-4 w-4" />
              </button>
            )}
            {isFetching && (
              <div className="absolute right-8 top-1/2 -translate-y-1/2">
                <div className="h-4 w-4 border-2 border-neutral-500 border-t-transparent rounded-full animate-spin" />
              </div>
            )}
          </div>
        </div>

        {/* Department Filter */}
        <select
          value={filters.department_id ?? ''}
          onChange={(e) => setFilters((f) => ({ 
            ...f, 
            department_id: e.target.value ? Number(e.target.value) : undefined,
            page: 1 
          }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none min-w-[180px]"
        >
          <option value="">All Departments</option>
          {(departmentsData?.data ?? []).map((dept) => (
            <option key={dept.id} value={dept.id}>{dept.name}</option>
          ))}
        </select>

        <select
          value={filters.status ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value as LeaveFilters['status'] || undefined, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Statuses</option>
          {['pending', 'approved', 'rejected', 'cancelled'].map((s) => (
            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
          ))}
        </select>

        <select
          value={filters.year ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, year: e.target.value ? Number(e.target.value) : undefined, page: 1 }))}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Years</option>
          {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
      </div>

      {/* Active Filters */}
      {(filters.department_id || filters.search || filters.status || filters.year) && (
        <div className="mb-4 flex items-center gap-2 flex-wrap">
          <span className="text-sm text-neutral-500">Active filters:</span>
          {filters.department_id && (
            <span className="text-xs bg-neutral-100 text-neutral-700 px-2 py-1 rounded">
              {departmentsData?.data.find(d => d.id === filters.department_id)?.name}
            </span>
          )}
          {filters.search && (
            <span className="text-xs bg-neutral-100 text-neutral-700 px-2 py-1 rounded">
              Search: {filters.search}
            </span>
          )}
          {filters.status && (
            <span className="text-xs bg-neutral-100 text-neutral-700 px-2 py-1 rounded capitalize">
              {filters.status}
            </span>
          )}
          {filters.year && (
            <span className="text-xs bg-neutral-100 text-neutral-700 px-2 py-1 rounded">
              Year: {filters.year}
            </span>
          )}
          <button
            onClick={() => {
              setSearchValue('')
              setFilters({ per_page: 15, page: 1 })
            }}
            className="text-xs text-neutral-500 hover:text-red-600 underline"
          >
            Clear all
          </button>
        </div>
      )}

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden relative">
        {/* Loading overlay for search/filter */}
        {isFetching && !isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <div className="bg-white px-4 py-2 rounded-lg border border-neutral-200 flex items-center gap-2">
              <div className="h-4 w-4 border-2 border-neutral-500 border-t-transparent rounded-full animate-spin" />
              <span className="text-sm text-neutral-600">Updating...</span>
            </div>
          </div>
        )}
        
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider w-10"></th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Employee</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Leave Type</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Period</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Days</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Status</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Approved By</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Filed On</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {rows.length === 0 && (
              <tr><td colSpan={8} className="px-3 py-8 text-center text-neutral-400">No leave requests found.</td></tr>
            )}
            {rows.map((row) => (
              <>
                <tr 
                  key={row.id} 
                  className="hover:bg-neutral-50 even:bg-neutral-100 transition-colors cursor-pointer"
                  onClick={() => setExpandedRow(expandedRow === row.id ? null : row.id)}
                >
                  <td className="px-3 py-2">
                    <button className="text-neutral-400 hover:text-neutral-600">
                      {expandedRow === row.id ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                    </button>
                  </td>
                  <td className="px-3 py-2 font-medium text-neutral-900">{row.employee?.full_name ?? `#${row.employee_id}`}</td>
                  <td className="px-3 py-2 text-neutral-600">{row.leave_type?.name ?? '—'}</td>
                  <td className="px-3 py-2 text-neutral-600">
                    {row.date_from} to {row.date_to}
                  </td>
                  <td className="px-3 py-2 text-neutral-600">{row.total_days}</td>
                  <td className="px-3 py-2"><StatusBadge status={row.status}>{row.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge></td>
                  <td className="px-3 py-2 text-neutral-600">
                    {row.reviewed_by ? (
                      <span className="text-green-700 font-medium">User #{row.reviewed_by}</span>
                    ) : (
                      <span className="text-neutral-400">Pending</span>
                    )}
                  </td>
                  <td className="px-3 py-2 text-neutral-500">{formatDate(row.created_at)}</td>
                </tr>
                
                {/* Expanded Details */}
                {expandedRow === row.id && (
                  <tr className="bg-neutral-50">
                    <td colSpan={8} className="px-4 py-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Request Details */}
                        <div className="bg-white rounded p-4 border border-neutral-200">
                          <h4 className="font-medium text-neutral-900 mb-3">Request Details</h4>
                          <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                              <span className="text-neutral-500">Reason:</span>
                              <span className="text-neutral-900 max-w-xs text-right">{row.reason || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-neutral-500">Half Day:</span>
                              <span className="text-neutral-900">{row.is_half_day ? (row.half_day_period === 'AM' ? 'Morning' : 'Afternoon') : 'No'}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-neutral-500">Filed By:</span>
                              <span className="text-neutral-900">User #{row.submitted_by}</span>
                            </div>
                          </div>
                        </div>

                        {/* Approval Timeline */}
                        <div className="bg-white rounded p-4 border border-neutral-200">
                          <h4 className="font-medium text-neutral-900 mb-3">Approval Timeline</h4>
                          <div className="space-y-3">
                            {/* Submitted */}
                            <div className="flex items-start gap-3">
                              <div className="w-2 h-2 rounded-full bg-neutral-500 mt-1.5"></div>
                              <div className="text-sm">
                                <p className="font-medium text-neutral-900">Submitted</p>
                                <p className="text-neutral-500">{formatDate(row.created_at)}</p>
                              </div>
                            </div>
                            
                            {/* Reviewed */}
                            {row.reviewed_at && (
                              <div className="flex items-start gap-3">
                                <div className={`w-2 h-2 rounded-full ${getStatusColor(row.status)} mt-1.5`}></div>
                                <div className="text-sm">
                                  <p className="font-medium text-neutral-900">
                                    {row.status === 'approved' ? 'Approved' : row.status === 'rejected' ? 'Rejected' : 'Reviewed'}
                                  </p>
                                  <p className="text-neutral-500">{formatDate(row.reviewed_at)}</p>
                                  {row.reviewer_remarks && (
                                    <p className="text-neutral-600 mt-1 bg-neutral-50 p-2 rounded text-xs">
                                      "{row.reviewer_remarks}"
                                    </p>
                                  )}
                                </div>
                              </div>
                            )}
                            
                            {/* Pending */}
                            {!row.reviewed_at && (
                              <div className="flex items-start gap-3">
                                <div className="w-2 h-2 rounded-full bg-amber-300 mt-1.5 animate-pulse"></div>
                                <div className="text-sm">
                                  <p className="font-medium text-amber-700">Pending Approval</p>
                                  <p className="text-neutral-500">Waiting for supervisor/manager review</p>
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      </div>


                    </td>
                  </tr>
                )}
              </>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
          <span>
            Page {meta.current_page} of {meta.last_page} • {meta.total} total
          </span>
          <div className="flex gap-2">
            <button
              disabled={meta.current_page <= 1}
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
