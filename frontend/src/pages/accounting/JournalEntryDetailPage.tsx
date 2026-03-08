import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'
import { ArrowLeft, RotateCcw } from 'lucide-react'
import {
  useJournalEntry,
  useSubmitJournalEntry,
  usePostJournalEntry,
  useReverseJournalEntry,
} from '@/hooks/useAccounting'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import SodActionButton from '@/components/ui/SodActionButton'
import type { JournalEntryLine } from '@/types/accounting'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

function formatAmount(n: number | null | undefined): string {
  if (n == null || n === 0) return '—'
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n)
}

function AmountCell({ value }: { value: number | null | undefined }) {
  if (value == null || value === 0) return <span className="text-neutral-300">—</span>
  return <span className="tabular-nums">{formatAmount(value)}</span>
}

// ---------------------------------------------------------------------------
// Lines table
// ---------------------------------------------------------------------------
function LinesTable({ lines }: { lines: JournalEntryLine[] }) {
  const totalDebit  = lines.reduce((s, l) => s + (l.debit  ?? 0), 0)
  const totalCredit = lines.reduce((s, l) => s + (l.credit ?? 0), 0)

  return (
    <div className="overflow-x-auto">
      <ExecutiveReadOnlyBanner />
      <table className="min-w-full text-sm">
        <thead className="bg-neutral-50 border-b border-neutral-200">
          <tr>
            <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500 w-24">Code</th>
            <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500">Account</th>
            <th className="px-4 py-3 text-left text-xs font-semibold text-neutral-500">Description</th>
            <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 w-36">Debit (₱)</th>
            <th className="px-4 py-3 text-right text-xs font-semibold text-neutral-500 w-36">Credit (₱)</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-neutral-100">
          {lines.map((line) => (
            <tr key={line.id} className="hover:bg-neutral-50">
              <td className="px-4 py-3 font-mono text-xs text-neutral-500">
                {line.account_code ?? '—'}
              </td>
              <td className="px-4 py-3 text-neutral-900">{line.account_name ?? '—'}</td>
              <td className="px-4 py-3 text-neutral-500 text-xs">{line.description ?? ''}</td>
              <td className="px-4 py-3 text-right text-neutral-800">
                <AmountCell value={line.debit} />
              </td>
              <td className="px-4 py-3 text-right text-neutral-800">
                <AmountCell value={line.credit} />
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot className="bg-neutral-50 border-t-2 border-neutral-300">
          <tr>
            <td colSpan={3} className="px-4 py-3 text-right text-xs font-bold text-neutral-600">
              Totals
            </td>
            <td className="px-4 py-3 text-right font-bold text-neutral-900 tabular-nums">
              ₱{formatAmount(totalDebit)}
            </td>
            <td className="px-4 py-3 text-right font-bold text-neutral-900 tabular-nums">
              ₱{formatAmount(totalCredit)}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------
export default function JournalEntryDetailPage() {
  const { ulid: id } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const entryId = id ?? null

  const { data: entry, isLoading, isError } = useJournalEntry(entryId)

  const submitMutation  = useSubmitJournalEntry(entryId ?? '')
  const postMutation    = usePostJournalEntry(entryId ?? '')
  const reverseMutation = useReverseJournalEntry(entryId ?? '')
  const busy = submitMutation.isPending || postMutation.isPending || reverseMutation.isPending

  if (isLoading) return <SkeletonLoader rows={8} />

  if (isError || !entry) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Journal entry not found or failed to load.
      </div>
    )
  }

  async function handleReverse() {
    const desc = window.prompt('Reversal description (optional):') ?? ''
    try {
      const reversed = await reverseMutation.mutateAsync(desc)
      toast.success('Reversal entry created.')
      navigate(`/accounting/journal-entries/${reversed.ulid}`)
    } catch {
      toast.error('Failed to create reversal.')
    }
  }

  return (
    <div className="max-w-5xl mx-auto">
      {/* Back nav */}
      <button
        onClick={() => navigate('/accounting/journal-entries')}
        className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 mb-6"
      >
        <ArrowLeft className="h-4 w-4" />
        Journal Entries
      </button>

      {/* Header */}
      <div className="flex items-start justify-between mb-6">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-semibold text-neutral-900 font-mono">
              {entry.je_number ?? <span className="text-neutral-400 font-sans text-lg">— Draft —</span>}
            </h1>
            <StatusBadge label={entry.status} autoVariant />
            {entry.is_auto_posted && (
              <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
                System
              </span>
            )}
            {entry.reversal_of != null && (
              <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-neutral-100 text-neutral-700">
                Reversal
              </span>
            )}
          </div>
          <p className="text-sm text-neutral-500 mt-1">{formatDate(entry.date)}</p>
        </div>

        {/* Action buttons */}
        {!entry.is_auto_posted && (
          <div className="flex items-center gap-2">
            {entry.status === 'draft' && (
              <button
                onClick={async () => {
                  try {
                    await submitMutation.mutateAsync()
                    toast.success('Journal entry submitted for approval.')
                  } catch {
                    toast.error('Failed to submit entry.')
                  }
                }}
                disabled={busy}
                className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded transition-colors disabled:opacity-50"
              >
                {submitMutation.isPending ? 'Submitting…' : 'Submit for Approval'}
              </button>
            )}
            {entry.status === 'submitted' && (
              <SodActionButton
                initiatedById={entry.created_by}
                label="Post"
                onClick={async () => {
                  try {
                    await postMutation.mutateAsync()
                    toast.success('Journal entry posted.')
                  } catch {
                    toast.error('Failed to post entry.')
                  }
                }}
                isLoading={postMutation.isPending}
                disabled={submitMutation.isPending || reverseMutation.isPending}
                variant="primary"
              />
            )}
            {entry.status === 'posted' && entry.reversal_of == null && (
              <button
                onClick={() => void handleReverse()}
                disabled={busy}
                className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-neutral-300 text-neutral-600 hover:bg-neutral-50 rounded transition-colors disabled:opacity-50"
              >
                <RotateCcw className="h-4 w-4" />
                {reverseMutation.isPending ? 'Reversing…' : 'Reverse'}
              </button>
            )}
          </div>
        )}
      </div>

      {/* Meta card */}
      <div className="bg-white border border-neutral-200 rounded p-6 mb-4">
        <dl className="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4">
          <div>
            <dt className="text-xs font-semibold text-neutral-500">Date</dt>
            <dd className="mt-1 text-sm text-neutral-900">{formatDate(entry.date)}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500">Status</dt>
            <dd className="mt-1"><StatusBadge label={entry.status} autoVariant /></dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500">Source</dt>
            <dd className="mt-1 text-sm text-neutral-900 capitalize">{entry.source_type}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-neutral-500">Fiscal Period</dt>
            <dd className="mt-1 text-sm text-neutral-900">
              {entry.fiscal_period?.name ?? entry.fiscal_period_id}
            </dd>
          </div>
          {entry.description && (
            <div className="col-span-4">
              <dt className="text-xs font-semibold text-neutral-500">Description</dt>
              <dd className="mt-1 text-sm text-neutral-900">{entry.description}</dd>
            </div>
          )}
          {entry.reversal_of != null && (
            <div>
              <dt className="text-xs font-semibold text-neutral-500">Reversal Of</dt>
              <dd className="mt-1">
                <button
                  onClick={() => navigate(`/accounting/journal-entries/${entry.reversal_of_ulid}`)}
                  className="text-sm text-neutral-600 hover:underline font-mono"
                >
                  #{entry.reversal_of}
                </button>
              </dd>
            </div>
          )}
          {entry.posted_at && (
            <div>
              <dt className="text-xs font-semibold text-neutral-500">Posted At</dt>
              <dd className="mt-1 text-sm text-neutral-900">{formatDate(entry.posted_at)}</dd>
            </div>
          )}
        </dl>
      </div>

      {/* Lines */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="px-4 py-3 bg-neutral-50 border-b border-neutral-200">
          <h2 className="text-sm font-semibold text-neutral-700">Journal Lines</h2>
        </div>
        {entry.lines && entry.lines.length > 0
          ? <LinesTable lines={entry.lines} />
          : (
            <p className="px-4 py-8 text-center text-neutral-400 text-sm">No lines loaded.</p>
          )
        }
      </div>
    </div>
  )
}
