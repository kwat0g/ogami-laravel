import { useState } from 'react'
import { toast } from 'sonner'
import { BarChart3, CheckCircle2, XCircle, Send } from 'lucide-react'
import {
  useBudgetLines,
  useCostCenters,
  useSubmitBudget,
  useApproveBudget,
  useRejectBudget,
  type BudgetLine,
} from '@/hooks/useBudget'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const currentYear = new Date().getFullYear()

const STATUS_MAP: Record<string, string> = {
  draft: 'draft',
  submitted: 'pending',
  approved: 'approved',
  rejected: 'rejected',
}

export default function BudgetVsActualPage(): React.ReactElement {
  const [fiscalYear, setFiscalYear] = useState(currentYear)
  const [costCenterId, setCostCenterId] = useState<number | null>(null)

  const { data: lineData, isLoading } = useBudgetLines({
    fiscal_year: fiscalYear,
    ...(costCenterId ? { cost_center_id: costCenterId } : {}),
  })
  const { data: ccData } = useCostCenters()
  const submitBudget = useSubmitBudget()
  const approveBudget = useApproveBudget()
  const rejectBudget = useRejectBudget()

  const canManage = useAuthStore((s) => s.hasPermission('budget.manage'))
  const canApprove = useAuthStore((s) => s.hasPermission('budget.approve'))

  const lines: BudgetLine[] = lineData?.data ?? []
  const costCenters = ccData?.data ?? []

  const fmt = (centavos: number) =>
    `₱${(centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}`

  async function handleSubmit(line: BudgetLine) {
    try {
      await submitBudget.mutateAsync(line.ulid)
      toast.success('Budget line submitted for approval.')
    } catch {
      toast.error('Submit failed.')
    }
  }

  async function handleApprove(line: BudgetLine) {
    try {
      await approveBudget.mutateAsync({ ulid: line.ulid })
      toast.success('Budget line approved.')
    } catch {
      toast.error('Approval failed.')
    }
  }

  async function handleReject(line: BudgetLine) {
    const remarks = prompt('Rejection remarks (optional):')
    try {
      await rejectBudget.mutateAsync({ ulid: line.ulid, remarks: remarks ?? undefined })
      toast.success('Budget line rejected.')
    } catch {
      toast.error('Rejection failed.')
    }
  }

  const totalBudgeted = lines.reduce((sum, l) => sum + (l.budgeted_amount_centavos ?? 0), 0)
  const approvedCount = lines.filter((l) => l.status === 'approved').length
  const submittedCount = lines.filter((l) => l.status === 'submitted').length

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Budget vs Actual"
        subtitle="Review budget allocations, approval status, and variance by cost center."
        icon={<BarChart3 className="w-5 h-5 text-neutral-600" />}
      />

      {/* Filters */}
      <div className="flex items-center gap-4">
        <div>
          <label className="block text-xs font-medium text-neutral-600 mb-1">Fiscal Year</label>
          <select
            value={fiscalYear}
            onChange={(e) => setFiscalYear(Number(e.target.value))}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
          >
            {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-600 mb-1">Cost Center</label>
          <select
            value={costCenterId ?? ''}
            onChange={(e) => setCostCenterId(e.target.value ? Number(e.target.value) : null)}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none min-w-[200px]"
          >
            <option value="">All cost centers</option>
            {costCenters.map((cc) => (
              <option key={cc.id} value={cc.id}>{cc.code} — {cc.name}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Total Budgeted</p>
          <p className="text-2xl font-bold text-blue-700 font-mono mt-1">{fmt(totalBudgeted)}</p>
          <p className="text-xs text-blue-600 mt-1">{lines.length} budget line{lines.length !== 1 ? 's' : ''}</p>
        </div>
        <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
          <p className="text-xs font-medium text-emerald-600 uppercase tracking-wide">Approved Lines</p>
          <p className="text-2xl font-bold text-emerald-700 mt-1">{approvedCount} <span className="text-emerald-500 text-lg">/ {lines.length}</span></p>
          <div className="w-full bg-emerald-200 rounded-full h-1.5 mt-2">
            <div 
              className="bg-emerald-500 h-1.5 rounded-full transition-all" 
              style={{ width: `${lines.length > 0 ? (approvedCount / lines.length) * 100 : 0}%` }}
            />
          </div>
        </div>
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p className="text-xs font-medium text-amber-600 uppercase tracking-wide">Pending Approval</p>
          <p className="text-2xl font-bold text-amber-700 mt-1">{submittedCount}</p>
          <p className="text-xs text-amber-600 mt-1">
            {submittedCount > 0 ? 'Awaiting review' : 'No pending items'}
          </p>
        </div>
      </div>

      {/* Table */}
      <Card>
        {isLoading ? (
          <SkeletonLoader rows={6} />
        ) : lines.length === 0 ? (
          <div className="p-8 text-center">
            <p className="text-sm text-neutral-500">No budget lines found for this period.</p>
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="border-b border-neutral-200 bg-neutral-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wider">Account</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Budgeted</th>
                <th className="px-4 py-3 text-center text-xs font-semibold text-neutral-500 uppercase tracking-wider">Status</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {lines.map((line) => (
                <tr key={line.ulid} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3">
                    <span className="font-mono text-xs text-neutral-400">{line.account?.code}</span>
                    <span className="ml-2 text-neutral-900">{line.account?.name}</span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <span className={`font-mono font-bold text-lg ${
                      line.status === 'approved' ? 'text-emerald-700' : 
                      line.status === 'rejected' ? 'text-red-700' : 
                      'text-blue-700'
                    }`}>
                      {fmt(line.budgeted_amount_centavos)}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <StatusBadge status={STATUS_MAP[line.status]}>
                      {line.status}
                    </StatusBadge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-1">
                      {canManage && (line.status === 'draft' || line.status === 'rejected') && (
                        <button
                          onClick={() => handleSubmit(line)}
                          className="inline-flex items-center gap-1 text-xs text-indigo-600 border border-indigo-300 rounded px-2 py-1 hover:bg-indigo-50"
                          disabled={submitBudget.isPending}
                        >
                          <Send className="w-3 h-3" /> Submit
                        </button>
                      )}
                      {canApprove && line.status === 'submitted' && (
                        <>
                          <button
                            onClick={() => handleApprove(line)}
                            className="inline-flex items-center gap-1 text-xs text-emerald-600 border border-emerald-300 rounded px-2 py-1 hover:bg-emerald-50"
                            disabled={approveBudget.isPending}
                          >
                            <CheckCircle2 className="w-3 h-3" /> Approve
                          </button>
                          <button
                            onClick={() => handleReject(line)}
                            className="inline-flex items-center gap-1 text-xs text-red-600 border border-red-300 rounded px-2 py-1 hover:bg-red-50"
                            disabled={rejectBudget.isPending}
                          >
                            <XCircle className="w-3 h-3" /> Reject
                          </button>
                        </>
                      )}
                      {line.approval_remarks && (
                        <span className="text-xs text-neutral-400 ml-2" title={line.approval_remarks}>
                          💬
                        </span>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
