import { formatPesoAmount } from '@/lib/formatters'
import { useState } from 'react'
import { useBalanceSheet } from '@/hooks/useReports'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'
import type { BalanceSheetFilters, BSSection, BSClassification } from '@/types/reports'

const ASSET_KEYS: BSClassification[] = ['current_asset', 'non_current_asset']
const LIABILITY_KEYS: BSClassification[] = ['current_liability', 'non_current_liability']
const EQUITY_KEYS: BSClassification[] = ['equity']

function SectionBlock({ section }: { section: BSSection }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-semibold text-neutral-500 px-2 pt-2">{section.label}</p>
      {section.accounts.map((acct, i) => (
        <div key={i} className="flex justify-between items-center px-4 py-1 text-sm text-neutral-700">
          <span>
            <span className="font-mono text-xs text-neutral-400 mr-2">{acct.code}</span>
            {acct.name}
          </span>
          <span className="font-mono">{formatPesoAmount(acct.balance)}</span>
        </div>
      ))}
      <div className="flex justify-between items-center px-4 py-1.5 bg-neutral-50 text-sm font-semibold text-neutral-800 border-t border-neutral-200">
        <span>Subtotal — {section.label}</span>
        <span className="font-mono">{formatPesoAmount(section.total)}</span>
      </div>
    </div>
  )
}

export default function BalanceSheetPage() {
  const [asOfDate, setAsOfDate] = useState('')
  const [compDate, setCompDate] = useState('')
  const [showComp, setShowComp] = useState(false)
  const [filters, setFilters] = useState<BalanceSheetFilters | null>(null)

  const { data: report, isLoading, isError } = useBalanceSheet(filters)

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!asOfDate) return
    setFilters({
      as_of_date: asOfDate,
      comparative_date: showComp && compDate ? compDate : undefined,
    })
  }

  const bs = report?.data

  return (
    <div className="space-y-6">
      <PageHeader title="Balance Sheet" />

      <div>
        <p className="text-sm text-neutral-500">
          Classified statement of financial position — PFRS compliant
        </p>
      </div>

      <form
        onSubmit={handleSubmit}
        className="bg-white border border-neutral-200 rounded p-4 flex flex-wrap gap-4 items-end"
      >
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">As of Date</label>
          <input
            type="date"
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
            value={asOfDate}
            onChange={e => setAsOfDate(e.target.value)}
          />
        </div>
        <div className="flex items-end gap-2">
          <label className="flex items-center gap-1.5 text-xs font-medium text-neutral-600 cursor-pointer pb-2">
            <input
              type="checkbox"
              className="rounded border-neutral-300"
              checked={showComp}
              onChange={e => setShowComp(e.target.checked)}
            />
            Comparative
          </label>
          {showComp && (
            <div className="flex flex-col gap-1">
              <label className="text-xs font-medium text-neutral-600">Comparative Date</label>
              <input
                type="date"
                className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none"
                value={compDate}
                onChange={e => setCompDate(e.target.value)}
              />
            </div>
          )}
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

      {bs && (() => {
        const assetSections     = bs.sections.filter(s => (ASSET_KEYS as string[]).includes(s.key))
        const liabilitySections = bs.sections.filter(s => (LIABILITY_KEYS as string[]).includes(s.key))
        const equitySections    = bs.sections.filter(s => (EQUITY_KEYS as string[]).includes(s.key))
        return (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Assets */}
            <div className="bg-white border border-neutral-200 rounded p-4 space-y-4">
              <h2 className="text-base font-bold text-neutral-800">Assets</h2>
              {assetSections.map((s, i) => <SectionBlock key={i} section={s} />)}
              <div className="flex justify-between items-center border-t-2 border-neutral-800 pt-2 font-bold text-neutral-900">
                <span>Total Assets</span>
                <span className="font-mono">{formatPesoAmount(bs.totals.total_assets)}</span>
              </div>
            </div>

            {/* Liabilities + Equity */}
            <div className="bg-white border border-neutral-200 rounded p-4 space-y-4">
              <h2 className="text-base font-bold text-neutral-800">Liabilities</h2>
              {liabilitySections.map((s, i) => <SectionBlock key={i} section={s} />)}
              <div className="flex justify-between items-center border-t border-neutral-200 pt-2 font-semibold text-neutral-700">
                <span>Total Liabilities</span>
                <span className="font-mono">{formatPesoAmount(bs.totals.total_liabilities)}</span>
              </div>

              <h2 className="text-base font-bold text-neutral-800 pt-2">Equity</h2>
              {equitySections.map((s, i) => <SectionBlock key={i} section={s} />)}
              <div className="flex justify-between items-center border-t border-neutral-200 pt-2 font-semibold text-neutral-700">
                <span>Total Equity</span>
                <span className="font-mono">{formatPesoAmount(bs.totals.total_equity)}</span>
              </div>

              <div className="flex justify-between items-center border-t-2 border-neutral-800 pt-2 font-bold text-neutral-900">
                <span>Total Liabilities &amp; Equity</span>
                <span className="font-mono">{formatPesoAmount(bs.totals.total_liabilities_and_equity)}</span>
              </div>
            </div>
          </div>
        )
      })()}
    </div>
  )
}
