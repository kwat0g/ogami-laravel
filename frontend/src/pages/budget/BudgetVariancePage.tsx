import { useState } from 'react'
import { BarChart3, TrendingUp, AlertTriangle } from 'lucide-react'
import { useBudgetVariance, useBudgetVarianceByCostCenter } from '@/hooks/useAnalytics'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import EmptyState from '@/components/ui/EmptyState'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const fmt = (centavos: number) => `₱${(centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`

export default function BudgetVariancePage(): React.ReactElement {
  const currentYear = new Date().getFullYear()
  const [fiscalYear, setFiscalYear] = useState(currentYear)

  const { data: varianceData, isLoading: loadingVariance } = useBudgetVariance({ fiscal_year: fiscalYear })
  const { data: byCostCenter, isLoading: loadingByCc } = useBudgetVarianceByCostCenter(fiscalYear)

  const isLoading = loadingVariance || loadingByCc

  if (isLoading) return <SkeletonLoader rows={8} />

  return (
    <div className="space-y-6">
      <PageHeader
        title="Budget Variance Analysis"
        subtitle={`Actual vs budgeted spending for FY ${fiscalYear}`}
      />

      <div className="flex items-center gap-4 mb-4">
        <label className="text-sm font-medium">Fiscal Year:</label>
        <select
          value={fiscalYear}
          onChange={(e) => setFiscalYear(Number(e.target.value))}
          className="input input-sm w-28"
        >
          {[2024, 2025, 2026, 2027].map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>
      </div>

      {/* Summary by Cost Center */}
      <Card className="p-4">
        <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
          <BarChart3 className="h-5 w-5" /> Variance by Cost Center
        </h3>
        {!byCostCenter || byCostCenter.length === 0 ? (
          <EmptyState
            icon={<TrendingUp className="h-12 w-12 text-gray-400" />}
            title="No budget data"
            description="No approved budget lines found for this fiscal year."
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="table w-full">
              <thead>
                <tr>
                  <th>Cost Center</th>
                  <th className="text-right">Budgeted</th>
                  <th className="text-right">Actual</th>
                  <th className="text-right">Variance</th>
                  <th className="text-right">Utilization</th>
                </tr>
              </thead>
              <tbody>
                {byCostCenter.map((row: { cost_center_name: string; total_budgeted_centavos: number; total_actual_centavos: number; total_variance_centavos: number; utilization_pct: number }, idx: number) => (
                  <tr key={idx}>
                    <td>{row.cost_center_name}</td>
                    <td className="text-right font-mono">{fmt(row.total_budgeted_centavos)}</td>
                    <td className="text-right font-mono">{fmt(row.total_actual_centavos)}</td>
                    <td className={`text-right font-mono ${row.total_variance_centavos < 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {fmt(row.total_variance_centavos)}
                    </td>
                    <td className="text-right">
                      <span className={`inline-flex items-center gap-1 ${row.utilization_pct > 100 ? 'text-red-600 font-semibold' : ''}`}>
                        {row.utilization_pct > 100 && <AlertTriangle className="h-3 w-3" />}
                        {row.utilization_pct.toFixed(1)}%
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Detail Breakdown */}
      {varianceData && varianceData.length > 0 && (
        <Card className="p-4">
          <h3 className="text-lg font-semibold mb-4">Line-by-Line Variance</h3>
          <div className="overflow-x-auto">
            <table className="table w-full text-sm">
              <thead>
                <tr>
                  <th>Account</th>
                  <th className="text-right">Budgeted</th>
                  <th className="text-right">Actual</th>
                  <th className="text-right">Variance</th>
                  <th className="text-right">%</th>
                </tr>
              </thead>
              <tbody>
                {varianceData.map((row: { account_name: string; budgeted_centavos: number; actual_centavos: number; variance_centavos: number; utilization_pct: number }, idx: number) => (
                  <tr key={idx}>
                    <td>{row.account_name}</td>
                    <td className="text-right font-mono">{fmt(row.budgeted_centavos)}</td>
                    <td className="text-right font-mono">{fmt(row.actual_centavos)}</td>
                    <td className={`text-right font-mono ${row.variance_centavos < 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {fmt(row.variance_centavos)}
                    </td>
                    <td className="text-right">{row.utilization_pct.toFixed(1)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  )
}
