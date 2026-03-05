import { useState, useEffect, useRef } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useAttendanceLogs, useCreateAttendanceLog, useUpdateAttendanceLog } from '@/hooks/useAttendance'
import { useEmployeeSearch } from '@/hooks/useEmployees'
import { useDebounce } from '@/hooks/useDebounce'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { AttendanceFilters, AttendanceLog } from '@/types/hr'
import { 
  Clock, 
  Calendar, 
  AlertCircle, 
  CheckCircle, 
  XCircle,
  Plus,
  Edit2,
  X,
  Search
} from 'lucide-react'
import { toast } from 'sonner'

function anomalyStatus(row: AttendanceLog): { label: string; className: string; icon: React.ElementType } {
  if (row.is_absent) return { label: 'Absent', className: 'bg-red-100 text-red-700 border-red-200', icon: XCircle }
  if (row.late_minutes > 0) return { label: 'Late', className: 'bg-yellow-100 text-yellow-700 border-yellow-200', icon: AlertCircle }
  if (row.time_in && row.time_out) return { label: 'Present', className: 'bg-green-100 text-green-700 border-green-200', icon: CheckCircle }
  if (row.time_in && !row.time_out) return { label: 'No Out', className: 'bg-orange-100 text-orange-700 border-orange-200', icon: Clock }
  return { label: 'No Entry', className: 'bg-gray-100 text-gray-600 border-gray-200', icon: Clock }
}

