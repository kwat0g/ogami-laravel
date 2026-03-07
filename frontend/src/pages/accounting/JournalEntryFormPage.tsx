import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, Trash2, CheckCircle, XCircle } from 'lucide-react'
import {
  useCreateJournalEntry,
  useChartOfAccounts,
  useFiscalPeriods,
} from '@/hooks/useAccounting'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type {
  ChartOfAccount,
  CreateJournalEntryPayload,
} from '@/types/accounting'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function formatAmount(n: number) {
  return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function isLeafAccount(account: ChartOfAccount, allAccounts: ChartOfAccount[]): boolean {
  return allAccounts.every((a) => a.parent_id !== account.id)
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

// ---------------------------------------------------------------------------
// Local line state type (debit_or_credit + amount for UX; mapped to payload)
// ---------------------------------------------------------------------------
interface LineState {
  _key: number
  account_id: number | null
  debit_or_credit: 'debit' | 'credit'
  amount: string
}

let _lineId = 0
function newLine(): LineState {
  return { _key: ++_lineId, account_id: null, debit_or_credit: 'debit', amount: '' }
}

// ---------------------------------------------------------------------------
// Line Row Component
// ---------------------------------------------------------------------------
interface LineRowProps {
  line: LineState
  leafAccounts: ChartOfAccount[]
  onChange: (key: number, field: keyof Omit<LineState, '_key'>, value: string | number | null) => void
  onRemove: (key: number) => void
  canRemove: boolean
}

function LineRow({ line, leafAccounts, onChange, onRemove, canRemove }: LineRowProps) {
  return (
    <tr>
      <td className="px-3 py-2">
        <select
          value={line.account_id ?? ''}
          onChange={(e) => onChange(line._key, 'account_id', e.target.value ? Number(e.target.value) : null)}
          className="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
          required
        >
          <option value="">Select account…</option>
          {leafAccounts.map((a) => (
            <option key={a.id} value={a.id}>
              {a.code} — {a.name}
            </option>
          ))}
        </select>
      </td>
      <td className="px-3 py-2">
        <select
          value={line.debit_or_credit}
          onChange={(e) => onChange(line._key, 'debit_or_credit', e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
        >
          <option value="debit">Debit</option>
          <option value="credit">Credit</option>
        </select>
      </td>
      <td className="px-3 py-2">
        <input
          type="number"
          min="0.01"
          step="0.01"
          value={line.amount}
          onChange={(e) => onChange(line._key, 'amount', e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="0.00"
          required
        />
      </td>
      <td className="px-3 py-2 text-center">
        <button
          type="button"
          onClick={() => onRemove(line._key)}
          disabled={!canRemove}
          className="p-1 text-gray-400 hover:text-red-500 transition-colors disabled:opacity-30"
          title="Remove line"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </td>
    </tr>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------
export default function JournalEntryFormPage() {
  const navigate = useNavigate()
  const [entryDate, setEntryDate] = useState('')
  const [description, setDescription] = useState('')
  const [lines, setLines] = useState<LineState[]>([newLine(), newLine()])
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [touchedDate, setTouchedDate] = useState(false)
  const entryDateError = touchedDate && !entryDate ? 'Entry date is required.' : undefined

  const { data: rawAccounts = [], isLoading: accountsLoading } = useChartOfAccounts({ tree: true })
  const { isLoading: periodsLoading } = useFiscalPeriods()
  const createMutation = useCreateJournalEntry()

  const allFlat = useMemo(() => flattenAccounts(rawAccounts), [rawAccounts])
  const leafAccounts = useMemo(
    () => allFlat.filter((a) => a.is_active && isLeafAccount(a, allFlat)),
    [allFlat],
  )

  // Running totals
  const { totalDebits, totalCredits, difference, isBalanced } = useMemo(() => {
    let dr = 0
    let cr = 0
    for (const l of lines) {
      const amt = parseFloat(l.amount) || 0
      if (l.debit_or_credit === 'debit') dr += amt
      else cr += amt
    }
    const diff = Math.abs(dr - cr)
    return { totalDebits: dr, totalCredits: cr, difference: diff, isBalanced: diff < 0.005 }
  }, [lines])

  function updateLine(key: number, field: keyof Omit<LineState, '_key'>, value: string | number | null) {
    setLines((prev) => prev.map((l) => (l._key === key ? { ...l, [field]: value } : l)))
  }

  function removeLine(key: number) {
    setLines((prev) => prev.filter((l) => l._key !== key))
  }

  function addLine() {
    setLines((prev) => [...prev, newLine()])
  }

  const canSubmit =
    isBalanced &&
    lines.length >= 2 &&
    entryDate !== '' &&
    lines.every((l) => (l.account_id ?? 0) > 0 && parseFloat(l.amount) > 0)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!canSubmit) return
    setSubmitError(null)
    try {
      const payload: CreateJournalEntryPayload = {
        date: entryDate,
        description,
        lines: lines.map((l) => ({
          account_id: l.account_id as number,
          debit:  l.debit_or_credit === 'debit'  ? parseFloat(l.amount) : null,
          credit: l.debit_or_credit === 'credit' ? parseFloat(l.amount) : null,
        })),
      }
      const entry = await createMutation.mutateAsync(payload)
      navigate(`/accounting/journal-entries/${entry.ulid}`)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      setSubmitError(msg ?? 'Failed to create journal entry. Please try again.')
    }
  }

  if (accountsLoading || periodsLoading) return <SkeletonLoader rows={6} />

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">New Journal Entry</h1>
          <p className="text-sm text-gray-500 mt-0.5">Create a manual journal entry</p>
        </div>
        <button
          type="button"
          onClick={() => navigate('/accounting/journal-entries')}
          className="text-sm text-gray-500 hover:text-gray-700 border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors"
        >
          Cancel
        </button>
      </div>

      <form onSubmit={handleSubmit}>
        {/* Header fields */}
        <div className="bg-white border border-gray-200 rounded-xl p-6 mb-4 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Entry Date <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={entryDate}
                onChange={(e) => setEntryDate(e.target.value)}
                onBlur={() => setTouchedDate(true)}
                required
                className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none ${entryDateError ? 'border-red-400' : 'border-gray-300'}`}
              />
              {entryDateError && <p className="mt-1 text-xs text-red-600">{entryDateError}</p>}
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
              placeholder="Optional description…"
            />
          </div>
        </div>

        {/* Lines */}
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
          <div className="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <h2 className="text-sm font-semibold text-gray-700">Journal Lines</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="border-b border-gray-100">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Account</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Dr / Cr</th>
                  <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">Amount (₱)</th>
                  <th className="px-3 py-2 w-12" />
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {lines.map((line) => (
                  <LineRow
                    key={line._key}
                    line={line}
                    leafAccounts={leafAccounts}
                    onChange={updateLine}
                    onRemove={removeLine}
                    canRemove={lines.length > 2}
                  />
                ))}
              </tbody>
            </table>
          </div>
          <div className="px-4 py-3 border-t border-gray-100">
            <button
              type="button"
              onClick={addLine}
              className="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              <Plus className="h-4 w-4" />
              Add Line
            </button>
          </div>
        </div>

        {/* Running totals + balance indicator */}
        <div className="bg-white border border-gray-200 rounded-xl px-6 py-4 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-8">
              <div>
                <div className="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-0.5">Total Debits</div>
                <div className="text-lg font-bold text-gray-900 tabular-nums">₱{formatAmount(totalDebits)}</div>
              </div>
              <div className="text-gray-300 text-2xl font-light">|</div>
              <div>
                <div className="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-0.5">Total Credits</div>
                <div className="text-lg font-bold text-gray-900 tabular-nums">₱{formatAmount(totalCredits)}</div>
              </div>
            </div>
            <div className="flex items-center gap-2">
              {isBalanced ? (
                <>
                  <CheckCircle className="h-5 w-5 text-green-500" />
                  <span className="text-sm font-semibold text-green-600">Balanced</span>
                </>
              ) : (
                <>
                  <XCircle className="h-5 w-5 text-red-500" />
                  <span className="text-sm font-semibold text-red-600">
                    Unbalanced — ₱{formatAmount(difference)} off
                  </span>
                </>
              )}
            </div>
          </div>
        </div>

        {submitError && (
          <div className="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
            {submitError}
          </div>
        )}

        {/* Submit */}
        <div className="flex justify-end">
          <button
            type="submit"
            disabled={!canSubmit || createMutation.isPending}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMutation.isPending ? 'Saving…' : 'Save as Draft'}
          </button>
        </div>
      </form>
    </div>
  )
}
