import { useState } from 'react'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import { Plus, FileCheck, CheckCircle, XCircle } from 'lucide-react'
import {
  useBudgetLines,
  useSetBudgetLine,
  useSubmitBudget,
  useApproveBudget,
  useRejectBudget,
  type BudgetLine,
} from '@/hooks/useBudget'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import Pagination from '@/components/ui/Pagination'

const fmt = (centavos: number) => `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`

export default function BudgetLinesPage(): React.ReactElement {
  const [filters, setFilters] = useState<{ fiscal_year?: number; cost_center_id?: number }>({
    fiscal_year: new Date().getFullYear(),
  })
  const [page, setPage] = useState(1)
  const { data, isLoading } = useBudgetLines({ ...filters })
  const setBudgetLine = useSetBudgetLine()
  const submitBudget = useSubmitBudget()
  const approveBudget = useApproveBudget()
  const rejectBudget = useRejectBudget()
  const canManage = useAuthStore((s) => s.hasPermission('budget.manage'))
  const canApprove = useAuthStore((s) => s.hasPermission('budget.approve'))

  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ cost_center_id: 0, account_id: 0, fiscal_year: new Date().getFullYear(), amount_centavos: 0 })

  const handleCreate = async () => {
    try {
      await setBudgetLine.mutateAsync(form)
      toast.success('Budget line created')
      setShowForm(false)
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  const handleSubmit = async (ulid: string) => {
    try {
      await submitBudget.mutateAsync(ulid)
      toast.success('Budget line submitted for approval')
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  const handleApprove = async (ulid: string) => {
    try {
      await approveBudget.mutateAsync({ ulid })
      toast.success('Budget line approved')
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  const handleReject = async (ulid: string) => {
    const remarks = window.prompt('Rejection reason:')
    if (!remarks) return
    try {
      await rejectBudget.mutateAsync({ ulid, remarks })
      toast.success('Budget line rejected')
    } catch (e) {
      toast.error(firstErrorMessage(e))
    }
  }

  const lines: BudgetLine[] = data?.data ?? []
  const meta = data?.meta

  if (isLoading) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-6">
      <PageHeader
        title="Budget Lines"
        subtitle="Annual budget allocations by cost center and GL account"
        actions={
          canManage ? (
            <button
              onClick={() => setShowForm(true)}
              className="btn btn-primary inline-flex items-center gap-2"
            >
              <Plus className="h-4 w-4" /> New Budget Line
            </button>
          ) : undefined
        }
      />

      <div className="flex items-center gap-4 mb-4">
        <label className="text-sm font-medium">Fiscal Year:</label>
        <select
          value={filters.fiscal_year ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, fiscal_year: e.target.value ? Number(e.target.value) : undefined }))}
          className="input input-sm w-28"
        >
          {[2024, 2025, 2026, 2027].map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>
      </div>

      {showForm && canManage && (
        <Card className="p-4 space-y-3">
          <h3 className="font-semibold">Create Budget Line</h3>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <input
              type="number"
              placeholder="Cost Center ID"
              value={form.cost_center_id || ''}
              onChange={(e) => setForm((f) => ({ ...f, cost_center_id: Number(e.target.value) }))}
              className="input input-sm"
            />
            <input
              type="number"
              placeholder="GL Account ID"
              value={form.account_id || ''}
              onChange={(e) => setForm((f) => ({ ...f, account_id: Number(e.target.value) }))}
              className="input input-sm"
            />
            <input
              type="number"
              placeholder="Amount (centavos)"
              value={form.amount_centavos || ''}
              onChange={(e) => setForm((f) => ({ ...f, amount_centavos: Number(e.target.value) }))}
              className="input input-sm"
            />
            <div className="flex gap-2">
              <button onClick={handleCreate} className="btn btn-primary btn-sm" disabled={setBudgetLine.isPending}>
                Save
              </button>
              <button onClick={() => setShowForm(false)} className="btn btn-ghost btn-sm">
                Cancel
              </button>
            </div>
          </div>
        </Card>
      )}

      {lines.length === 0 ? (
        <EmptyState
          icon={<FileCheck className="h-12 w-12 text-gray-400" />}
          title="No budget lines"
          description="Create budget lines to allocate annual budgets by cost center and GL account."
        />
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="table w-full">
              <thead>
                <tr>
                  <th>Cost Center</th>
                  <th>GL Account</th>
                  <th>FY</th>
                  <th className="text-right">Budgeted</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((line) => (
                  <tr key={line.id}>
                    <td>{line.cost_center?.name ?? `#${line.cost_center_id}`}</td>
                    <td>{line.account ? `${line.account.code} - ${line.account.name}` : `#${line.account_id}`}</td>
                    <td>{line.fiscal_year}</td>
                    <td className="text-right font-mono">{fmt(line.budgeted_amount_centavos)}</td>
                    <td><StatusBadge status={line.status} /></td>
                    <td className="flex gap-1">
                      {line.status === 'draft' && canManage && (
                        <button
                          onClick={() => handleSubmit(line.ulid)}
                          className="btn btn-xs btn-outline"
                          title="Submit for approval"
                        >
                          <FileCheck className="h-3 w-3" />
                        </button>
                      )}
                      {line.status === 'submitted' && canApprove && (
                        <>
                          <button
                            onClick={() => handleApprove(line.ulid)}
                            className="btn btn-xs btn-success"
                            title="Approve"
                          >
                            <CheckCircle className="h-3 w-3" />
                          </button>
                          <button
                            onClick={() => handleReject(line.ulid)}
                            className="btn btn-xs btn-error"
                            title="Reject"
                          >
                            <XCircle className="h-3 w-3" />
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta && meta.last_page > 1 && (
            <Pagination
              currentPage={meta.current_page}
              lastPage={meta.last_page}
              onPageChange={setPage}
            />
          )}
        </Card>
      )}
    </div>
  )
}
