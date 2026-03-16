/**
 * Step 2 (Draft) — Set Employee Scope for a new, unsaved payroll run.
 *
 * All data is kept locally in PayrollWizardContext (sessionStorage).
 * No API call is made here — the scope is saved when the user clicks
 * "Begin Computation" on the next page (/payroll/runs/new/validate).
 *
 * Live preview uses GET /api/v1/payroll/runs/draft-scope-preview which does
 * not require an existing run record.
 */
import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { ArrowLeft, ArrowRight, Users, Minus, Plus, Loader2, Search, X, Info } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { usePayrollWizard, type DraftExclusion } from '@/contexts/PayrollWizardContext'
import { useEmployeeSearch } from '@/hooks/useEmployees'
import type { ScopePreview } from '@/types/payroll'
import { WizardStepHeader } from '@/components/payroll/WizardStepHeader'

const EMPLOYMENT_TYPES = [
  { value: 'regular', label: 'Regular' },
  { value: 'probationary', label: 'Probationary' },
  { value: 'contractual', label: 'Contractual' },
  { value: 'project_based', label: 'Project-Based' },
  { value: 'casual', label: 'Casual' },
]

// ── Draft scope-preview hook (no run ID) ─────────────────────────────────────

interface DraftPreviewParams {
  cutoff_end: string
  departments?: number[]
  employment_types?: string[]
  include_unpaid_leave: boolean
  include_probation_end: boolean
  exclude_employee_ids?: number[]
}

function useDraftScopePreview(params: DraftPreviewParams, enabled: boolean) {
  return useQuery({
    queryKey: ['draft-scope-preview', params],
    queryFn: async () => {
      const res = await api.get<{ data: ScopePreview }>('/payroll/runs/draft-scope-preview', {
        params: {
          cutoff_end: params.cutoff_end,
          departments: params.departments?.length ? params.departments : undefined,
          employment_types: params.employment_types,
          include_unpaid_leave: params.include_unpaid_leave ? 1 : 0,
          include_probation_end: params.include_probation_end ? 1 : 0,
          exclude_employee_ids: params.exclude_employee_ids?.length
            ? params.exclude_employee_ids
            : undefined,
        },
      })
      return res.data.data
    },
    enabled: enabled && !!params.cutoff_end,
    staleTime: 10_000,
  })
}

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

// ─────────────────────────────────────────────────────────────────────────────

