import { useState } from 'react'
import { TrendingUp } from 'lucide-react'
import { useBudgetForecast } from '@/hooks/useSupplementaryKpis'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

function fmt(c: number) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(c / 100) }

export default function BudgetForecastPage() {
  const [year, setYear] = useState(new Date().getFullYear())
  const { data, isLoading } = useBudgetForecast(year)

  return (
    <div className="space-y-6">
      <PageHeader title="Budget Year-End Forecast" icon={<TrendingUp className="w-5 h-5 text-neutral-600" />} />
      <Card className="p-4">
        <select className="input-sm" value={year} onChange={e => setYear(Number(e.target.value))}>
          {[year - 1, year, year + 1].map(y => <option key={y} value={y}>{y}</option>)}
        </select>
      </Card>
      {isLoading ? <SkeletonLoader rows={8} /> : (
        <Card className="overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th className="text-left p-3">Cost Center</th><th className="text-left p-3">Account</th>
                <th className="text-right p-3">Budget</th><th className="text-right p-3">Actual YTD</th>
                <th className="text-right p-3">Burn Rate/Mo</th><th className="text-right p-3">Forecasted</th>
                <th className="text-right p-3">Variance</th><th className="text-center p-3">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {(data ?? []).map((line: any, i: number) => (
                <tr key={i} className={!line.on_track ? 'bg-red-50/50' : ''}>
                  <td className="p-3">{line.cost_center}</td>
                  <td className="p-3">{line.account}</td>
                  <td className="p-3 text-right font-mono">{fmt(line.budgeted_centavos)}</td>
                  <td className="p-3 text-right font-mono">{fmt(line.actual_ytd_centavos)}</td>
                  <td className="p-3 text-right font-mono text-neutral-500">{fmt(line.monthly_burn_rate_centavos)}</td>
                  <td className="p-3 text-right font-mono font-bold">{fmt(line.forecasted_year_end_centavos)}</td>
                  <td className="p-3 text-right font-mono"><span className={line.variance_centavos >= 0 ? 'text-green-600' : 'text-red-600'}>{fmt(line.variance_centavos)}</span></td>
                  <td className="p-3 text-center">{line.on_track ? <span className="text-green-600 font-bold">On Track</span> : <span className="text-red-600 font-bold">Over Budget</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  )
}
