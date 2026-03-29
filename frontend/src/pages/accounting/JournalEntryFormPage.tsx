import { useState, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Trash2, CheckCircle, XCircle } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useCreateJournalEntry,
  useChartOfAccounts,
  useFiscalPeriods,
  useJournalEntryTemplates,
  useApplyJournalEntryTemplate,
  useCreateJournalEntryTemplate,
} from '@/hooks/useAccounting'
import { firstErrorMessage } from '@/lib/errorHandler'
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
// Validation helpers
// ---------------------------------------------------------------------------
interface ValidationErrors {
  entryDate?: string
  lines?: string
  accountIds?: Record<number, string>
  amounts?: Record<number, string>
}

function validateJournalEntry(
  entryDate: string,
  lines: LineState[],
  isBalanced: boolean
): { isValid: boolean; errors: ValidationErrors } {
  const errors: ValidationErrors = {}

  // Entry date validation
  if (!entryDate) {
    errors.entryDate = 'Entry date is required.'
  }

  // Lines validation
  if (lines.length < 2) {
    errors.lines = 'At least two journal lines are required (one debit and one credit).'
  }

  // Individual line validation
  const accountIdsErrors: Record<number, string> = {}
  const amountsErrors: Record<number, string> = {}

  lines.forEach((line) => {
    if (!line.account_id) {
      accountIdsErrors[line._key] = 'Account is required.'
    }
    if (!line.amount || parseFloat(line.amount) <= 0) {
      amountsErrors[line._key] = 'Amount must be greater than 0.'
    }
  })

  if (Object.keys(accountIdsErrors).length > 0) {
    errors.accountIds = accountIdsErrors
  }
  if (Object.keys(amountsErrors).length > 0) {
    errors.amounts = amountsErrors
  }

  // Balance validation
  if (!isBalanced) {
    errors.lines = errors.lines || 'Debits and credits must be equal.'
  }

  const isValid = !errors.entryDate && !errors.lines && !errors.accountIds && !errors.amounts

  return { isValid, errors }
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
  validationErrors?: {
    accountId?: string
    amount?: string
  }
  touched: boolean
}

