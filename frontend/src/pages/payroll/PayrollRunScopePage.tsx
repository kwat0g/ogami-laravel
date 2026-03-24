/**
 * Step 2 — Set Employee Scope
 * Allows the initiator to filter which employees will be included in this payroll run.
 * Provides a live preview of in-scope employee counts by department.
 * Manual exclusions can be added per employee.
 */
import { useState, useEffect, useRef } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowLeft,
  ArrowRight,
  Users,
  Minus,
  Plus,
  Loader2,
  Ban,
  Search,
  X,
  Info,
} from 'lucide-react'
import {
  usePayrollRun,
  useScopePreview,
  useConfirmScope,
  useAddExclusion,
  useRemoveExclusion,
  useCancelPayrollRun,
} from '@/hooks/usePayroll'
import { useEmployeeSearch } from '@/hooks/useEmployees'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'

const EMPLOYMENT_TYPES = [
  { value: 'regular', label: 'Regular' },
  { value: 'probationary', label: 'Probationary' },
  { value: 'contractual', label: 'Contractual' },
  { value: 'project_based', label: 'Project-Based' },
  { value: 'casual', label: 'Casual' },
]

// ── Employee Search Dropdown Component ───────────────────────────────────────

interface EmployeeSearchDropdownProps {
  selectedEmployee: { id: number; full_name: string } | null
  onSelect: (employee: { id: number; full_name: string; employee_code: string }) => void
  excludedIds: number[]
}

