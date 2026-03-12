import { useState, useRef, useEffect, Fragment } from 'react'
import { useLeaveBalances, useCreateLeaveBalance } from '@/hooks/useLeave'
import { useDepartments, useEmployees } from '@/hooks/useEmployees'
import { useDebounce } from '@/hooks/useDebounce'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import { ChevronDown, ChevronRight, Search, X, Info, Calendar, Plus, AlertCircle } from 'lucide-react'
import { toast } from 'sonner'

const YEARS = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

// Format number to clean display (1 decimal max, no trailing zeros)
function fmt(n: number): string {
  if (n === 0) return '0'
  // Round to 1 decimal place and remove trailing zeros
  return parseFloat(n.toFixed(1)).toString()
}

// Leave type code to full name mapping for display
const _LEAVE_TYPE_NAMES: Record<string, string> = {
  VL: 'Vacation Leave',
  SL: 'Sick Leave',
  SIL: 'Service Incentive Leave',
  ML: 'Maternity Leave',
  PL: 'Paternity Leave',
  SPL: 'Solo Parent Leave',
  VAWCL: 'VAWC Leave',
  LWOP: 'Leave Without Pay',
}

export default function LeaveBalancesPage() {
  // Filters
  const [year, setYear] = useState(new Date().getFullYear())
  const [departmentId, setDepartmentId] = useState<number | undefined>(undefined)
  const [searchValue, setSearchValue] = useState('')
  const [page, setPage] = useState(1)
  const debouncedSearch = useDebounce(searchValue, 600)
  
  // Search focus management
  const searchInputRef = useRef<HTMLInputElement>(null)
  const [isSearchFocused, setIsSearchFocused] = useState(false)
  
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set())
  
  // Special Leave Grant Modal
  const [showGrantModal, setShowGrantModal] = useState(false)
  const [grantForm, setGrantForm] = useState({
    employee_id: '',
    leave_type: 'SPL' as 'SPL' | 'VAWCL' | 'ML' | 'PL',
    days: 7,
  })
  const [employeeSearch, setEmployeeSearch] = useState('')
  const debouncedEmployeeSearch = useDebounce(employeeSearch, 500)
  
  const { data: employeesData } = useEmployees(
    debouncedEmployeeSearch.length >= 3
      ? { search: debouncedEmployeeSearch, is_active: true, per_page: 50 }
      : { is_active: true, per_page: 0 }, // per_page=0 returns empty — no fetch until 3 chars
  )
  const createBalanceMutation = useCreateLeaveBalance()

  // Fetch the selected employee's balances so we can show which special
  // leave types have already been granted and disable them in the dropdown.
  const selectedEmployeeIdNum = Number(grantForm.employee_id) || null
  const { data: selectedEmpBalances } = useLeaveBalances(
    selectedEmployeeIdNum
      ? { employee_id: selectedEmployeeIdNum, year, per_page: 50 }
      : {},
  )
  const selectedEmpBalanceMap = Object.fromEntries(
    (selectedEmpBalances?.data?.[0]?.balances ?? []).map((b) => [
      b.leave_type_code,
      { granted: b.has_balance && b.opening_balance > 0, balance: b.balance, opening: b.opening_balance },
    ]),
  )

  const SPECIAL_LEAVE_TYPES: Array<{
    code: 'ML' | 'PL' | 'SPL' | 'VAWCL'
    label: string
    defaultDays: number
  }> = [
    { code: 'ML',    label: 'ML - Maternity Leave (RA 11210)',  defaultDays: 105 },
    { code: 'PL',    label: 'PL - Paternity Leave (RA 8187)',   defaultDays: 7   },
    { code: 'SPL',   label: 'SPL - Solo Parent Leave (RA 8972)', defaultDays: 7  },
    { code: 'VAWCL', label: 'VAWCL - VAWC Leave (RA 9262)',    defaultDays: 10  },
  ]

  // When employee selection changes, auto-select the first non-granted type.
  useEffect(() => {
    if (!selectedEmployeeIdNum) return
    const first = SPECIAL_LEAVE_TYPES.find(
      (t) => !selectedEmpBalanceMap[t.code]?.granted,
    )
    if (first) {
      setGrantForm((prev) => ({ ...prev, leave_type: first.code, days: first.defaultDays }))
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedEmployeeIdNum, selectedEmpBalances])

  const { data, isLoading, isFetching, isError } = useLeaveBalances({
    year,
    department_id: departmentId,
    search: debouncedSearch || undefined,
    per_page: 15,
    page,
  })
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

  const employees = data?.data ?? []
  const leaveTypes = data?.leave_types ?? []
  const meta = data?.meta

  const toggleRow = (empId: number) => {
    setExpandedRows((prev) => {
      const next = new Set(prev)
      if (next.has(empId)) {
        next.delete(empId)
      } else {
        next.add(empId)
      }
      return next
    })
  }

  // Only show full skeleton on initial load, not when refetching
  if ((isLoading && !isFetching) || deptLoading) return <SkeletonLoader rows={6} />
  if (isError) return <div className="text-red-600 text-sm mt-4">Failed to load leave balances.</div>

  return (
    <div>
      <PageHeader
        title="Leave Balances"
        subtitle={`${meta?.total ?? 0} employees • Showing ${employees.length} per page`}
        backTo="/hr/leave"
        actions={
          <div className="flex items-center gap-2">
            <button
              onClick={() => setShowGrantModal(true)}
              className="inline-flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
            >
              <Plus className="h-4 w-4" />
              Grant Special Leave
            </button>
            <div className="flex items-center gap-2 text-sm text-neutral-600 bg-neutral-100 px-3 py-2 rounded">
              <Calendar className="h-4 w-4" />
              <span>Auto-managed</span>
            </div>
          </div>
        }
      />

      {/* Info Banner */}
      <div className="mb-4 bg-neutral-50 border border-neutral-200 rounded-lg p-4">
        <div className="flex items-start gap-3">
          <Info className="h-5 w-5 text-neutral-600 mt-0.5 flex-shrink-0" />
          <div className="text-sm text-neutral-700">
            <p className="font-medium mb-1">How Leave Balances Work</p>
            <ul className="space-y-1 text-neutral-600 list-disc list-inside">
              <li><strong>Auto-created:</strong> Leave balances are automatically set up when an employee is activated</li>
              <li><strong>Monthly accrual:</strong> VL and SL accrue monthly (0.4 days/month = 5 days/year)</li>
              <li><strong>Yearly renewal:</strong> All leave balances reset on January 1st of each year</li>
              <li><strong>Used days:</strong> Automatically deducted when leave requests are approved</li>
              <li><strong>Special Leave:</strong> SPL and VAWCL require HR verification — use "Grant Special Leave" button</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded-lg p-4 mb-4 flex flex-wrap gap-3">
        {/* Department Filter */}
        <select
          value={departmentId ?? ''}
          onChange={(e) => {
            setDepartmentId(Number(e.target.value) || undefined)
            setPage(1)
          }}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none min-w-[200px]"
        >
          <option value="">All Departments</option>
          {(departmentsData?.data ?? []).map((dept) => (
            <option key={dept.id} value={dept.id}>{dept.name}</option>
          ))}
        </select>

        {/* Year Filter */}
        <select
          value={year}
          onChange={(e) => {
            setYear(Number(e.target.value))
            setPage(1)
          }}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>

        {/* Search */}
        <div className="flex gap-2 flex-1 min-w-[200px] max-w-md">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
            <input
              ref={searchInputRef}
              type="text"
              placeholder="Search employee name..."
              value={searchValue}
              onChange={(e) => {
                setSearchValue(e.target.value)
                setPage(1)
              }}
              onFocus={() => setIsSearchFocused(true)}
              onBlur={() => setIsSearchFocused(false)}
              className="w-full border border-neutral-300 rounded pl-9 pr-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 outline-none"
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
                setPage(1)
                searchInputRef.current?.focus()
              }}
              className="px-3 py-2 text-sm bg-neutral-100 hover:bg-neutral-200 rounded text-neutral-700 transition-colors"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>

      {/* Active Filters Indicator */}
      {(departmentId || searchValue) && (
        <div className="mb-4 flex items-center gap-2">
          <span className="text-sm text-neutral-500">Active filters:</span>
          {departmentId && (
            <span className="text-sm bg-neutral-100 text-neutral-700 px-2 py-0.5 rounded">
              {departmentsData?.data.find(d => d.id === departmentId)?.name}
            </span>
          )}
          {searchValue && (
            <span className="text-sm bg-neutral-100 text-neutral-700 px-2 py-0.5 rounded">
              Search: {searchValue}
            </span>
          )}
          <button
            onClick={() => {
              setDepartmentId(undefined)
              setSearchValue('')
              setPage(1)
            }}
            className="text-xs text-neutral-500 hover:text-red-600 underline"
          >
            Clear all
          </button>
        </div>
      )}

      {/* Leave Type Reference */}
      <div className="mb-4 bg-neutral-50 border border-neutral-200 rounded-lg p-3 text-sm">
        <p className="font-medium text-neutral-700 mb-2">Leave Type Reference:</p>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1 text-xs text-neutral-600">
          <span title="5 days/year, accrues 1.25 days/month"><strong>VL</strong> = Vacation Leave</span>
          <span title="5 days/year, accrues 1.25 days/month"><strong>SL</strong> = Sick Leave</span>
          <span title="5 days after 1 year of service"><strong>SIL</strong> = Service Incentive Leave</span>
          <span title="105 days for normal delivery"><strong>ML</strong> = Maternity Leave</span>
          <span title="7 days for married fathers"><strong>PL</strong> = Paternity Leave</span>
          <span title="7 days for solo parents"><strong>SPL</strong> = Solo Parent Leave</span>
          <span title="10 days for VAWC victims"><strong>VAWCL</strong> = VAWC Leave</span>
          <span title="Unpaid leave"><strong>LWOP</strong> = Leave Without Pay</span>
        </div>
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
        {employees.length === 0 ? (
          <div className="px-4 py-8 text-center text-neutral-400">
            No employees found. Try adjusting your filters.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider w-10"></th>
                  <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider min-w-[200px]">Employee</th>
                  <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Department</th>
                  {leaveTypes.map((type) => (
                    <th key={type.id} className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider min-w-[80px]">
                      {type.code}
                    </th>
                  ))}
                  <th className="px-3 py-2.5 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {employees.map((employee) => {
                  const isExpanded = expandedRows.has(employee.employee_id)
                  const hasNoBalances = employee.total_balance === 0
                  
                  return (
                    <Fragment key={employee.employee_id}>
                      {/* Summary Row */}
                      <tr
                        className={`hover:bg-neutral-50 even:bg-neutral-100 cursor-pointer transition-colors ${hasNoBalances ? 'bg-red-50/30' : ''}`}
                        onClick={() => toggleRow(employee.employee_id)}
                      >
                        <td className="px-3 py-2">
                          <button className="text-neutral-400 hover:text-neutral-600">
                            {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                          </button>
                        </td>
                        <td className="px-3 py-2">
                          <div className="font-medium text-neutral-900">{employee.employee_name}</div>
                          <div className="text-xs text-neutral-500">{employee.employee_code}</div>
                        </td>
                        <td className="px-3 py-2 text-neutral-600">
                          {employee.department_name ?? '—'}
                        </td>
                        {employee.balances.map((bal) => (
                          <td key={bal.leave_type_id} className="px-3 py-2 text-center">
                            {bal.has_balance ? (
                              <span className={`font-semibold ${bal.balance > 0 ? 'text-neutral-700' : 'text-neutral-400'}`}>
                                {fmt(bal.balance)}
                              </span>
                            ) : (
                              <span className="text-neutral-300 text-xs" title="Balance not yet created - will be set up automatically">—</span>
                            )}
                          </td>
                        ))}
                        <td className="px-3 py-2 text-center">
                          <span className={`font-bold ${employee.total_balance > 0 ? 'text-neutral-700' : 'text-red-400'}`}>
                            {fmt(employee.total_balance)}
                          </span>
                        </td>
                      </tr>
                      
                      {/* Expanded Detail Row */}
                      {isExpanded && (
                        <tr className="bg-neutral-50/50">
                          <td colSpan={leaveTypes.length + 4} className="px-4 py-4">
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                              {employee.balances.map((bal) => (
                                <div 
                                  key={bal.leave_type_id} 
                                  className={`rounded p-3 border ${bal.has_balance ? 'bg-white border-neutral-200' : 'bg-neutral-100 border-neutral-200 border-dashed'}`}
                                >
                                  <div className="flex items-center justify-between mb-1">
                                    <p className="text-xs text-neutral-500">{bal.leave_type_name}</p>
                                  </div>
                                  <div className="flex items-baseline gap-2">
                                    <span className={`text-lg font-bold ${bal.balance > 0 ? 'text-neutral-700' : bal.has_balance ? 'text-neutral-400' : 'text-neutral-300'}`}>
                                      {bal.has_balance ? fmt(bal.balance) : '—'}
                                    </span>
                                    {bal.has_balance && <span className="text-xs text-neutral-400">days</span>}
                                  </div>
                                  {bal.has_balance ? (
                                    <div className="mt-2 pt-2 border-t border-neutral-100 text-xs text-neutral-500">
                                      <div className="flex justify-between">
                                        <span>Opening:</span>
                                        <span>{fmt(bal.opening_balance)}</span>
                                      </div>
                                      <div className="flex justify-between">
                                        <span>Accrued:</span>
                                        <span className="text-green-600">+{fmt(bal.accrued)}</span>
                                      </div>
                                      <div className="flex justify-between">
                                        <span>Adjusted:</span>
                                        <span className={bal.adjusted >= 0 ? 'text-green-600' : 'text-red-600'}>
                                          {bal.adjusted >= 0 ? '+' : ''}{fmt(bal.adjusted)}
                                        </span>
                                      </div>
                                      <div className="flex justify-between">
                                        <span>Used:</span>
                                        <span className="text-red-600">-{fmt(bal.used)}</span>
                                      </div>
                                    </div>
                                  ) : (
                                    <p className="mt-2 text-xs text-neutral-400 italic">Auto-setup pending</p>
                                  )}
                                </div>
                              ))}
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="px-4 py-3 border-t border-neutral-100 flex items-center justify-between text-sm text-neutral-600">
            <span>
              Page {meta.current_page} of {meta.last_page} • {meta.total} total employees
            </span>
            <div className="flex gap-2">
              <button
                disabled={meta.current_page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={meta.current_page >= meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="px-3 py-1 rounded border border-neutral-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Legend */}
      <div className="mt-4 flex flex-wrap gap-4 text-xs text-neutral-500">
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded bg-neutral-100 border border-neutral-300"></span>
          <span>Click row to expand details</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded bg-red-50 border border-red-200"></span>
          <span>Red highlight = No leave balances (new employee)</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="font-bold text-neutral-700">Blue numbers</span>
          <span>= Available balance</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="text-neutral-300">—</span>
          <span>= Balance pending auto-setup</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="text-neutral-300">SPL/VAWCL</span>
          <span>= Require HR verification (Grant Special Leave button)</span>
        </div>
      </div>

      {/* Grant Special Leave Modal */}
      {showGrantModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg border border-neutral-200 max-w-md w-full">
            <div className="px-6 py-4 border-b border-neutral-200">
              <h2 className="text-lg font-semibold text-neutral-900">Grant Special Leave</h2>
              <p className="text-sm text-neutral-500 mt-1">
                Manually grant ML, PL, SPL, or VAWCL leave. Requires eligibility verification.
              </p>
            </div>
            <form 
              onSubmit={async (e) => {
                e.preventDefault()
                
                const selectedEmployee = employeesData?.data.find(e => e.id === Number(grantForm.employee_id))
                if (!selectedEmployee) {
                  toast.error('Please select an employee')
                  return
                }

                // Find the leave type ID
                const leaveType = data?.leave_types.find(t => t.code === grantForm.leave_type)
                if (!leaveType) {
                  toast.error('Leave type not found')
                  return
                }

                // Guard: prevent double-granting (in case select was somehow not disabled)
                const existingGrant = selectedEmpBalanceMap[grantForm.leave_type]
                if (existingGrant?.granted) {
                  toast.error(`${grantForm.leave_type} has already been granted to this employee (${existingGrant.opening} days opening balance)`)
                  return
                }

                // Eligibility confirmations per leave type
                if (grantForm.leave_type === 'ML') {
                  const confirmed = window.confirm(
                    'Maternity Leave (RA 11210) is for female employees who are pregnant or have recently given birth/miscarried.\n\n' +
                    'Please confirm this employee has submitted the required medical certificate before proceeding.'
                  )
                  if (!confirmed) return
                }
                if (grantForm.leave_type === 'PL') {
                  const confirmed = window.confirm(
                    'Paternity Leave (RA 8187) is for married male employees on the birth or miscarriage of a legitimate child.\n\n' +
                    'Please confirm this employee is eligible (married, maximum 4 deliveries) before proceeding.'
                  )
                  if (!confirmed) return
                }
                if (grantForm.leave_type === 'VAWCL') {
                  const confirmed = window.confirm(
                    'VAWC Leave (RA 9262) is specifically for women and children victims of violence.\n\n' +
                    'Please confirm this employee is eligible before proceeding.'
                  )
                  if (!confirmed) return
                }

                try {
                  await createBalanceMutation.mutateAsync({
                    employee_id: Number(grantForm.employee_id),
                    leave_type_id: leaveType.id,
                    year: year,
                    opening_balance: grantForm.days,
                    accrued: 0,
                    adjusted: 0,
                    used: 0,
                  })
                  toast.success(`Granted ${grantForm.days} days ${grantForm.leave_type} to ${selectedEmployee.full_name}`)
                  setShowGrantModal(false)
                  setGrantForm({ employee_id: '', leave_type: 'SPL', days: 7 })
                  setEmployeeSearch('')
                } catch (err: unknown) {
                  const error = err as { response?: { data?: { message?: string } } }
                  toast.error(error.response?.data?.message || 'Failed to grant leave')
                }
              }}
              className="p-6 space-y-4"
            >
              {/* Employee Search */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Employee <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  {grantForm.employee_id ? (
                    /* Selected state — shown inside the "input" box */
                    <div className="flex items-center justify-between w-full border border-green-400 bg-green-50 rounded px-3 py-2 text-sm">
                      <span className="text-green-800 font-medium truncate">
                        {employeesData?.data.find(e => e.id === Number(grantForm.employee_id))?.full_name}
                        <span className="ml-2 text-xs text-green-600 font-mono font-normal">
                          {employeesData?.data.find(e => e.id === Number(grantForm.employee_id))?.employee_code}
                        </span>
                      </span>
                      <button
                        type="button"
                        onClick={() => {
                          setGrantForm({ ...grantForm, employee_id: '' })
                          setEmployeeSearch('')
                        }}
                        className="ml-2 flex-shrink-0 text-green-600 hover:text-red-500 transition-colors"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>
                  ) : (
                    /* Search state */
                    <input
                      type="text"
                      placeholder="Type at least 3 letters of last name..."
                      value={employeeSearch}
                      onChange={(e) => setEmployeeSearch(e.target.value)}
                      className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                      autoFocus
                    />
                  )}
                </div>
                {/* Search Results dropdown */}
                {employeeSearch.length >= 3 && !grantForm.employee_id && (
                  <div className="mt-1 max-h-40 overflow-y-auto border border-neutral-200 rounded divide-y divide-neutral-100">
                    {(employeesData?.data ?? []).length === 0 ? (
                      <p className="px-3 py-2 text-sm text-neutral-500">No active employees found</p>
                    ) : (
                      (employeesData?.data ?? []).map((emp) => (
                        <button
                          key={emp.id}
                          type="button"
                          onClick={() => {
                            setGrantForm({ ...grantForm, employee_id: String(emp.id) })
                            setEmployeeSearch(emp.full_name)
                          }}
                          className="w-full px-3 py-2 text-left text-sm hover:bg-neutral-50 flex justify-between items-center"
                        >
                          <span>{emp.full_name}</span>
                          <span className="text-xs text-neutral-500 font-mono">{emp.employee_code}</span>
                        </button>
                      ))
                    )}
                  </div>
                )}
              </div>

              {/* Leave Type */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Leave Type <span className="text-red-500">*</span>
                </label>
                <select
                  value={grantForm.leave_type}
                  onChange={(e) => {
                    const lt = e.target.value as 'SPL' | 'VAWCL' | 'ML' | 'PL'
                    const defaultDays = lt === 'VAWCL' ? 10 : lt === 'ML' ? 105 : 7
                    setGrantForm({ ...grantForm, leave_type: lt, days: defaultDays })
                  }}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                >
                  {SPECIAL_LEAVE_TYPES.map((lt) => {
                    const grant = selectedEmpBalanceMap[lt.code]
                    const alreadyGranted = grant?.granted ?? false
                    return (
                      <option key={lt.code} value={lt.code} disabled={alreadyGranted}>
                        {lt.label}{alreadyGranted ? ` — already granted (${grant!.opening} days)` : ''}
                      </option>
                    )
                  })}
                </select>
                {/* Alert if all special leave types are already granted */}
                {SPECIAL_LEAVE_TYPES.every((lt) => selectedEmpBalanceMap[lt.code]?.granted) && grantForm.employee_id && (
                  <div className="flex items-start gap-1.5 mt-1">
                    <AlertCircle className="h-3.5 w-3.5 text-red-500 mt-0.5" />
                    <p className="text-xs text-red-600">
                      All special leave types have already been granted to this employee for {year}.
                    </p>
                  </div>
                )}
                
                {grantForm.leave_type === 'ML' && (
                  <div className="flex items-start gap-1.5 mt-1">
                    <AlertCircle className="h-3.5 w-3.5 text-neutral-500 mt-0.5" />
                    <p className="text-xs text-neutral-600">
                      105 days paid leave for normal delivery (120 days for solo mothers). For female employees. Requires medical certificate.
                    </p>
                  </div>
                )}
                {grantForm.leave_type === 'PL' && (
                  <p className="text-xs text-neutral-600 mt-1">
                    7 days for married male employees on birth/miscarriage of legitimate child. Requires marriage certificate.
                  </p>
                )}
                {grantForm.leave_type === 'SPL' && (
                  <p className="text-xs text-neutral-600 mt-1">
                    For solo parents (single mothers/fathers, widowed, legally separated, etc.)
                  </p>
                )}
                {grantForm.leave_type === 'VAWCL' && (
                  <div className="flex items-start gap-1.5 mt-1">
                    <AlertCircle className="h-3.5 w-3.5 text-amber-500 mt-0.5" />
                    <p className="text-xs text-amber-700">
                      For women and children victims of violence. Requires DSWD/court documentation.
                    </p>
                  </div>
                )}
              </div>

              {/* Days */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Days to Grant <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  min="1"
                  max="105"
                  value={grantForm.days}
                  readOnly
                  className="w-full border border-neutral-200 rounded px-3 py-2 text-sm bg-neutral-50 text-neutral-500 cursor-not-allowed outline-none"
                />
                <p className="text-xs text-neutral-500 mt-1">
                  Standard: 105 days for ML, 7 days for PL, 7 days for SPL, 10 days for VAWCL
                </p>
              </div>

              {/* Actions */}
              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowGrantModal(false)
                    setGrantForm({ employee_id: '', leave_type: 'SPL', days: 7 })
                    setEmployeeSearch('')
                  }}
                  className="flex-1 px-4 py-2 border border-neutral-300 text-neutral-700 rounded hover:bg-neutral-50 text-sm font-medium"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={
                    !grantForm.employee_id ||
                    createBalanceMutation.isPending ||
                    (selectedEmpBalanceMap[grantForm.leave_type]?.granted ?? false) ||
                    SPECIAL_LEAVE_TYPES.every((lt) => selectedEmpBalanceMap[lt.code]?.granted)
                  }
                  className="flex-1 px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                >
                  {createBalanceMutation.isPending ? 'Granting...' : 'Grant Leave'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
