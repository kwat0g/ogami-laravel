import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus, RefreshCw, RotateCcw } from 'lucide-react'
import {
  useJournalEntries,
  useFiscalPeriods,
  useSubmitJournalEntry,
  usePostJournalEntry,
  useReverseJournalEntry,
} from '@/hooks/useAccounting'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
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
  manual:  'bg-blue-100 text-blue-700',
  payroll: 'bg-purple-100 text-purple-700',
  ap:      'bg-yellow-100 text-yellow-700',
  ar:      'bg-teal-100 text-teal-700',
}

function SourceTypeBadge({ sourceType }: { sourceType: string }) {
  const color = SOURCE_TYPE_COLORS[sourceType] ?? 'bg-gray-100 text-gray-600'
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
    return <span className="text-xs text-gray-400 italic">System</span>
  }

  return (
    <div className="flex items-center justify-end gap-2">
      {entry.status === 'draft' && (
        <button
          onClick={() => void submitMutation.mutateAsync()}
          disabled={busy}
          className="text-xs text-blue-600 hover:underline disabled:opacity-50"
        >
          {submitMutation.isPending ? 'Submitting…' : 'Submit'}
        </button>
      )}
      {entry.status === 'submitted' && (
        <button
          onClick={() => void postMutation.mutateAsync()}
          disabled={busy}
          className="text-xs text-green-600 hover:underline disabled:opacity-50"
        >
          {postMutation.isPending ? 'Posting…' : 'Post'}
        </button>
      )}
      {entry.status === 'posted' && !entry.reversal_of && (
        <button
          onClick={() => {
            const desc = window.prompt('Reversal description (optional):') ?? ''
            void reverseMutation.mutateAsync(desc).then(() => onReversed?.())
          }}
          disabled={busy}
          className="text-xs text-orange-600 hover:underline disabled:opacity-50 flex items-center gap-1"
        >
          <RotateCcw className="h-3 w-3" />
          {reverseMutation.isPending ? 'Reversing…' : 'Reverse'}
        </button>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------
export default function JournalEntriesPage() {
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState<JournalEntryStatus | undefined>(undefined)
  const [fiscalPeriodId, setFiscalPeriodId] = useState<number | undefined>(undefined)
  const [sourceType, setSourceType] = useState<string | undefined>(undefined)
  const [dateFrom, setDateFrom] = useState('')
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
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Journal Entries</h1>
          <p className="text-sm text-gray-500 mt-0.5">{data?.meta?.total ?? entries.length} entries</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => void refetch()}
            disabled={isFetching}
            className="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors disabled:opacity-40"
            title="Refresh"
          >
            <RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          </button>
          <button
            onClick={() => navigate('/accounting/journal-entries/new')}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            <Plus className="h-4 w-4" />
            New Entry
          </button>
        </div>
      </div>

      {/* Status tabs */}
      <div className="flex gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.label}
            onClick={() => setActiveTab(tab.value)}
            className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
              activeTab === tab.value
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap gap-3">
        <select
          value={fiscalPeriodId ?? ''}
          onChange={(e) => setFiscalPeriodId(e.target.value ? Number(e.target.value) : undefined)}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
        >
          <option value="">All Periods</option>
          {periods.map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </select>

        <select
          value={sourceType ?? ''}
          onChange={(e) => setSourceType(e.target.value || undefined)}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
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
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="Date from"
          />
          <span className="text-gray-400 text-sm">–</span>
          <input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="Date to"
            min={dateFrom}
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">JE Number</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Debits</th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {entries.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-3 py-10 text-center text-gray-400 text-sm">
                    No journal entries found.
                  </td>
                </tr>
              ) : (
                entries.map((entry) => (
                  <tr key={entry.id} className="even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                    <td
                      className="px-3 py-2 font-mono text-xs text-blue-700 font-medium cursor-pointer hover:underline"
                      onClick={() => navigate(`/accounting/journal-entries/${entry.ulid}`)}
                    >
                      {entry.je_number ?? <span className="text-gray-400 italic">Draft</span>}
                    </td>
                    <td className="px-3 py-2 text-gray-600">{formatDate(entry.date)}</td>
                    <td className="px-3 py-2 text-gray-900 max-w-xs truncate" title={entry.description}>
                      {entry.description || <span className="text-gray-400 italic">—</span>}
                      {entry.reversal_of && (
                        <span className="ml-1 text-xs text-orange-500">(reversal)</span>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      <SourceTypeBadge sourceType={entry.source_type} />
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge label={entry.status} autoVariant />
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums text-gray-700">
                      {entry.lines && entry.lines.length > 0
                        ? `₱${formatAmount(entry.lines.reduce((s, l) => s + (l.debit ?? 0), 0))}`
                        : <span className="text-gray-400">—</span>
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
          <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-600">
            <span>
              Page {data.meta.current_page} of {data.meta.last_page} &middot; {data.meta.total} total
            </span>
            <div className="flex gap-2">
              <button
                disabled={data.meta.current_page <= 1}
                className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={data.meta.current_page >= data.meta.last_page}
                className="px-3 py-1 rounded border border-gray-200 disabled:opacity-40 hover:bg-gray-50 transition-colors"
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