function formatTime24to12(time: string | null): string {
  if (!time) return '—'
  // Handle time format like "08:00:00" or "08:00"
  const timeParts = time.split(':')
  const hours = parseInt(timeParts[0], 10)
  const minutes = parseInt(timeParts[1], 10)
  
  if (isNaN(hours) || isNaN(minutes)) return time
  
  const period = hours >= 12 ? 'PM' : 'AM'
  const displayHours = hours % 12 || 12
  return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`
}

// Convert time input value (which may include seconds) to H:i format for display
function formatTimeInput(value: string): string {
  if (!value) return ''
  const parts = value.split(':')
  return `${parts[0]}:${parts[1]}`
}

interface EmployeeOption {
  id: number
  employee_code: string
  full_name: string
  department_name?: string
  position_title?: string
}

export default function AttendanceListPage() {
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
  
  const [showModal, setShowModal] = useState(false)
  const [editingLog, setEditingLog] = useState<AttendanceLog | null>(null)
  const [formData, setFormData] = useState({
    employee_id: '',
    work_date: new Date().toISOString().slice(0, 10),
    time_in: '',
    time_out: '',
    remarks: '',
  })
  
  // Employee search state
  const [empQuery, setEmpQuery] = useState('')
  const [selectedEmp, setSelectedEmp] = useState<EmployeeOption | null>(null)
  const [showEmpDropdown, setShowEmpDropdown] = useState(false)

  const { data, isLoading, isFetching, isError } = useAttendanceLogs(filters)
  const { data: empResults, isLoading: empLoading } = useEmployeeSearch(empQuery, showEmpDropdown)
  const createMutation = useCreateAttendanceLog()
  const updateMutation = useUpdateAttendanceLog()

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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      if (editingLog) {
        await updateMutation.mutateAsync({
          id: editingLog.id,
          time_in: formData.time_in || undefined,
          time_out: formData.time_out || undefined,
          remarks: formData.remarks || undefined,
        })
        toast.success('Attendance log updated successfully')
      } else {
        await createMutation.mutateAsync({
          employee_id: Number(formData.employee_id),
          work_date: formData.work_date,
          time_in: formData.time_in || undefined,
          time_out: formData.time_out || undefined,
          remarks: formData.remarks || undefined,
        })
        toast.success('Attendance log created successfully')
      }
      closeModal()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to save attendance log')
    }
  }

  const closeModal = () => {
    setShowModal(false)
    setEditingLog(null)
    setFormData({
      employee_id: '',
      work_date: new Date().toISOString().slice(0, 10),
      time_in: '',
      time_out: '',
      remarks: '',
    })
    setEmpQuery('')
    setSelectedEmp(null)
    setShowEmpDropdown(false)
  }

  const openEditModal = (log: AttendanceLog) => {
    setEditingLog(log)
    setFormData({
      employee_id: String(log.employee_id),
      work_date: log.work_date,
      time_in: formatTimeInput(log.time_in || ''),
      time_out: formatTimeInput(log.time_out || ''),
      remarks: log.remarks || '',
    })
    setSelectedEmp(log.employee ? {
      id: log.employee_id,
      employee_code: log.employee.employee_code || '',
      full_name: log.employee.full_name || `Employee #${log.employee_id}`,
    } : null)
    setShowModal(true)
  }

  const openCreateModal = () => {
    setEditingLog(null)
    setFormData({
      employee_id: '',
      work_date: new Date().toISOString().slice(0, 10),
      time_in: '',
      time_out: '',
      remarks: '',
    })
    setEmpQuery('')
    setSelectedEmp(null)
    setShowModal(true)
  }

  const handleEmpSelect = (emp: EmployeeOption) => {
    setSelectedEmp(emp)
    setFormData(prev => ({ ...prev, employee_id: String(emp.id) }))
    setEmpQuery('')
    setShowEmpDropdown(false)
  }

  const clearEmpSelection = () => {
    setSelectedEmp(null)
    setFormData(prev => ({ ...prev, employee_id: '' }))
    setEmpQuery('')
  }

  // Only show full skeleton on initial load, not when refetching due to search
  if (isLoading && !isFetching) return <SkeletonLoader rows={12} />
  if (isError)   return <div className="text-red-600 text-sm mt-4">Failed to load attendance logs.</div>

  const rows = data?.data ?? []

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Attendance Logs</h1>
          <p className="text-sm text-gray-500 mt-0.5">{data?.meta?.total ?? 0} records</p>
          {(filters.employee_id || searchValue || filters.search) && (
            <div className="flex items-center gap-2 mt-2">
              <span className="text-sm text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                Filtered by: {searchValue || filters.search || `Employee #${filters.employee_id}`}
              </span>
              <button
                onClick={() => {
                  setFilters((f) => ({ ...f, employee_id: undefined, search: undefined, page: 1 }))
                  setSearchValue('')
                  setUserModifiedSearch(false)
                }}
                className="text-xs text-gray-500 hover:text-red-600 underline"
              >
                Clear filter
              </button>
            </div>
          )}
        </div>
        <div className="flex gap-2">
          <button
            onClick={openCreateModal}
            className="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            <Plus className="h-4 w-4" />
            Manual Entry
          </button>
          <Link to="/hr/attendance/import"
            className="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Import CSV
          </Link>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        <div className="flex items-center gap-2">
          <label className="text-sm text-gray-600">From</label>
          <input type="date" value={filters.date_from ?? ''}
            onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value, page: 1 }))}
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div className="flex items-center gap-2">
          <label className="text-sm text-gray-600">To</label>
          <input type="date" value={filters.date_to ?? ''}
            onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value, page: 1 }))}
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        {/* Search */}
        <div className="flex gap-2 flex-1 min-w-[200px] max-w-md">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
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
              className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
            />
            {isFetching && (
              <div className="absolute right-3 top-1/2 -translate-y-1/2">
                <div className="h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
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
              className="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 transition-colors"
            >
              Clear
            </button>
          )}
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {[
          { label: 'Present', value: rows.filter(r => r.is_present).length, color: 'bg-green-50 text-green-700' },
          { label: 'Absent', value: rows.filter(r => r.is_absent).length, color: 'bg-red-50 text-red-700' },
          { label: 'Late', value: rows.filter(r => r.late_minutes > 0).length, color: 'bg-yellow-50 text-yellow-700' },
          { label: 'Incomplete', value: rows.filter(r => r.time_in && !r.time_out).length, color: 'bg-orange-50 text-orange-700' },
        ].map((stat) => (
          <div key={stat.label} className={`${stat.color} rounded-xl p-4 border border-gray-200`}>
            <p className="text-xs font-medium uppercase tracking-wide opacity-75">{stat.label}</p>
            <p className="text-2xl font-bold mt-1">{stat.value}</p>
          </div>
        ))}
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden relative">
        {/* Loading overlay - only shows when refetching, not initial load */}
        {isFetching && !isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <div className="bg-white px-4 py-2 rounded-lg shadow-lg border border-gray-200 flex items-center gap-2">
              <div className="h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
              <span className="text-sm text-gray-600">Updating...</span>
            </div>
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Time In</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Time Out</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Hours</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Late</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Undertime</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {rows.length === 0 && (
                <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">No attendance logs for selected period.</td></tr>
              )}
              {rows.map((row) => {
                const status = anomalyStatus(row)
                const StatusIcon = status.icon
                return (
                  <tr key={row.id} className="even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                    <td className="px-3 py-2">
                      <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${status.className}`}>
                        <StatusIcon className="h-3.5 w-3.5" />
                        {status.label}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold">
                          {row.employee?.full_name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() || '#' + row.employee_id}
                        </div>
                        <div>
                          <p className="font-medium text-gray-900">{row.employee?.full_name ?? `Employee #${row.employee_id}`}</p>
                          <p className="text-xs text-gray-500">{row.employee?.employee_code}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-gray-700">
                      <div className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-gray-400" />
                        {row.work_date}
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5 text-gray-400" />
                        <span className={row.time_in ? 'text-gray-900' : 'text-gray-400'}>
                          {formatTime24to12(row.time_in)}
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5 text-gray-400" />
                        <span className={row.time_out ? 'text-gray-900' : 'text-gray-400'}>
                          {formatTime24to12(row.time_out)}
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-center">
                      <span className="font-medium text-gray-900">{row.worked_hours ?? '—'}</span>
                    </td>
                    <td className="px-3 py-2 text-center">
                      {row.late_minutes > 0 ? (
                        <span className="text-yellow-700 font-medium">{row.late_minutes}m</span>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-center">
                      {row.undertime_minutes > 0 ? (
                        <span className="text-orange-700 font-medium">{row.undertime_minutes}m</span>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-center">
                      <button
                        onClick={() => openEditModal(row)}
                        className="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-xs font-medium"
                      >
                        <Edit2 className="h-3.5 w-3.5" />
                        Edit
                      </button>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
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

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">
                {editingLog ? 'Edit Attendance' : 'Manual Time Entry'}
              </h2>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              {/* Employee Search (Create Only) */}
              {!editingLog && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                  {selectedEmp ? (
                    <div className="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                      <div>
                        <p className="font-medium text-gray-900">{selectedEmp.full_name}</p>
                        <p className="text-xs text-gray-500">{selectedEmp.employee_code}</p>
                      </div>
                      <button
                        type="button"
                        onClick={clearEmpSelection}
                        className="text-gray-400 hover:text-gray-600"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>
                  ) : (
                    <div className="relative">
                      <div className="flex items-center border border-gray-300 rounded-lg px-3 py-2 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
                        <Search className="h-4 w-4 text-gray-400 mr-2" />
                        <input
                          type="text"
                          value={empQuery}
                          onChange={(e) => {
                            setEmpQuery(e.target.value)
                            setShowEmpDropdown(true)
                          }}
                          onFocus={() => setShowEmpDropdown(true)}
                          placeholder="Search employee..."
                          className="flex-1 outline-none text-sm"
                          autoComplete="off"
                        />
                      </div>
                      
                      {/* Dropdown */}
                      {showEmpDropdown && (empQuery.length >= 2 || (empResults && empResults.length > 0)) && (
                        <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                          {empLoading ? (
                            <div className="px-4 py-3 text-sm text-gray-500">Searching...</div>
                          ) : empResults && empResults.length > 0 ? (
                            empResults.map((emp) => (
                              <button
                                key={emp.id}
                                type="button"
                                onClick={() => handleEmpSelect(emp as EmployeeOption)}
                                className="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                              >
                                <p className="font-medium text-gray-900">{emp.full_name}</p>
                                <p className="text-xs text-gray-500">
                                  {emp.employee_code} {emp.department_name && `· ${emp.department_name}`}
                                </p>
                              </button>
                            ))
                          ) : empQuery.length >= 2 ? (
                            <div className="px-4 py-3 text-sm text-gray-500">No employees found</div>
                          ) : null}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}
              
              {/* Show selected employee info when editing */}
              {editingLog && selectedEmp && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                  <div className="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                    <p className="font-medium text-gray-900">{selectedEmp.full_name}</p>
                    <p className="text-xs text-gray-500">{selectedEmp.employee_code}</p>
                  </div>
                </div>
              )}
              
              {/* Date (Create Only) */}
              {!editingLog && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                  <input
                    type="date"
                    value={formData.work_date}
                    onChange={(e) => setFormData({ ...formData, work_date: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                    required
                  />
                </div>
              )}

              {/* Time In/Out */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Time In</label>
                  <input
                    type="time"
                    value={formData.time_in}
                    onChange={(e) => setFormData({ ...formData, time_in: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                  />
                  {formData.time_in && (
                    <p className="text-xs text-gray-500 mt-1">{formatTime24to12(formData.time_in)}</p>
                  )}
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Time Out</label>
                  <input
                    type="time"
                    value={formData.time_out}
                    onChange={(e) => setFormData({ ...formData, time_out: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                  />
                  {formData.time_out && (
                    <p className="text-xs text-gray-500 mt-1">{formatTime24to12(formData.time_out)}</p>
                  )}
                </div>
              </div>

              {/* Remarks */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                <textarea
                  value={formData.remarks}
                  onChange={(e) => setFormData({ ...formData, remarks: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                  rows={2}
                  placeholder="Optional notes..."
                />
              </div>

              {/* Actions */}
              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={closeModal}
                  className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || updateMutation.isPending || (!editingLog && !formData.employee_id)}
                  className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                >
                  {createMutation.isPending || updateMutation.isPending ? 'Saving...' : editingLog ? 'Update' : 'Save'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
