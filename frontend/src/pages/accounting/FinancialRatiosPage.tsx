import { useState } from 'react'
import { useFinancialRatios } from '@/hooks/useEnhancements'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card, CardBody } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const STATUS_COLORS: Record<string, string> = {
  healthy: 'text-green-600 bg-green-50',
  excellent: 'text-green-600 bg-green-50',
  good: 'text-green-600 bg-green-50',
  adequate: 'text-yellow-600 bg-yellow-50',
  moderate: 'text-yellow-600 bg-yellow-50',
  normal: 'text-blue-600 bg-blue-50',
  warning: 'text-red-600 bg-red-50',
  high_leverage: 'text-red-600 bg-red-50',
  below_target: 'text-orange-600 bg-orange-50',
  slow_collection: 'text-red-600 bg-red-50',
  slow_payer: 'text-yellow-600 bg-yellow-50',
  fast_payer: 'text-blue-600 bg-blue-50',
}

export default function FinancialRatiosPage() {
  const [year, setYear] = useState(new Date().getFullYear())
  const { data, isLoading } = useFinancialRatios(year)

  const ratios = data ? Object.entries(data).filter(([k]) => k !== 'fiscal_year') : []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Financial Ratios"
        subtitle="Key financial health indicators computed from GL data"
        actions={
          <select
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
            className="text-sm border border-neutral-300 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-neutral-400 bg-white dark:bg-neutral-800 dark:border-neutral-700"
          >
            {[2024, 2025, 2026].map(y => <option key={y} value={y}>{y}</option>)}
          </select>
        }
      />

      {isLoading ? (
        <SkeletonLoader rows={6} />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {ratios.map(([key, ratio]) => {
            const r = ratio as { value: number; formula: string; status: string; days_sales_outstanding?: number; days_payable_outstanding?: number }
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
            return (
              <Card key={key}>
                <CardBody>
                  <div className="text-xs text-neutral-500 uppercase tracking-wide">{label}</div>
                  <div className="mt-2 flex items-baseline gap-2">
                    <span className="text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                      {key.includes('margin') || key.includes('return') ? `${r.value}%` : r.value}
                    </span>
                    <span className={`text-xs px-2 py-0.5 rounded-full ${STATUS_COLORS[r.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                      {r.status.replace(/_/g, ' ')}
                    </span>
                  </div>
                  {r.days_sales_outstanding !== undefined && (
                    <div className="text-xs text-neutral-400 mt-1">DSO: {r.days_sales_outstanding} days</div>
                  )}
                  {r.days_payable_outstanding !== undefined && (
                    <div className="text-xs text-neutral-400 mt-1">DPO: {r.days_payable_outstanding} days</div>
                  )}
                  <div className="text-xs text-neutral-400 mt-2">{r.formula}</div>
                </CardBody>
              </Card>
            )
          })}
        </div>
      )}
    </div>
  )
}
