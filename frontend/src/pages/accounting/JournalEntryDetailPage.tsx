import { useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { RotateCcw, BookOpen } from 'lucide-react'
import {
  useJournalEntry,
  useSubmitJournalEntry,
  usePostJournalEntry,
  useReverseJournalEntry,
} from '@/hooks/useAccounting'
import { firstErrorMessage } from '@/lib/errorHandler'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import SodActionButton from '@/components/ui/SodActionButton'
import PageHeader from '@/components/ui/PageHeader'
import { Card, CardHeader, CardBody } from '@/components/ui/Card'
import { InfoRow, InfoList } from '@/components/ui/InfoRow'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
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

  async function handlePost() {
    try {
      await postMutation.mutateAsync()
      toast.success('Journal entry posted.')
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  async function handleReverse() {
    const desc = window.prompt('Reversal description (optional):') ?? ''
    try {
      const reversed = await reverseMutation.mutateAsync(desc)
      toast.success('Reversal entry created.')
      navigate(`/accounting/journal-entries/${reversed.ulid}`)
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  async function _handleDelete() {
    try {
      await deleteMutation.mutateAsync()
      toast.success('Journal entry deleted.')
      navigate('/accounting/journal-entries')
    } catch (_err) {
      toast.error(firstErrorMessage(err))
    }
  }

  const statusBadges = (
    <div className="flex items-center gap-2">
      <StatusBadge status={entry.status}>{entry.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
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
  )

  const _canDelete = entry.status === 'draft' && !entry.is_auto_posted

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        backTo="/accounting/journal-entries"
        title={entry.je_number ?? 'Draft Journal Entry'}
        subtitle={formatDate(entry.date)}
        icon={<BookOpen className="w-5 h-5" />}
        status={statusBadges}
        actions={
          !entry.is_auto_posted && (
            <div className="flex items-center gap-2">
              {entry.status === 'draft' && (
                <ConfirmDialog
                  title="Submit for Approval?"
                  description="This will submit the journal entry for review and approval. Continue?"
                  confirmLabel="Submit"
                  onConfirm={async () => {
                    try {
                      await submitMutation.mutateAsync()
                      toast.success('Journal entry submitted for approval.')
                    } catch (_err) {
                      toast.error(firstErrorMessage(err))
                    }
                  }}
                >
                  <button
                    disabled={busy}
                    className="px-4 py-2 text-sm font-medium bg-neutral-900 hover:bg-neutral-800 text-white rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {submitMutation.isPending ? 'Submitting…' : 'Submit for Approval'}
                  </button>
                </ConfirmDialog>
              )}
              {entry.status === 'submitted' && (
                <ConfirmDialog
                  title="Post Journal Entry?"
                  description="Posted entries cannot be edited. This will finalize the journal entry in the general ledger. Continue?"
                  confirmLabel="Post Entry"
                  onConfirm={handlePost}
                >
                  <SodActionButton
                    initiatedById={entry.created_by}
                    label="Post"
                    onClick={() => {}} // Handled by ConfirmDialog
                    isLoading={postMutation.isPending}
                    disabled={submitMutation.isPending || reverseMutation.isPending}
                    variant="primary"
                  />
                </ConfirmDialog>
              )}
              {entry.status === 'posted' && entry.reversal_of == null && (
                <ConfirmDestructiveDialog
                  title="Reverse Journal Entry?"
                  description="This will create a reversing journal entry to cancel this transaction. The original entry will remain posted. Continue?"
                  confirmWord="REVERSE"
                  confirmLabel="Reverse Entry"
                  onConfirm={handleReverse}
                >
                  <button
                    disabled={busy}
                    className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <RotateCcw className="h-4 w-4" />
                    {reverseMutation.isPending ? 'Reversing…' : 'Reverse'}
                  </button>
                </ConfirmDestructiveDialog>
              )}
            </div>
          )
        }
      />

      {/* Meta card */}
      <Card className="mb-4">
        <CardHeader>Entry Information</CardHeader>
        <CardBody>
          <InfoList columns={2}>
            <InfoRow label="Date" value={formatDate(entry.date)} />
            <InfoRow label="Status" value={<StatusBadge status={entry.status}>{entry.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>} />
            <InfoRow label="Source" value={<span className="capitalize">{entry.source_type}</span>} />
            <InfoRow label="Fiscal Period" value={entry.fiscal_period?.name ?? entry.fiscal_period_id} />
            {entry.description && <InfoRow label="Description" value={entry.description} />}
            {entry.reversal_of != null && (
              <InfoRow 
                label="Reversal Of" 
                value={
                  <button
                    onClick={() => navigate(`/accounting/journal-entries/${entry.reversal_of_ulid}`)}
                    className="text-sm text-neutral-600 hover:underline font-mono"
                  >
                    #{entry.reversal_of}
                  </button>
                } 
              />
            )}
            {entry.posted_at && <InfoRow label="Posted At" value={formatDate(entry.posted_at)} />}
          </InfoList>
        </CardBody>
      </Card>

      {/* Lines */}
      <Card>
        <CardHeader>Journal Lines</CardHeader>
        <CardBody className="p-0">
          {entry.lines && entry.lines.length > 0
            ? <LinesTable lines={entry.lines} />
            : (
              <p className="px-4 py-8 text-center text-neutral-400 text-sm">No lines loaded.</p>
            )
          }
        </CardBody>
      </Card>
    </div>
  )
}
