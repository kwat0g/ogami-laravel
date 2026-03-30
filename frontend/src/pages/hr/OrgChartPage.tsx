import { useMemo } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useEmployees, useDepartments } from '@/hooks/useEmployees'

interface Employee {
  id: number
  ulid: string
  first_name: string
  last_name: string
  full_name: string
  position_title?: string
  department_name?: string
  department_id?: number
  employment_status: string
  reports_to?: number
  user_roles: string[]
}

interface DeptNode {
  id: number
  name: string
  code: string
  employees: Employee[]
  children: DeptNode[]
}

export default function OrgChartPage() {
  const { data: employeesData, isLoading: empLoading } = useEmployees({ per_page: 500, employment_status: 'active' })
  const { data: departmentsData, isLoading: deptLoading } = useDepartments()

  const employees: Employee[] = employeesData?.data ?? []
  const departments = departmentsData?.data ?? []

  // Build department tree with employees
  const tree = useMemo(() => {
    const deptMap = new Map<number, DeptNode>()

    for (const dept of departments) {
      deptMap.set(dept.id, {
        id: dept.id,
        name: dept.name,
        code: dept.code,
        employees: [],
        children: [],
      })
    }

    // Assign employees to departments
    for (const emp of employees) {
      if (emp.department_id && deptMap.has(emp.department_id)) {
        deptMap.get(emp.department_id)!.employees.push(emp)
      }
    }

    // Build tree (parent-child)
    const roots: DeptNode[] = []
    for (const dept of departments) {
      const node = deptMap.get(dept.id)!
      if (dept.parent_department_id && deptMap.has(dept.parent_department_id)) {
        deptMap.get(dept.parent_department_id)!.children.push(node)
      } else {
        roots.push(node)
      }
    }

    return roots
  }, [employees, departments])

  const isLoading = empLoading || deptLoading

  const roleColors: Record<string, string> = {
    manager: 'bg-purple-100 text-purple-700',
    head: 'bg-blue-100 text-blue-700',
    officer: 'bg-indigo-100 text-indigo-700',
    staff: 'bg-neutral-100 text-neutral-600',
  }

  function DeptCard({ dept, level = 0 }: { dept: DeptNode; level?: number }) {
    const managers = dept.employees.filter(e => e.user_roles.some(r => ['manager', 'head'].includes(r.toLowerCase())))
    const staff = dept.employees.filter(e => !e.user_roles.some(r => ['manager', 'head'].includes(r.toLowerCase())))

    return (
      <div className={`${level > 0 ? 'ml-6 border-l-2 border-neutral-200 pl-4' : ''}`}>
        <div className="bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 mb-3">
          <div className="flex items-center justify-between mb-2">
            <div>
              <h3 className="font-semibold text-neutral-900 dark:text-neutral-100">{dept.name}</h3>
              <span className="text-xs text-neutral-500 font-mono">{dept.code}</span>
            </div>
            <span className="text-xs bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 px-2 py-1 rounded">
              {dept.employees.length} members
            </span>
          </div>

          {managers.length > 0 && (
            <div className="mb-2">
              {managers.map(emp => (
                <div key={emp.id} className="flex items-center gap-2 py-1">
                  <div className="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-medium">
                    {emp.first_name[0]}{emp.last_name[0]}
                  </div>
                  <div>
                    <p className="text-sm font-medium">{emp.full_name}</p>
                    <p className="text-xs text-neutral-500">{emp.position_title ?? 'Manager'}</p>
                  </div>
                  {emp.user_roles.map(role => (
                    <span key={role} className={`text-xs px-1.5 py-0.5 rounded ${roleColors[role.toLowerCase()] ?? 'bg-neutral-100'}`}>
                      {role}
                    </span>
                  ))}
                </div>
              ))}
            </div>
          )}

          {staff.length > 0 && (
            <div className="border-t border-neutral-100 dark:border-neutral-700 pt-2 mt-2">
              <p className="text-xs text-neutral-500 mb-1">{staff.length} staff member{staff.length > 1 ? 's' : ''}</p>
              <div className="flex flex-wrap gap-1">
                {staff.slice(0, 8).map(emp => (
                  <div key={emp.id} className="w-7 h-7 rounded-full bg-neutral-200 dark:bg-neutral-600 text-neutral-600 dark:text-neutral-300 flex items-center justify-center text-xs" title={emp.full_name}>
                    {emp.first_name[0]}{emp.last_name[0]}
                  </div>
                ))}
                {staff.length > 8 && (
                  <div className="w-7 h-7 rounded-full bg-neutral-300 text-neutral-700 flex items-center justify-center text-xs">
                    +{staff.length - 8}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {dept.children.map(child => (
          <DeptCard key={child.id} dept={child} level={level + 1} />
        ))}
      </div>
    )
  }

  return (
    <div className="p-6 space-y-6">
      <PageHeader title="Organization Chart" />

      <div className="grid grid-cols-3 gap-4 mb-4">
        <div className="bg-white dark:bg-neutral-800 rounded-lg border p-4 text-center">
          <p className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{departments.length}</p>
          <p className="text-xs text-neutral-500 uppercase">Departments</p>
        </div>
        <div className="bg-white dark:bg-neutral-800 rounded-lg border p-4 text-center">
          <p className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{employees.length}</p>
          <p className="text-xs text-neutral-500 uppercase">Active Employees</p>
        </div>
        <div className="bg-white dark:bg-neutral-800 rounded-lg border p-4 text-center">
          <p className="text-2xl font-bold text-neutral-900 dark:text-neutral-100">
            {employees.filter(e => e.user_roles.some(r => ['manager', 'head'].includes(r.toLowerCase()))).length}
          </p>
          <p className="text-xs text-neutral-500 uppercase">Managers/Heads</p>
        </div>
      </div>

      {isLoading ? (
        <div className="animate-pulse space-y-4">{[1,2,3].map(i => <div key={i} className="h-24 bg-neutral-200 rounded" />)}</div>
      ) : tree.length > 0 ? (
        <div className="space-y-4">
          {tree.map(dept => <DeptCard key={dept.id} dept={dept} />)}
        </div>
      ) : (
        <div className="bg-white dark:bg-neutral-800 rounded-lg border p-8 text-center text-neutral-500">
          No departments found. Create departments and assign employees to see the org chart.
        </div>
      )}
    </div>
  )
}
