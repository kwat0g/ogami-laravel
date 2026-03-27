import { useState } from 'react'
import { useBudgetVariance, useBudgetVarianceByCostCenter, type VarianceRow } from '@/hooks/useAnalytics'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const currentYear = new Date().getFullYear()

function formatPeso(centavos: number): string {
  return '₱' + (centavos / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function StatusBadge({ status }: { status: string }): JSX.Element {
  const map: Record<string, string> = {
    under_budget: 'bg-green-100 text-green-700',
    on_track: 'bg-blue-100 text-blue-700',
    warning: 'bg-amber-100 text-amber-700',
    critical: 'bg-orange-100 text-orange-700',
    over_budget: 'bg-red-100 text-red-700',
  }
  const label: Record<string, string> = {
    under_budget: 'Under Budget',
    on_track: 'On Track',
    warning: 'Warning',
    critical: 'Critical',
    over_budget: 'Over Budget',
  }

  return (
    <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide ${map[status] ?? 'bg-neutral-100 text-neutral-600'}`}>
      {label[status] ?? status}
    </span>
  )
}

export default function BudgetVarianceReportPage(): JSX.Element {
  const [fiscalYear, setFiscalYear] = useState(currentYear)
  const [view, setView] = useState<'detail' | 'summary'>('summary')

  const { data: summaryData, isLoading: summaryLoading } = useBudgetVarianceByCostCenter(fiscalYear)
  const { data: detailData, isLoading: detailLoading } = useBudgetVariance({ fiscal_year: fiscalYear })

  const isLoading = view === 'summary' ? summaryLoading : detailLoading

  return (
    <div className="space-y-6">
      <PageHeader title="Budget Variance Analysis" subtitle="Budget vs actual spend with utilization tracking" />

      <Card className="p-4">
        <div className="flex items-end gap-4 mb-4">
          <div>
            <label className="text-xs text-neutral-500 block mb-1">Fiscal Year</label>
            <select
              value={fiscalYear}
              onChange={(e) => setFiscalYear(Number(e.target.value))}
              className="border border-neutral-300 rounded px-3 py-1.5 text-sm"
            >
              {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                <option key={y} value={y}>{y}</option>
              ))}
            </select>
          </div>
          <div className="flex gap-1">
            <button
              onClick={() => setView('summary')}
              className={`px-3 py-1.5 text-sm rounded ${view === 'summary' ? 'bg-blue-600 text-white' : 'bg-neutral-100 text-neutral-600'}`}
            >
              By Cost Center
            </button>
            <button
              onClick={() => setView('detail')}
              className={`px-3 py-1.5 text-sm rounded ${view === 'detail' ? 'bg-blue-600 text-white' : 'bg-neutral-100 text-neutral-600'}`}
            >
              Line Detail
            </button>
          </div>
        </div>

        {isLoading && <SkeletonLoader lines={8} />}

        {/* Summary View */}
        {view === 'summary' && summaryData && (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-neutral-200 bg-neutral-50">
                  <th className="text-left px-3 py-2 font-semibold text-neutral-600">Cost Center</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Budgeted</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Actual</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Variance</th>
                  <th className="px-3 py-2 font-semibold text-neutral-600 w-40">Utilization</th>
                  <th className="text-center px-3 py-2 font-semibold text-neutral-600">Lines</th>
                </tr>
              </thead>
              <tbody>
                {summaryData.map((row) => (
                  <tr key={row.cost_center_id} className="border-b border-neutral-100 hover:bg-neutral-50">
                    <td className="px-3 py-2 font-medium text-neutral-800">
                      <span className="font-mono text-xs text-neutral-400 mr-2">{row.cost_center_code}</span>
                      {row.cost_center_name}
                    </td>
                    <td className="text-right px-3 py-2 font-mono text-neutral-600">{formatPeso(row.total_budgeted_centavos)}</td>
                    <td className="text-right px-3 py-2 font-mono text-neutral-600">{formatPeso(row.total_actual_centavos)}</td>
                    <td className={`text-right px-3 py-2 font-mono font-semibold ${row.total_variance_centavos >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                      {formatPeso(row.total_variance_centavos)}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <div className="flex-1 h-2 bg-neutral-100 rounded-full overflow-hidden">
                          <div
                            className={`h-full rounded-full ${
                              row.utilization_pct > 100 ? 'bg-red-500' :
                              row.utilization_pct > 80 ? 'bg-amber-500' : 'bg-green-500'
                            }`}
                            style={{ width: `${Math.min(row.utilization_pct, 100)}%` }}
                          />
                        </div>
                        <span className="text-xs font-semibold text-neutral-600 w-12 text-right">{row.utilization_pct}%</span>
                      </div>
                    </td>
                    <td className="text-center px-3 py-2 text-neutral-500">
                      {row.line_count}
                      {row.over_budget_lines > 0 && (
                        <span className="text-red-600 ml-1">({row.over_budget_lines} over)</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Detail View */}
        {view === 'detail' && detailData && (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-neutral-200 bg-neutral-50">
                  <th className="text-left px-3 py-2 font-semibold text-neutral-600">Cost Center</th>
                  <th className="text-left px-3 py-2 font-semibold text-neutral-600">Account</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Budgeted</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Actual</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">Variance</th>
                  <th className="text-right px-3 py-2 font-semibold text-neutral-600">%</th>
                  <th className="text-center px-3 py-2 font-semibold text-neutral-600">Status</th>
                </tr>
              </thead>
              <tbody>
                {detailData.map((row: VarianceRow, i: number) => (
                  <tr key={i} className="border-b border-neutral-100 hover:bg-neutral-50">
                    <td className="px-3 py-2 text-neutral-600">{row.cost_center_name}</td>
                    <td className="px-3 py-2">
                      <span className="font-mono text-xs text-neutral-400 mr-1">{row.account_code}</span>
                      {row.account_name}
                    </td>
                    <td className="text-right px-3 py-2 font-mono">{formatPeso(row.budgeted_centavos)}</td>
                    <td className="text-right px-3 py-2 font-mono">{formatPeso(row.actual_centavos)}</td>
                    <td className={`text-right px-3 py-2 font-mono font-semibold ${row.variance_centavos >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                      {formatPeso(row.variance_centavos)}
                    </td>
                    <td className="text-right px-3 py-2 font-mono text-neutral-600">{row.utilization_pct}%</td>
                    <td className="text-center px-3 py-2"><StatusBadge status={row.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  )
}