function EmployeeSearchDropdown({
  selectedEmployee,
  onSelect,
  excludedIds,
}: EmployeeSearchDropdownProps) {
  const [query, setQuery] = useState('')
  const [isOpen, setIsOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)

  const { data: employees, isLoading } = useEmployeeSearch(query, isOpen)

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const filteredEmployees = employees?.filter((emp) => !excludedIds.includes(emp.id)) || []

  const handleSelect = (employee: { id: number; full_name: string; employee_code: string }) => {
    onSelect(employee)
    setQuery('')
    setIsOpen(false)
  }

  const handleClear = () => {
    onSelect({ id: 0, full_name: '', employee_code: '' })
    setQuery('')
  }

  return (
    <div ref={containerRef} className="relative flex-1">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400" />
        <input
          type="text"
          placeholder={
            selectedEmployee ? selectedEmployee.full_name : 'Search by surname or name...'
          }
          value={selectedEmployee ? selectedEmployee.full_name : query}
          onChange={(e) => {
            setQuery(e.target.value)
            setIsOpen(true)
          }}
          onFocus={() => setIsOpen(true)}
          className="w-full border border-neutral-300 rounded pl-10 pr-10 py-2 text-sm focus:ring-2 focus:ring-neutral-500 focus:border-neutral-500 outline-none"
        />
        {selectedEmployee && (
          <button
            onClick={handleClear}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {isOpen && (
        <div className="absolute z-50 w-full mt-1 bg-white border border-neutral-200 rounded  max-h-60 overflow-y-auto">
          {isLoading ? (
            <div className="flex items-center gap-2 px-4 py-3 text-sm text-neutral-500">
              <Loader2 className="h-4 w-4 animate-spin" /> Searching...
            </div>
          ) : query.length < 2 ? (
            <div className="px-4 py-3 text-sm text-neutral-500">
              Type at least 2 characters to search
            </div>
          ) : filteredEmployees.length === 0 ? (
            <div className="px-4 py-3 text-sm text-neutral-500">No employees found</div>
          ) : (
            filteredEmployees.map((emp) => (
              <button
                key={emp.id}
                onClick={() => handleSelect(emp)}
                className="w-full text-left px-4 py-2 hover:bg-neutral-50 border-b border-neutral-50 last:border-0"
              >
                <div className="flex items-center justify-between">
                  <span className="font-medium text-neutral-800">{emp.full_name}</span>
                  <span className="text-xs text-neutral-500">{emp.employee_code}</span>
                </div>
                <div className="text-xs text-neutral-500">
                  {emp.department?.name || 'No Department'} • {emp.position?.title || 'No Position'}
                </div>
              </button>
            ))
          )}
        </div>
      )}
    </div>
  )
}

export default function PayrollRunScopePage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const runId = id ?? null
  const navigate = useNavigate()

  const { data: run, isLoading: runLoading } = usePayrollRun(runId)

  // ── Scope filter state ────────────────────────────────────────────────────
  const [departments, setDepartments] = useState<number[]>([])
  const [positions, _setPositions] = useState<number[]>([])
  const [employmentTypes, setEmploymentTypes] = useState<string[]>([
    'regular',
    'probationary',
    'contractual',
    'project_based',
    'casual',
  ])
  const [includeUnpaidLeave, setIncludeUnpaidLeave] = useState(false)
  const [includeProbationEnd, setIncludeProbationEnd] = useState(false)
  const [excludeNoAttendance, setExcludeNoAttendance] = useState(false)

  // Guard so filter restoration from run data only fires once per mount
  const filtersRestoredRef = useRef(false)

  // ── New manual exclusion form ─────────────────────────────────────────────
  const [selectedEmployee, setSelectedEmployee] = useState<{
    id: number
    full_name: string
    employee_code: string
  } | null>(null)
  const [exclReason, setExclReason] = useState('')

  // Pre-populate from existing run scope if re-entering — fires only once per mount
  useEffect(() => {
    if (!run || filtersRestoredRef.current) return
    filtersRestoredRef.current = true
    if (run.scope_employment_types?.length) setEmploymentTypes(run.scope_employment_types)
    if (run.scope_departments?.length) setDepartments(run.scope_departments)
    setIncludeUnpaidLeave(run.scope_include_unpaid_leave ?? false)
    setIncludeProbationEnd(run.scope_include_probation_end ?? false)
    setExcludeNoAttendance(run.scope_exclude_no_attendance ?? false)
  }, [run])

  // ── Live preview ──────────────────────────────────────────────────────────
  const previewFilters = {
    departments: departments.length ? departments : undefined,
    positions: positions.length ? positions : undefined,
    employment_types: employmentTypes,
    include_unpaid_leave: includeUnpaidLeave,
    include_probation_end: includeProbationEnd,
    exclude_no_attendance: excludeNoAttendance,
  }

  const { data: preview, isFetching: previewLoading } = useScopePreview(
    runId,
    previewFilters,
    !runLoading,
  )

  // ── Mutations ─────────────────────────────────────────────────────────────
  const confirmScope = useConfirmScope(runId)
  const addExclusion = useAddExclusion(runId)
  const removeExclusion = useRemoveExclusion(runId)
  const cancelRun = useCancelPayrollRun(runId)

  const [showMissingBankConfirm, setShowMissingBankConfirm] = useState(false)

  // ── Auto-excluded employees (missing bank account) ──────────────────────────
  const autoExcludedIdsRef = useRef<Set<number>>(new Set())
  const [autoExcludedEmployees, setAutoExcludedEmployees] = useState<
    Array<{ id: number; full_name: string; employee_code: string; department_name: string }>
  >([])

  // ── Auto-exclude employees without bank accounts on preview load ─────────────
  useEffect(() => {
    const missing = preview?.missing_bank_employees ?? []
    if (missing.length === 0) return
    const toAdd = missing.filter((emp) => !autoExcludedIdsRef.current.has(emp.id))
    if (toAdd.length === 0) return
    toAdd.forEach((emp) => autoExcludedIdsRef.current.add(emp.id))
    setAutoExcludedEmployees((prev) => {
      const existingIds = new Set(prev.map((e) => e.id))
      return [...prev, ...toAdd.filter((e) => !existingIds.has(e.id))]
    })
    void Promise.allSettled(
      toAdd.map((emp) =>
        addExclusion.mutateAsync({ employee_id: emp.id, reason: 'Missing bank account number' }),
      ),
    ).then((results) => {
      const failed = results.filter((r) => r.status === 'rejected').length
      if (failed === 0) {
        toast.success(
          `${toAdd.length} employee${toAdd.length !== 1 ? 's' : ''} automatically excluded (no bank account).`,
        )
      } else {
        toast.warning(`${toAdd.length - failed} employees auto-excluded; ${failed} failed.`)
      }
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [preview?.missing_bank_employees])

  // Redirect if run is past SCOPE_SET already
  useEffect(() => {
    if (!run) return
    if (
      run.status === 'PRE_RUN_CHECKED' ||
      run.status === 'PROCESSING' ||
      run.status === 'COMPUTED'
    ) {
      navigate(`/payroll/runs/${runId}/validate`)
    }
  }, [run, runId, navigate])

  if (runLoading) {
    return (
      <div className="flex items-center gap-2 text-sm text-neutral-500">
        <Loader2 className="h-4 w-4 animate-spin" /> Loading…
      </div>
    )
  }
  if (!run) return <div className="text-red-500 text-sm">Payroll run not found.</div>

  function toggleEmploymentType(val: string) {
    setEmploymentTypes((prev) =>
      prev.includes(val) ? prev.filter((e) => e !== val) : [...prev, val],
    )
  }

  // ── Validation helpers ────────────────────────────────────────────────────
  function validateAddExclusion(): boolean {
    if (!selectedEmployee || selectedEmployee.id <= 0) {
      toast.error('Please search and select an employee.')
      return false
    }
    if (!exclReason.trim()) {
      toast.error('A reason for exclusion is required.')
      return false
    }
    if (exclReason.trim().length < 5) {
      toast.error('Reason must be at least 5 characters.')
      return false
    }
    return true
  }

  async function handleAddExclusion() {
    if (!validateAddExclusion()) return
    try {
      await addExclusion.mutateAsync({
        employee_id: selectedEmployee!.id,
        reason: exclReason.trim(),
      })
      setSelectedEmployee(null)
      setExclReason('')
      toast.success('Exclusion added successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  async function handleRemoveExclusion(employeeId: number) {
    try {
      await removeExclusion.mutateAsync(employeeId)
      toast.success('Exclusion removed successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  // ── Confirm scope ─────────────────────────────────────────────────────────
  function validateConfirmScope(): boolean {
    if (!employmentTypes.length) {
      toast.error('Select at least one employment type.')
      return false
    }
    if (!preview || preview.net_in_scope === 0) {
      toast.error('No employees match the current filters.')
      return false
    }
    return true
  }

  async function doConfirmScope() {
    try {
      await confirmScope.mutateAsync({
        departments: departments.length ? departments : undefined,
        positions: positions.length ? positions : undefined,
        employment_types: employmentTypes,
        include_unpaid_leave: includeUnpaidLeave,
        include_probation_end: includeProbationEnd,
        exclude_no_attendance: excludeNoAttendance,
      })
      toast.success('Scope confirmed. Proceeding to validation.')
      navigate(`/payroll/runs/${runId}/validate`)
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  function handleConfirmClick() {
    if (!validateConfirmScope()) return
    // Warn if any in-scope employees still have no bank account
    if (preview?.missing_bank_employees && preview.missing_bank_employees.length > 0) {
      setShowMissingBankConfirm(true)
      return
    }
    void doConfirmScope()
  }

  // ── Cancel run ────────────────────────────────────────────────────────────
  async function handleCancel() {
    try {
      await cancelRun.mutateAsync()
      toast.success('Payroll run cancelled.')
      navigate('/payroll/runs')
    } catch (err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const exclusions = run?.exclusions ?? []

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate('/payroll/runs')}
          className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" /> Back to Payroll Runs
        </button>

        <ConfirmDestructiveDialog
          title="Cancel payroll run?"
          description="Cancelling will permanently stop this payroll run. Employees will not be paid from this run. This action cannot be undone."
          confirmWord="CANCEL"
          confirmLabel="Cancel Run"
          onConfirm={handleCancel}
        >
          <button
            type="button"
            disabled={cancelRun.isPending}
            className="flex items-center gap-1.5 px-3 py-2 text-sm text-red-600 hover:text-red-800 border border-red-200 hover:border-red-400 rounded transition-colors disabled:opacity-50"
          >
            {cancelRun.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Ban className="h-4 w-4" />}
            Cancel Run
          </button>
        </ConfirmDestructiveDialog>
      </div>

      <WizardStepHeader
        step={2}
        title="Set Employee Scope"
        description={`Run #${run.reference_no} — Define which employees will be included in this payroll run.`}
        currentStep={2}
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* ── Left: Scope Filters ── */}
        <div className="lg:col-span-2 space-y-5">
          <div className="bg-white border border-neutral-200 rounded p-5 space-y-5">
            <h3 className="text-sm font-semibold text-neutral-800">Scope Filters</h3>

            {/* Employment Types */}
            <div>
              <p className="text-sm font-medium text-neutral-700 mb-2">Employment Types <span className="text-red-500">*</span></p>
              <div className="flex flex-wrap gap-2">
                {EMPLOYMENT_TYPES.map(({ value, label }) => (
                  <label
                    key={value}
                    className={`flex items-center gap-2 px-3 py-1.5 rounded border text-sm cursor-pointer transition-colors ${
                      employmentTypes.includes(value)
                        ? 'bg-neutral-900 border-neutral-900 text-white'
                        : 'bg-white border-neutral-300 text-neutral-700 hover:border-neutral-400'
                    }`}
                  >
                    <input
                      type="checkbox"
                      className="sr-only"
                      checked={employmentTypes.includes(value)}
                      onChange={() => toggleEmploymentType(value)}
                    />
                    {label}
                  </label>
                ))}
              </div>
              {employmentTypes.length === 0 && (
                <p className="text-xs text-red-500 mt-2">Select at least one employment type.</p>
              )}
            </div>

            {/* Toggles */}
            <div className="space-y-3">
              <label className="flex items-center justify-between cursor-pointer">
                <span className="text-sm text-neutral-700">Include employees on unpaid leave</span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={includeUnpaidLeave}
                  onClick={() => setIncludeUnpaidLeave((p) => !p)}
                  className={`relative inline-flex h-5 w-9 rounded transition-colors ${
                    includeUnpaidLeave ? 'bg-neutral-900' : 'bg-neutral-300'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 bg-white rounded shadow transform transition-transform mt-0.5 ${
                      includeUnpaidLeave ? 'translate-x-4' : 'translate-x-0.5'
                    }`}
                  />
                </button>
              </label>
              <label className="flex items-center justify-between cursor-pointer">
                <span className="text-sm text-neutral-700">
                  Include employees whose probation ends this period
                </span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={includeProbationEnd}
                  onClick={() => setIncludeProbationEnd((p) => !p)}
                  className={`relative inline-flex h-5 w-9 rounded transition-colors ${
                    includeProbationEnd ? 'bg-neutral-900' : 'bg-neutral-300'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 bg-white rounded shadow transform transition-transform mt-0.5 ${
                      includeProbationEnd ? 'translate-x-4' : 'translate-x-0.5'
                    }`}
                  />
                </button>
              </label>
              <label className="flex items-center justify-between cursor-pointer">
                <span className="text-sm text-neutral-700">
                  Exclude employees with no attendance records
                </span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={excludeNoAttendance}
                  onClick={() => setExcludeNoAttendance((p) => !p)}
                  className={`relative inline-flex h-5 w-9 rounded transition-colors ${
                    excludeNoAttendance ? 'bg-neutral-900' : 'bg-neutral-300'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 bg-white rounded shadow transform transition-transform mt-0.5 ${
                      excludeNoAttendance ? 'translate-x-4' : 'translate-x-0.5'
                    }`}
                  />
                </button>
              </label>
            </div>
          </div>

          {/* ── Auto-excluded: missing bank accounts ── */}
          {autoExcludedEmployees.length > 0 && (
            <div className="bg-neutral-50 border border-neutral-200 rounded p-4 space-y-3">
              <div className="flex items-center gap-2">
                <Info className="h-4 w-4 text-neutral-900 shrink-0" />
                <p className="text-sm font-semibold text-neutral-800">
                  {autoExcludedEmployees.length} employee
                  {autoExcludedEmployees.length !== 1 ? 's' : ''} automatically excluded
                </p>
              </div>
              <p className="text-xs text-neutral-800">
                These employees have no bank account on file and were excluded from this run
                automatically. You may remove them from the exclusion list below to include them
                (payroll will need to be disbursed manually).
              </p>
              <div className="border border-neutral-100 rounded overflow-hidden divide-y divide-neutral-50 max-h-36 overflow-y-auto">
                {autoExcludedEmployees.map((emp) => (
                  <div key={emp.id} className="flex items-center gap-3 px-3 py-2 bg-white/70">
                    <span className="text-xs font-mono text-neutral-400 shrink-0">
                      {emp.employee_code}
                    </span>
                    <span className="text-sm text-neutral-700">{emp.full_name}</span>
                    <span className="text-xs text-neutral-400 ml-auto shrink-0">
                      {emp.department_name}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* ── Manual Exclusions ── */}
          <div className="bg-white border border-neutral-200 rounded p-5 space-y-4">
            <h3 className="text-sm font-semibold text-neutral-800">Manual Exclusions</h3>
            <p className="text-xs text-neutral-500">
              Exclude specific employees from this run. Exclusions persist until removed. The
              employee will not receive a payslip for this run.
            </p>

            {/* Add exclusion row */}
            <div className="flex gap-2 items-end">
              <EmployeeSearchDropdown
                selectedEmployee={selectedEmployee}
                onSelect={setSelectedEmployee}
                excludedIds={exclusions.map((e) => e.employee_id)}
              />
              <div className="flex-[1.5] space-y-1">
                <input
                  type="text"
                  placeholder="Reason for exclusion (min. 5 chars)"
                  value={exclReason}
                  onChange={(e) => setExclReason(e.target.value)}
                  className={`w-full border rounded px-3 py-2 text-sm ${
                    exclReason.length > 0 && exclReason.trim().length < 5
                      ? 'border-red-300 focus:ring-red-400'
                      : 'border-neutral-300'
                  } focus:outline-none focus:ring-2`}
                />
                {exclReason.length > 0 && exclReason.trim().length < 5 && (
                  <p className="text-xs text-red-500">
                    {5 - exclReason.trim().length} more character
                    {5 - exclReason.trim().length !== 1 ? 's' : ''} needed
                  </p>
                )}
              </div>
              <button
                type="button"
                onClick={handleAddExclusion}
                disabled={addExclusion.isPending}
                className="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded text-sm font-medium flex items-center gap-1 transition-colors"
              >
                <Plus className="h-3.5 w-3.5" /> Exclude
              </button>
            </div>

            {/* Existing exclusions */}
            {exclusions.length > 0 && (
              <div className="border border-red-100 rounded divide-y divide-red-50">
                {exclusions.map(
                  (exc: {
                    employee_id: number
                    reason: string
                    employee?: { first_name: string; last_name: string }
                  }) => (
                    <div key={exc.employee_id} className="flex items-center gap-3 px-4 py-2">
                      <Users className="h-4 w-4 text-red-400 shrink-0" />
                      <div className="flex-1 min-w-0">
                        <span className="text-sm text-neutral-800">
                          {exc.employee
                            ? `${exc.employee.first_name} ${exc.employee.last_name}`
                            : `Employee #${exc.employee_id}`}
                        </span>
                        <p className="text-xs text-neutral-500 truncate">{exc.reason}</p>
                      </div>
                      <button
                        type="button"
                        onClick={() => handleRemoveExclusion(exc.employee_id)}
                        className="text-red-500 hover:text-red-700"
                      >
                        <Minus className="h-4 w-4" />
                      </button>
                    </div>
                  ),
                )}
              </div>
            )}
          </div>
        </div>

        {/* ── Right: Live Preview ── */}
        <div className="space-y-4">
          <div className="bg-white border border-neutral-200 rounded p-5 space-y-4 sticky top-6">
            <h3 className="text-sm font-semibold text-neutral-800 flex items-center gap-2">
              <Users className="h-4 w-4 text-neutral-500" />
              In-Scope Preview
              {previewLoading && <Loader2 className="h-3 w-3 animate-spin text-neutral-400" />}
            </h3>

            {preview ? (
              <>
                <div className="grid grid-cols-3 gap-3 text-center">
                  <div className="bg-green-50 rounded p-3">
                    <p className="text-2xl font-bold text-green-700">{preview.total_eligible}</p>
                    <p className="text-xs text-green-600">Eligible</p>
                  </div>
                  <div className="bg-red-50 rounded p-3">
                    <p className="text-2xl font-bold text-red-600">{preview.manually_excluded}</p>
                    <p className="text-xs text-red-500">Excluded</p>
                  </div>
                  <div className="bg-neutral-50 rounded p-3">
                    <p className="text-2xl font-bold text-neutral-800">{preview.net_in_scope}</p>
                    <p className="text-xs text-neutral-900">In Scope</p>
                  </div>
                </div>

                {preview.by_department?.length > 0 && (
                  <div className="space-y-1 max-h-64 overflow-y-auto">
                    <p className="text-xs font-medium text-neutral-500 font-medium">
                      By Department
                    </p>
                    {preview.by_department?.map((dept) => (
                      <div
                        key={dept.department_id}
                        className="flex items-center justify-between text-sm py-1 border-b border-neutral-50"
                      >
                        <span
                          className="text-neutral-700 truncate max-w-[140px]"
                          title={dept.department_name}
                        >
                          {dept.department_name}
                        </span>
                        <span className="text-neutral-900 font-medium shrink-0">
                          {dept.in_scope}{' '}
                          <span className="text-neutral-400 font-normal">/ {dept.eligible}</span>
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </>
            ) : (
              <p className="text-xs text-neutral-400">Adjust filters to see live counts.</p>
            )}

            <ConfirmDialog
              title="Confirm Scope Settings?"
              description={`This will set the employee scope for ${preview?.net_in_scope ?? 0} employees and proceed to pre-run validation. This action cannot be undone.`}
              confirmLabel="Confirm Scope"
              onConfirm={doConfirmScope}
            >
              <button
                type="button"
                disabled={confirmScope.isPending || !preview || preview.net_in_scope === 0 || employmentTypes.length === 0}
                className="w-full px-4 py-2.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors flex items-center justify-center gap-2"
              >
                {confirmScope.isPending ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" /> Confirming…
                  </>
                ) : (
                  <>
                    Confirm Scope <ArrowRight className="h-4 w-4" />
                  </>
                )}
              </button>
            </ConfirmDialog>

            {preview?.net_in_scope === 0 && (
              <p className="text-xs text-red-600 text-center">
                No employees match the current filters.
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Missing bank account — proceed anyway confirmation dialog */}
      {showMissingBankConfirm && preview?.missing_bank_employees && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
          onClick={() => setShowMissingBankConfirm(false)}
        >
          <div
            className="bg-white rounded w-full max-w-md max-h-[90vh] overflow-y-auto p-4 sm:p-6 space-y-4"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-start gap-3">
              <Info className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
              <div>
                <h3 className="font-semibold text-neutral-900">
                  {preview.missing_bank_employees.length} employee
                  {preview.missing_bank_employees.length !== 1 ? 's' : ''} without a bank account
                </h3>
                <p className="text-sm text-neutral-600 mt-1">
                  These employees cannot be included in bank disbursement. They will be computed but
                  skipped when generating the bank file.
                </p>
              </div>
            </div>
            <div className="border border-amber-100 rounded divide-y divide-amber-50 max-h-40 overflow-y-auto">
              {preview.missing_bank_employees.map((emp) => (
                <div key={emp.id} className="flex items-center gap-3 px-3 py-2">
                  <span className="text-xs font-mono text-neutral-400 shrink-0">
                    {emp.employee_code}
                  </span>
                  <span className="text-sm text-neutral-700">{emp.full_name}</span>
                  <span className="text-xs text-neutral-400 ml-auto shrink-0">
                    {emp.department_name}
                  </span>
                </div>
              ))}
            </div>
            <p className="text-xs text-neutral-500">
              You can still go back and use the <strong>Auto-exclude</strong> button in the warning
              panel above.
            </p>
            <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-2 pt-1">
              <button
                type="button"
                onClick={() => setShowMissingBankConfirm(false)}
                className="px-4 py-2 text-sm font-medium text-neutral-700 hover:text-neutral-900 border border-neutral-200 rounded transition-colors"
              >
                Go back
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowMissingBankConfirm(false)
                  void doConfirmScope()
                }}
                disabled={confirmScope.isPending}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 hover:bg-neutral-800 rounded transition-colors disabled:opacity-50 flex items-center gap-1"
              >
                {confirmScope.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                Proceed anyway
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
