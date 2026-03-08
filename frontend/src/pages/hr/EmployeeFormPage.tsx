import { useEffect, useRef, forwardRef, useMemo } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useEmployee, useCreateEmployee, useUpdateEmployee, useSalaryGrades, useDepartments, usePositions, useEmployees } from '@/hooks/useEmployees'
import { useShifts, useAssignShift } from '@/hooks/useAttendance'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { ApiError } from '@/types/api'
import type { CreateEmployeePayload } from '@/types/hr'

const employeeSchema = z.object({
  first_name:         z.string().min(1, 'First name is required').max(80),
  last_name:          z.string().min(1, 'Last name is required').max(80),
  middle_name:        z.string().max(80).optional(),
  suffix:             z.string().max(10).optional(),
  date_of_birth:      z.string().optional(),
  gender:             z.enum(['male', 'female', 'other'], { errorMap: () => ({ message: 'Please select a gender' }) }),
  civil_status:       z.enum(['', 'SINGLE', 'MARRIED', 'WIDOWED', 'LEGALLY_SEPARATED', 'HEAD_OF_FAMILY']).optional(),
  employment_type:    z.enum(['regular', 'contractual', 'project_based', 'casual', 'probationary'], { errorMap: () => ({ message: 'Please select an employment type' }) }),
  pay_basis:          z.enum(['monthly', 'daily'], { errorMap: () => ({ message: 'Please select a pay basis' }) }),
  basic_monthly_rate: z.coerce.number({ invalid_type_error: 'Please enter a valid rate' }).min(0.01, 'Rate must be greater than 0'),
  date_hired:         z.string().min(1, 'Date hired is required'),
  salary_grade_id:    z.coerce.number().int().positive().optional(),
  department_id:      z.coerce.number().int().positive().optional(),
  position_id:        z.coerce.number().int().positive().optional(),
  reports_to:         z.coerce.number().int().positive().optional().or(z.literal('')),
  personal_email:     z.string().email().optional().or(z.literal('')),
  personal_phone:     z.string().max(20).optional(),
  citizenship:        z.string().max(60).optional(),
  present_address:    z.string().max(255).optional(),
  permanent_address:  z.string().max(255).optional(),
  sss_no:             z.string().max(12).optional(),
  tin:                z.string().max(15).optional(),
  philhealth_no:      z.string().max(14).optional(),
  pagibig_no:         z.string().max(14).optional(),
  bank_name:          z.string().max(100).optional(),
  bank_account_no:    z.string().max(30).optional(),
  notes:              z.string().max(2000).optional(),
  shift_schedule_id:  z.coerce.number().int().positive().optional(),
})

type EmployeeFormData = z.infer<typeof employeeSchema>

function FormField({
  label,
  required,
  hint,
  error,
  children,
}: {
  label: string
  required?: boolean
  hint?: React.ReactNode
  error?: string
  children: React.ReactNode
}) {
  return (
    <div>
      <label className="block text-xs font-medium text-neutral-700 mb-1">
        {label} {required && <span className="text-red-500">*</span>}
      </label>
      {children}
      {hint && !error && <p className="text-xs text-neutral-400 mt-1">{hint}</p>}
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  )
}

const Input = forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
  function Input({ className = '', ...props }, ref) {
    return (
      <input
        {...props}
        ref={ref}
        className={`w-full border border-neutral-300 rounded px-3 py-2 text-sm
                    focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400
                    disabled:bg-neutral-50 disabled:text-neutral-500 ${className}`}
      />
    )
  },
)

const Select = forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(
  function Select({ className = '', children, ...props }, ref) {
    return (
      <select
        {...props}
        ref={ref}
        className={`w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white
                    focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400 ${className}`}
      >
        {children}
      </select>
    )
  },
)

