import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { RefreshCw } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useJournalEntries,
  useFiscalPeriods,
  useSubmitJournalEntry,
  usePostJournalEntry,
  useReverseJournalEntry,
} from '@/hooks/useAccounting'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { ExportButton } from '@/components/ui/ExportButton'
import StatusBadge from '@/components/ui/StatusBadge'
import { ActionButton, DepartmentGuard } from '@/components/ui/guards'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import type { JournalEntry, JournalEntryStatus, JournalEntryFilters } from '@/types/accounting'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function formatAmount(amount: number): string {
  return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount)
}

const STATUS_TABS: Array<{ label: string; value: JournalEntryStatus | undefined }> = [
  { label: 'All', value: undefined },
  { label: 'Draft', value: 'draft' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Posted', value: 'posted' },
  { label: 'Stale', value: 'stale' },
  { label: 'Cancelled', value: 'cancelled' },
]

const SOURCE_TYPE_COLORS: Record<string, string> = {
  manual:  'bg-neutral-100 text-neutral-700',
  payroll: 'bg-neutral-100 text-neutral-700',
  ap:      'bg-neutral-100 text-neutral-700',
  ar:      'bg-neutral-100 text-neutral-700',
}

function SourceTypeBadge({ sourceType }: { sourceType: string }) {
  const color = SOURCE_TYPE_COLORS[sourceType] ?? 'bg-neutral-100 text-neutral-600'
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize ${color}`}>
      {sourceType}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Row-level action buttons (hooks need specific id)
// ---------------------------------------------------------------------------
function JournalEntryActions({ entry, onReversed }: { entry: JournalEntry; onReversed?: () => void }) {
  const submitMutation = useSubmitJournalEntry(entry.ulid)
  const postMutation = usePostJournalEntry(entry.ulid)
  const reverseMutation = useReverseJournalEntry(entry.ulid)
  const busy = submitMutation.isPending || postMutation.isPending || reverseMutation.isPending

  if (entry.is_auto_posted) {
    return <span className="text-xs text-neutral-400 italic">System</span>
  }

  return (
    <div className="flex items-center justify-end gap-2">
      <DepartmentGuard module="journal_entries">
        {entry.status === 'draft' && (
          <ConfirmDialog
            title="Submit for Approval?"
            description="This will submit the journal entry for review and approval. Continue?"
            confirmLabel="Submit"
            onConfirm={async () => {
              try {
                await submitMutation.mutateAsync()
                toast.success('Journal entry submitted.')
              } catch (err) {
                toast.error(firstErrorMessage(err))
              }
            }}
          >
            <ActionButton
              label="Submit"
              permission="journal_entries.submit"
              module="journal_entries"
              onClick={() => {}} // Handled by ConfirmDialog
              isLoading={submitMutation.isPending}
              disabled={busy}
              variant="ghost"
              className="text-xs px-2 py-1"
            />
          </ConfirmDialog>
        )}
        {entry.status === 'submitted' && (
          <ConfirmDialog
            title="Post Journal Entry?"
            description="Posted entries cannot be edited. This will finalize the journal entry in the general ledger. Continue?"
            confirmLabel="Post"
            onConfirm={async () => {
              try {
                await postMutation.mutateAsync()
                toast.success('Journal entry posted.')
              } catch (err) {
                toast.error(firstErrorMessage(err))
              }
            }}
          >
            <ActionButton
              label="Post"
              permission="journal_entries.post"
              module="journal_entries"
              onClick={() => {}} // Handled by ConfirmDialog
              isLoading={postMutation.isPending}
              disabled={busy}
              variant="success"
              className="text-xs px-2 py-1"
            />
          </ConfirmDialog>
        )}
        {entry.status === 'posted' && !entry.reversal_of && (
          <ConfirmDestructiveDialog
            title="Reverse Journal Entry?"
            description="This will create a reversing journal entry to cancel this transaction. The original entry will remain posted. Continue?"
            confirmWord="REVERSE"
            confirmLabel="Reverse"
            onConfirm={async () => {
              const desc = window.prompt('Reversal description (optional):') ?? ''
              try {
                await reverseMutation.mutateAsync(desc)
                toast.success('Reversal entry created.')
                onReversed?.()
              } catch (err) {
                toast.error(firstErrorMessage(err))
              }
            }}
          >
            <ActionButton
              label="Reverse"
              permission="journal_entries.reverse"
              module="journal_entries"
              onClick={() => {}} // Handled by ConfirmDestructiveDialog
              isLoading={reverseMutation.isPending}
              disabled={busy}
              variant="warning"
              className="text-xs px-2 py-1"
            />
          </ConfirmDestructiveDialog>
        )}
      </DepartmentGuard>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------
export default function JournalEntriesPage() {
  const navigate = useNavigate()
  const _canCreate = useAuthStore(s => s.hasPermission('journal_entries.create'))
  const [activeTab, setActiveTab] = useState<JournalEntryStatus | undefined>(undefined)
  const [fiscalPeriodId, setFiscalPeriodId] = useState<number | undefined>(undefined)
  const [sourceType, setSourceType] = useState<string | undefined>(undefined)
  const [dateFrom, setDateFrom] = useState('')
  const [isArchiveView, setIsArchiveView] = useState(false)
  const [dateTo, setDateTo] = useState('')

  const filters: JournalEntryFilters = {
    status: activeTab,
    fiscal_period_id: fiscalPeriodId,
    source_type: sourceType as JournalEntryFilters['source_type'],
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
  }

  const { data, isLoading, isError, refetch, isFetching } = useJournalEntries(filters)
  const { data: periodsData } = useFiscalPeriods()

  if (isLoading) return <SkeletonLoader rows={8} />

  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Failed to load journal entries. Please try again.
      </div>
    )
  }

  const entries = data?.data ?? []
  const periods = periodsData?.data ?? []

  return (
    <div>
      <PageHeader
        title="Journal Entries"
        actions={
          <ExportButton
            data={data?.data ?? []}
            columns={[
              { key: 'je_number', label: 'JE Number' },
              { key: 'description', label: 'Description' },
              { key: 'status', label: 'Status' },
              { key: 'total_debit', label: 'Total Debit', format: (v: unknown) => `${((v as number) / 100).toFixed(2)}` },
              { key: 'entry_date', label: 'Entry Date' },
              { key: 'posted_at', label: 'Posted At' },
            ]}
            filename="journal-entries"
          />
        }
      />

      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <p className="text-sm text-neutral-500">{data?.meta?.total ?? entries.length} entries</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => void refetch()}
            disabled={isFetching}
            className="p-2 rounded border border-neutral-300 hover:bg-neutral-50 text-neutral-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            title="Refresh"
          >
            <RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          </button>
          <DepartmentGuard module="journal_entries">
            <ActionButton
              label="New Entry"
              permission="journal_entries.create"
              module="journal_entries"
              onClick={() => navigate('/accounting/journal-entries/new')}
              variant="primary"
              className="flex items-center gap-2"
            />
          </DepartmentGuard>
        </div>
      </div>

      {/* Status tabs */}
      <div className="flex gap-1 mb-4 bg-neutral-100 p-1 rounded w-fit">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.label}
            onClick={() => setActiveTab(tab.value)}
            className={`px-3 py-1.5 text-sm font-medium rounded transition-colors ${
              activeTab === tab.value
                ? 'bg-white text-neutral-900'
                : 'text-neutral-500 hover:text-neutral-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={fiscalPeriodId ?? ''}
          onChange={(e) => setFiscalPeriodId(e.target.value ? Number(e.target.value) : undefined)}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Periods</option>
          {periods.map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </select>

        <select
          value={sourceType ?? ''}
          onChange={(e) => setSourceType(e.target.value || undefined)}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Sources</option>
          <option value="manual">Manual</option>
          <option value="payroll">Payroll</option>
          <option value="ap">AP</option>
          <option value="ar">AR</option>
        </select>

        <div className="flex items-center gap-2">
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
            placeholder="Date from"
          />
          <span className="text-neutral-400 text-sm">–</span>
          <input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
            placeholder="Date to"
            min={dateFrom}
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">JE Number</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Date</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Description</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Source</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Status</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500">Total Debits</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500 w-36">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {entries.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-3 py-10 text-center text-neutral-400 text-sm">
                    No journal entries found.
                  </td>
                </tr>
              ) : (
                entries.map((entry) => (
                  <tr key={entry.id} className="hover:bg-neutral-50 transition-colors">
                    <td
                      className="px-3 py-2 font-mono text-xs text-neutral-700 font-medium cursor-pointer hover:underline"
                      onClick={() => navigate(`/accounting/journal-entries/${entry.ulid}`)}
                    >
                      {entry.je_number ?? <span className="text-neutral-400 italic">Draft</span>}
                    </td>
                    <td className="px-3 py-2 text-neutral-600">{formatDate(entry.date)}</td>
                    <td className="px-3 py-2 text-neutral-900 max-w-xs truncate" title={entry.description}>
                      {entry.description || <span className="text-neutral-400 italic">—</span>}
                      {entry.reversal_of && (
                        <span className="ml-1 text-xs text-neutral-500">(reversal)</span>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      <SourceTypeBadge sourceType={entry.source_type} />
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge status={entry.status}>{entry.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums text-neutral-700">
                      {entry.lines && entry.lines.length > 0
                        ? `₱${formatAmount(entry.lines.reduce((s, l) => s + (l.debit ?? 0), 0))}`
                        : <span className="text-neutral-400">—</span>
                      }
                    </td>
                    <td className="px-3 py-2">
                      <JournalEntryActions entry={entry} onReversed={() => void refetch()} />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {data?.meta && data.meta.last_page > 1 && (
          <div className="px-4 py-3 border-t border-neutral-200 flex items-center justify-between text-sm text-neutral-600">
            <span>
              Page {data.meta.current_page} of {data.meta.last_page} &middot; {data.meta.total} total
            </span>
            <div className="flex gap-2">
              <button
                disabled={data.meta.current_page <= 1}
                className="px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                className="px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-neutral-50 transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
