import { useParams, Link } from 'react-router-dom'
import { useEmployee, useEmployeeTransition } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { EmployeeProfileView } from '@/components/employee'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { toast } from 'sonner'
import { useSodCheck } from '@/hooks/useSodCheck'
import type { EmploymentStatus } from '@/types/hr'
import { Edit3 } from 'lucide-react'

const ALLOWED_TRANSITIONS: Record<EmploymentStatus, EmploymentStatus[]> = {
  draft:      ['active'],
  active:     ['on_leave', 'suspended', 'resigned', 'terminated'],
  on_leave:   ['active', 'resigned', 'terminated'],
  suspended:  ['active', 'resigned', 'terminated'],
  resigned:   [],
  terminated: [],
}

function statusLabel(s: string) {
  return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

export default function EmployeeDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const employeeId = id ?? null

  const { data: employee, isLoading, isError } = useEmployee(employeeId)
  const transitionMutation = useEmployeeTransition(employeeId!)
  const { isBlocked: activateBlocked, reason: activateReason } = useSodCheck(
    employee?.created_by_id ?? null,
  )

  if (isLoading) return <SkeletonLoader rows={12} />

  if (isError || !employee) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Employee not found or failed to load.
      </div>
    )
  }

  const allowedNext = ALLOWED_TRANSITIONS[employee.employment_status] ?? []

  // Build action buttons for HR view
  const actions = (
    <>
      <Link
        to={`/hr/employees/${employee.ulid}/edit`}
        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 transition-colors"
      >
        <Edit3 className="h-4 w-4" />
        Edit Profile
      </Link>
      
      {allowedNext.length > 0 && (
        <div className="flex gap-1">
          {allowedNext.map((state) => {
            const isSodBlocked = state === 'active' && activateBlocked
            return (
              <ConfirmDestructiveDialog
                key={state}
                title={`Change Status to "${statusLabel(state)}"?`}
                description={`This will transition ${employee.full_name}'s employment status to "${statusLabel(state)}". The action is recorded in the audit log.`}
                confirmWord={state.toUpperCase().replace('_', '')}
                confirmLabel={`Set ${statusLabel(state)}`}
                onConfirm={async () => {
                  try {
                    await transitionMutation.mutateAsync(state)
                    toast.success(`Status changed to "${statusLabel(state)}".`)
                  } catch {
                    toast.error('Failed to change employee status.')
                  }
                }}
              >
                <button
                  disabled={isSodBlocked}
                  title={isSodBlocked ? activateReason : undefined}
                  className="px-3 py-2 text-xs border border-neutral-300 rounded text-neutral-700 hover:bg-neutral-50 transition-colors capitalize disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  → {statusLabel(state)}
                </button>
              </ConfirmDestructiveDialog>
            )
          })}
        </div>
      )}
    </>
  )

  return (
    <div className="max-w-7xl">
      <ExecutiveReadOnlyBanner permission="employees.transition" />
      <EmployeeProfileView
        employee={employee}
        viewContext="hr"
        backTo="/hr/employees/all"
        backLabel="Back to Employees"
        actions={actions}
      />
    </div>
  )
}
