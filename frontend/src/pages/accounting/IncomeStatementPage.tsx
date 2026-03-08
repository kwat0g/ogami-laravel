import { useState } from 'react'
import { useIncomeStatement } from '@/hooks/useReports'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { PeriodFilters, ISSection } from '@/types/reports'

function ISSection({ section, indent = false }: { section: ISSection; indent?: boolean }) {
  return (
    <div className={`space-y-0.5 ${indent ? 'pl-4' : ''}`}>
      {section.accounts.map((acct, i) => (
        <div key={i} className="flex justify-between items-center py-1 text-sm text-neutral-700 px-2">
          <span>
            <span className="font-mono text-xs text-neutral-400 mr-2">{acct.code}</span>
            {acct.name}
          </span>
          <span className="font-mono">₱{acct.balance.toLocaleString()}</span>
        </div>
      ))}
    </div>
  )
}

function SummaryRow({ label, amount, highlight = false }: {
  label: string
  amount: number
  highlight?: boolean
}) {
  return (
    <div className={`flex justify-between items-center px-2 py-2 ${highlight
      ? 'bg-neutral-50 font-bold text-neutral-900 rounded border border-neutral-200'
      : 'border-t border-neutral-200 font-semibold text-neutral-800'
    }`}>
      <span>{label}</span>
      <span className="font-mono">
        {amount < 0 ? `(₱${Math.abs(amount).toLocaleString()})` : `₱${amount.toLocaleString()}`}
      </span>
    </div>
  )
}

export default function IncomeStatementPage() {
  const [filters, setFilters] = useState<PeriodFilters>({ date_from: '', date_to: '' })
  const [submitted, setSubmitted] = useState<PeriodFilters | null>(null)

  const { data: report, isLoading, isError } = useIncomeStatement(submitted)
  const is = report?.data

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!filters.date_from || !filters.date_to) return
    setSubmitted({ ...filters })
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900 mb-1">Income Statement</h1>
        <p className="text-sm text-neutral-500">
          Revenue, expenses and net income waterfall
        </p>
      </div>

      <form
        onSubmit={handleSubmit}
        className="bg-white border border-neutral-200 rounded p-4 flex flex-wrap gap-4 items-end"
      >
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">From</label>
          <input
            type="date"
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
            value={filters.date_from}
            onChange={e => setFilters(f => ({ ...f, date_from: e.target.value }))}
          />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">To</label>
          <input
            type="date"
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
            value={filters.date_to}
            onChange={e => setFilters(f => ({ ...f, date_to: e.target.value }))}
          />
        </div>
        <button
          type="submit"
          className="px-5 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
        >
          Generate
        </button>
      </form>

      {isLoading && <SkeletonLoader rows={10} />}
      {isError && (
        <p className="text-red-600 text-sm">Failed to load report. Check filters and try again.</p>
      )}

      {is && (
        <div className="bg-white border border-neutral-200 rounded p-5 space-y-3">
          <h2 className="text-sm font-semibold text-neutral-500">Revenue</h2>
          <ISSection section={is.revenue} />
          <SummaryRow label="Net Revenue" amount={is.revenue.total} />

          <h2 className="text-sm font-semibold text-neutral-500 pt-2">Cost of Goods Sold</h2>
          <ISSection section={is.cogs} indent />
          <SummaryRow label="Gross Profit" amount={is.gross_profit} />

          <h2 className="text-sm font-semibold text-neutral-500 pt-2">Operating Expenses</h2>
          <ISSection section={is.operating_expenses} indent />
          <SummaryRow label="Operating Income" amount={is.operating_income} />

          <h2 className="text-sm font-semibold text-neutral-500 pt-2">Tax</h2>
          <ISSection section={is.income_tax} indent />

          <SummaryRow label="Net Income" amount={is.net_income} highlight />

          <p className="text-xs text-neutral-400 text-right pt-1">
            Generated: {new Date(report.meta.generated_at).toLocaleString()}
          </p>
        </div>
      )}
    </div>
  )
}
