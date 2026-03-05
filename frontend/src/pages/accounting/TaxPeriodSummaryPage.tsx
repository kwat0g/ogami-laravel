import { useVatLedgerList } from '@/hooks/useTax'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

// ---------------------------------------------------------------------------
// Tax Period Summary Page — tabular view of all VAT periods
// ---------------------------------------------------------------------------

export default function TaxPeriodSummaryPage() {
  const { data, isLoading } = useVatLedgerList({ per_page: 24 })
  const ledgers = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Tax Period Summary</h1>
        <p className="text-sm text-gray-500 mt-0.5">VAT ledger across all fiscal periods</p>
      </div>

      {isLoading ? (
        <SkeletonLoader rows={8} />
      ) : (
        <div className="overflow-x-auto rounded-xl border">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
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
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-100">
              {ledgers.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-gray-400">
                    No VAT data found.
                  </td>
                </tr>
              ) : (
                ledgers.map((l) => (
                  <tr key={l.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium text-gray-900">#{l.fiscal_period_id}</td>
                    <td className="px-4 py-3 text-gray-700">₱{l.input_vat.toLocaleString()}</td>
                    <td className="px-4 py-3 text-gray-700">₱{l.output_vat.toLocaleString()}</td>
                    <td className={`px-4 py-3 font-medium ${l.net_vat >= 0 ? 'text-green-700' : 'text-red-600'}`}>
                      ₱{l.net_vat.toLocaleString()}
                    </td>
                    <td className="px-4 py-3 text-indigo-600">
                      {l.carry_forward_from_prior > 0 ? `₱${l.carry_forward_from_prior.toLocaleString()}` : '—'}
                    </td>
                    <td className={`px-4 py-3 font-semibold ${l.vat_payable > 0 ? 'text-red-700' : 'text-green-700'}`}>
                      ₱{l.vat_payable.toLocaleString()}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`px-2 py-0.5 rounded text-xs font-medium ${
                          l.is_closed
                            ? 'bg-gray-100 text-gray-500'
                            : 'bg-blue-100 text-blue-600'
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
