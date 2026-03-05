import { useState } from 'react'
import { useCashFlow } from '@/hooks/useReports'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { PeriodFilters, CFLine } from '@/types/reports'

function CFSectionTable({ title, lines, total }: {
  title: string
  lines: CFLine[]
  total: number
}) {
  const [open, setOpen] = useState(true)
  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden">
      <button
        type="button"
        className="w-full flex justify-between items-center px-4 py-3 bg-gray-50 text-sm font-semibold text-gray-800 hover:bg-gray-100"
        onClick={() => setOpen(o => !o)}
      >
        <span>{title}</span>
        <span className={`font-mono ${total < 0 ? 'text-red-600' : 'text-indigo-700'}`}>
          {total < 0 ? `(₱${Math.abs(total).toLocaleString()})` : `₱${total.toLocaleString()}`}
        </span>
      </button>
      {open && (
        <table className="w-full text-sm">
          <tbody>
            {lines.map((line, i) => (
              <tr key={i} className="border-t border-gray-100 even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                <td className="px-3 py-1.5 text-gray-700 pl-6">
                  <span className="font-mono text-xs text-gray-400 mr-2">{line.code}</span>
                  {line.name}
                </td>
                <td className="px-3 py-1.5 text-right font-mono text-gray-700">
                  {line.amount < 0
                    ? `(₱${Math.abs(line.amount).toLocaleString()})`
                    : `₱${line.amount.toLocaleString()}`}
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
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Cash Flow Statement</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Indirect method — operating, investing, financing activities (GL-005)
        </p>
      </div>

      <form
        onSubmit={handleSubmit}
        className="bg-white border border-gray-200 rounded-xl p-4 flex flex-wrap gap-4 items-end"
      >
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-gray-600">From</label>
          <input
            type="date"
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            value={filters.date_from}
            onChange={e => setFilters(f => ({ ...f, date_from: e.target.value }))}
          />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-gray-600">To</label>
          <input
            type="date"
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
            value={filters.date_to}
            onChange={e => setFilters(f => ({ ...f, date_to: e.target.value }))}
          />
        </div>
        <button
          type="submit"
          className="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
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
          <div className="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
            <div className="flex justify-between text-sm text-gray-700">
              <span>Net Change in Cash</span>
              <span className={`font-mono font-semibold ${netChange < 0 ? 'text-red-600' : 'text-indigo-700'}`}>
                {netChange < 0
                  ? `(₱${Math.abs(netChange).toLocaleString()})`
                  : `₱${netChange.toLocaleString()}`}
              </span>
            </div>
            <div className="flex justify-between text-sm text-gray-700 border-t border-gray-100 pt-2">
              <span>Opening Cash Balance</span>
              <span className="font-mono">₱{cf.opening_cash_balance.toLocaleString()}</span>
            </div>
            <div className="flex justify-between font-bold text-gray-900 border-t-2 border-gray-800 pt-2">
              <span>Closing Cash Balance</span>
              <span className="font-mono">₱{cf.closing_cash_balance.toLocaleString()}</span>
            </div>
          </div>

          <p className="text-xs text-gray-400 text-right">
            Generated: {new Date(report.meta.generated_at).toLocaleString()}
          </p>
        </div>
      )}
    </div>
  )
}
