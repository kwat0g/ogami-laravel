import { useState } from 'react'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import {
  useBankReconciliations,
  useBankReconciliation,
  useCreateBankReconciliation,
  useImportStatement,
  useMatchTransaction,
  useUnmatchTransaction,
  useCertifyReconciliation,
} from '@/hooks/useBanking'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

import { SodActionButton } from '@/components/ui/SodActionButton'
import type {
  BankReconciliation,
  BankTransaction,
  CreateBankReconciliationPayload,
  BankStatementLine,
} from '@/types/banking'

// ---------------------------------------------------------------------------
// Certify button — SoD enforced at both UI and server layer (SOD-007)
// The preparer of a reconciliation cannot be the one to certify it.
// ---------------------------------------------------------------------------

function CertifyButton({ reconciliationId, createdBy }: { reconciliationId: string; createdBy: number }) {
  const { mutate: certify, isPending } = useCertifyReconciliation(reconciliationId)
  return (
    <SodActionButton
      initiatedById={createdBy}
      label="Certify"
      onClick={() => certify()}
      isLoading={isPending}
      variant="primary"
    />
  )
}

// ---------------------------------------------------------------------------
// Create reconciliation modal
// ---------------------------------------------------------------------------

const EMPTY: CreateBankReconciliationPayload = {
  bank_account_id: 0,
  period_from: '',
  period_to: '',
  opening_balance: 0,
  closing_balance: 0,
  notes: '',
}

function CreateReconciliationModal({ onClose }: { onClose: () => void }) {
  const [form, setForm] = useState<CreateBankReconciliationPayload>(EMPTY)
  const { mutate: create, isPending } = useCreateBankReconciliation()
  const inputCls = 'border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-indigo-500 focus:outline-none'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <ExecutiveReadOnlyBanner />
      <form
        onSubmit={e => {
          e.preventDefault()
          create(form, { onSuccess: onClose })
        }}
        className="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4"
      >
        <h2 className="text-lg font-bold text-gray-900">New Reconciliation</h2>
        {[
          ['Bank Account ID', <input type="number" min={1} className={inputCls} value={form.bank_account_id || ''} onChange={e => setForm(f => ({ ...f, bank_account_id: parseInt(e.target.value) || 0 }))} required />],
          ['Period From', <input type="date" className={inputCls} value={form.period_from} onChange={e => setForm(f => ({ ...f, period_from: e.target.value }))} required />],
          ['Period To', <input type="date" className={inputCls} value={form.period_to} onChange={e => setForm(f => ({ ...f, period_to: e.target.value }))} required />],
          ['Opening Balance', <input type="number" step="0.01" className={inputCls} value={form.opening_balance} onChange={e => setForm(f => ({ ...f, opening_balance: parseFloat(e.target.value) || 0 }))} />],
          ['Closing Balance', <input type="number" step="0.01" className={inputCls} value={form.closing_balance} onChange={e => setForm(f => ({ ...f, closing_balance: parseFloat(e.target.value) || 0 }))} />],
          ['Notes', <input className={inputCls} value={form.notes ?? ''} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} />],
        ].map(([label, input]) => (
          <div key={label as string} className="flex flex-col gap-1">
            <label className="text-xs font-medium text-gray-600">{label as string}</label>
            {input as React.ReactNode}
          </div>
        ))}
        <div className="flex gap-3 pt-2">
          <button type="submit" disabled={isPending}
            className="flex-1 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
            {isPending ? 'Creating…' : 'Create'}
          </button>
          <button type="button" onClick={onClose}
            className="flex-1 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
        </div>
      </form>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Reconciliation detail panel
// ---------------------------------------------------------------------------

