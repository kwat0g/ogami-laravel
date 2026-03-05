import { useState } from 'react'
import { useVatLedgerList, useCloseVatPeriod } from '@/hooks/useTax'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { VatLedger } from '@/types/tax'

// ---------------------------------------------------------------------------
// VAT Summary Card
// ---------------------------------------------------------------------------

function VatCard({ label, amount, highlight }: { label: string; amount: number; highlight?: 'green' | 'red' | 'blue' }) {
  const colours = {
    green: 'border-green-200 bg-green-50 text-green-700',
    red:   'border-red-200 bg-red-50 text-red-700',
    blue:  'border-blue-200 bg-blue-50 text-blue-700',
    default: 'border-gray-200 bg-gray-50 text-gray-700',
  }
  const cls = highlight ? colours[highlight] : colours.default

  return (
    <div className={`rounded-xl border p-4 ${cls}`}>
      <p className="text-xs font-medium uppercase tracking-wide opacity-70">{label}</p>
      <p className="text-2xl font-bold mt-1">₱{amount.toLocaleString()}</p>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Close Period Button
// ---------------------------------------------------------------------------

function ClosePeriodButton({ ledger }: { ledger: VatLedger }) {
  const closeMut = useCloseVatPeriod(ledger.id)
  const [nextPeriodId] = useState<string>('')

  return (
    <ConfirmDestructiveDialog
      title="Close VAT Period"
      description={`Close vat period for fiscal period #${ledger.fiscal_period_id}? ${
        ledger.vat_payable < 0
          ? `Net VAT is negative (₱${ledger.vat_payable.toLocaleString()}). The surplus will be carried forward to the next period.`
          : `VAT payable: ₱${ledger.vat_payable.toLocaleString()}.`
      }`}
      confirmWord="CLOSE"
      confirmLabel="Close Period"
      onConfirm={async () => {
        await closeMut.mutateAsync(
          nextPeriodId ? { next_fiscal_period_id: parseInt(nextPeriodId) } : {}
        )
      }}
    >
      <button className="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">
        Close Period
      </button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// VAT Ledger Page
// ---------------------------------------------------------------------------

export default function VatLedgerPage() {
  const { data, isLoading } = useVatLedgerList({ per_page: 12 })
  const ledgers = data?.data ?? []

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">VAT Ledger</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Per-period input / output / net VAT tracking (VAT-004)
        </p>
      </div>

      {isLoading ? (
        <SkeletonLoader rows={4} />
      ) : ledgers.length === 0 ? (
        <p className="text-gray-400 text-sm">No VAT ledger entries found.</p>
      ) : (
        <div className="space-y-8">
          {ledgers.map((ledger) => (
            <div key={ledger.id} className="rounded-2xl border p-5 space-y-4">
              {/* Period header */}
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-semibold text-gray-900">Fiscal Period #{ledger.fiscal_period_id}</p>
                  {ledger.is_closed && (
                    <p className="text-xs text-gray-400 mt-0.5">
                      Closed {ledger.closed_at ? new Date(ledger.closed_at).toLocaleDateString() : ''}
                      {ledger.closed_by ? ` by ${ledger.closed_by.name}` : ''}
                    </p>
                  )}
                </div>
                {ledger.is_closed ? (
                  <span className="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Closed</span>
                ) : (
                  <div className="flex items-center gap-2">
                    <span className="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-600">Open</span>
                    <ClosePeriodButton ledger={ledger} />
                  </div>
                )}
              </div>

              {/* VAT cards */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <VatCard label="Input VAT" amount={ledger.input_vat} />
                <VatCard label="Output VAT" amount={ledger.output_vat} highlight="blue" />
                <VatCard
                  label="Net VAT"
                  amount={ledger.net_vat}
                  highlight={ledger.net_vat >= 0 ? 'green' : 'red'}
                />
                <VatCard
                  label="VAT Payable"
                  amount={ledger.vat_payable}
                  highlight={ledger.vat_payable > 0 ? 'red' : 'green'}
                />
              </div>

              {/* Carry-forward indicator */}
              {ledger.carry_forward_from_prior > 0 && (
                <div className="text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
                  Carry-forward from prior period: ₱{ledger.carry_forward_from_prior.toLocaleString()}
                  (reduces this period's VAT payable)
                </div>
              )}
              {ledger.vat_payable < 0 && !ledger.is_closed && (
                <div className="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2">
                  Negative VAT payable: ₱{Math.abs(ledger.vat_payable).toLocaleString()} will be carried forward
                  to the next period upon closing.
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
