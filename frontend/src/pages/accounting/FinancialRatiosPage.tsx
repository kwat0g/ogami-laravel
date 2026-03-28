import { useState } from 'react'
import { useFinancialRatios } from '@/hooks/useEnhancements'

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
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Financial Ratios</h1>
          <p className="text-sm text-gray-500 mt-1">Key financial health indicators computed from GL data</p>
        </div>
        <select
          value={year}
          onChange={(e) => setYear(Number(e.target.value))}
          className="border rounded-lg px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-700"
        >
          {[2024, 2025, 2026].map(y => <option key={y} value={y}>{y}</option>)}
        </select>
      </div>

      {isLoading ? (
        <div className="text-center py-12 text-gray-500">Computing ratios...</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {ratios.map(([key, ratio]) => {
            const r = ratio as { value: number; formula: string; status: string; days_sales_outstanding?: number; days_payable_outstanding?: number }
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
            return (
              <div key={key} className="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div className="text-xs text-gray-500 uppercase tracking-wide">{label}</div>
                <div className="mt-2 flex items-baseline gap-2">
                  <span className="text-3xl font-bold text-gray-900 dark:text-white">
                    {key.includes('margin') || key.includes('return') ? `${r.value}%` : r.value}
                  </span>
                  <span className={`text-xs px-2 py-0.5 rounded-full ${STATUS_COLORS[r.status] ?? 'bg-gray-100 text-gray-600'}`}>
                    {r.status.replace(/_/g, ' ')}
                  </span>
                </div>
                {r.days_sales_outstanding !== undefined && (
                  <div className="text-xs text-gray-400 mt-1">DSO: {r.days_sales_outstanding} days</div>
                )}
                {r.days_payable_outstanding !== undefined && (
                  <div className="text-xs text-gray-400 mt-1">DPO: {r.days_payable_outstanding} days</div>
                )}
                <div className="text-xs text-gray-400 mt-2">{r.formula}</div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