function LineRow({ line, leafAccounts, onChange, onRemove, canRemove, validationErrors, touched }: LineRowProps) {
  const showAccountError = touched && validationErrors?.accountId
  const showAmountError = touched && validationErrors?.amount

  return (
    <tr>
      <td className="px-3 py-2">
        <select
          value={line.account_id ?? ''}
          onChange={(e) => onChange(line._key, 'account_id', e.target.value ? Number(e.target.value) : null)}
          className={`w-full border rounded px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none ${
            showAccountError ? 'border-red-400' : 'border-neutral-300'
          }`}
          required
        >
          <option value="">Select account…</option>
          {leafAccounts.map((a) => (
            <option key={a.id} value={a.id}>
              {a.code} — {a.name}
            </option>
          ))}
        </select>
        {showAccountError && <p className="mt-1 text-xs text-red-600">{validationErrors?.accountId}</p>}
      </td>
      <td className="px-3 py-2">
        <select
          value={line.debit_or_credit}
          onChange={(e) => onChange(line._key, 'debit_or_credit', e.target.value)}
          className="w-full border border-neutral-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
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
          className={`w-full border rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-neutral-400 outline-none ${
            showAmountError ? 'border-red-400' : 'border-neutral-300'
          }`}
          placeholder="0.00"
          required
        />
        {showAmountError && <p className="mt-1 text-xs text-red-600 text-right">{validationErrors?.amount}</p>}
      </td>
      <td className="px-3 py-2 text-center">
        <button
          type="button"
          onClick={() => onRemove(line._key)}
          disabled={!canRemove}
          className="p-1 text-neutral-400 hover:text-neutral-600 transition-colors disabled:opacity-30"
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
  const [touched, setTouched] = useState(false)

  const { data: rawAccounts = [], isLoading: accountsLoading } = useChartOfAccounts({ tree: true })
  const { isLoading: periodsLoading } = useFiscalPeriods()
  const createMutation = useCreateJournalEntry()
  
  // Template hooks
  const { data: _templates = [] } = useJournalEntryTemplates()
  const applyTemplate = useApplyJournalEntryTemplate()
  const createTemplate = useCreateJournalEntryTemplate()
  const [selectedTemplate, setSelectedTemplate] = useState<number | ''>('')
  const [showSaveTemplate, setShowSaveTemplate] = useState(false)
  const [templateName, setTemplateName] = useState('')
  const [templateDescription, setTemplateDescription] = useState('')

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

  // Validation
  const { isValid: _isFormValid, errors: validationErrors } = useMemo(
    () => validateJournalEntry(entryDate, lines, isBalanced),
    [entryDate, lines, isBalanced]
  )

  function updateLine(key: number, field: keyof Omit<LineState, '_key'>, value: string | number | null) {
    setLines((prev) => prev.map((l) => (l._key === key ? { ...l, [field]: value } : l)))
  }

  function removeLine(key: number) {
    setLines((prev) => prev.filter((l) => l._key !== key))
  }

  function addLine() {
    setLines((prev) => [...prev, newLine()])
  }
  
  // Template handlers
  async function _handleApplyTemplate() {
    if (!selectedTemplate) return
    try {
      const result = await applyTemplate.mutateAsync(Number(selectedTemplate))
      // Convert template lines to LineState
      const newLines = result.lines.map((line) => ({
        _key: ++_lineId,
        account_id: line.account_id,
        debit_or_credit: line.debit_or_credit,
        amount: '', // User fills this
      }))
      setLines(newLines)
      toast.success(`Template "${result.template_name}" applied`)
      setSelectedTemplate('')
    } catch (err) {
      toast.error('Failed to apply template')
    }
  }
  
  async function handleSaveTemplate() {
    if (!templateName.trim()) {
      toast.error('Template name is required')
      return
    }
    if (lines.length < 2) {
      toast.error('Template must have at least 2 lines')
      return
    }
    if (lines.some(l => !l.account_id)) {
      toast.error('All lines must have an account selected')
      return
    }
    try {
      await createTemplate.mutateAsync({
        name: templateName,
        description: templateDescription,
        lines: lines.map(l => ({
          account_id: l.account_id as number,
          debit_or_credit: l.debit_or_credit,
          description: null,
        })),
      })
      toast.success('Template saved successfully')
      setShowSaveTemplate(false)
      setTemplateName('')
      setTemplateDescription('')
    } catch (err) {
      toast.error('Failed to save template')
    }
  }

  const canSubmit =
    isBalanced &&
    lines.length >= 2 &&
    entryDate !== '' &&
    lines.every((l) => (l.account_id ?? 0) > 0 && parseFloat(l.amount) > 0)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
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
      toast.success('Journal entry created.')
      navigate(`/accounting/journal-entries/${entry.ulid}`)
    } catch (err: unknown) {
      const msg = firstErrorMessage(err)
      setSubmitError(msg)
      toast.error(msg)
    }
  }

  if (accountsLoading || periodsLoading) return <SkeletonLoader rows={6} />

  return (
    <div className="max-w-4xl mx-auto">
      <PageHeader title="New Journal Entry" backTo="/accounting/journal-entries" />

      <form onSubmit={handleSubmit}>
        {/* Header fields */}
        <div className="bg-white border border-neutral-200 rounded p-6 mb-4 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Entry Date <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                value={entryDate}
                onChange={(e) => setEntryDate(e.target.value)}
                onBlur={() => setTouched(true)}
                required
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                  touched && validationErrors.entryDate ? 'border-red-400' : 'border-neutral-300'
                }`}
              />
              {touched && validationErrors.entryDate && (
                <p className="mt-1 text-xs text-red-600">{validationErrors.entryDate}</p>
              )}
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Description</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none resize-none"
              placeholder="Optional description…"
            />
          </div>
        </div>

        {/* Lines */}
        <div className="bg-white border border-neutral-200 rounded overflow-hidden mb-4">
          <div className="px-4 py-3 bg-neutral-50 border-b border-neutral-200">
            <h2 className="text-sm font-semibold text-neutral-700">Journal Lines</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="border-b border-neutral-100">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-neutral-500">Account <span className="text-red-500">*</span></th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-neutral-500 w-28">Dr / Cr <span className="text-red-500">*</span></th>
                  <th className="px-3 py-2 text-right text-xs font-semibold text-neutral-500 w-40">Amount (₱) <span className="text-red-500">*</span></th>
                  <th className="px-3 py-2 w-12" />
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {lines.map((line) => (
                  <LineRow
                    key={line._key}
                    line={line}
                    leafAccounts={leafAccounts}
                    onChange={updateLine}
                    onRemove={removeLine}
                    canRemove={lines.length > 2}
                    validationErrors={{
                      accountId: validationErrors.accountIds?.[line._key],
                      amount: validationErrors.amounts?.[line._key],
                    }}
                    touched={touched}
                  />
                ))}
              </tbody>
            </table>
          </div>
          {touched && validationErrors.lines && (
            <div className="px-4 py-2 bg-red-50 border-t border-red-100">
              <p className="text-xs text-red-600">{validationErrors.lines}</p>
            </div>
          )}
          <div className="px-4 py-3 border-t border-neutral-100">
            <button
              type="button"
              onClick={addLine}
              className="flex items-center gap-1.5 text-sm text-neutral-600 hover:text-neutral-800 font-medium"
            >
              <Plus className="h-4 w-4" />
              Add Line
            </button>
          </div>
        </div>

        {/* Running totals + balance indicator */}
        <div className="bg-white border border-neutral-200 rounded px-6 py-4 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-8">
              <div>
                <div className="text-xs text-neutral-500 font-semibold mb-0.5">Total Debits</div>
                <div className="text-lg font-bold text-neutral-900 tabular-nums">₱{formatAmount(totalDebits)}</div>
              </div>
              <div className="text-neutral-300 text-2xl font-light">|</div>
              <div>
                <div className="text-xs text-neutral-500 font-semibold mb-0.5">Total Credits</div>
                <div className="text-lg font-bold text-neutral-900 tabular-nums">₱{formatAmount(totalCredits)}</div>
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
          <div className="mb-4 bg-red-50 border border-red-200 rounded px-4 py-3 text-sm text-red-700">
            {submitError}
          </div>
        )}

        {/* Submit */}
        <div className="flex justify-end">
          <button
            type="submit"
            disabled={!canSubmit || createMutation.isPending}
            className="flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-6 py-2.5 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMutation.isPending ? 'Saving…' : 'Save as Draft'}
          </button>
        </div>
      </form>

      {/* Save Template Modal */}
      {showSaveTemplate && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg w-full max-w-md p-6">
            <h2 className="text-xl font-semibold mb-4">Save as Template</h2>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Template Name *</label>
                <input
                  type="text"
                  value={templateName}
                  onChange={(e) => setTemplateName(e.target.value)}
                  placeholder="e.g., Monthly Payroll Entry"
                  className="w-full border rounded px-3 py-2"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Description</label>
                <textarea
                  value={templateDescription}
                  onChange={(e) => setTemplateDescription(e.target.value)}
                  placeholder="Optional description..."
                  rows={2}
                  className="w-full border rounded px-3 py-2"
                />
              </div>
            </div>
            <div className="flex gap-3 mt-6">
              <button
                type="button"
                onClick={() => setShowSaveTemplate(false)}
                className="flex-1 py-2 border rounded-lg hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleSaveTemplate}
                disabled={createTemplate.isPending}
                className="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {createTemplate.isPending ? 'Saving…' : 'Save Template'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
