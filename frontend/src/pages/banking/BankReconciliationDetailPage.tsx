import { useParams } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import { PERMISSIONS } from '@/lib/permissions'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import {
  useBankReconciliation,
  useUnmatchTransaction,
  useCertifyReconciliation,
} from '@/hooks/useBanking'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import SodActionButton from '@/components/ui/SodActionButton'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonTable from '@/components/ui/SkeletonTable'
import { PageHeader } from '@/components/ui/PageHeader'
import type { BankTransaction } from '@/types/banking'

function fmtAmt(amount: number) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
}

export default function BankReconciliationDetailPage() {
  const { hasPermission } = useAuthStore()
  const { ulid: id } = useParams<{ ulid: string }>()
  const reconciliationId = id ?? null

  const canCertify = hasPermission(PERMISSIONS.bank_reconciliations.certify)
  const canEdit = hasPermission(PERMISSIONS.bank_reconciliations.create)

  const { data: recon, isLoading, isError } = useBankReconciliation(reconciliationId)
  const unmatch  = useUnmatchTransaction(reconciliationId ?? '')
  const certify  = useCertifyReconciliation(reconciliationId ?? '')

  if (isLoading) return (
    <div className="space-y-4">
      <div className="h-6 w-48 bg-neutral-200 animate-pulse rounded" />
      <SkeletonTable rows={8} cols={5} />
    </div>
  )

  if (isError || !recon) return (
    <div className="p-6 text-red-600 text-sm">Bank reconciliation not found or failed to load.</div>
  )

  const transactions = recon.transactions ?? []
  const unmatched    = transactions.filter(t => t.status === 'unmatched')
  const matched      = transactions.filter(t => t.status !== 'unmatched')
  const isDraft      = recon.status === 'draft'

  async function handleCertify() {
    try {
      await certify.mutateAsync()
      toast.success('Bank reconciliation certified successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to certify reconciliation.'))
    }
  }

  async function handleUnmatch(txId: number) {
    try {
      await unmatch.mutateAsync(txId)
      toast.success('Transaction unmatched successfully.')
    } catch (err) {
      toast.error(firstErrorMessage(err, 'Failed to unmatch transaction.'))
    }
  }

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      <ExecutiveReadOnlyBanner />

      <PageHeader
        title={recon.bank_account?.account_number ?? `Account #${recon.bank_account_id}`}
        backTo="/banking/reconciliations"
      />

      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-neutral-500">
            {recon.period_from} – {recon.period_to}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <StatusBadge status={recon.status}>{recon.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
          {isDraft && canCertify && (
            <SodActionButton
              initiatedById={recon.created_by_id}
              label={certify.isPending ? 'Certifying…' : 'Certify'}
              onClick={() => void handleCertify()}
              isLoading={certify.isPending}
              disabled={recon.unmatched_count > 0}
            />
          )}
        </div>
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-4 gap-4">
        {[
          { label: 'Opening Balance',  value: fmtAmt(recon.opening_balance) },
          { label: 'Closing Balance',  value: fmtAmt(recon.closing_balance) },
          { label: 'Difference',       value: fmtAmt(recon.closing_balance - recon.opening_balance) },
          { label: 'Unmatched',        value: String(recon.unmatched_count) },
        ].map(({ label, value }) => (
          <div key={label} className="rounded border border-neutral-200 bg-white p-4">
            <p className="text-xs text-neutral-500">{label}</p>
            <p className="mt-1 text-lg font-semibold text-neutral-900">{value}</p>
          </div>
        ))}
      </div>

      {/* Notes */}
      {recon.notes && (
        <div className="rounded border-l-4 border-neutral-400 bg-neutral-50 px-4 py-3 text-sm text-neutral-700">
          {recon.notes}
        </div>
      )}

      {/* Unmatched transactions */}
      <section>
        <h2 className="text-sm font-semibold text-neutral-700 mb-2">
          Unmatched Transactions
          {unmatched.length > 0 && (
            <span className="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700">
              {unmatched.length}
            </span>
          )}
        </h2>
        <TransactionTable
          transactions={unmatched}
          isDraft={isDraft}
          onUnmatch={null}
          emptyMsg="No unmatched transactions."
        />
      </section>

      {/* Matched / reconciled transactions */}
      <section>
        <h2 className="text-sm font-semibold text-neutral-700 mb-2">
          Matched Transactions
          <span className="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700">
            {matched.length}
          </span>
        </h2>
        <TransactionTable
          transactions={matched}
          isDraft={isDraft}
          onUnmatch={isDraft && canEdit ? (txId) => void handleUnmatch(txId) : null}
          emptyMsg="No matched transactions yet."
        />
      </section>

      {/* Certification info */}
      {recon.certified_at && (
        <p className="text-xs text-neutral-500">
          Certified on {new Date(recon.certified_at).toLocaleDateString('en-PH', { dateStyle: 'long' })}
          {recon.certified_by ? ` by user #${recon.certified_by}` : ''}
        </p>
      )}
    </div>
  )
}

interface TxTableProps {
  transactions: BankTransaction[]
  isDraft: boolean
  onUnmatch: ((id: number) => void) | null
  emptyMsg: string
}

function TransactionTable({ transactions, isDraft, onUnmatch, emptyMsg }: TxTableProps) {
  return (
    <div className="overflow-x-auto rounded border border-neutral-200 bg-white">
      <table className="min-w-full divide-y divide-neutral-100 text-sm">
        <thead className="bg-neutral-50 border-b border-neutral-200">
          <tr>
            {['Date', 'Description', 'Type', 'Amount', 'Status', isDraft && onUnmatch ? 'Actions' : null]
              .filter(Boolean)
              .map(h => (
                <th key={h as string} className="text-left px-4 py-2 font-medium text-neutral-600">{h}</th>
              ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-neutral-100">
          {transactions.length === 0 ? (
            <tr>
              <td colSpan={6} className="px-4 py-6 text-center text-neutral-400">{emptyMsg}</td>
            </tr>
          ) : transactions.map(tx => (
            <tr key={tx.id} className="hover:bg-neutral-50">
              <td className="px-4 py-2 whitespace-nowrap">{tx.transaction_date}</td>
              <td className="px-4 py-2 max-w-xs truncate">{tx.description}</td>
              <td className="px-4 py-2 capitalize">{tx.transaction_type}</td>
              <td className="px-4 py-2 text-right tabular-nums">
                {new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(tx.amount)}
              </td>
              <td className="px-4 py-2">
                <StatusBadge status={tx.status}>{tx.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
              </td>
              {isDraft && onUnmatch && (
                <td className="px-4 py-2">
                  {tx.status === 'matched' && (
                    <button
                      onClick={() => onUnmatch(tx.id)}
                      className="text-xs px-2 py-1 border border-neutral-300 rounded hover:bg-neutral-50"
                    >
                      Unmatch
                    </button>
                  )}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
