import { formatPesoAmount } from '@/lib/formatters'
import { useState } from 'react'
import { formatPesoAmount } from '@/lib/formatters'
import { useTrialBalance } from '@/hooks/useReports'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
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
    <div className="space-y-6">
      <PageHeader title="Trial Balance" />

      <div>
        <p className="text-sm text-neutral-500">
          Debit/Credit totals per account for a given period (GL-002)
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

      {report && (
        <div className="space-y-3">
          <div className="bg-white border border-neutral-200 rounded overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 text-xs font-semibold text-neutral-500">
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
                  <tr key={i} className="border-b border-neutral-100 even:bg-neutral-100 hover:bg-neutral-50 transition-colors text-sm">
                    <td className="px-3 py-2 font-mono text-xs text-neutral-500">{line.code}</td>
                    <td className="px-3 py-2 text-neutral-700">{line.name}</td>
                    <td className="px-3 py-2 text-right font-mono text-neutral-600">
                      {line.opening_debit ? `${formatPesoAmount(line.opening_debit)}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono text-neutral-600">
                      {line.opening_credit ? `${formatPesoAmount(line.opening_credit)}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono text-neutral-700">
                      {line.period_debit ? `${formatPesoAmount(line.period_debit)}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono text-neutral-700">
                      {line.period_credit ? `${formatPesoAmount(line.period_credit)}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono font-semibold text-neutral-900">
                      {line.closing_debit ? `${formatPesoAmount(line.closing_debit)}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-right font-mono font-semibold text-neutral-900">
                      {line.closing_credit ? `${formatPesoAmount(line.closing_credit)}` : '—'}
                    </td>
                  </tr>
                ))}
                {/* Totals row */}
                <tr className="bg-neutral-50 font-semibold text-sm border-t-2 border-neutral-200">
                  <td className="px-3 py-2" colSpan={2}>TOTAL</td>
                  <td className="px-3 py-2 text-right font-mono">
                    {formatPesoAmount(report.data.totals.opening_debit)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    {formatPesoAmount(report.data.totals.opening_credit)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    {formatPesoAmount(report.data.totals.period_debit)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    {formatPesoAmount(report.data.totals.period_credit)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    {formatPesoAmount(report.data.totals.closing_debit)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    {formatPesoAmount(report.data.totals.closing_credit)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p className="text-xs text-neutral-400 text-right">
            Generated: {new Date(report.meta.generated_at).toLocaleString()}
          </p>
        </div>
      )}
    </div>
  )
}