function statusBadge(status: string) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${
      status === 'certified'
        ? 'bg-emerald-100 text-emerald-700'
        : status === 'matched'
        ? 'bg-blue-100 text-blue-700'
        : 'bg-yellow-100 text-yellow-700'
    }`}>
      {status}
    </span>
  )
}

function ReconciliationDetail({ reconciliation }: { reconciliation: BankReconciliation }) {
  const { data, isLoading } = useBankReconciliation(reconciliation.ulid)
  const recon = data
  const { mutate: importStmt, isPending: importing } = useImportStatement(reconciliation.ulid)
  const { mutate: match, isPending: matching } = useMatchTransaction(reconciliation.ulid)
  const { mutate: unmatch } = useUnmatchTransaction(reconciliation.ulid)

  const [selectedBankTx, setSelectedBankTx] = useState<number | null>(null)
  const [jeLineId, setJeLineId] = useState('')
  const [csvLines] = useState<BankStatementLine[]>([]) // In a real app: parse CSV upload

  if (isLoading) return <SkeletonLoader rows={6} />
  if (!recon) return null

  const isCertified = recon.status === 'certified'

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-6 bg-white border border-gray-200 rounded-xl p-4 items-center">
        <div>
          <p className="text-xs text-gray-500">Period</p>
          <p className="font-semibold text-gray-900">{recon.period_from} → {recon.period_to}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Opening</p>
          <p className="font-mono font-semibold">₱{recon.opening_balance.toLocaleString()}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Closing</p>
          <p className="font-mono font-semibold">₱{recon.closing_balance.toLocaleString()}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Status</p>
          {statusBadge(recon.status)}
        </div>
        <div>
          <p className="text-xs text-gray-500">Unmatched</p>
          <p className="font-semibold text-gray-900">{recon.unmatched_count}</p>
        </div>
        {!isCertified && (
          <div className="ml-auto flex gap-2">
            <button
              type="button"
              disabled={importing}
              onClick={() => importStmt({ transactions: csvLines })}
              className="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              {importing ? 'Importing…' : 'Import Statement'}
            </button>
            <CertifyButton reconciliationId={recon.ulid} createdBy={recon.created_by} />
          </div>
        )}
      </div>

      {/* Transactions table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
            <tr>
              <th className="px-3 py-2 text-left">Date</th>
              <th className="px-3 py-2 text-left">Description</th>
              <th className="px-3 py-2 text-left">Ref</th>
              <th className="px-3 py-2 text-left">Type</th>
              <th className="px-3 py-2 text-right">Amount</th>
              <th className="px-3 py-2 text-left">Status</th>
              {!isCertified && <th className="px-3 py-2 text-left">Action</th>}
            </tr>
          </thead>
          <tbody>
            {(recon.transactions ?? [] as BankTransaction[]).map((tx: BankTransaction) => (
              <tr key={tx.id} className={`border-b border-gray-100 hover:bg-gray-50 ${
                selectedBankTx === tx.id ? 'bg-indigo-50' : ''
              }`}>
                <td className="px-3 py-2 text-gray-500 whitespace-nowrap">{tx.transaction_date}</td>
                <td className="px-3 py-2 text-gray-700 max-w-xs truncate">{tx.description}</td>
                <td className="px-3 py-2 font-mono text-xs text-gray-400">{tx.reference_number ?? '—'}</td>
                <td className="px-3 py-2">
                  <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium capitalize ${
                    tx.transaction_type === 'debit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'
                  }`}>{tx.transaction_type}</span>
                </td>
                <td className="px-3 py-2 text-right font-mono">₱{tx.amount.toLocaleString()}</td>
                <td className="px-3 py-2">{statusBadge(tx.status)}</td>
                {!isCertified && (
                  <td className="px-3 py-2">
                    {tx.status === 'unmatched' && (
                      <button
                        type="button"
                        onClick={() => setSelectedBankTx(tx.id === selectedBankTx ? null : tx.id)}
                        className="text-indigo-600 hover:text-indigo-800 text-xs font-medium"
                      >
                        {selectedBankTx === tx.id ? 'Cancel' : 'Match'}
                      </button>
                    )}
                    {tx.status === 'matched' && (
                      <button
                        type="button"
                        onClick={() => unmatch(tx.id)}
                        className="text-orange-500 hover:text-orange-700 text-xs font-medium"
                      >
                        Unmatch
                      </button>
                    )}
                    {tx.status === 'reconciled' && (
                      <span className="text-emerald-600 text-xs font-medium">Reconciled</span>
                    )}
                  </td>
                )}
              </tr>
            ))}
            {(recon.transactions ?? []).length === 0 && (
              <tr>
                <td colSpan={7} className="text-center py-8 text-gray-400 text-sm">
                  No transactions. Import a bank statement to begin.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Match panel */}
      {selectedBankTx !== null && !isCertified && (
        <div className="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex gap-4 items-end">
          <p className="text-sm text-indigo-800 font-medium">
            Matching bank transaction #{selectedBankTx} to JE line:
          </p>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-indigo-700">JE Line ID</label>
            <input
              type="number"
              className="border border-indigo-300 rounded-lg px-3 py-2 text-sm w-36 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
              value={jeLineId}
              onChange={e => setJeLineId(e.target.value)}
            />
          </div>
          <button
            type="button"
            disabled={matching || !jeLineId}
            onClick={() => {
              match(
                { bank_transaction_id: selectedBankTx!, journal_entry_line_id: parseInt(jeLineId) },
                { onSuccess: () => { setSelectedBankTx(null); setJeLineId('') } }
              )
            }}
            className="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
          >
            {matching ? 'Matching…' : 'Confirm Match'}
          </button>
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function BankReconciliationPage() {
  const { data, isLoading } = useBankReconciliations()
  const reconciliations: BankReconciliation[] = data?.data ?? []
  const [showCreate, setShowCreate] = useState(false)
  const [selected, setSelected] = useState<BankReconciliation | null>(null)

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Bank Reconciliation</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Match bank transactions to GL journal entry lines (GL-006)
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowCreate(true)}
          className="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700"
        >
          + New Reconciliation
        </button>
      </div>

      {isLoading && <SkeletonLoader rows={5} />}

      {/* Reconciliation list */}
      {!selected && (
        <div className="bg-white border border-gray-200 rounded-xl overflow-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
              <tr>
                <th className="px-4 py-2 text-left">Period</th>
                <th className="px-4 py-2 text-left">Bank Account</th>
                <th className="px-4 py-2 text-right">Opening</th>
                <th className="px-4 py-2 text-right">Closing</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Unmatched</th>
                <th className="px-4 py-2 text-left">Action</th>
              </tr>
            </thead>
            <tbody>
              {reconciliations.length === 0 && !isLoading && (
                <tr>
                  <td colSpan={7} className="text-center py-8 text-gray-400 text-sm">
                    No reconciliations yet.
                  </td>
                </tr>
              )}
              {reconciliations.map(rec => (
                <tr key={rec.id} className="border-b border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-2 text-gray-700">
                    {rec.period_from} → {rec.period_to}
                  </td>
                  <td className="px-4 py-2 text-gray-600">{rec.bank_account_id}</td>
                  <td className="px-4 py-2 text-right font-mono">₱{rec.opening_balance.toLocaleString()}</td>
                  <td className="px-4 py-2 text-right font-mono">₱{rec.closing_balance.toLocaleString()}</td>
                  <td className="px-4 py-2">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${
                      rec.status === 'certified'
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}>
                      {rec.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-gray-600">{rec.unmatched_count}</td>
                  <td className="px-4 py-2">
                    <button
                      type="button"
                      onClick={() => setSelected(rec)}
                      className="text-indigo-600 hover:text-indigo-800 text-xs font-medium"
                    >
                      Open →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Detail panel */}
      {selected && (
        <div className="space-y-3">
          <button
            type="button"
            onClick={() => setSelected(null)}
            className="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
          >
            ← Back to list
          </button>
          <ReconciliationDetail reconciliation={selected} />
        </div>
      )}

      {showCreate && <CreateReconciliationModal onClose={() => setShowCreate(false)} />}
    </div>
  )
}
