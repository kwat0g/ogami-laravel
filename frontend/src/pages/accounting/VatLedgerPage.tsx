import { useState } from 'react'
import { toast } from 'sonner'
import { FileText } from 'lucide-react'
import { useVatLedgerList, useCloseVatPeriod } from '@/hooks/useTax'
import { firstErrorMessage } from '@/lib/errorHandler'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import { PageHeader } from '@/components/ui/PageHeader'
import type { VatLedger } from '@/types/tax'

// ---------------------------------------------------------------------------
// VAT Summary Card
// ---------------------------------------------------------------------------

function VatCard({ label, amount, highlight: _highlight }: { label: string; amount: number; highlight?: 'green' | 'red' | 'blue' }) {
  // Neutral styling regardless of highlight
  return (
    <div className="rounded border border-neutral-200 bg-neutral-50 p-4">
      <p className="text-xs font-medium opacity-70 text-neutral-600">{label}</p>
      <p className="text-lg font-semibold mt-1 text-neutral-900">₱{amount.toLocaleString()}</p>
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
        try {
          await closeMut.mutateAsync(
            nextPeriodId ? { next_fiscal_period_id: parseInt(nextPeriodId) } : {}
          )
          toast.success('VAT period closed.')
        } catch (err) {
          toast.error(firstErrorMessage(err))
        }
      }}
    >
      <button className="px-3 py-1.5 rounded bg-neutral-900 text-white text-xs font-medium hover:bg-neutral-800">
        Close Period
      </button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Generate Report Button (placeholder - hook needs to be implemented)
// ---------------------------------------------------------------------------

function GenerateReportButton({ ledger }: { ledger: VatLedger }) {
  const handleGenerate = () => {
    // Placeholder: This would generate a VAT report when the hook is available
    toast.info('VAT report generation coming soon.')
  }

  return (
    <ConfirmDialog
      title="Generate VAT Report?"
      description={`Generate VAT report for fiscal period #${ledger.fiscal_period_id}? This will create a detailed breakdown of input/output VAT for filing purposes.`}
      confirmLabel="Generate"
      onConfirm={handleGenerate}
    >
      <button 
        className="flex items-center gap-1.5 px-3 py-1.5 rounded border border-neutral-300 text-neutral-700 text-xs font-medium hover:bg-neutral-50"
      >
        <FileText className="w-3.5 h-3.5" />
        Generate Report
      </button>
    </ConfirmDialog>
  )
}



// ---------------------------------------------------------------------------
// VAT Ledger Page
// ---------------------------------------------------------------------------

export default function VatLedgerPage() {
  const { data, isLoading } = useVatLedgerList({ per_page: 12 })
  const ledgers = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader title="VAT Ledger" />

      <div>
        <p className="text-sm text-neutral-500">
          Per-period input / output / net VAT tracking (VAT-004)
        </p>
      </div>

      {isLoading ? (
        <SkeletonLoader rows={4} />
      ) : ledgers.length === 0 ? (
        <p className="text-neutral-400 text-sm">No VAT ledger entries found.</p>
      ) : (
        <div className="space-y-8">
          {ledgers.map((ledger) => (
            <div key={ledger.id} className="rounded border border-neutral-200 p-5 space-y-4">
              {/* Period header */}
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-semibold text-neutral-900">Fiscal Period #{ledger.fiscal_period_id}</p>
                  {ledger.is_closed && (
                    <p className="text-xs text-neutral-400 mt-0.5">
                      Closed {ledger.closed_at ? new Date(ledger.closed_at).toLocaleDateString() : ''}
                      {ledger.closed_by ? ` by ${ledger.closed_by.name}` : ''}
                    </p>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  {ledger.is_closed ? (
                    <>
                      <span className="px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-500">Closed</span>

                    </>
                  ) : (
                    <>
                      <span className="px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">Open</span>
                      <GenerateReportButton ledger={ledger} />
                      <ClosePeriodButton ledger={ledger} />
                    </>
                  )}
                </div>
              </div>

              {/* VAT cards */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <VatCard label="Input VAT" amount={ledger.input_vat} />
                <VatCard label="Output VAT" amount={ledger.output_vat} />
                <VatCard
                  label="Net VAT"
                  amount={ledger.net_vat}
                />
                <VatCard
                  label="VAT Payable"
                  amount={ledger.vat_payable}
                />
              </div>

              {/* Carry-forward indicator */}
              {ledger.carry_forward_from_prior > 0 && (
                <div className="text-xs text-neutral-600 bg-neutral-50 border border-neutral-200 rounded px-3 py-2">
                  Carry-forward from prior period: ₱{ledger.carry_forward_from_prior.toLocaleString()}
                  (reduces this period's VAT payable)
                </div>
              )}
              {ledger.vat_payable < 0 && !ledger.is_closed && (
                <div className="text-xs text-neutral-600 bg-neutral-50 border border-neutral-200 rounded px-3 py-2">
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
