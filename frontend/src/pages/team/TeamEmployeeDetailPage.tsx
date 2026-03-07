import { useParams } from 'react-router-dom'
import { useEmployee } from '@/hooks/useEmployees'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { EmployeeProfileView } from '@/components/employee'

export default function TeamEmployeeDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const employeeId = id ?? null

  const { data: employee, isLoading, isError } = useEmployee(employeeId)

  if (isLoading) return <SkeletonLoader rows={12} />

  if (isError || !employee) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Employee not found or failed to load.
      </div>
    )
  }

  return (
    <EmployeeProfileView
      employee={employee}
      viewContext="team"
      backTo="/team/employees"
      backLabel="Back to My Team"
    />
  )
}
