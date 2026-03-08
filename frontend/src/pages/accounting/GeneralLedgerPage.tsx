import { useState } from 'react'
import { useGeneralLedger } from '@/hooks/useReports'
import { useChartOfAccounts } from '@/hooks/useAccounting'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import type { GLFilters } from '@/types/reports'

// ---------------------------------------------------------------------------
// Running balance row
// ---------------------------------------------------------------------------

function GLRow({
  date, jeNumber, description, sourceType, debit, credit, runningBalance,
}: {
  date: string
  jeNumber: string | null
  description: string
  sourceType: string
  debit: number | null
  credit: number | null
  runningBalance: number
}) {
  return (
    <tr className="border-b border-neutral-100 even:bg-neutral-100 hover:bg-neutral-50 transition-colors text-sm">
      <td className="px-3 py-2 text-neutral-500 whitespace-nowrap">{date}</td>
      <td className="px-3 py-2 font-mono text-xs text-neutral-500">{jeNumber ?? '—'}</td>
      <td className="px-3 py-2 text-neutral-700 max-w-xs truncate">{description}</td>
      <td className="px-3 py-2">
        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-neutral-100 text-neutral-600 capitalize">
          {sourceType}
        </span>
      </td>
      <td className="px-3 py-2 text-right font-mono text-neutral-700">
        {debit != null ? `₱${debit.toLocaleString()}` : ''}
      </td>
      <td className="px-3 py-2 text-right font-mono text-neutral-700">
        {credit != null ? `₱${credit.toLocaleString()}` : ''}
      </td>
      <td className="px-3 py-2 text-right font-mono font-semibold text-neutral-900">
        ₱{runningBalance.toLocaleString()}
      </td>
    </tr>
  )
}

// ---------------------------------------------------------------------------
// General Ledger Page
// ---------------------------------------------------------------------------

export default function GeneralLedgerPage() {
  const [filters, setFilters] = useState<GLFilters>({
    account_id: 0,
    date_from: '',
    date_to: '',
    cost_center_id: null,
  })
  const [submitted, setSubmitted] = useState<GLFilters | null>(null)

  const { data: report, isLoading, isError } = useGeneralLedger(submitted)
  const { data: accounts = [] } = useChartOfAccounts({})

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!filters.account_id || !filters.date_from || !filters.date_to) return
    setSubmitted({ ...filters })
  }

  return (
    <div className="p-6 space-y-6">
      <PageHeader title="General Ledger" />

      <div>
        <p className="text-sm text-neutral-500">
          Line-by-line movement for a single account with running balance
        </p>
      </div>

      {/* Filter form */}
      <form
        onSubmit={handleSubmit}
        className="bg-white border border-neutral-200 rounded p-4 flex flex-wrap gap-4 items-end"
      >
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Account</label>
          <select
            className="border border-neutral-300 rounded px-3 py-2 text-sm w-64 focus:ring-1 focus:ring-neutral-400 focus:outline-none"
            value={filters.account_id || ''}
            onChange={e => setFilters(f => ({ ...f, account_id: parseInt(e.target.value) || 0 }))}
          >
            <option value="">Select account…</option>
            {accounts.map(a => (
              <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
            ))}
          </select>
        </div>
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
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Cost Center (optional)</label>
          <input
            type="number"
            min={1}
            className="border border-neutral-300 rounded px-3 py-2 text-sm w-36 focus:ring-1 focus:ring-neutral-400 focus:outline-none"
            placeholder="Leave blank"
            value={filters.cost_center_id ?? ''}
            onChange={e =>
              setFilters(f => ({ ...f, cost_center_id: e.target.value ? parseInt(e.target.value) : null }))
            }
          />
        </div>
        <button
          type="submit"
          className="px-5 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
        >
          Run Report
        </button>
      </form>

      {/* Results */}
      {isLoading && <SkeletonLoader rows={8} />}
      {isError && (
        <p className="text-red-600 text-sm">Failed to load report. Check filters and try again.</p>
      )}

      {report && (
        <div className="space-y-4">
          {/* Account header + summary */}
          <div className="flex flex-wrap gap-6 bg-white border border-neutral-200 rounded p-4">
            <div>
              <p className="text-xs text-neutral-500">Account</p>
              <p className="font-semibold text-neutral-900">
                {report.data.account.code} — {report.data.account.name}
              </p>
            </div>
            <div>
              <p className="text-xs text-neutral-500">Opening Balance</p>
              <p className="font-mono font-semibold text-neutral-900">
                ₱{report.data.opening_balance.toLocaleString()}
              </p>
            </div>
            <div>
              <p className="text-xs text-neutral-500">Closing Balance</p>
              <p className="font-mono font-semibold text-neutral-800">
                ₱{report.data.closing_balance.toLocaleString()}
              </p>
            </div>
            <div>
              <p className="text-xs text-neutral-500">Lines</p>
              <p className="font-semibold text-neutral-900">{report.data.lines.length}</p>
            </div>
          </div>

          {/* Lines table */}
          <div className="bg-white border border-neutral-200 rounded overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 text-xs font-medium text-neutral-500">
                <tr>
                  <th className="px-3 py-2.5 text-left">Date</th>
                  <th className="px-3 py-2.5 text-left">JE #</th>
                  <th className="px-3 py-2.5 text-left">Description</th>
                  <th className="px-3 py-2.5 text-left">Source</th>
                  <th className="px-3 py-2.5 text-right">Debit</th>
                  <th className="px-3 py-2.5 text-right">Credit</th>
                  <th className="px-3 py-2.5 text-right">Running Balance</th>
                </tr>
              </thead>
              <tbody>
                {report.data.lines.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="text-center py-8 text-neutral-400 text-sm">
                      No transactions in this period.
                    </td>
                  </tr>
                ) : (
                  report.data.lines.map((line, i) => (
                    <GLRow key={i} {...{
                      date: line.date,
                      jeNumber: line.je_number,
                      description: line.description,
                      sourceType: line.source_type,
                      debit: line.debit,
                      credit: line.credit,
                      runningBalance: line.running_balance,
                    }} />
                  ))
                )}
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