export default function PayrollRunDraftScopePage() {
  const navigate = useNavigate()
  const { state, setStep2 } = usePayrollWizard()
  const step1 = state.step1

  // Redirect back to Step 1 if wizard state is missing
  useEffect(() => {
    if (!step1) navigate('/payroll/runs/new', { replace: true })
  }, [step1, navigate])

  // ── Scope filter state (pre-populate from wizard context if returning) ─────
  const [employmentTypes, setEmploymentTypes] = useState<string[]>(
    state.step2?.employment_types ?? [
      'regular',
      'probationary',
      'contractual',
      'project_based',
      'casual',
    ],
  )
  const [departments, _setDepartments] = useState<number[]>(state.step2?.departments ?? [])
  const [includeUnpaidLeave, setIncludeUnpaidLeave] = useState(
    state.step2?.include_unpaid_leave ?? false,
  )
  const [includeProbationEnd, setIncludeProbationEnd] = useState(
    state.step2?.include_probation_end ?? false,
  )
  const [exclusions, setExclusions] = useState<DraftExclusion[]>(state.step2?.exclusions ?? [])

  // ── New exclusion form state ───────────────────────────────────────────────
  const [selectedEmployee, setSelectedEmployee] = useState<{
    id: number
    full_name: string
    employee_code: string
  } | null>(null)
  const [exclReason, setExclReason] = useState('')
  const [showMissingBankConfirm, setShowMissingBankConfirm] = useState(false)

  // ── Auto-excluded employees (missing bank account) ────────────────────────
  const autoExcludedIdsRef = useRef<Set<number>>(new Set())
  const [autoExcludedEmployees, setAutoExcludedEmployees] = useState<
    Array<{ id: number; full_name: string; employee_code: string; department_name: string }>
  >([])
  const exclusionsRef = useRef(exclusions)
  exclusionsRef.current = exclusions

  // ── Live preview ──────────────────────────────────────────────────────────
  const { data: preview, isFetching: previewLoading } = useDraftScopePreview(
    {
      cutoff_end: step1?.cutoff_end ?? '',
      departments: departments.length ? departments : undefined,
      employment_types: employmentTypes,
      include_unpaid_leave: includeUnpaidLeave,
      include_probation_end: includeProbationEnd,
      exclude_employee_ids: exclusions.map((e) => e.employee_id),
    },
    !!step1,
  )

  // ── Auto-exclude employees without bank accounts ──────────────────────────
  useEffect(() => {
    const missing = preview?.missing_bank_employees ?? []
    if (missing.length === 0) return
    const toAdd = missing.filter((emp) => !autoExcludedIdsRef.current.has(emp.id))
    if (toAdd.length === 0) return
    toAdd.forEach((emp) => autoExcludedIdsRef.current.add(emp.id))
    const alreadyExcludedIds = new Set(exclusionsRef.current.map((e) => e.employee_id))
    const newExclusions = toAdd.filter((emp) => !alreadyExcludedIds.has(emp.id))
    if (newExclusions.length > 0) {
      setExclusions((prev) => [
        ...prev,
        ...newExclusions.map((emp) => ({
          employee_id: emp.id,
          label: emp.full_name,
          reason: 'Missing bank account number',
        })),
      ])
    }
    setAutoExcludedEmployees((prev) => {
      const existingIds = new Set(prev.map((e) => e.id))
      return [...prev, ...toAdd.filter((e) => !existingIds.has(e.id))]
    })
  }, [preview?.missing_bank_employees])

  // ── Handlers ─────────────────────────────────────────────────────────────

  function toggleEmploymentType(val: string) {
    setEmploymentTypes((prev) =>
      prev.includes(val) ? prev.filter((e) => e !== val) : [...prev, val],
    )
  }

  function handleAddExclusion() {
    if (!selectedEmployee || selectedEmployee.id <= 0) {
      toast.error('Please search and select an employee.')
      return
    }
    if (!exclReason.trim()) {
      toast.error('A reason for exclusion is required.')
      return
    }
    if (exclusions.some((e) => e.employee_id === selectedEmployee.id)) {
      toast.error('Employee already excluded.')
      return
    }
    setExclusions((prev) => [
      ...prev,
      {
        employee_id: selectedEmployee.id,
        label: selectedEmployee.full_name,
        reason: exclReason.trim(),
      },
    ])
    setSelectedEmployee(null)
    setExclReason('')
  }

  function handleRemoveExclusion(employeeId: number) {
    setExclusions((prev) => prev.filter((e) => e.employee_id !== employeeId))
  }

  function handleNext() {
    if (!employmentTypes.length) {
      toast.error('Select at least one employment type.')
      return
    }
    if (!preview || preview.net_in_scope === 0) {
      toast.error('No employees match the current filters. Adjust your scope before proceeding.')
      return
    }
    // Warn if any in-scope employees still have no bank account
    if (preview?.missing_bank_employees && preview.missing_bank_employees.length > 0) {
      setShowMissingBankConfirm(true)
      return
    }
    doNavigateNext()
  }

  function doNavigateNext() {
    setStep2({
      departments,
      employment_types: employmentTypes,
      include_unpaid_leave: includeUnpaidLeave,
      include_probation_end: includeProbationEnd,
      exclusions,
    })
    navigate('/payroll/runs/new/validate')
  }

  if (!step1) return null // redirecting

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <button
        onClick={() => navigate('/payroll/runs/new')}
        className="flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors"
      >
        <ArrowLeft className="h-4 w-4" /> Back to Run Definition
      </button>

      <WizardStepHeader
        step={2}
        title="Set Employee Scope"
        description={`New Payroll Run · ${step1.cutoff_start} → ${step1.cutoff_end} · Pay date: ${step1.pay_date}`}
      />

      {/* Draft notice */}
      <div className="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded px-4 py-2.5 text-xs text-amber-800">
        <span className="font-semibold">Draft — not saved yet.</span>
        <span>
          The payroll run is only created in the database when you click "Begin Computation" on the
          next step.
        </span>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* ── Left: Scope Filters ─────────────────────────────────────────── */}
        <div className="lg:col-span-2 space-y-5">
          <div className="bg-white border border-neutral-200 rounded p-5 space-y-5">
            <h3 className="text-sm font-semibold text-neutral-800">Scope Filters</h3>

            {/* Employment Types */}
            <div>
              <p className="text-sm font-medium text-neutral-700 mb-2">Employment Types</p>
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
            </div>

            {/* Toggles */}
            <div className="space-y-3">
              {(
                [
                  [
                    includeUnpaidLeave,
                    setIncludeUnpaidLeave,
                    'Include employees on unpaid leave',
                  ] as const,
                  [
                    includeProbationEnd,
                    setIncludeProbationEnd,
                    'Include employees whose probation ends this period',
                  ] as const,
                ] as const
              ).map(([val, set, label]) => (
                <label key={label} className="flex items-center justify-between cursor-pointer">
                  <span className="text-sm text-neutral-700">{label}</span>
                  <button
                    type="button"
                    role="switch"
                    aria-checked={val}
                    onClick={() => (set as (v: boolean) => void)(!val)}
                    className={`relative inline-flex h-5 w-9 rounded transition-colors ${val ? 'bg-neutral-900' : 'bg-neutral-300'}`}
                  >
                    <span
                      className={`inline-block h-4 w-4 bg-white rounded shadow transform transition-transform mt-0.5 ${val ? 'translate-x-4' : 'translate-x-0.5'}`}
                    />
                  </button>
                </label>
              ))}
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

          {/* ── Manual Exclusions ─────────────────────────────────────────── */}
          <div className="bg-white border border-neutral-200 rounded p-5 space-y-4">
            <h3 className="text-sm font-semibold text-neutral-800">Manual Exclusions</h3>
            <p className="text-xs text-neutral-500">
              Exclude specific employees from this run. These are stored locally until you reach the
              final step.
            </p>

            {/* Add row */}
            <div className="flex gap-2 items-end">
              <EmployeeSearchDropdown
                selectedEmployee={selectedEmployee}
                onSelect={setSelectedEmployee}
                excludedIds={exclusions.map((e) => e.employee_id)}
              />
              <div className="flex-[1.5]">
                <input
                  type="text"
                  placeholder="Reason for exclusion"
                  value={exclReason}
                  onChange={(e) => setExclReason(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && handleAddExclusion()}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm"
                />
              </div>
              <button
                type="button"
                onClick={handleAddExclusion}
                className="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded text-sm font-medium flex items-center gap-1 transition-colors"
              >
                <Plus className="h-3.5 w-3.5" /> Exclude
              </button>
            </div>

            {/* List */}
            {exclusions.length > 0 && (
              <div className="border border-red-100 rounded divide-y divide-red-50">
                {exclusions.map((exc) => (
                  <div key={exc.employee_id} className="flex items-center gap-3 px-4 py-2">
                    <Users className="h-4 w-4 text-red-400 shrink-0" />
                    <div className="flex-1 min-w-0">
                      <span className="text-sm text-neutral-800">{exc.label}</span>
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
                ))}
              </div>
            )}
          </div>
        </div>

        {/* ── Right: Live Preview ──────────────────────────────────────────── */}
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
                    {preview.by_department.map((dept) => (
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

            <button
              type="button"
              onClick={handleNext}
              disabled={previewLoading || !preview || preview.net_in_scope === 0}
              className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-neutral-900 hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors"
            >
              Next: Review &amp; Begin <ArrowRight className="h-4 w-4" />
            </button>

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
            className="bg-white rounded shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto p-4 sm:p-6 space-y-4"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-start gap-3">
              <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
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
                  doNavigateNext()
                }}
                className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 hover:bg-neutral-800 rounded transition-colors"
              >
                Proceed anyway
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
