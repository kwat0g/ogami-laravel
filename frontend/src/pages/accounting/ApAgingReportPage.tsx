import { useState, useMemo } from 'react'
import { BarChart3 } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useApAgingReport, type ApAgingRow } from '@/hooks/useAP'

const BUCKET_LABELS: { key: keyof ApAgingRow; label: string; color: string }[] = [
  { key: 'current',  label: 'Current',   color: 'bg-emerald-500' },
  { key: '1_30',     label: '1-30 days',  color: 'bg-amber-400' },
  { key: '31_60',    label: '31-60 days', color: 'bg-orange-400' },
  { key: '61_90',    label: '61-90 days', color: 'bg-red-400' },
  { key: 'over_90',  label: '90+ days',   color: 'bg-red-600' },
]

const fmt = (v: number) =>
  `₱${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

export default function ApAgingReportPage(): React.ReactElement {
  const [asOfDate, setAsOfDate] = useState(new Date().toISOString().split('T')[0])
  const { data, isLoading } = useApAgingReport(asOfDate)

  const rows: ApAgingRow[] = useMemo(() => data?.data ?? [], [data?.data])

  const totals = useMemo(() => {
    const t = { current: 0, '1_30': 0, '31_60': 0, '61_90': 0, over_90: 0, total: 0 }
    for (const r of rows) {
      t.current += r.current
      t['1_30'] += r['1_30']
      t['31_60'] += r['31_60']
      t['61_90'] += r['61_90']
      t.over_90 += r.over_90
      t.total += r.total
    }
    return t
  }, [rows])

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="AP Aging Report"
        subtitle="Payable balances grouped by vendor and days overdue"
        icon={<BarChart3 className="w-5 h-5 text-rose-600" />}
        actions={
          <div>
            <label className="block text-xs font-medium text-neutral-500 mb-1">As of Date</label>
            <input
              type="date"
              value={asOfDate}
              onChange={(e) => setAsOfDate(e.target.value)}
              className="border border-neutral-300 rounded px-3 py-2 text-sm"
            />
          </div>
        }
      />

      {/* Summary bar */}
      {rows.length > 0 && totals.total > 0 && (
        <div className="mb-6 bg-white border border-neutral-200 rounded-lg p-4">
          <div className="flex items-center gap-2 mb-2">
            <span className="text-sm font-medium text-neutral-700">Total Outstanding Payables:</span>
            <span className="text-lg font-bold text-neutral-900">{fmt(totals.total)}</span>
          </div>
          <div className="h-3 w-full rounded-full overflow-hidden flex">
            {BUCKET_LABELS.map(({ key, color }) => {
              const pct = totals.total > 0 ? ((totals[key as keyof typeof totals] as number) / totals.total) * 100 : 0
              return pct > 0 ? (
                <div key={key} className={`${color} h-full`} style={{ width: `${pct}%` }} title={`${key}: ${pct.toFixed(1)}%`} />
              ) : null
            })}
          </div>
          <div className="flex gap-4 mt-2">
            {BUCKET_LABELS.map(({ key, label, color }) => (
              <span key={key} className="flex items-center gap-1 text-xs text-neutral-500">
                <span className={`w-2.5 h-2.5 rounded-sm ${color}`} /> {label}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        {isLoading ? (
          <p className="text-sm text-neutral-500 p-6">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="text-sm text-neutral-500 p-6">No outstanding payables as of this date.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="border-b border-neutral-200 bg-neutral-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Vendor</th>
                {BUCKET_LABELS.map(({ key, label }) => (
                  <th key={key} className="px-4 py-3 text-right text-xs font-medium text-neutral-500">{label}</th>
                ))}
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-700">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {rows.map((row) => (
                <tr key={row.vendor_id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 text-neutral-800 font-medium">{row.vendor_name}</td>
                  {BUCKET_LABELS.map(({ key }) => {
                    const val = row[key] as number
                    return (
                      <td key={key} className={`px-4 py-3 text-right tabular-nums ${val > 0 ? 'text-neutral-900' : 'text-neutral-300'}`}>
                        {val > 0 ? fmt(val) : '—'}
                      </td>
                    )
                  })}
                  <td className="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900">{fmt(row.total)}</td>
                </tr>
              ))}
              {/* Totals row */}
              <tr className="bg-neutral-50 font-semibold border-t-2 border-neutral-300">
                <td className="px-4 py-3 text-neutral-700">Grand Total</td>
                {BUCKET_LABELS.map(({ key }) => (
                  <td key={key} className="px-4 py-3 text-right tabular-nums text-neutral-900">
                    {fmt(totals[key as keyof typeof totals] as number)}
                  </td>
                ))}
                <td className="px-4 py-3 text-right tabular-nums font-bold text-neutral-900">{fmt(totals.total)}</td>
              </tr>
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
