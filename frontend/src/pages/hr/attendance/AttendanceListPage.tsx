import { firstErrorMessage } from '@/lib/errorHandler'
import { useState, useEffect, useRef } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { PageHeader } from '@/components/ui/PageHeader'
import { useAttendanceLogs, useCreateAttendanceLog, useUpdateAttendanceLog, useEmployeeShiftAssignments } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'
import { useEmployeeSearch } from '@/hooks/useEmployees'
import { useDebounce } from '@/hooks/useDebounce'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { ExportButton } from '@/components/ui/ExportButton'
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
  return { label: 'No Entry', className: 'bg-neutral-100 text-neutral-600 border-neutral-200', icon: Clock }
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
  ulid: string
  employee_code: string
  full_name: string
  department_name?: string
  position_title?: string
}

export default function AttendanceListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { hasPermission } = useAuthStore()
  const canManageShifts = hasPermission('attendance.manage_shifts')
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
  const { data: shiftAssignments } = useEmployeeShiftAssignments(selectedEmp?.ulid ?? null)
  const createMutation = useCreateAttendanceLog()
  const updateMutation = useUpdateAttendanceLog()

  // Derive the active shift for the selected employee on the chosen work date
  const activeShift = (() => {
    if (!shiftAssignments?.length) return null
    const workDate = formData.work_date || new Date().toISOString().slice(0, 10)
    return shiftAssignments.find(
      (a) =>
        a.effective_from <= workDate &&
        (a.effective_to === null || a.effective_to >= workDate)
    )?.shift_schedule ?? null
  })()

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
      toast.error(firstErrorMessage(error, 'Failed to save attendance log'))
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
      ulid: log.employee.ulid ?? '',
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
      <PageHeader
        title="Attendance"
        actions={
          <div className="flex gap-2">
            <ExportButton
              data={data?.data ?? []}
              columns={[
                { key: 'employee.full_name', label: 'Employee' },
                { key: 'work_date', label: 'Date' },
                { key: 'time_in', label: 'Time In' },
                { key: 'time_out', label: 'Time Out' },
                { key: 'worked_minutes', label: 'Worked (min)' },
                { key: 'late_minutes', label: 'Late (min)' },
                { key: 'ot_minutes', label: 'OT (min)' },
                { key: 'is_absent', label: 'Absent', format: (v: unknown) => v ? 'Yes' : 'No' },
              ]}
              filename="attendance"
            />
            {canManageShifts && (
              <button
                onClick={openCreateModal}
                className="inline-flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
              >
                <Plus className="h-4 w-4" />
                Manual Entry
              </button>
            )}
            <Link to="/hr/attendance/import"
              className="inline-flex items-center gap-2 bg-white border border-neutral-300 hover:bg-neutral-50 text-neutral-700 text-sm font-medium px-4 py-2 rounded transition-colors">
              Import CSV
            </Link>
          </div>
        }
      />

      {/* Header Actions */}
      <div className="flex items-center justify-between mb-6">
        <p className="text-sm text-neutral-500">{data?.meta?.total ?? 0} records</p>
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-4 flex flex-wrap gap-3">
        <div className="flex items-center gap-2">
          <label className="text-sm text-neutral-600">From</label>
          <input type="date" value={filters.date_from ?? ''}
            onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value, page: 1 }))}
            className="border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
        </div>

        <div className="flex items-center gap-2">
          <label className="text-sm text-neutral-600">To</label>
          <input type="date" value={filters.date_to ?? ''}
            onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value, page: 1 }))}
            className="border border-neutral-300 rounded px-3 py-2 text-sm outline-none focus:ring-1 focus:ring-neutral-400" />
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
              className="w-full border border-neutral-300 rounded pl-9 pr-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none transition-all"
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

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {[
          { label: 'Present', value: rows.filter(r => r.is_present).length, color: 'bg-green-50 text-green-700' },
          { label: 'Absent', value: rows.filter(r => r.is_absent).length, color: 'bg-red-50 text-red-700' },
          { label: 'Late', value: rows.filter(r => r.late_minutes > 0).length, color: 'bg-yellow-50 text-yellow-700' },
          { label: 'Incomplete', value: rows.filter(r => r.time_in && !r.time_out).length, color: 'bg-orange-50 text-orange-700' },
        ].map((stat) => (
          <div key={stat.label} className={`${stat.color} rounded-lg p-4 border border-neutral-200`}>
            <p className="text-xs font-medium uppercase tracking-wide opacity-75">{stat.label}</p>
            <p className="text-lg font-semibold mt-1">{stat.value}</p>
          </div>
        ))}
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden relative">
        {/* Loading overlay - only shows when refetching, not initial load */}
        {isFetching && !isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <div className="bg-white px-4 py-2 rounded-lg border border-neutral-200 flex items-center gap-2">
              <div className="h-4 w-4 border-2 border-neutral-500 border-t-transparent rounded-full animate-spin" />
              <span className="text-sm text-neutral-600">Updating...</span>
            </div>
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Status</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Employee</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Date</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Time In</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Time Out</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">Hours</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">Late</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">Undertime</th>
                <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {rows.length === 0 && (
                <tr><td colSpan={9} className="px-4 py-8 text-center text-neutral-400">No attendance logs for selected period.</td></tr>
              )}
              {rows.map((row) => {
                const status = anomalyStatus(row)
                const StatusIcon = status.icon
                return (
                  <tr key={row.id} className="hover:bg-neutral-50 transition-colors">
                    <td className="px-3 py-2">
                      <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-medium border ${status.className}`}>
                        <StatusIcon className="h-3.5 w-3.5" />
                        {status.label}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-full bg-neutral-100 flex items-center justify-center text-neutral-700 text-xs font-bold">
                          {row.employee?.full_name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() || '#' + row.employee_id}
                        </div>
                        <div>
                          <p className="font-medium text-neutral-900">{row.employee?.full_name ?? `Employee #${row.employee_id}`}</p>
                          <p className="text-xs text-neutral-500">{row.employee?.employee_code}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-neutral-700">
                      <div className="flex items-center gap-1.5">
                        <Calendar className="h-3.5 w-3.5 text-neutral-400" />
                        {row.work_date}
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5 text-neutral-400" />
                        <span className={row.time_in ? 'text-neutral-900' : 'text-neutral-400'}>
                          {formatTime24to12(row.time_in)}
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1.5">
                        <Clock className="h-3.5 w-3.5 text-neutral-400" />
                        <span className={row.time_out ? 'text-neutral-900' : 'text-neutral-400'}>
                          {formatTime24to12(row.time_out)}
                        </span>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-center">
                      <span className="font-medium text-neutral-900">{row.worked_hours ?? '—'}</span>
                    </td>
                    <td className="px-3 py-2 text-center">
                      {row.late_minutes > 0 ? (
                        <span className="text-yellow-700 font-medium">{row.late_minutes}m</span>
                      ) : (
                        <span className="text-neutral-400">—</span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-center">
                      {row.undertime_minutes > 0 ? (
                        <span className="text-orange-700 font-medium">{row.undertime_minutes}m</span>
                      ) : (
                        <span className="text-neutral-400">—</span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-center">
                      <button
                        onClick={() => openEditModal(row)}
                        className="inline-flex items-center gap-1 text-neutral-600 hover:text-neutral-900 text-xs font-medium"
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
        <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
          <span>Page {data?.meta?.current_page} of {data?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={(filters.page ?? 1) <= 1} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40">Prev</button>
            <button disabled={(filters.page ?? 1) >= (data?.meta?.last_page ?? 1)} onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40">Next</button>
          </div>
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg border border-neutral-200 max-w-md w-full">
            <div className="px-6 py-4 border-b border-neutral-200">
              <h2 className="text-lg font-semibold text-neutral-900">
                {editingLog ? 'Edit Attendance' : 'Manual Time Entry'}
              </h2>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              {/* Employee Search (Create Only) */}
              {!editingLog && (
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Employee</label>
                  {selectedEmp ? (
                    <div className="flex items-center justify-between bg-neutral-50 border border-neutral-200 rounded px-3 py-2">
                      <div>
                        <p className="font-medium text-neutral-900">{selectedEmp.full_name}</p>
                        <p className="text-xs text-neutral-500">{selectedEmp.employee_code}</p>
                      </div>
                      <button
                        type="button"
                        onClick={clearEmpSelection}
                        className="text-neutral-400 hover:text-neutral-600"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>
                  ) : (
                    <div className="relative">
                      <div className="flex items-center border border-neutral-300 rounded px-3 py-2 focus-within:ring-1 focus-within:ring-neutral-400 focus-within:border-neutral-400">
                        <Search className="h-4 w-4 text-neutral-400 mr-2" />
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
                        <div className="absolute z-10 w-full mt-1 bg-white border border-neutral-200 rounded shadow-lg max-h-60 overflow-auto">
                          {empLoading ? (
                            <div className="px-4 py-3 text-sm text-neutral-500">Searching...</div>
                          ) : empResults && empResults.length > 0 ? (
                            empResults.map((emp) => (
                              <button
                                key={emp.id}
                                type="button"
                                onClick={() => handleEmpSelect(emp as EmployeeOption)}
                                className="w-full text-left px-4 py-2 hover:bg-neutral-50 border-b border-neutral-100 last:border-0"
                              >
                                <p className="font-medium text-neutral-900">{emp.full_name}</p>
                                <p className="text-xs text-neutral-500">
                                  {emp.full_name}{emp.department_name ? ` — ${emp.department_name}` : ''}
                                </p>
                              </button>
                            ))
                          ) : empQuery.length >= 2 ? (
                            <div className="px-4 py-3 text-sm text-neutral-500">No employees found</div>
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
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Employee</label>
                  <div className="bg-neutral-50 border border-neutral-200 rounded px-3 py-2">
                    <p className="font-medium text-neutral-900">{selectedEmp.full_name}</p>
                    <p className="text-xs text-neutral-500">{selectedEmp.employee_code}</p>
                  </div>
                </div>
              )}
              
              {/* Date (Create Only) */}
              {!editingLog && (
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Date</label>
                  <input
                    type="date"
                    value={formData.work_date}
                    onChange={(e) => setFormData({ ...formData, work_date: e.target.value })}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                    required
                  />
                </div>
              )}

              {/* Time In/Out */}
              {/* Shift schedule badge — shows active shift for the selected employee + work date */}
              {selectedEmp && (
                <div>
                  {activeShift ? (
                    <div className="flex items-center gap-2 bg-neutral-50 border border-neutral-200 rounded px-3 py-2 text-sm">
                      <Clock className="h-4 w-4 text-neutral-500 shrink-0" />
                      <div>
                        <span className="font-medium text-neutral-700">{activeShift.name}</span>
                        <span className="text-neutral-500 ml-2">
                          {activeShift.start_time.slice(0, 5)} – {activeShift.end_time.slice(0, 5)}
                          {activeShift.grace_period_minutes > 0 && (
                            <span className="ml-1 text-neutral-400">({activeShift.grace_period_minutes} min grace)</span>
                          )}
                        </span>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 bg-neutral-50 border border-neutral-200 rounded px-3 py-2 text-sm text-neutral-500">
                      <Clock className="h-4 w-4 text-neutral-400 shrink-0" />
                      <span>No shift assigned for this date</span>
                    </div>
                  )}
                </div>
              )}

              {/* Time In / Time Out */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Time In</label>
                  <input
                    type="time"
                    value={formData.time_in}
                    onChange={(e) => setFormData({ ...formData, time_in: e.target.value })}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                  />
                  {formData.time_in && (
                    <p className="text-xs text-neutral-500 mt-1">{formatTime24to12(formData.time_in)}</p>
                  )}
                </div>
                <div>
                  <label className="block text-sm font-medium text-neutral-700 mb-1">Time Out</label>
                  <input
                    type="time"
                    value={formData.time_out}
                    onChange={(e) => setFormData({ ...formData, time_out: e.target.value })}
                    className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                  />
                  {formData.time_out && (
                    <p className="text-xs text-neutral-500 mt-1">{formatTime24to12(formData.time_out)}</p>
                  )}
                </div>
              </div>

              {/* Remarks */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Remarks</label>
                <textarea
                  value={formData.remarks}
                  onChange={(e) => setFormData({ ...formData, remarks: e.target.value })}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none"
                  rows={2}
                  placeholder="Optional notes..."
                />
              </div>

              {/* Actions */}
              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={closeModal}
                  className="flex-1 px-4 py-2 border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 text-sm font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || updateMutation.isPending || (!editingLog && !formData.employee_id)}
                  className="flex-1 px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
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
