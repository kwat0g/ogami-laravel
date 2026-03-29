import { useState } from 'react'
import {
  useBankAccounts,
  useCreateBankAccount,
  useUpdateBankAccount,
  useDeleteBankAccount,
} from '@/hooks/useBanking'
import { toast } from 'sonner'
import { useChartOfAccounts } from '@/hooks/useAccounting'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { DepartmentGuard } from '@/components/ui/guards'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { PageHeader } from '@/components/ui/PageHeader'
import type { BankAccount, CreateBankAccountPayload } from '@/types/banking'

// ---------------------------------------------------------------------------
// Delete button — children-as-trigger pattern for ConfirmDestructiveDialog
// ---------------------------------------------------------------------------

function DeleteBankAccountButton({ account }: { account: BankAccount }) {
  const { mutate: remove, isPending } = useDeleteBankAccount()
  return (
    <ConfirmDestructiveDialog
      title="Delete Bank Account"
      description={`Delete "${account.name}"? This cannot be undone if no reconciliations exist.`}
      onConfirm={() => remove(account.id)}
    >
      <button
        type="button"
        className="text-red-500 hover:text-red-700 text-xs font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        disabled={isPending}
      >
        Delete
      </button>
    </ConfirmDestructiveDialog>
  )
}

// ---------------------------------------------------------------------------
// Create / Edit form modal
// ---------------------------------------------------------------------------

const EMPTY: CreateBankAccountPayload = {
  name: '',
  account_number: '',
  bank_name: '',
  account_type: 'checking',
  account_id: 0,
  opening_balance: 0,
  is_active: true,
}

function BankAccountFormModal({
  initial,
  onClose,
}: {
  initial?: BankAccount
  onClose: () => void
}) {
  const [form, setForm] = useState<CreateBankAccountPayload>(
    initial
      ? {
          name: initial.name,
          account_number: initial.account_number,
          bank_name: initial.bank_name,
          account_type: initial.account_type,
          account_id: initial.account_id,
          opening_balance: initial.opening_balance,
          is_active: initial.is_active,
        }
      : EMPTY
  )

  const { mutate: update, isPending: updating } = useUpdateBankAccount(initial?.id ?? 0)
  const { mutate: create, isPending: creating } = useCreateBankAccount()
  const { data: accounts } = useChartOfAccounts()
  const busy = creating || updating

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (initial) {
      update(form, {
        onSuccess: () => { toast.success('Bank account updated.'); onClose() },
        onError: () => toast.error('Failed to update bank account.'),
      })
    } else {
      create(form, {
        onSuccess: () => { toast.success('Bank account created.'); onClose() },
        onError: () => toast.error('Failed to create bank account.'),
      })
    }
  }

  function field(label: string, children: React.ReactNode) {
    return (
      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-neutral-600">{label}</label>
        {children}
      </div>
    )
  }

  const inputCls = 'border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 focus:outline-none'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <form onSubmit={handleSubmit} className="bg-white rounded border border-neutral-200 p-4 sm:p-6 w-full max-w-md max-h-[90vh] overflow-y-auto space-y-4">
        <h2 className="text-lg font-semibold text-neutral-900">
          {initial ? 'Edit Bank Account' : 'New Bank Account'}
        </h2>

        {field('Name', (
          <input className={inputCls} value={form.name}
            onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
        ))}
        {field('Account Number', (
          <input className={inputCls} value={form.account_number}
            onChange={e => setForm(f => ({ ...f, account_number: e.target.value }))} required />
        ))}
        {field('Bank Name', (
          <input className={inputCls} value={form.bank_name}
            onChange={e => setForm(f => ({ ...f, bank_name: e.target.value }))} required />
        ))}
        {field('Account Type', (
          <select className={inputCls} value={form.account_type}
            onChange={e => setForm(f => ({ ...f, account_type: e.target.value as 'checking' | 'savings' }))}>
            <option value="checking">Checking</option>
            <option value="savings">Savings</option>
          </select>
        ))}
        {field('GL Account', (
          <select
            className={inputCls}
            value={form.account_id || ''}
            onChange={e => setForm(f => ({ ...f, account_id: parseInt(e.target.value) || 0 }))}
            required
          >
            <option value="">— Select GL Account —</option>
            {accounts?.map(a => (
              <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
            ))}
          </select>
        ))}
        {field('Opening Balance', (
          <input type="number" step="0.01" className={inputCls} value={form.opening_balance}
            onChange={e => setForm(f => ({ ...f, opening_balance: parseFloat(e.target.value) || 0 }))} />
        ))}
        {field('Active', (
          <input type="checkbox" className="rounded w-4 h-4" checked={form.is_active}
            onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))} />
        ))}

        <div className="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3 pt-2">
          <button type="submit" disabled={busy}
            className="flex-1 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed">
            {busy ? 'Saving…' : 'Save'}
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
// Page
// ---------------------------------------------------------------------------

