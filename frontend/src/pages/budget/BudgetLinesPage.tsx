import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, Wallet } from 'lucide-react'
import { useBudgetLines, useSetBudgetLine, useCostCenters, type BudgetLine } from '@/hooks/useBudget'
import { useChartOfAccounts } from '@/hooks/useAccounting'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const currentYear = new Date().getFullYear()

export default function BudgetLinesPage(): React.ReactElement {
  const [fiscalYear, setFiscalYear] = useState(currentYear)
  const [costCenterId, setCostCenterId] = useState<number | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ cost_center_id: 0, account_id: 0, amount: 0 })

  const { data: lineData, isLoading } = useBudgetLines({ fiscal_year: fiscalYear, ...(costCenterId ? { cost_center_id: costCenterId } : {}) })
  const { data: ccData } = useCostCenters()
  const { data: accounts } = useChartOfAccounts()
  const setBudget = useSetBudgetLine()
  const canManage = useAuthStore((s) => s.hasPermission('budget.manage'))

  const lines: BudgetLine[] = lineData?.data ?? []
  const costCenters = ccData?.data ?? []
  
  // Calculate total budget
  const totalBudget = lines.reduce((sum, l) => sum + (l.amount_centavos || 0), 0)

  async function handleSave(e: React.FormEvent) {
    e.preventDefault()
    try {
      await setBudget.mutateAsync({
        cost_center_id: form.cost_center_id,
        account_id: form.account_id,
        fiscal_year: fiscalYear,
        amount_centavos: Math.round(form.amount * 100),
      })
      toast.success('Budget line saved.')
      setShowForm(false)
      setForm({ cost_center_id: 0, account_id: 0, amount: 0 })
    } catch {
      toast.error('Failed to save budget line.')
    }
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="Budget Lines"
        subtitle="Set annual budget allocations per cost center and GL account."
        icon={<Wallet className="w-5 h-5 text-neutral-600" />}
        actions={
          canManage && (
            <button onClick={() => setShowForm(true)}
              className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Set Budget
            </button>
          )
        }
      />

      {/* Filters */}
      <div className="flex items-center gap-4">
        <div>
          <label className="block text-xs font-medium text-neutral-600 mb-1">Fiscal Year</label>
          <select value={fiscalYear} onChange={(e) => setFiscalYear(Number(e.target.value))}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
            {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-600 mb-1">Cost Center</label>
          <select value={costCenterId ?? ''} onChange={(e) => setCostCenterId(e.target.value ? Number(e.target.value) : null)}
            className="border border-neutral-300 rounded px-3 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400 outline-none min-w-[200px]">
            <option value="">All</option>
            {costCenters.map((cc) => <option key={cc.id} value={cc.id}>{cc.code} — {cc.name}</option>)}
          </select>
        </div>
      </div>

      {/* Form */}
      {showForm && (
        <Card>
          <form onSubmit={handleSave} className="grid grid-cols-4 gap-4">
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Cost Center</label>
              <select value={form.cost_center_id} onChange={(e) => setForm((f) => ({ ...f, cost_center_id: Number(e.target.value) }))}
                required className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
                <option value={0}>Select…</option>
                {costCenters.map((cc) => <option key={cc.id} value={cc.id}>{cc.code} — {cc.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">GL Account</label>
              <select value={form.account_id} onChange={(e) => setForm((f) => ({ ...f, account_id: Number(e.target.value) }))}
                required className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none">
                <option value={0}>Select…</option>
                {(accounts ?? []).map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-600 mb-1">Amount (₱)</label>
              <input type="number" step="0.01" min={0} value={form.amount}
                onChange={(e) => setForm((f) => ({ ...f, amount: Number(e.target.value) }))}
                required className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none" />
            </div>
            <div className="flex items-end gap-3">
              <button type="submit" disabled={setBudget.isPending}
                className="btn-primary disabled:opacity-50">
                {setBudget.isPending ? 'Saving…' : 'Save'}
              </button>
              <button type="button" onClick={() => setShowForm(false)} className="btn-ghost">Cancel</button>
            </div>
          </form>
        </Card>
      )}

      {/* Total Summary */}
      {!isLoading && lines.length > 0 && (
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs font-medium text-blue-600 uppercase tracking-wide">Total Budget for {fiscalYear}</p>
              <p className="text-2xl font-bold text-blue-700 font-mono mt-1">
                ₱{(totalBudget / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-blue-600">{lines.length} budget line{lines.length !== 1 ? 's' : ''}</p>
              <p className="text-xs text-blue-500">{costCenterId ? 'Filtered by cost center' : 'All cost centers'}</p>
            </div>
          </div>
        </div>
      )}

      {/* Table */}
      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : lines.length === 0 ? (
        <EmptyState
          title={`No budget lines for ${fiscalYear}`}
          description="Set budget allocations to start tracking expenses against budget."
          action={canManage && (
            <button onClick={() => setShowForm(true)} className="btn-primary">
              <Plus className="w-3.5 h-3.5" /> Set Budget
            </button>
          )}
        />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Cost Center', 'GL Account', 'Amount (₱)'].map((h) => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {lines.map((ln) => (
                <tr key={ln.id} className="even:bg-neutral-50 hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 text-neutral-800">
                    <span className="font-mono text-xs text-neutral-400">{ln.cost_center?.code}</span>
                    <span className="ml-2 font-medium">{ln.cost_center?.name ?? ''}</span>
                  </td>
                  <td className="px-4 py-3 text-neutral-800">
                    <span className="font-mono text-xs text-neutral-400">{ln.account?.code}</span>
                    <span className="ml-2 font-medium">{ln.account?.name ?? ''}</span>
                  </td>
                  <td className="px-4 py-3">
                    <span className="font-mono font-bold text-blue-700">
                      ₱{(ln.amount_centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-neutral-50 border-t-2 border-neutral-200">
              <tr>
                <td colSpan={2} className="px-4 py-3 text-sm font-semibold text-neutral-700 text-right">Total Budget:</td>
                <td className="px-4 py-3 font-mono font-bold text-blue-700 text-lg">
                  ₱{(totalBudget / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </td>
              </tr>
            </tfoot>
          </table>
        </Card>
      )}
    </div>
  )
}
