import { useState, useEffect, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTeamAttendanceLogs } from '@/hooks/useAttendance'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/ui/PageHeader'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { AttendanceFilters, AttendanceLog } from '@/types/hr'
import { 
  Clock, 
  Calendar, 
  User, 
  AlertCircle, 
  CheckCircle, 
  XCircle,
  Search,
} from 'lucide-react'

function anomalyStatus(row: AttendanceLog): { label: string; className: string; icon: React.ElementType } {
  if (row.is_absent) return { label: 'Absent', className: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: XCircle }
  if (row.late_minutes > 0) return { label: 'Late', className: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: AlertCircle }
  if (row.time_in && row.time_out) return { label: 'Present', className: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: CheckCircle }
  if (row.time_in && !row.time_out) return { label: 'No Out', className: 'bg-neutral-100 text-neutral-700 border-neutral-200', icon: Clock }
  return { label: 'No Entry', className: 'bg-neutral-100 text-neutral-600 border-neutral-200', icon: Clock }
}

function formatTime24to12(time: string | null): string {
  if (!time) return '—'
  const timeParts = time.split(':')
  const hours = parseInt(timeParts[0], 10)
  const minutes = parseInt(timeParts[1], 10)
  
  if (isNaN(hours) || isNaN(minutes)) return time
  
  const period = hours >= 12 ? 'PM' : 'AM'
  const displayHours = hours % 12 || 12
  return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`
}

export default function TeamAttendancePage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const searchInputRef = useRef<HTMLInputElement>(null)
  const [isSearchFocused, setIsSearchFocused] = useState(false)
  const [userModifiedSearch, setUserModifiedSearch] = useState(false) // Track if user manually typed
  
  // Read query params for auto-filter
  const queryEmployeeId = searchParams.get('employee_id')
  const queryEmployeeName = searchParams.get('employee_name')
  
  // Get first and last day of current month
  const now = new Date()
  const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const lastDayOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  
  const [filters, setFilters] = useState<AttendanceFilters>({
    per_page: 50,
    date_from: firstDayOfMonth,
    date_to:   lastDayOfMonth,
    // Use employee_id for initial filter (exact match), not search
    // Search will only be applied when user manually types
    employee_id: queryEmployeeId ? Number(queryEmployeeId) : undefined,
  })
  const [searchValue, setSearchValue] = useState(queryEmployeeName || '')
  const debouncedSearch = useDebounce(searchValue, 600) // 600ms delay to reduce API calls

  const { data, isLoading, isFetching, isError } = useTeamAttendanceLogs(filters)

  // Apply debounced search to filters
  // When user types in search box, clear employee_id (to avoid AND condition)
  // But DON'T apply search if it came from queryEmployeeName (full name won't match first/last)
  useEffect(() => {
    // Only apply search filter if user manually typed (not from URL query param)
    if (userModifiedSearch) {
      setFilters((f) => ({ 
        ...f, 
        search: debouncedSearch || undefined,
        employee_id: debouncedSearch ? undefined : f.employee_id, // Clear employee_id when searching
        page: 1 
      }))
    }
    // If not user modified, don't touch filters (keep employee_id from query param)
  }, [debouncedSearch, userModifiedSearch])
  
  // Refocus search input after data loads (if it was focused before)
  useEffect(() => {
    if (!isFetching && isSearchFocused && searchInputRef.current) {
      const cursorPos = searchInputRef.current.selectionStart
      searchInputRef.current.focus()
      if (cursorPos !== null) {
        searchInputRef.current.setSelectionRange(cursorPos, cursorPos)
      }
    }
  }, [isFetching, isSearchFocused])
  
  // Clear query params after reading (optional - keeps URL clean)
  useEffect(() => {
    if (queryEmployeeId || queryEmployeeName) {
      // Clear the query params to keep URL clean, filters are already set
      setSearchParams({}, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Only show full skeleton on initial load, not when refetching due to search
  if (isLoading && !isFetching) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="text-neutral-700 text-sm mt-4">
        Failed to load attendance records. Please try again.
      </div>
    )
  }

  const logs = data?.data ?? []

  return (
    <div>
      <PageHeader title="Team Attendance" />
      <p className="text-sm text-neutral-500 mb-4">
        {data?.meta?.total ?? 0} records
        <span className="ml-2 text-xs text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded">
          Department Only
        </span>
      </p>
      {(filters.employee_id || searchValue || filters.search) && (
        <div className="flex items-center gap-2 mb-4">
          <span className="text-sm text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded">
            Filtered by: {searchValue || filters.search || `Employee #${filters.employee_id}`}
          </span>
          <button
            onClick={() => {
              setFilters((f) => ({ ...f, employee_id: undefined, search: undefined, page: 1 }))
              setSearchValue('')
              setUserModifiedSearch(false)
            }}
            className="text-xs text-neutral-500 hover:text-neutral-900 underline"
          >
            Clear filter
          </button>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <div className="flex items-center gap-2">
          <Calendar className="h-4 w-4 text-neutral-400" />
          <input
            type="date"
            value={filters.date_from ?? ''}
            onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value || undefined, page: 1 }))}
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
          />
          <span className="text-neutral-400">to</span>
          <input
            type="date"
            value={filters.date_to ?? ''}
            onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value || undefined, page: 1 }))}
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
          />
        </div>

        {/* Search */}
        <div className="flex gap-2 flex-1 min-w-[200px] max-w-md">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
            <input
              ref={searchInputRef}
              type="text"
              placeholder="Search name or code…"
              value={searchValue}
              onChange={(e) => {
                setSearchValue(e.target.value)
                setUserModifiedSearch(true) // Mark as user-initiated search
              }}
              onFocus={() => setIsSearchFocused(true)}
              onBlur={() => setIsSearchFocused(false)}
              className="w-full border border-neutral-300 rounded pl-9 pr-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none transition-all"
            />
            {isFetching && (
              <div className="absolute right-3 top-1/2 -translate-y-1/2">
                <div className="h-4 w-4 border-2 border-neutral-500 border-t-transparent rounded-full animate-spin" />
              </div>
            )}
          </div>
          {searchValue && (
            <button
              onClick={() => {
                setSearchValue('')
                setUserModifiedSearch(false)
                // Also clear the actual filters
                setFilters((f) => ({ ...f, search: undefined, employee_id: undefined, page: 1 }))
                searchInputRef.current?.focus()
              }}
              className="px-3 py-2 text-sm bg-neutral-100 hover:bg-neutral-200 rounded text-neutral-700 transition-colors"
            >
              Clear
            </button>
          )}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden relative">
        {/* Loading overlay - only shows when refetching, not initial load */}
        {isFetching && !isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <div className="bg-white px-4 py-2 rounded border border-neutral-200 flex items-center gap-2">
              <div className="h-4 w-4 border-2 border-neutral-500 border-t-transparent rounded-full animate-spin" />
              <span className="text-sm text-neutral-600">Updating...</span>
            </div>
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Date</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Employee</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Status</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Time In</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">Time Out</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">Worked</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">Late</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-600">OT</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {logs.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-10 text-center text-neutral-400 text-sm">
                    No attendance records found.
                  </td>
                </tr>
              ) : (
                logs.map((row) => {
                  const status = anomalyStatus(row)
                  const StatusIcon = status.icon
                  return (
                    <tr key={row.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                      <td className="px-3 py-2 text-neutral-900">
                        {new Date(row.work_date).toLocaleDateString('en-PH', {
                          month: 'short', day: 'numeric', year: 'numeric'
                        })}
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-2">
                          <User className="h-3.5 w-3.5 text-neutral-400" />
                          <span className="font-medium text-neutral-900">
                            {row.employee?.full_name ?? `Employee #${row.employee_id}`}
                          </span>
                        </div>
                      </td>
                      <td className="px-3 py-2">
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border ${status.className}`}>
                          <StatusIcon className="h-3 w-3" />
                          {status.label}
                        </span>
                      </td>
                      <td className="px-3 py-2 font-mono text-neutral-700">
                        {formatTime24to12(row.time_in)}
                      </td>
                      <td className="px-3 py-2 font-mono text-neutral-700">
                        {formatTime24to12(row.time_out)}
                      </td>
                      <td className="px-3 py-2 text-right text-neutral-700">
                        {row.worked_hours ? `${row.worked_hours.toFixed(2)}h` : '—'}
                      </td>
                      <td className="px-3 py-2 text-right">
                        {row.late_minutes > 0 ? (
                          <span className="text-neutral-900 text-xs">{row.late_minutes}m</span>
                        ) : (
                          <span className="text-neutral-400">—</span>
                        )}
                      </td>
                      <td className="px-3 py-2 text-right">
                        {row.overtime_minutes > 0 ? (
                          <span className="text-neutral-700 text-xs">{Math.round(row.overtime_minutes / 60 * 10) / 10}h</span>
                        ) : (
                          <span className="text-neutral-400">—</span>
                        )}
                      </td>
                    </tr>
                  )
                })
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
