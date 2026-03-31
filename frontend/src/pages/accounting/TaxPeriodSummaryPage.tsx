import { formatPesoAmount } from '@/lib/formatters'
import { useVatLedgerList } from '@/hooks/useTax'
import { formatPesoAmount } from '@/lib/formatters'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'

// ---------------------------------------------------------------------------
// Tax Period Summary Page — tabular view of all VAT periods
// ---------------------------------------------------------------------------

export default function TaxPeriodSummaryPage() {
  const { data, isLoading } = useVatLedgerList({ per_page: 24 })
  const ledgers = data?.data ?? []

  return (
    <div className="space-y-4">
      <PageHeader title="Tax Period Summary" />
      <div>
        <p className="text-sm text-neutral-500">VAT ledger across all fiscal periods</p>
      </div>

      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : (
        <div className="overflow-x-auto rounded border border-neutral-200">
          <table className="min-w-full divide-y divide-neutral-100 text-sm">
            <thead className="bg-neutral-50">
              <tr>
                {[
                  'Fiscal Period',
                  'Input VAT',
                  'Output VAT',
                  'Net VAT',
                  'Carry-Forward',
                  'VAT Payable',
                  'Status',
                ].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-neutral-500">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-neutral-100">
              {ledgers.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-neutral-400">
                    No VAT data found.
                  </td>
                </tr>
              ) : (
                ledgers.map((l) => (
                  <tr key={l.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-3 font-medium text-neutral-900">#{l.fiscal_period_id}</td>
                    <td className="px-4 py-3 text-neutral-700">{formatPesoAmount(l.input_vat)}</td>
                    <td className="px-4 py-3 text-neutral-700">{formatPesoAmount(l.output_vat)}</td>
                    <td className={`px-4 py-3 font-medium ${l.net_vat >= 0 ? 'text-neutral-800' : 'text-neutral-700'}`}>
                      {formatPesoAmount(l.net_vat)}
                    </td>
                    <td className="px-4 py-3 text-neutral-600">
                      {l.carry_forward_from_prior > 0 ? `${formatPesoAmount(l.carry_forward_from_prior)}` : '—'}
                    </td>
                    <td className={`px-4 py-3 font-semibold ${l.vat_payable > 0 ? 'text-neutral-800' : 'text-neutral-700'}`}>
                      {formatPesoAmount(l.vat_payable)}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`px-2 py-0.5 rounded text-xs font-medium ${
                          l.is_closed
                            ? 'bg-neutral-100 text-neutral-500'
                            : 'bg-neutral-100 text-neutral-700'
                        }`}
                      >
                        {l.is_closed ? 'Closed' : 'Open'}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