export default function BankAccountsPage() {
  const { hasPermission } = useAuthStore()
  const { data, isLoading } = useBankAccounts()
  const accounts = data ?? []
  const [showForm, setShowForm] = useState(false)
  const [_isArchiveView, _setIsArchiveView] = useState(false)
  const [editing, setEditing] = useState<BankAccount | undefined>()

  const canCreate = hasPermission('bank_accounts.create')
  const canEdit = hasPermission('bank_accounts.update')
  const canDelete = hasPermission('bank_accounts.delete')

  return (
    <div className="p-6 space-y-6">
      <PageHeader
        title="Bank Accounts"
        actions={
          canCreate ? (
            <DepartmentGuard module="bank_accounts">
              <button
                type="button"
                onClick={() => { setEditing(undefined); setShowForm(true) }}
                className="px-4 py-2 rounded bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
              >
                + New Account
              </button>
            </DepartmentGuard>
          ) : undefined
        }
      />
      <div>
        <p className="text-sm text-neutral-500">Manage bank accounts linked to GL (GL-006)</p>
      </div>

      {isLoading && <SkeletonLoader rows={5} />}

      <div className="bg-white border border-neutral-200 rounded overflow-auto">
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Name</th>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Account #</th>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Bank</th>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Type</th>
              <th className="text-right px-3 py-2.5 font-medium text-neutral-600">Opening Balance</th>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Status</th>
              <th className="text-left px-3 py-2.5 font-medium text-neutral-600">Actions</th>
            </tr>
          </thead>
          <tbody>
            {accounts.length === 0 && !isLoading && (
              <tr>
                <td colSpan={7} className="text-center px-3 py-8 text-neutral-400 text-sm">
                  No bank accounts yet.
                </td>
              </tr>
            )}
            {(accounts as BankAccount[]).map((acct: BankAccount) => (
              <tr key={acct.id} className="border-b border-neutral-100 hover:bg-neutral-50 transition-colors">
                <td className="px-3 py-2 font-medium text-neutral-900">{acct.name}</td>
                <td className="px-3 py-2 font-mono text-xs text-neutral-500">{acct.account_number}</td>
                <td className="px-3 py-2 text-neutral-700">{acct.bank_name}</td>
                <td className="px-3 py-2 capitalize text-neutral-600">{acct.account_type}</td>
                <td className="px-3 py-2 text-right font-mono">₱{(Number(acct.opening_balance) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                <td className="px-3 py-2">
                  <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                    acct.is_active
                      ? 'bg-neutral-100 text-neutral-700'
                      : 'bg-neutral-100 text-neutral-500'
                  }`}>
                    {acct.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td className="px-3 py-2 flex gap-3 items-center">
                  {canEdit && (
                    <button
                      type="button"
                      onClick={() => { setEditing(acct); setShowForm(true) }}
                      className="text-neutral-600 hover:text-neutral-800 text-xs font-medium"
                    >
                      Edit
                    </button>
                  )}
                  {canDelete && <DeleteBankAccountButton account={acct} />}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showForm && (
        <BankAccountFormModal
          initial={editing}
          onClose={() => { setShowForm(false); setEditing(undefined) }}
        />
      )}
    </div>
  )
}
