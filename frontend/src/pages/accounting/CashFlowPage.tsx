import { formatPesoAmount } from '@/lib/formatters'
import { useState } from 'react'
import { formatPesoAmount } from '@/lib/formatters'
import { useCashFlow } from '@/hooks/useReports'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import type { PeriodFilters, CFLine } from '@/types/reports'

function CFSectionTable({ title, lines, total }: {
  title: string
  lines: CFLine[]
  total: number
}) {
  const [open, setOpen] = useState(true)
  return (
    <div className="border border-neutral-200 rounded-xl overflow-hidden">
      <button
        type="button"
        className="w-full flex justify-between items-center px-4 py-3 bg-neutral-50 text-sm font-semibold text-neutral-800 hover:bg-neutral-100"
        onClick={() => setOpen(o => !o)}
      >
        <span>{title}</span>
        <span className={`font-mono ${total < 0 ? 'text-neutral-700' : 'text-neutral-800'}`}>
          {total < 0 ? `(${formatPesoAmount(Math.abs(total))})` : `${formatPesoAmount(total)}`}
        </span>
      </button>
      {open && (
        <table className="w-full text-sm">
          <tbody>
            {lines.map((line, i) => (
              <tr key={i} className="border-t border-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-1.5 text-neutral-700 pl-6">
                  <span className="font-mono text-xs text-neutral-400 mr-2">{line.code}</span>
                  {line.name}
                </td>
                <td className="px-3 py-1.5 text-right font-mono text-neutral-700">
                  {line.amount < 0
                    ? `(${formatPesoAmount(Math.abs(line.amount))})`
                    : `${formatPesoAmount(line.amount)}`}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

export default function CashFlowPage() {
  const [filters, setFilters] = useState<PeriodFilters>({ date_from: '', date_to: '' })
  const [submitted, setSubmitted] = useState<PeriodFilters | null>(null)

  const { data: report, isLoading, isError } = useCashFlow(submitted)
  const cf = report?.data

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!filters.date_from || !filters.date_to) return
    setSubmitted({ ...filters })
  }

  const netChange = cf?.net_change_in_cash ?? 0

  return (
    <div className="space-y-6">
      <PageHeader title="Cash Flow" />

      <div>
        <p className="text-sm text-neutral-500">
          Indirect method — operating, investing, financing activities
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

      {isLoading && <SkeletonLoader rows={12} />}
      {isError && (
        <p className="text-red-600 text-sm">Failed to load report. Check filters and try again.</p>
      )}

      {cf && (
        <div className="space-y-4">
          <CFSectionTable
            title="Operating Activities"
            lines={cf.operating.adjustments}
            total={cf.operating.total_operating}
          />
          <CFSectionTable
            title="Investing Activities"
            lines={cf.investing.lines}
            total={cf.investing.total_investing}
          />
          <CFSectionTable
            title="Financing Activities"
            lines={cf.financing.lines}
            total={cf.financing.total_financing}
          />

          {/* Bottom summary */}
          <div className="bg-white border border-neutral-200 rounded p-4 space-y-2">
            <div className="flex justify-between text-sm text-neutral-700">
              <span>Net Change in Cash</span>
              <span className={`font-mono font-semibold ${netChange < 0 ? 'text-neutral-700' : 'text-neutral-800'}`}>
                {netChange < 0
                  ? `(${formatPesoAmount(Math.abs(netChange))})`
                  : `${formatPesoAmount(netChange)}`}
              </span>
            </div>
            <div className="flex justify-between text-sm text-neutral-700 border-t border-neutral-100 pt-2">
              <span>Opening Cash Balance</span>
              <span className="font-mono">{formatPesoAmount(cf.opening_cash_balance)}</span>
            </div>
            <div className="flex justify-between font-bold text-neutral-900 border-t-2 border-neutral-800 pt-2">
              <span>Closing Cash Balance</span>
              <span className="font-mono">{formatPesoAmount(cf.closing_cash_balance)}</span>
            </div>
          </div>

          <p className="text-xs text-neutral-400 text-right">
            Generated: {new Date(report.meta.generated_at).toLocaleString()}
          </p>
        </div>
      )}
    </div>
  )
}
