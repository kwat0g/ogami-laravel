import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, RefreshCw, ChevronRight, ChevronDown, Archive } from 'lucide-react'
import ArchiveToggleButton from '@/components/ui/ArchiveToggleButton'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useChartOfAccounts,
  useCreateAccount,
  useUpdateAccount,
  useArchiveAccount,
} from '@/hooks/useAccounting'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { ChartOfAccount, AccountType, NormalBalance, CreateAccountPayload } from '@/types/accounting'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
const ACCOUNT_TYPE_COLORS: Record<AccountType, string> = {
  ASSET:     'bg-neutral-100 text-neutral-700',
  LIABILITY: 'bg-neutral-100 text-neutral-700',
  EQUITY:    'bg-neutral-100 text-neutral-700',
  REVENUE:   'bg-neutral-100 text-neutral-700',
  COGS:      'bg-neutral-100 text-neutral-700',
  OPEX:      'bg-neutral-100 text-neutral-700',
  TAX:       'bg-neutral-100 text-neutral-700',
}

const ACCOUNT_TYPES: AccountType[] = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'OPEX', 'TAX']
const NORMAL_BALANCES: NormalBalance[] = ['DEBIT', 'CREDIT']

function AccountTypeBadge({ type }: { type: AccountType }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize ${ACCOUNT_TYPE_COLORS[type]}`}>
      {type}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Account Row (recursive tree)
// ---------------------------------------------------------------------------
interface AccountRowProps {
  account: ChartOfAccount
  depth: number
  onEdit: (a: ChartOfAccount) => void
  onArchive: (a: ChartOfAccount) => void
  canManage: boolean
}

function AccountRow({ account, depth, onEdit, onArchive, canManage }: AccountRowProps) {
  const [expanded, setExpanded] = useState(true)
  const hasChildren = (account.children?.length ?? 0) > 0

  return (
    <>
      <tr className="hover:bg-neutral-50 transition-colors">
        <td className="px-3 py-2">
          <div className="flex items-center gap-1" style={{ paddingLeft: `${depth * 20}px` }}>
            {hasChildren ? (
              <button
                onClick={() => setExpanded((v) => !v)}
                className="p-0.5 text-neutral-400 hover:text-neutral-700"
              >
                {expanded ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
              </button>
            ) : (
              <span className="w-5" />
            )}
            <span className="font-mono text-xs text-neutral-500">{account.code}</span>
          </div>
        </td>
        <td className="px-3 py-2">
          <span className={`font-medium text-sm ${!account.is_active ? 'text-neutral-400 line-through' : 'text-neutral-900'}`}>
            {account.name}
          </span>
          {account.is_system && (
            <span className="ml-2 text-xs text-neutral-400">(system)</span>
          )}
        </td>
        <td className="px-3 py-2">
          <AccountTypeBadge type={account.account_type} />
        </td>
        <td className="px-3 py-2">
          <StatusBadge status={account.normal_balance}>{account.normal_balance}</StatusBadge>
        </td>
        <td className="px-3 py-2 text-right">
          <div className="flex items-center justify-end gap-2">
            {account.is_active && canManage && (
              <>
                <button
                  onClick={() => onEdit(account)}
                  className="text-xs text-neutral-700 hover:underline"
                >
                  Edit
                </button>
                {!account.is_system && (
                  <button
                    onClick={() => onArchive(account)}
                    className="text-xs text-red-500 hover:underline"
                  >
                    Delete
                  </button>
                )}
              </>
            )}
            {!account.is_active && (
              <span className="text-xs text-neutral-400 flex items-center gap-1">
                <Archive className="h-3 w-3" /> Archived
              </span>
            )}
          </div>
        </td>
      </tr>
      {hasChildren && expanded &&
        account.children!.map((child) => (
          <AccountRow
            key={child.id}
            account={child}
            depth={depth + 1}
            onEdit={onEdit}
            onArchive={onArchive}
            canManage={canManage}
          />
        ))}
    </>
  )
}

// ---------------------------------------------------------------------------
// Modal
// ---------------------------------------------------------------------------
interface AccountModalProps {
  open: boolean
  initial?: ChartOfAccount | null
  accounts: ChartOfAccount[]
  onClose: () => void
  onSave: (payload: CreateAccountPayload, id?: number) => void
  saving: boolean
}

function flattenAccounts(accounts: ChartOfAccount[]): ChartOfAccount[] {
  const result: ChartOfAccount[] = []
  function walk(list: ChartOfAccount[]) {
    for (const a of list) {
      result.push(a)
      if (a.children?.length) walk(a.children)
    }
  }
  walk(accounts)
  return result
}

function AccountModal({ open, initial, accounts, onClose, onSave, saving }: AccountModalProps) {
  const flat = flattenAccounts(accounts)
  const [code, setCode] = useState(initial?.code ?? '')
  const [name, setName] = useState(initial?.name ?? '')
  const [accountType, setAccountType] = useState<AccountType>(initial?.account_type ?? 'ASSET')
  const [normalBalance, setNormalBalance] = useState<NormalBalance>(initial?.normal_balance ?? 'DEBIT')
  const [parentId, setParentId] = useState<string>(initial?.parent_id?.toString() ?? '')
  const [touched, setTouched] = useState(false)

  // Reset when initial changes
  if (!open) return null

  // Client-side validation
  const codeError = touched && !code.trim() ? 'Account code is required.' : undefined
  const nameError = touched && !name.trim() ? 'Account name is required.' : undefined
  const isValid = code.trim() && name.trim()

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
    if (!isValid) return
    onSave(
      {
        code,
        name,
        account_type: accountType,
        normal_balance: normalBalance,
        parent_id: parentId ? Number(parentId) : undefined,
      },
      initial?.id,
    )
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-4">
          {initial ? 'Edit Account' : 'New Account'}
        </h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Code <span className="text-red-500">*</span>
            </label>
            <input
              className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none font-mono ${
                codeError ? 'border-red-400' : 'border-neutral-300'
              }`}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              onBlur={() => setTouched(true)}
              required
              placeholder="e.g. 1010"
            />
            {codeError && <p className="mt-1 text-xs text-red-600">{codeError}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Name <span className="text-red-500">*</span>
            </label>
            <input
              className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                nameError ? 'border-red-400' : 'border-neutral-300'
              }`}
              value={name}
              onChange={(e) => setName(e.target.value)}
              onBlur={() => setTouched(true)}
              required
              placeholder="Account name"
            />
            {nameError && <p className="mt-1 text-xs text-red-600">{nameError}</p>}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Type</label>
              <select
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
                value={accountType}
                onChange={(e) => setAccountType(e.target.value as AccountType)}
              >
                {ACCOUNT_TYPES.map((t) => (
                  <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Normal Balance</label>
              <select
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
                value={normalBalance}
                onChange={(e) => setNormalBalance(e.target.value as NormalBalance)}
              >
                {NORMAL_BALANCES.map((b) => (
                  <option key={b} value={b}>{b.charAt(0).toUpperCase() + b.slice(1)}</option>
                ))}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Parent Account (optional)</label>
            <select
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
              value={parentId}
              onChange={(e) => setParentId(e.target.value)}
            >
              <option value="">— No Parent —</option>
              {flat
                .filter((a) => a.is_active && a.id !== initial?.id)
                .map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.code} — {a.name}
                  </option>
                ))}
            </select>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving || !isValid}
              className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 hover:bg-neutral-800 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {saving ? 'Saving…' : 'Save'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------
export default function AccountsPage() {
  const canManage = useAuthStore(s => s.hasPermission('chart_of_accounts.manage'))
  const [includeArchived, setIncludeArchived] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<ChartOfAccount | null>(null)
  const [archiveTarget, setArchiveTarget] = useState<ChartOfAccount | null>(null)

  const { data: accounts = [], isLoading, isError, refetch, isFetching } =
    useChartOfAccounts({ tree: true, include_archived: includeArchived })

  const createMutation = useCreateAccount()
  const updateMutation = useUpdateAccount(editing?.id ?? 0)
  const archiveMutation = useArchiveAccount(archiveTarget?.id ?? 0)

  function openCreate() {
    setEditing(null)
    setModalOpen(true)
  }

  function openEdit(account: ChartOfAccount) {
    setEditing(account)
    setModalOpen(true)
  }

  async function handleSave(payload: CreateAccountPayload, id?: number) {
    try {
      if (id) {
        await updateMutation.mutateAsync(payload)
        toast.success('Account updated.')
      } else {
        await createMutation.mutateAsync(payload)
        toast.success('Account created.')
      }
      setModalOpen(false)
      setEditing(null)
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  async function handleArchive() {
    if (!archiveTarget) return
    try {
      await archiveMutation.mutateAsync()
      toast.success('Account archived.')
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
    setArchiveTarget(null)
  }

  if (isLoading) return <SkeletonLoader rows={10} />

  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Failed to load chart of accounts. Please try again.
      </div>
    )
  }

  return (
    <div>
      <PageHeader title="Chart of Accounts" />

      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <p className="text-sm text-neutral-500">
            {flattenAccounts(accounts).filter((a) => a.is_active).length} active accounts
          </p>
        </div>
        <div className="flex items-center gap-2">
          <ArchiveToggleButton isArchiveView={includeArchived} onToggle={() => setIncludeArchived(prev => !prev)} />
          <button
            onClick={() => void refetch()}
            disabled={isFetching}
            className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 text-neutral-600 transition-colors disabled:opacity-40"
            title="Refresh"
          >
            <RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          </button>
          {canManage && (
            <button
              onClick={openCreate}
              className="flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
            >
              <Plus className="h-4 w-4" />
              New Account
            </button>
          )}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 w-32">Code</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Name</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 w-32">Type</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500 w-32">Normal Balance</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 w-28">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {accounts.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-3 py-10 text-center text-neutral-400 text-sm">
                    No accounts found.
                  </td>
                </tr>
              ) : (
                accounts.map((account) => (
                  <AccountRow
                    key={account.id}
                    account={account}
                    depth={0}
                    onEdit={openEdit}
                    onArchive={setArchiveTarget}
                    canManage={canManage}
                  />
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Create/Edit Modal */}
      {modalOpen && (
        <AccountModal
          open={modalOpen}
          initial={editing}
          accounts={accounts}
          onClose={() => { setModalOpen(false); setEditing(null) }}
          onSave={handleSave}
          saving={createMutation.isPending || updateMutation.isPending}
        />
      )}

      {/* Archive Confirm Dialog */}
      {archiveTarget && (
        <ConfirmDestructiveDialog
          title="Archive Account?"
          description={`Archive "${archiveTarget.code} — ${archiveTarget.name}"? This account will no longer be available for new journal entries. Existing transactions will not be affected.`}
          confirmWord="ARCHIVE"
          confirmLabel="Archive"
          onConfirm={handleArchive}
        >
          <span /> {/* Trigger is handled by setArchiveTarget */}
        </ConfirmDestructiveDialog>
      )}

      {/* Archive Confirmation Modal - using ConfirmDestructiveDialog wrapper */}
      {archiveTarget && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded border border-neutral-200 w-full max-w-sm p-6">
            <h2 className="text-lg font-semibold text-neutral-900 mb-2">Archive Account?</h2>
            <p className="text-sm text-neutral-600 mb-6">
              Archive <strong>{archiveTarget.code} — {archiveTarget.name}</strong>? This account will no longer be available for new journal entries.
            </p>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setArchiveTarget(null)}
                className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <ConfirmDestructiveDialog
                title="Archive Account?"
                description={`Archive "${archiveTarget.code} — ${archiveTarget.name}"? This account will no longer be available for new journal entries. Existing transactions will not be affected.`}
                confirmWord="ARCHIVE"
                confirmLabel="Archive"
                onConfirm={handleArchive}
              >
                <button
                  disabled={archiveMutation.isPending}
                  className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 hover:bg-neutral-800 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {archiveMutation.isPending ? 'Archiving…' : 'Archive'}
                </button>
              </ConfirmDestructiveDialog>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
