import { useState } from 'react'
import { useTrialBalance } from '@/hooks/useReports'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { PeriodFilters } from '@/types/reports'

export default function TrialBalancePage() {
  const [filters, setFilters] = useState<PeriodFilters>({ date_from: '', date_to: '' })
  const [submitted, setSubmitted] = useState<PeriodFilters | null>(null)

  const { data: report, isLoading, isError } = useTrialBalance(submitted)

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!filters.date_from || !filters.date_to) return
    setSubmitted({ ...filters })
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Trial Balance</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Debit/Credit totals per account for a given period (GL-002)
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

      {isLoading && <SkeletonLoader rows={10} />}
      {isError && (
        <p className="text-red-600 text-sm">Failed to load report. Check filters and try again.</p>
      )}

      {report && (
        <div className="space-y-3">
          <div className="bg-white border border-gray-200 rounded-xl overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                <tr>
                  <th className="px-3 py-2.5 text-left">Code</th>
                  <th className="px-3 py-2.5 text-left">Account Name</th>
                  <th className="px-3 py-2.5 text-right">Opening DR</th>
                  <th className="px-3 py-2.5 text-right">Opening CR</th>
                  <th className="px-3 py-2.5 text-right">Period DR</th>
                  <th className="px-3 py-2.5 text-right">Period CR</th>
                  <th className="px-3 py-2.5 text-right">Closing DR</th>
                  <th className="px-3 py-2.5 text-right">Closing CR</th>
                </tr>
              </thead>
              <tbody>
                {report.data.accounts.map((line, i) => (
                  <tr key={i} className="border-b border-gray-100 even:bg-slate-50 hover:bg-blue-50/60 transition-colors text-sm">
                    <td className="px-3 py-2 font-mono text-xs text-gray-500">{line.code}</td>
                    <td className="px-3 py-2 text-gray-700">{line.name}</td>
                    <td className="px-3 py-2 text-right font-mono text-gray-600">
                      {line.opening_debit ? `₱${line.opening_debit.toLocaleString()}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono text-gray-600">
                      {line.opening_credit ? `₱${line.opening_credit.toLocaleString()}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono text-green-700">
                      {line.period_debit ? `₱${line.period_debit.toLocaleString()}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono text-red-600">
                      {line.period_credit ? `₱${line.period_credit.toLocaleString()}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono font-semibold text-gray-900">
                      {line.closing_debit ? `₱${line.closing_debit.toLocaleString()}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono font-semibold text-gray-900">
                      {line.closing_credit ? `₱${line.closing_credit.toLocaleString()}` : '—'}
                    </td>
                  </tr>
                ))}
                {/* Totals row */}
                <tr className="bg-indigo-50 font-semibold text-sm border-t-2 border-indigo-200">
                  <td className="px-3 py-2" colSpan={2}>TOTAL</td>
                  <td className="px-3 py-2 text-right font-mono">
                    ₱{report.data.totals.opening_debit.toLocaleString()}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    ₱{report.data.totals.opening_credit.toLocaleString()}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    ₱{report.data.totals.period_debit.toLocaleString()}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    ₱{report.data.totals.period_credit.toLocaleString()}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    ₱{report.data.totals.closing_debit.toLocaleString()}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    ₱{report.data.totals.closing_credit.toLocaleString()}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p className="text-xs text-gray-400 text-right">
            Generated: {new Date(report.meta.generated_at).toLocaleString()}
          </p>
        </div>
      )}
    </div>
  )
}
