import { useState } from 'react'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { firstErrorMessage } from '@/lib/errorHandler'
import { Wallet, Calendar, Edit2, Save, X } from 'lucide-react'
import api from '@/lib/api'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'

interface DepartmentBudget {
  id: number
  code: string
  name: string
  annual_budget_centavos: number
  fiscal_year_start_month: number
  is_active: boolean
}

// Hook to fetch department budgets
function useDepartmentBudgets() {
  return useQuery({
    queryKey: ['department-budgets'],
    queryFn: async () => {
      const res = await api.get<{ data: DepartmentBudget[]; meta: { last_page: number } }>(
        '/budget/department-budgets?per_page=100'
      )
      return res.data
    },
    staleTime: 30_000,
  })
}

// Hook to update department budget
function useUpdateDepartmentBudget() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ 
      id, 
      annual_budget_centavos, 
      fiscal_year_start_month 
    }: { 
      id: number
      annual_budget_centavos: number
      fiscal_year_start_month: number
    }) => {
      const res = await api.patch(`/budget/department-budgets/${id}`, {
        annual_budget_centavos,
        fiscal_year_start_month,
      })
      return res.data
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['department-budgets'] })
    },
  })
}

const formatMoney = (centavos: number): string => {
  return '₱' + (centavos / 100).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

export default function DepartmentBudgetsPage(): React.ReactElement {
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission('budget.manage')
  const { data, isLoading, isError } = useDepartmentBudgets()
  const updateBudget = useUpdateDepartmentBudget()

  const [editingId, setEditingId] = useState<number | null>(null)
  const [editForm, setEditForm] = useState<{
    annual_budget: string
    fiscal_year_start_month: number
  }>({ annual_budget: '', fiscal_year_start_month: 1 })

  const departments = data?.data ?? []

  const startEdit = (dept: DepartmentBudget) => {
    setEditingId(dept.id)
    setEditForm({
      annual_budget: (dept.annual_budget_centavos / 100).toString(),
      fiscal_year_start_month: dept.fiscal_year_start_month || 1,
    })
  }

  const cancelEdit = () => {
    setEditingId(null)
    setEditForm({ annual_budget: '', fiscal_year_start_month: 1 })
  }

  const saveEdit = async (id: number) => {
    const budgetValue = parseFloat(editForm.annual_budget.replace(/,/g, ''))
    if (isNaN(budgetValue) || budgetValue < 0) {
      toast.error('Please enter a valid budget amount')
      return
    }

    try {
      await updateBudget.mutateAsync({
        id,
        annual_budget_centavos: Math.round(budgetValue * 100),
        fiscal_year_start_month: editForm.fiscal_year_start_month,
      })
      toast.success('Department budget updated successfully')
      setEditingId(null)
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to update budget'))
    }
  }

  if (isLoading) return <SkeletonLoader rows={8} />
  if (isError) return <div className="text-red-600 text-sm mt-4">Failed to load department budgets.</div>

  return (
    <div className="max-w-5xl mx-auto">
      <PageHeader
        title="Department Budgets"
        subtitle="Manage annual budgets for each department. These budgets are used for Purchase Request validation."
      />

      {/* Info Card */}
      <Card className="mb-6">
        <CardBody>
          <div className="flex items-start gap-3 text-sm text-neutral-600">
            <Wallet className="w-5 h-5 text-neutral-400 mt-0.5" />
            <div>
              <p className="font-medium text-neutral-800">How Department Budgets Work</p>
              <ul className="mt-1 space-y-1 list-disc list-inside">
                <li>Department heads can create Purchase Requests up to their department&apos;s annual budget</li>
                <li>Budget is checked in real-time when creating PRs</li>
                <li>Fiscal year start month determines when the budget resets</li>
                <li>Set budget to 0 (or leave empty) to allow unlimited spending</li>
              </ul>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Departments Table */}
      <Card>
        <CardHeader>Department Budget Allocation</CardHeader>
        <CardBody>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                    Department
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                    Code
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                    Fiscal Year Start
                  </th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                    Annual Budget
                  </th>
                  <th className="px-4 py-3 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                    Status
                  </th>
                  {canManage && (
                    <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">
                      Actions
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {departments.length === 0 && (
                  <tr>
                    <td colSpan={canManage ? 6 : 5} className="px-4 py-8 text-center text-neutral-400">
                      No departments found.
                    </td>
                  </tr>
                )}
                {departments.map((dept) => (
                  <tr
                    key={dept.id}
                    className={`hover:bg-neutral-50 transition-colors ${!dept.is_active ? 'opacity-60' : ''}`}
                  >
                    <td className="px-4 py-3 font-medium text-neutral-900">{dept.name}</td>
                    <td className="px-4 py-3 font-mono text-neutral-600">{dept.code}</td>
                    <td className="px-4 py-3 text-neutral-600">
                      {editingId === dept.id ? (
                        <select
                          value={editForm.fiscal_year_start_month}
                          onChange={(e) =>
                            setEditForm((f) => ({ ...f, fiscal_year_start_month: parseInt(e.target.value) }))
                          }
                          className="w-full text-sm border border-neutral-300 rounded px-2 py-1"
                        >
                          {months.map((month, idx) => (
                            <option key={idx + 1} value={idx + 1}>
                              {month}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <span className="flex items-center gap-1">
                          <Calendar className="w-3.5 h-3.5 text-neutral-400" />
                          {months[(dept.fiscal_year_start_month || 1) - 1]}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {editingId === dept.id ? (
                        <div className="flex items-center justify-end gap-2">
                          <span className="text-neutral-500">₱</span>
                          <input
                            type="number"
                            value={editForm.annual_budget}
                            onChange={(e) => setEditForm((f) => ({ ...f, annual_budget: e.target.value }))}
                            className="w-32 text-sm border border-neutral-300 rounded px-2 py-1 text-right"
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                          />
                        </div>
                      ) : (
                        <span
                          className={`font-medium ${
                            dept.annual_budget_centavos > 0 ? 'text-neutral-900' : 'text-neutral-400 italic'
                          }`}
                        >
                          {dept.annual_budget_centavos > 0
                            ? formatMoney(dept.annual_budget_centavos)
                            : 'No limit'}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-center">
                      <span
                        className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${
                          dept.is_active
                            ? 'bg-green-100 text-green-700'
                            : 'bg-neutral-100 text-neutral-500'
                        }`}
                      >
                        {dept.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    {canManage && (
                      <td className="px-4 py-3 text-right">
                        {editingId === dept.id ? (
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => saveEdit(dept.id)}
                              disabled={updateBudget.isPending}
                              className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                            >
                              <Save className="w-3.5 h-3.5" />
                              Save
                            </button>
                            <button
                              onClick={cancelEdit}
                              className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-neutral-200 text-neutral-700 rounded hover:bg-neutral-300"
                            >
                              <X className="w-3.5 h-3.5" />
                              Cancel
                            </button>
                          </div>
                        ) : (
                          <button
                            onClick={() => startEdit(dept)}
                            className="inline-flex items-center gap-1 px-2 py-1 text-xs text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                            Edit Budget
                          </button>
                        )}
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      {/* Summary Footer */}
      <div className="mt-6 grid grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <p className="text-xs text-neutral-500 uppercase tracking-wider">Total Departments</p>
            <p className="text-2xl font-bold text-neutral-900 mt-1">{departments.length}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <p className="text-xs text-neutral-500 uppercase tracking-wider">With Budget Set</p>
            <p className="text-2xl font-bold text-green-600 mt-1">
              {departments.filter((d) => d.annual_budget_centavos > 0).length}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <p className="text-xs text-neutral-500 uppercase tracking-wider">Total Budget Allocated</p>
            <p className="text-2xl font-bold text-neutral-900 mt-1">
              {formatMoney(departments.reduce((sum, d) => sum + d.annual_budget_centavos, 0))}
            </p>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
