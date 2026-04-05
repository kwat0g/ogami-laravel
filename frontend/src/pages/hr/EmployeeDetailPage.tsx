import { useParams, Link } from 'react-router-dom'
import { useEmployee, useEmployeeTransition, useDeleteEmployee } from '@/hooks/useEmployees'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmployeeProfileView } from '@/components/employee'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useSodCheck } from '@/hooks/useSodCheck'
import type { EmploymentStatus } from '@/types/hr'
import { Edit3, ChevronDown, Trash2 } from 'lucide-react'
import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'

const ALLOWED_TRANSITIONS: Record<EmploymentStatus, EmploymentStatus[]> = {
  draft: ['active'],
  active: ['on_leave', 'suspended', 'resigned', 'terminated'],
  on_leave: ['active', 'resigned', 'terminated'],
  suspended: ['active', 'resigned', 'terminated'],
  resigned: [],
  terminated: [],
}

// Status display config for dropdown button
const statusConfig: Record<EmploymentStatus, { label: string; color: string }> = {
  active: { label: 'Active', color: 'bg-green-600 hover:bg-green-700' },
  on_leave: { label: 'On Leave', color: 'bg-blue-600 hover:bg-blue-700' },
  suspended: { label: 'Suspended', color: 'bg-amber-600 hover:bg-amber-700' },
  resigned: { label: 'Resigned', color: 'bg-red-600 hover:bg-red-700' },
  terminated: { label: 'Terminated', color: 'bg-red-600 hover:bg-red-700' },
  draft: { label: 'Draft', color: 'bg-neutral-600 hover:bg-neutral-700' },
}

// Color coding for status transitions
const transitionColors: Record<EmploymentStatus, string> = {
  active: 'bg-green-600 text-white hover:bg-green-700',
  on_leave: 'bg-blue-600 text-white hover:bg-blue-700',
  suspended: 'bg-amber-600 text-white hover:bg-amber-700',
  resigned: 'bg-red-600 text-white hover:bg-red-700',
  terminated: 'bg-red-600 text-white hover:bg-red-700',
  draft: 'bg-neutral-600 text-white hover:bg-neutral-700',
}

