import { useParams, useNavigate } from 'react-router-dom'
import { useEmployee, useEmployeeTransition } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { EmployeeProfileView } from '@/components/employee'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { EmploymentStatus } from '@/types/hr'

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

export default function TeamEmployeeDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const _navigate = useNavigate()
  const employeeId = id ?? null

  const { data: employee, isLoading, isError } = useEmployee(employeeId)
  const transitionMutation = useEmployeeTransition(employeeId!)

  if (isLoading) return <SkeletonLoader rows={12} />

  if (isError || !employee) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Employee not found or failed to load.
      </div>
    )
  }

  const allowedNext = ALLOWED_TRANSITIONS[employee.employment_status] ?? []

  // Build action buttons for Team view (status transitions only)
  const actions = allowedNext.length > 0 && (
    <div className="flex flex-wrap gap-1">
      {allowedNext.map((state) => (
        <ConfirmDestructiveDialog
          key={state}
          title={`Change Status to "${statusLabel(state)}"?`}
          description={`This will transition ${employee.full_name}'s employment status to "${statusLabel(state)}". The action is recorded in the audit log.`}
          confirmWord={state.toUpperCase().replace('_', '')}
          confirmLabel={`Set ${statusLabel(state)}`}
          onConfirm={() => void transitionMutation.mutateAsync(state)}
        >
          <button
            className="px-3 py-2 text-xs border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors capitalize"
          >
            → {statusLabel(state)}
          </button>
        </ConfirmDestructiveDialog>
      ))}
    </div>
  )

  return (
    <EmployeeProfileView
      employee={employee}
      viewContext="team"
      backTo="/team/employees"
      backLabel="Back to My Team"
      actions={actions}
    />
  )
}