export default function EmployeeFormPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const isEditing = Boolean(id)
  const employeeId = id ?? null
  // Guard against malformed route params (e.g. "new" leaking into edit route)
  const validId = employeeId

  // Build schema: gov IDs and bank details are required only on create
  const schema = useMemo(
    () =>
      isEditing
        ? employeeSchema
        : employeeSchema.extend({
            sss_no:          z.string().min(1, 'SSS No. is required').max(12),
            tin:             z.string().min(1, 'TIN is required').max(15),
            philhealth_no:   z.string().min(1, 'PhilHealth No. is required').max(14),
            pagibig_no:      z.string().min(1, 'Pag-IBIG No. is required').max(14),
            bank_name:       z.string().min(1, 'Bank name is required').max(100),
            bank_account_no: z.string().min(1, 'Bank account number is required').max(30),
          }),
    [isEditing],
  )

  const {
    data: existing,
    isLoading: loadingEmployee,
    isError: employeeError,
    refetch: refetchEmployee,
  } = useEmployee(isEditing ? validId : null, { staleTime: 0, refetchOnMount: 'always' })
  const { data: salaryGrades = [], isLoading: loadingSalaryGrades } = useSalaryGrades()
  const { data: deptData, isLoading: loadingDepartments } = useDepartments()
  const { data: employeesData } = useEmployees({ page: 1, per_page: 1000, is_active: true })
  
  // Track when all reference data is loaded to avoid race conditions
  const referenceDataLoaded = !loadingSalaryGrades && !loadingDepartments

  const createMutation = useCreateEmployee()
  const updateMutation = useUpdateEmployee(validId ?? '')
  const { data: shiftsData } = useShifts(true)
  const assignShiftMutation = useAssignShift()

  const {
    register,
    handleSubmit,
    setError,
    setValue,
    watch,
    reset,
    formState: { errors, isSubmitting, isDirty },
  } = useForm<EmployeeFormData>({
    resolver: zodResolver(schema),
    mode: 'onBlur',
    defaultValues: {
      first_name: '',
      last_name: '',
      middle_name: '',
      suffix: '',
      date_of_birth: '',
      gender: undefined,
      civil_status: '',
      employment_type: undefined,
      pay_basis: 'monthly',
      basic_monthly_rate: 0,
      date_hired: new Date().toISOString().split('T')[0],
      salary_grade_id: undefined,
      department_id: undefined,
      position_id: undefined,
      reports_to: undefined,
      personal_email: '',
      personal_phone: '',
      citizenship: 'Filipino',
      present_address: '',
      permanent_address: '',
      bank_name: '',
      bank_account_no: '',
      notes: '',
    },
  })

  // Use reset() instead of values prop to properly handle async data loading
  // This fixes the issue where department/salary grade fields are empty on first load
  // IMPORTANT: Only reset after BOTH employee data AND reference data are loaded
  // to ensure select dropdowns have the matching options available
  useEffect(() => {
    if (existing && referenceDataLoaded) {
      reset({
        first_name:         existing.first_name,
        last_name:          existing.last_name,
        middle_name:        existing.middle_name ?? '',
        suffix:             existing.suffix ?? '',
        date_of_birth:      existing.date_of_birth ?? '',
        gender:             existing.gender,
        civil_status:       (existing.civil_status ?? '') as '' | 'SINGLE' | 'MARRIED' | 'WIDOWED' | 'LEGALLY_SEPARATED' | 'HEAD_OF_FAMILY',
        employment_type:    existing.employment_type,
        pay_basis:          existing.pay_basis,
        basic_monthly_rate: existing.basic_monthly_rate / 100,
        date_hired:         existing.date_hired,
        salary_grade_id:    existing.salary_grade?.id,
        department_id:      existing.department_id ?? undefined,
        position_id:        existing.position_id ?? undefined,
        reports_to:         existing.reports_to ?? undefined,
        shift_schedule_id:  existing.current_shift?.shift_schedule_id ?? undefined,
        personal_email:     existing.personal_email ?? '',
        personal_phone:     existing.personal_phone ?? '',
        citizenship:        existing.citizenship ?? '',
        present_address:    existing.present_address ?? '',
        permanent_address:  existing.permanent_address ?? '',
        bank_name:          existing.bank_name ?? '',
        bank_account_no:    existing.bank_account_no ?? '',
        notes:              existing.notes ?? '',
        // Government IDs: Don't pre-fill encrypted values, leave empty to keep existing
        sss_no:             '',
        tin:                '',
        philhealth_no:      '',
        pagibig_no:         '',
      })
    }
  }, [existing, referenceDataLoaded, reset])

  // Watch fields for live derived displays
  const selectedDepartmentId = watch('department_id')
  const selectedDeptId = Number(selectedDepartmentId) || undefined
  const prevDeptIdRef = useRef<typeof selectedDeptId>(undefined)
  const watchedGradeId    = watch('salary_grade_id')
  const watchedMonthlyRate = watch('basic_monthly_rate')

  const selectedGrade = salaryGrades.find((g) => g.id === Number(watchedGradeId))

  // Auto-fill rate from grade midpoint when grade changes (add-mode or explicit change)
  useEffect(() => {
    if (!watchedGradeId || !selectedGrade) return
    const midpoint = Math.round((selectedGrade.min_monthly_rate + selectedGrade.max_monthly_rate) / 2 / 100)
    setValue('basic_monthly_rate', midpoint, { shouldValidate: true })
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [watchedGradeId])

  // Reset position when department changes so a position from the old dept isn't submitted.
  // Guard: only reset when the user actively switches departments (prevDeptId was already
  // defined), not on the initial values-sync when loading an existing employee.
  useEffect(() => {
    const prev = prevDeptIdRef.current
    prevDeptIdRef.current = selectedDeptId
    if (prev !== undefined && prev !== selectedDeptId) {
      setValue('position_id', undefined)
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedDeptId])

  const watchedPresentAddress = watch('present_address')

  // Derived daily rate for the live preview (÷22)
  const monthlyPesos  = Number(watchedMonthlyRate) || 0
  const dailyPesos    = monthlyPesos > 0 ? (monthlyPesos / 22).toFixed(2)  : null

  const { data: positionsData, isLoading: positionsLoading } = usePositions()
  const currentPositionId = existing?.position_id
  const currentPosition = existing?.position
  const currentDepartment = existing?.department
  
  // Build position list: active positions for selected dept + current position
  const allPositions = positionsData?.data ?? []
  const deptPositions = allPositions.filter(
    (p) => p.is_active && p.department_id === selectedDeptId
  )
  
  // Include current position if not already in the list
  const filteredPositions = currentPosition && !deptPositions.find(p => p.id === currentPosition.id)
    ? [...deptPositions, { ...currentPosition, is_active: true }]
    : deptPositions

  // Filter supervisors: only managers/supervisors from the same department
  const supervisors = useMemo(() => {
    const allEmployees = employeesData?.data ?? []
    return allEmployees.filter((e) => {
      // Exclude current employee being edited (compare by ULID since validId is a ULID string)
      if (e.ulid === validId) return false
      // Must have manager or head role
      const supervisorRoles = ['manager', 'head']
      const hasManagerRole = e.user_roles.some(role =>
        supervisorRoles.includes(role.toLowerCase())
      )
      if (!hasManagerRole) return false
      // Must be in the same department (or if no department selected, show all managers)
      if (!selectedDeptId) return true
      return e.department_id === selectedDeptId
    })
  }, [employeesData?.data, validId, selectedDeptId])

  // Show loading state while either employee data or reference data is loading
  if (isEditing && (loadingEmployee || loadingSalaryGrades || loadingDepartments)) {
    return <SkeletonLoader rows={8} />
  }

  if (isEditing && employeeError) {
    return (
      <div className="max-w-4xl">
        <button type="button" onClick={() => navigate(-1)} className="text-sm text-neutral-600 hover:underline mb-4 block">← Back</button>
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
          <p className="text-red-700 font-medium mb-1">Failed to load employee data</p>
          <p className="text-red-600 text-sm mb-4">The record may not exist or you may not have permission to edit it.</p>
          <button
            type="button"
            onClick={() => void refetchEmployee()}
            className="px-4 py-2 bg-neutral-900 text-white text-sm rounded hover:bg-neutral-800 transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    )
  }

  const onSubmit = async (data: EmployeeFormData) => {
    // Build payload: exclude empty government IDs when editing (keep existing values)
    const payload: Record<string, unknown> = {
      ...data,
      basic_monthly_rate: Math.round(data.basic_monthly_rate * 100),
      civil_status: data.civil_status || null,
    }
    // Remove shift_schedule_id from employee payload — handled via separate assignment endpoint
    delete payload.shift_schedule_id

    // When editing, don't send empty strings for government IDs (keep existing encrypted values)
    if (isEditing) {
      if (!payload.sss_no) delete payload.sss_no
      if (!payload.tin) delete payload.tin
      if (!payload.philhealth_no) delete payload.philhealth_no
      if (!payload.pagibig_no) delete payload.pagibig_no
    }
    try {
      if (isEditing && validId) {
        await updateMutation.mutateAsync(payload as unknown as Partial<CreateEmployeePayload>)
        // If shift changed, create a new assignment (effective today)
        if (
          data.shift_schedule_id &&
          Number(data.shift_schedule_id) !== existing?.current_shift?.shift_schedule_id
        ) {
          await assignShiftMutation.mutateAsync({
            employee_ulid: existing!.ulid,
            shift_schedule_id: Number(data.shift_schedule_id),
            effective_from: new Date().toISOString().split('T')[0],
          })
        }
        toast.success('Employee updated.')
      } else {
        const newEmployee = await createMutation.mutateAsync(payload as unknown as CreateEmployeePayload)
        if (data.shift_schedule_id) {
          await assignShiftMutation.mutateAsync({
            employee_ulid: newEmployee.ulid,
            shift_schedule_id: Number(data.shift_schedule_id),
            effective_from: data.date_hired,
          })
        }
        toast.success('Employee created.')
      }
      navigate('/hr/employees/all')
    } catch (err: unknown) {
      const apiErr = err as ApiError
      if (apiErr.errors) {
        Object.entries(apiErr.errors).forEach(([field, msgs]) => {
          setError(field as keyof EmployeeFormData, { message: msgs[0] })
        })
      }
      // Show general error message (e.g., duplicate gov ID)
      if (apiErr.message && !apiErr.errors) {
        alert(apiErr.message)
      }
    }
  }

  return (
    <div className="max-w-4xl">
      <div className="mb-6">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="text-sm text-neutral-600 hover:underline mb-2 block"
        >
          ← Back
        </button>
        <h1 className="text-lg font-semibold text-neutral-900">
          {isEditing ? 'Edit Employee' : 'Add Employee'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        {/* Personal */}
        <section className="bg-white border border-neutral-200 rounded-lg p-5">
          <h2 className="text-sm font-semibold text-neutral-700 mb-4">Personal Information</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="First Name" required error={errors.first_name?.message}>
              <Input {...register('first_name')} placeholder="Juan" />
            </FormField>
            <FormField label="Last Name" required error={errors.last_name?.message}>
              <Input {...register('last_name')} placeholder="Cruz" />
            </FormField>
            <FormField label="Middle Name" error={errors.middle_name?.message}>
              <Input {...register('middle_name')} placeholder="Santos" />
            </FormField>
            <FormField label="Suffix" error={errors.suffix?.message}>
              <Input {...register('suffix')} placeholder="Jr." />
            </FormField>
            <FormField label="Date of Birth" error={errors.date_of_birth?.message}>
              <Input type="date" {...register('date_of_birth')} />
            </FormField>
            <FormField label="Gender" required error={errors.gender?.message}>
              <Select {...register('gender')}>
                <option value="">Select…</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </Select>
            </FormField>
            <FormField label="Civil Status" error={errors.civil_status?.message}>
              <Select {...register('civil_status')}>
                <option value="">Select…</option>
                <option value="SINGLE">Single</option>
                <option value="MARRIED">Married</option>
                <option value="WIDOWED">Widowed</option>
                <option value="LEGALLY_SEPARATED">Legally Separated</option>
                <option value="HEAD_OF_FAMILY">Head of Family</option>
              </Select>
            </FormField>
            <FormField label="Personal Email" error={errors.personal_email?.message}>
              <Input type="email" {...register('personal_email')} placeholder="juan@email.com" />
            </FormField>
            <FormField label="Personal Phone" error={errors.personal_phone?.message}>
              <Input {...register('personal_phone')} placeholder="+63 9XX XXX XXXX" />
            </FormField>
            <FormField label="Citizenship" error={errors.citizenship?.message}>
              <Input {...register('citizenship')} placeholder="Filipino" />
            </FormField>
            <FormField label="Present Address" error={errors.present_address?.message}>
              <Input {...register('present_address')} placeholder="Street, Barangay, City/Municipality, Province" />
            </FormField>
            <div className="sm:col-span-2">
              <FormField label="Permanent Address" error={errors.permanent_address?.message}>
                <div className="space-y-1">
                  <Input {...register('permanent_address')} placeholder="Street, Barangay, City/Municipality, Province" />
                  <button
                    type="button"
                    onClick={() => {
                      if (watchedPresentAddress) setValue('permanent_address', watchedPresentAddress, { shouldDirty: true, shouldValidate: true })
                    }}
                    className="text-xs text-neutral-600 hover:underline"
                  >
                    Same as present address
                  </button>
                </div>
              </FormField>
            </div>
          </div>
        </section>

        {/* Employment */}
        <section className="bg-white border border-neutral-200 rounded-lg p-5">
          <h2 className="text-sm font-semibold text-neutral-700 mb-4">Employment Details</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Employment Type" required error={errors.employment_type?.message}>
              <Select {...register('employment_type')}>
                <option value="">Select…</option>
                <option value="regular">Regular</option>
                <option value="probationary">Probationary</option>
                <option value="contractual">Contractual</option>
                <option value="project_based">Project-Based</option>
                <option value="casual">Casual</option>
              </Select>
            </FormField>
            <FormField label="Date Hired" required error={errors.date_hired?.message}>
              <Input type="date" {...register('date_hired')} />
            </FormField>

            {/* ── Compensation ── */}
            <FormField
              label="Pay Basis"
              required
              hint="How attendance is counted for payroll: Monthly = fixed regardless of days worked, Daily = rate ÷ 22 working days."
              error={errors.pay_basis?.message}
            >
              <Select {...register('pay_basis')}>
                <option value="monthly">Monthly (fixed salary)</option>
                <option value="daily">Daily (÷ 22 days/month)</option>
              </Select>
            </FormField>
            <FormField
              label="Salary Grade"
              hint={
                selectedGrade
                  ? `Range: ₱${(selectedGrade.min_monthly_rate / 100).toLocaleString()} – ₱${(selectedGrade.max_monthly_rate / 100).toLocaleString()} /mo — rate auto-filled to midpoint, adjust freely.`
                  : 'Optional. Pick a grade to auto-fill the rate and set a valid range.'
              }
              error={errors.salary_grade_id?.message}
            >
              <Select {...register('salary_grade_id')}>
                <option value="">No grade (enter rate manually)</option>
                {salaryGrades.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.code} — {g.name} (₱{(g.min_monthly_rate / 100).toLocaleString()}–₱{(g.max_monthly_rate / 100).toLocaleString()})
                  </option>
                ))}
              </Select>
            </FormField>

            <div className="sm:col-span-2">
              <FormField
                label="Basic Monthly Rate (₱)"
                required
                hint={
                  dailyPesos
                    ? `= ₱${Number(dailyPesos).toLocaleString(undefined, { minimumFractionDigits: 2 })}/day — always enter as the monthly total, even for daily-rate employees.`
                    : 'Always enter as gross monthly total. The system derives the daily rate automatically.'
                }
                error={errors.basic_monthly_rate?.message}
              >
                <Input
                  type="number"
                  min={0.01}
                  step={0.01}
                  {...register('basic_monthly_rate')}
                  placeholder="20000.00"
                />
              </FormField>
            </div>

            {/* ── Placement ── */}
            <FormField label="Department" error={errors.department_id?.message}>
              <Select {...register('department_id')}>
                <option value="">None</option>
                {/* Include current department if not in list yet */}
                {currentDepartment && !(deptData?.data ?? []).find(d => d.id === currentDepartment.id) && (
                  <option key={currentDepartment.id} value={currentDepartment.id}>{currentDepartment.name}</option>
                )}
                {(deptData?.data ?? []).map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </Select>
            </FormField>
            <FormField label="Position" error={errors.position_id?.message}>
              <Select 
                {...register('position_id')} 
                disabled={positionsLoading && !currentPositionId}
              >
                <option value="">
                  {positionsLoading && !currentPosition
                    ? 'Loading positions…'
                    : selectedDepartmentId
                      ? filteredPositions.length === 0
                        ? 'No positions for this department'
                        : 'Select position…'
                      : 'Select a department first'}
                </option>
                {filteredPositions.map((p) => (
                  <option key={p.id} value={p.id}>{p.title}</option>
                ))}
              </Select>
            </FormField>
            <FormField label="Reporting Structure (Supervisor)" error={errors.reports_to?.message}>
              <Select {...register('reports_to')}>
                <option value="">None / Self-managed</option>
                {supervisors.map((s) => {
                  const supervisorRoles = ['manager', 'head']
                  const supervisorRole = s.user_roles.find(r => supervisorRoles.includes(r.toLowerCase()))
                  const roleLabel = supervisorRole === 'manager' ? 'Manager' : 'Head'
                  return (
                    <option key={s.id} value={s.id}>
                      {s.first_name} {s.last_name} ({roleLabel})
                    </option>
                  )
                })}
              </Select>
            </FormField>

            {/* ── Shift Schedule ── */}
            <FormField
              label="Shift Schedule"
              hint={isEditing ? 'Changing this creates a new assignment effective today.' : 'Assign an initial work schedule for this employee.'}
              error={errors.shift_schedule_id?.message}
            >
              <Select {...register('shift_schedule_id')}>
                <option value="">— No schedule assigned —</option>
                {(shiftsData?.data ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </FormField>
          </div>
        </section>

        {/* Government IDs */}
        <section className="bg-white border border-neutral-200 rounded-lg p-5">
          <h2 className="text-sm font-semibold text-neutral-700 mb-4">Government IDs</h2>
          <p className="text-xs text-neutral-500 mb-4">
            IDs are <span className="font-medium">encrypted at rest</span> and never exposed after saving.
            {isEditing && ' Leave a field blank to keep the current saved value.'}
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField
              label="SSS No."
              required={!isEditing}
              hint={isEditing && existing?.has_sss_no ? '🔒 Encrypted value on file — enter new value to replace' : undefined}
              error={errors.sss_no?.message}
            >
              <Input {...register('sss_no')} placeholder={isEditing && existing?.has_sss_no ? '(unchanged)' : '03-XXXXXXX-X'} />
            </FormField>
            <FormField
              label="TIN"
              required={!isEditing}
              hint={isEditing && existing?.has_tin ? '🔒 Encrypted value on file — enter new value to replace' : undefined}
              error={errors.tin?.message}
            >
              <Input {...register('tin')} placeholder={isEditing && existing?.has_tin ? '(unchanged)' : 'XXX-XXX-XXX-XXX'} />
            </FormField>
            <FormField
              label="PhilHealth No."
              required={!isEditing}
              hint={isEditing && existing?.has_philhealth_no ? '🔒 Encrypted value on file — enter new value to replace' : undefined}
              error={errors.philhealth_no?.message}
            >
              <Input {...register('philhealth_no')} placeholder={isEditing && existing?.has_philhealth_no ? '(unchanged)' : 'XX-XXXXXXXXX-X'} />
            </FormField>
            <FormField
              label="Pag-IBIG No."
              required={!isEditing}
              hint={isEditing && existing?.has_pagibig_no ? '🔒 Encrypted value on file — enter new value to replace' : undefined}
              error={errors.pagibig_no?.message}
            >
              <Input {...register('pagibig_no')} placeholder={isEditing && existing?.has_pagibig_no ? '(unchanged)' : 'XXXX-XXXX-XXXX'} />
            </FormField>
          </div>
        </section>

        {/* Bank */}
        <section className="bg-white border border-neutral-200 rounded-lg p-5">
          <h2 className="text-sm font-semibold text-neutral-700 mb-4">Bank Disbursement</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Bank Name" required={!isEditing} error={errors.bank_name?.message}>
              <Input {...register('bank_name')} placeholder="BDO, BPI, UnionBank…" />
            </FormField>
            <FormField label="Account No." required={!isEditing} error={errors.bank_account_no?.message}>
              <Input {...register('bank_account_no')} />
            </FormField>
          </div>
          <div className="mt-4">
            <FormField label="Notes" error={errors.notes?.message}>
              <textarea
                {...register('notes')}
                rows={3}
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm resize-none
                           focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400"
                placeholder="Internal notes…"
              />
            </FormField>
          </div>
        </section>

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-5 py-2 text-sm border border-neutral-300 rounded text-neutral-700 hover:bg-neutral-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting || (isEditing && !isDirty)}
            className="px-5 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800
                       disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {isSubmitting ? 'Saving…' : isEditing ? 'Save Changes' : 'Create Employee'}
          </button>
        </div>
      </form>
    </div>
  )
}
