import { useState } from 'react'
import { useAuthStore } from '@/stores/authStore'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/lib/errorHandler'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import {
  useBankReconciliations,
  useBankReconciliation,
  useCreateBankReconciliation,
  useImportStatement,
  useMatchTransaction,
  useUnmatchTransaction,
  useCertifyReconciliation,
  useBankAccounts,
} from '@/hooks/useBanking'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { PageHeader } from '@/components/ui/PageHeader'

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
  
  const handleCertify = () => {
    certify(undefined, {
      onSuccess: () => toast.success('Bank reconciliation certified successfully.'),
      onError: (err) => toast.error(firstErrorMessage(err, 'Failed to certify reconciliation.')),
    })
  }
  
  return (
    <SodActionButton
      initiatedById={createdBy}
      label="Certify"
      onClick={handleCertify}
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
  const [touched, setTouched] = useState<Record<string, boolean>>({})
  const { mutate: create, isPending } = useCreateBankReconciliation()
  const { data: bankAccounts, isLoading: loadingAccounts } = useBankAccounts()
  const inputCls = 'border border-neutral-300 rounded px-3 py-2 text-sm w-full focus:ring-1 focus:ring-neutral-400 focus:outline-none'
  const errorCls = 'border-red-400'

  // Validation
  const errors: Record<string, string | undefined> = {
    bank_account_id: touched.bank_account_id && !form.bank_account_id ? 'Bank account is required.' : undefined,
    period_from: touched.period_from && !form.period_from ? 'Period from is required.' : undefined,
    period_to: touched.period_to && !form.period_to ? 'Period to is required.' : undefined,
  }

  const isValid = form.bank_account_id && form.period_from && form.period_to

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setTouched({ bank_account_id: true, period_from: true, period_to: true })
    
    if (!isValid) {
      toast.error('Please fill in all required fields.')
      return
    }

    create(form, { 
      onSuccess: () => {
        toast.success('Bank reconciliation created successfully.')
        onClose()
      },
      onError: (err) => toast.error(firstErrorMessage(err, 'Failed to create reconciliation.')),
    })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <ExecutiveReadOnlyBanner />
      <form onSubmit={handleSubmit} className="bg-white rounded border border-neutral-200 p-4 sm:p-6 w-full max-w-md max-h-[90vh] overflow-y-auto space-y-4">
        <h2 className="text-lg font-semibold text-neutral-900">New Reconciliation</h2>
        
        {/* Bank Account Dropdown */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Bank Account <span className="text-red-500">*</span></label>
          <select 
            className={`${inputCls} ${errors.bank_account_id ? errorCls : ''}`} 
            value={form.bank_account_id || ''} 
            onChange={e => setForm(f => ({ ...f, bank_account_id: parseInt(e.target.value) || 0 }))} 
            onBlur={() => setTouched(t => ({ ...t, bank_account_id: true }))}
            disabled={loadingAccounts}
          >
            <option value="">{loadingAccounts ? 'Loading accounts...' : 'Select bank account'}</option>
            {bankAccounts?.map(account => (
              <option key={account.id} value={account.id}>
                {account.account_name} - {account.bank_name} (₱{account.current_balance?.toLocaleString() || '0'})
              </option>
            ))}
          </select>
          {errors.bank_account_id && <p className="text-xs text-red-600">{errors.bank_account_id}</p>}
        </div>

        {/* Period From */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Period From <span className="text-red-500">*</span></label>
          <input 
            type="date" 
            className={`${inputCls} ${errors.period_from ? errorCls : ''}`} 
            value={form.period_from} 
            onChange={e => setForm(f => ({ ...f, period_from: e.target.value }))} 
            onBlur={() => setTouched(t => ({ ...t, period_from: true }))}
          />
          {errors.period_from && <p className="text-xs text-red-600">{errors.period_from}</p>}
        </div>

        {/* Period To */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Period To <span className="text-red-500">*</span></label>
          <input 
            type="date" 
            className={`${inputCls} ${errors.period_to ? errorCls : ''}`} 
            value={form.period_to} 
            onChange={e => setForm(f => ({ ...f, period_to: e.target.value }))} 
            onBlur={() => setTouched(t => ({ ...t, period_to: true }))}
          />
          {errors.period_to && <p className="text-xs text-red-600">{errors.period_to}</p>}
        </div>

        {/* Opening Balance */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Opening Balance</label>
          <input 
            type="number" 
            step="0.01" 
            className={inputCls} 
            value={form.opening_balance} 
            onChange={e => setForm(f => ({ ...f, opening_balance: parseFloat(e.target.value) || 0 }))} 
          />
        </div>

        {/* Closing Balance */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Closing Balance</label>
          <input 
            type="number" 
            step="0.01" 
            className={inputCls} 
            value={form.closing_balance} 
            onChange={e => setForm(f => ({ ...f, closing_balance: parseFloat(e.target.value) || 0 }))} 
          />
        </div>

        {/* Notes */}
        <div className="flex flex-col gap-1">
          <label className="text-xs font-medium text-neutral-600">Notes</label>
          <input 
            className={inputCls} 
            value={form.notes ?? ''} 
            onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} 
          />
        </div>

        <div className="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3 pt-2">
          <button type="submit" disabled={isPending}
            className="flex-1 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed">
            {isPending ? 'Creating…' : 'Create'}
          </button>
          <button type="button" onClick={onClose}
            className="flex-1 py-2 rounded border border-neutral-300 text-sm text-neutral-700 hover:bg-neutral-50">
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
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
      status === 'certified'
        ? 'bg-neutral-100 text-neutral-700'
        : status === 'matched'
        ? 'bg-neutral-100 text-neutral-700'
        : 'bg-neutral-100 text-neutral-700'
    }`}>
      {status}
    </span>
  )
}

function ReconciliationDetail({ reconciliation }: { reconciliation: BankReconciliation }) {
  const { hasPermission } = useAuthStore()
  const canEdit = hasPermission('bank_reconciliations.create')
  const canCertify = hasPermission('bank_reconciliations.certify')

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

  const handleImport = () => {
    importStmt({ transactions: csvLines }, {
      onSuccess: () => toast.success('Bank statement imported successfully.'),
      onError: (err) => toast.error(firstErrorMessage(err, 'Failed to import statement.')),
    })
  }

  const handleUnmatch = (txId: number) => {
    unmatch(txId, {
      onSuccess: () => toast.success('Transaction unmatched successfully.'),
      onError: (err) => toast.error(firstErrorMessage(err, 'Failed to unmatch transaction.')),
    })
  }

  const handleMatch = () => {
    if (!selectedBankTx || !jeLineId) return
    match(
      { bank_transaction_id: selectedBankTx, journal_entry_line_id: parseInt(jeLineId) },
      { 
        onSuccess: () => {
          toast.success('Transaction matched successfully.')
          setSelectedBankTx(null)
          setJeLineId('')
        },
        onError: (err) => toast.error(firstErrorMessage(err, 'Failed to match transaction.')),
      }
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-6 bg-white border border-neutral-200 rounded p-4 items-center">
        <div>
          <p className="text-xs text-neutral-500">Period</p>
          <p className="font-semibold text-neutral-900">{recon.period_from} → {recon.period_to}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Opening</p>
          <p className="font-mono font-semibold">₱{recon.opening_balance.toLocaleString()}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Closing</p>
          <p className="font-mono font-semibold">₱{recon.closing_balance.toLocaleString()}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">Status</p>
          {statusBadge(recon.status)}
        </div>
        <div>
          <p className="text-xs text-neutral-500">Unmatched</p>
          <p className="font-semibold text-neutral-900">{recon.unmatched_count}</p>
        </div>
        {!isCertified && (
          <div className="ml-auto flex gap-2">
            {canEdit && (
              <button
                type="button"
                disabled={importing}
                onClick={handleImport}
                className="px-3 py-2 rounded border border-neutral-300 text-sm text-neutral-700 hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {importing ? 'Importing…' : 'Import Statement'}
              </button>
            )}
            {canCertify && <CertifyButton reconciliationId={recon.ulid} createdBy={recon.created_by} />}
          </div>
        )}
      </div>

      {/* Transactions table */}
      <div className="bg-white border border-neutral-200 rounded overflow-auto">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left px-3 py-2 font-medium text-neutral-600">Date</th>
              <th className="text-left px-3 py-2 font-medium text-neutral-600">Description</th>
              <th className="text-left px-3 py-2 font-medium text-neutral-600">Ref</th>
              <th className="text-left px-3 py-2 font-medium text-neutral-600">Type</th>
              <th className="text-right px-3 py-2 font-medium text-neutral-600">Amount</th>
              <th className="text-left px-3 py-2 font-medium text-neutral-600">Status</th>
              {!isCertified && canEdit && <th className="text-left px-3 py-2 font-medium text-neutral-600">Action</th>}
            </tr>
          </thead>
          <tbody>
            {(recon.transactions ?? [] as BankTransaction[]).map((tx: BankTransaction) => (
              <tr key={tx.id} className={`border-b border-neutral-100 hover:bg-neutral-50 ${
                selectedBankTx === tx.id ? 'bg-neutral-50' : ''
              }`}>
                <td className="px-3 py-2 text-neutral-500 whitespace-nowrap">{tx.transaction_date}</td>
                <td className="px-3 py-2 text-neutral-700 max-w-xs truncate">{tx.description}</td>
                <td className="px-3 py-2 font-mono text-xs text-neutral-400">{tx.reference_number ?? '—'}</td>
                <td className="px-3 py-2">
                  <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium capitalize ${
                    tx.transaction_type === 'debit' ? 'bg-neutral-100 text-neutral-700' : 'bg-neutral-100 text-neutral-700'
                  }`}>{tx.transaction_type}</span>
                </td>
                <td className="px-3 py-2">{statusBadge(tx.status)}</td>
                {!isCertified && canEdit && (
                  <td className="px-3 py-2">
                    {tx.status === 'unmatched' && (
                      <button
                        type="button"
                        onClick={() => setSelectedBankTx(tx.id === selectedBankTx ? null : tx.id)}
                        className="text-neutral-600 hover:text-neutral-800 text-xs font-medium"
                      >
                        {selectedBankTx === tx.id ? 'Cancel' : 'Match'}
                      </button>
                    )}
                    {tx.status === 'matched' && (
                      <button
                        type="button"
                        onClick={() => handleUnmatch(tx.id)}
                        className="px-2 py-1 text-xs font-medium border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900"
                      >
                        Unmatch
                      </button>
                    )}
                    {tx.status === 'reconciled' && (
                      <span className="text-neutral-600 text-xs font-medium">Reconciled</span>
                    )}
                  </td>
                )}
              </tr>
            ))}
            {(recon.transactions ?? []).length === 0 && (
              <tr>
                <td colSpan={7} className="text-center py-8 text-neutral-400 text-sm">
                  No transactions. Import a bank statement to begin.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Match panel */}
      {selectedBankTx !== null && !isCertified && canEdit && (
        <div className="bg-neutral-50 border border-neutral-200 rounded p-4 flex gap-4 items-end">
          <p className="text-sm text-neutral-800 font-medium">
            Matching bank transaction #{selectedBankTx} to JE line:
          </p>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-neutral-600">JE Line ID</label>
            <input
              type="number"
              className="border border-neutral-300 rounded px-3 py-2 text-sm w-36 focus:ring-1 focus:ring-neutral-400 focus:outline-none"
              value={jeLineId}
              onChange={e => setJeLineId(e.target.value)}
            />
          </div>
          <button
            type="button"
            disabled={matching || !jeLineId}
            onClick={handleMatch}
            className="px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
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
  const { hasPermission } = useAuthStore()
  const { data, isLoading } = useBankReconciliations()
  const reconciliations: BankReconciliation[] = data?.data ?? []
  const [showCreate, setShowCreate] = useState(false)
  const [selected, setSelected] = useState<BankReconciliation | null>(null)

  const canCreate = hasPermission('bank_reconciliations.create')

  return (
    <div className="space-y-6">
      <PageHeader title="Bank Reconciliation" />
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-neutral-500">
            Match bank transactions to GL journal entry lines (GL-006)
          </p>
        </div>
        {canCreate && (
          <button
            type="button"
            onClick={() => setShowCreate(true)}
            className="px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
          >
            + New Reconciliation
          </button>
        )}
      </div>

      {isLoading && <SkeletonLoader rows={5} />}

      {/* Reconciliation list */}
      {!selected && (
        <div className="bg-white border border-neutral-200 rounded overflow-auto">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="text-left px-4 py-2 font-medium text-neutral-600">Period</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600">Bank Account</th>
                <th className="text-right px-4 py-2 font-medium text-neutral-600">Opening</th>
                <th className="text-right px-4 py-2 font-medium text-neutral-600">Closing</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600">Status</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600">Unmatched</th>
                <th className="text-left px-4 py-2 font-medium text-neutral-600">Action</th>
              </tr>
            </thead>
            <tbody>
              {reconciliations.length === 0 && !isLoading && (
                <tr>
                  <td colSpan={7} className="text-center py-8 text-neutral-400 text-sm">
                    No reconciliations yet.
                  </td>
                </tr>
              )}
              {reconciliations.map(rec => (
                <tr key={rec.id} className="border-b border-neutral-100 hover:bg-neutral-50">
                  <td className="px-4 py-2 text-neutral-700">
                    {rec.period_from} → {rec.period_to}
                  </td>
                  <td className="px-4 py-2 text-neutral-600">{rec.bank_account_id}</td>
                  <td className="px-4 py-2 text-right font-mono">₱{rec.opening_balance.toLocaleString()}</td>
                  <td className="px-4 py-2 text-right font-mono">₱{rec.closing_balance.toLocaleString()}</td>
                  <td className="px-4 py-2">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                      rec.status === 'certified'
                        ? 'bg-neutral-100 text-neutral-700'
                        : 'bg-neutral-100 text-neutral-700'
                    }`}>
                      {rec.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-neutral-600">{rec.unmatched_count}</td>
                  <td className="px-4 py-2">
                    <button
                      type="button"
                      onClick={() => setSelected(rec)}
                      className="text-neutral-600 hover:text-neutral-800 text-xs font-medium"
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
            className="text-neutral-600 hover:text-neutral-800 text-sm font-medium"
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