function statusLabel(s: string | undefined | null) {
  if (!s) return '—'
  return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function getMissingOnboardingItems(employee: {
  date_of_birth: string | null
  present_address: string | null
  personal_email: string | null
  personal_phone: string | null
  has_sss_no: boolean
  has_tin: boolean
  has_philhealth_no: boolean
  has_pagibig_no: boolean
}) {
  const missing: string[] = []

  if (!employee.date_of_birth) missing.push('Date of birth')
  if (!employee.present_address) missing.push('Present address')
  if (!employee.personal_email) missing.push('Personal email')
  if (!employee.personal_phone) missing.push('Personal phone')
  if (!employee.has_sss_no) missing.push('SSS number')
  if (!employee.has_tin) missing.push('TIN')
  if (!employee.has_philhealth_no) missing.push('PhilHealth number')
  if (!employee.has_pagibig_no) missing.push('Pag-IBIG number')

  return missing
}

export default function EmployeeDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { hasPermission } = useAuthStore()
  const canEdit = hasPermission(PERMISSIONS.employees.update)
  const canDelete = hasPermission(PERMISSIONS.employees.delete)
  const canTransition = hasPermission(PERMISSIONS.employees.suspend) || hasPermission(PERMISSIONS.employees.terminate)
  const employeeId = id ?? null

  const { data: employee, isLoading, isError, refetch } = useEmployee(employeeId)
  const transitionMutation = useEmployeeTransition(employeeId!)
  const deleteMutation = useDeleteEmployee()
  const { isBlocked: activateBlocked, reason: activateReason } = useSodCheck(
    employee?.created_by_id ?? null,
  )
  const [isDropdownOpen, setIsDropdownOpen] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsDropdownOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const handleDelete = async () => {
    if (!employee) return
    try {
      await deleteMutation.mutateAsync(employee.ulid)
      toast.success('Employee deleted successfully')
      navigate('/hr/employees/all')
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      throw err
    }
  }

  const handleTransition = async (state: EmploymentStatus) => {
    try {
      await transitionMutation.mutateAsync(state)
      toast.success(`Status changed to "${statusLabel(state)}"`)
      setIsDropdownOpen(false)
      refetch()
    } catch (err: unknown) {
      const message = firstErrorMessage(err)
      throw err
    }
  }

  if (isLoading) return <SkeletonLoader rows={12} />

  if (isError || !employee) {
    return <div className="text-red-600 text-sm mt-4">Employee not found or failed to load.</div>
  }

  const allowedNext = ALLOWED_TRANSITIONS[employee.employment_status] ?? []
  const missingOnboardingItems = getMissingOnboardingItems(employee)
  const showOnboardingBanner = !employee.is_active || employee.onboarding_status === 'documents_pending'

  // Build action buttons for HR view
  const actions = (
    <>
      {canEdit && (
        <Link
          to={`/hr/employees/${employee.ulid}/edit`}
          className="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 transition-colors"
        >
          <Edit3 className="h-4 w-4" />
          Edit Profile
        </Link>
      )}

      {canDelete && (
        <ConfirmDestructiveDialog
          title="Delete Employee?"
          description={`This will permanently delete ${employee.full_name}'s record. This action cannot be undone.`}
          confirmWord="DELETE"
          confirmLabel="Delete Employee"
          onConfirm={handleDelete}
        >
          <button
            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
            disabled={deleteMutation.isPending}
          >
            <Trash2 className="h-4 w-4" />
            Delete
          </button>
        </ConfirmDestructiveDialog>
      )}

      {allowedNext.length > 0 && canTransition && (
        <div className="relative" ref={dropdownRef}>
          <button
            onClick={() => setIsDropdownOpen(!isDropdownOpen)}
            className={`inline-flex items-center gap-1.5 px-4 py-2 text-sm text-white rounded transition-colors ${statusConfig[employee.employment_status].color}`}
          >
            {statusConfig[employee.employment_status].label}
            <ChevronDown
              className={`h-4 w-4 transition-transform ${isDropdownOpen ? 'rotate-180' : ''}`}
            />
          </button>

          {isDropdownOpen && (
            <div className="absolute right-0 mt-1 w-48 bg-white border border-neutral-200 rounded-lg shadow-lg z-50 py-1">
              <div className="px-3 py-2 text-xs text-neutral-500 border-b border-neutral-100">
                Change Status to:
              </div>
              {allowedNext.map((state) => {
                const isSodBlocked = state === 'active' && activateBlocked
                const isDestructive = state === 'resigned' || state === 'terminated'
                
                return isDestructive ? (
                  <ConfirmDestructiveDialog
                    key={state}
                    title={`${statusLabel(state)} Employee?`}
                    description={`This will mark ${employee.full_name} as ${statusLabel(state)}. This action cannot be undone.`}
                    confirmWord={state.toUpperCase()}
                    confirmLabel={`Set ${statusLabel(state)}`}
                    onConfirm={() => handleTransition(state)}
                  >
                    <button
                      disabled={isSodBlocked}
                      title={isSodBlocked ? (activateReason ?? undefined) : undefined}
                      className={`w-full text-left px-3 py-2 text-sm transition-colors hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 ${
                        isSodBlocked ? 'text-neutral-400' : 'text-neutral-700'
                      }`}
                      onClick={(e) => {
                        if (isSodBlocked) {
                          e.preventDefault()
                        }
                      }}
                    >
                      <span
                        className={`w-2 h-2 rounded-full ${transitionColors[state].split(' ')[0].replace('bg-', 'bg-')}`}
                      />
                      {statusLabel(state)}
                    </button>
                  </ConfirmDestructiveDialog>
                ) : (
                  <ConfirmDialog
                    key={state}
                    title={`Change Status to "${statusLabel(state)}"?`}
                    description={`This will transition ${employee.full_name}'s employment status to "${statusLabel(state)}". The action is recorded in the audit log.`}
                    confirmLabel={`Set ${statusLabel(state)}`}
                    variant={state === 'suspended' ? 'danger' : 'default'}
                    onConfirm={() => handleTransition(state)}
                  >
                    <button
                      disabled={isSodBlocked}
                      title={isSodBlocked ? (activateReason ?? undefined) : undefined}
                      className={`w-full text-left px-3 py-2 text-sm transition-colors hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 ${
                        isSodBlocked ? 'text-neutral-400' : 'text-neutral-700'
                      }`}
                      onClick={(e) => {
                        if (isSodBlocked) {
                          e.preventDefault()
                        }
                      }}
                    >
                      <span
                        className={`w-2 h-2 rounded-full ${transitionColors[state].split(' ')[0].replace('bg-', 'bg-')}`}
                      />
                      {statusLabel(state)}
                    </button>
                  </ConfirmDialog>
                )
              })}
            </div>
          )}
        </div>
      )}
    </>
  )

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader title="Employee Details" backTo="/hr/employees/all" />
      <ExecutiveReadOnlyBanner />
      {showOnboardingBanner && (
        <div className="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
          <p className="font-semibold">Employee is inactive until onboarding details are complete.</p>
          {missingOnboardingItems.length > 0 ? (
            <p className="mt-1">Missing: {missingOnboardingItems.join(', ')}</p>
          ) : (
            <p className="mt-1">Record is marked as pending activation.</p>
          )}
        </div>
      )}
      <EmployeeProfileView employee={employee} viewContext="hr" actions={actions} />
    </div>
  )
}
