import { useState } from 'react'
import { Plus, RefreshCw } from 'lucide-react'
import {
  useFiscalPeriods,
  useCreateFiscalPeriod,
  useOpenFiscalPeriod,
  useCloseFiscalPeriod,
} from '@/hooks/useAccounting'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import type { FiscalPeriod, CreateFiscalPeriodPayload, FiscalPeriodStatus } from '@/types/accounting'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

const STATUS_COLORS: Record<FiscalPeriodStatus, string> = {
  open: 'bg-green-100 text-green-700',
  closed: 'bg-gray-100 text-gray-600',
}

function PeriodStatusBadge({ status }: { status: FiscalPeriodStatus }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize ${STATUS_COLORS[status]}`}>
      {status}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Action buttons per row — need individual hook calls per id
// ---------------------------------------------------------------------------
function PeriodActions({ period }: { period: FiscalPeriod }) {
  const openMutation = useOpenFiscalPeriod(period.id)
  const closeMutation = useCloseFiscalPeriod(period.id)
  const busy = openMutation.isPending || closeMutation.isPending

  if (period.status === 'closed') {
    return (
      <button
        onClick={() => void openMutation.mutateAsync()}
        disabled={busy}
        className="text-xs text-green-600 hover:underline disabled:opacity-50"
      >
        {openMutation.isPending ? 'Opening…' : 'Open'}
      </button>
    )
  }

  return (
    <button
      onClick={() => void closeMutation.mutateAsync()}
      disabled={busy}
      className="text-xs text-red-500 hover:underline disabled:opacity-50"
    >
      {closeMutation.isPending ? 'Closing…' : 'Close'}
    </button>
  )
}

// ---------------------------------------------------------------------------
// Create Modal
// ---------------------------------------------------------------------------
interface CreateModalProps {
  open: boolean
  onClose: () => void
  onSave: (payload: CreateFiscalPeriodPayload) => void
  saving: boolean
}

function CreatePeriodModal({ open, onClose, onSave, saving }: CreateModalProps) {
  const [name, setName] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  if (!open) return null

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    onSave({ name, date_from: dateFrom, date_to: dateTo })
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">New Fiscal Period</h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Period Name</label>
            <input
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              placeholder="e.g. January 2026"
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date From</label>
              <input
                type="date"
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date To</label>
              <input
                type="date"
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                required
                min={dateFrom}
              />
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
            >
              {saving ? 'Creating…' : 'Create Period'}
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
export default function FiscalPeriodsPage() {
  const [statusFilter, setStatusFilter] = useState<FiscalPeriodStatus | undefined>(undefined)
  const [modalOpen, setModalOpen] = useState(false)

  const { data, isLoading, isError, refetch, isFetching } = useFiscalPeriods(statusFilter)
  const createMutation = useCreateFiscalPeriod()

  async function handleCreate(payload: CreateFiscalPeriodPayload) {
    await createMutation.mutateAsync(payload)
    setModalOpen(false)
  }

  if (isLoading) return <SkeletonLoader rows={6} />

  if (isError) {
    return (
      <div className="text-red-600 text-sm mt-4">
        Failed to load fiscal periods. Please try again.
      </div>
    )
  }

  const periods = data?.data ?? []

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Fiscal Periods</h1>
          <p className="text-sm text-gray-500 mt-0.5">{data?.meta?.total ?? periods.length} periods</p>
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
            onClick={() => setModalOpen(true)}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
          >
            <Plus className="h-4 w-4" />
            New Period
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white border border-gray-200 rounded-xl p-4 mb-4 flex gap-3">
        <select
          value={statusFilter ?? ''}
          onChange={(e) => setStatusFilter((e.target.value as FiscalPeriodStatus) || undefined)}
          className="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none"
        >
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date From</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date To</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {periods.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-3 py-10 text-center text-gray-400 text-sm">
                  No fiscal periods found.
                </td>
              </tr>
            ) : (
              periods.map((period) => (
                <tr key={period.id} className="even:bg-slate-50 hover:bg-blue-50/60 transition-colors">
                  <td className="px-3 py-2 font-medium text-gray-900">{period.name}</td>
                  <td className="px-3 py-2 text-gray-600">{formatDate(period.date_from)}</td>
                  <td className="px-3 py-2 text-gray-600">{formatDate(period.date_to)}</td>
                  <td className="px-3 py-2">
                    <PeriodStatusBadge status={period.status} />
                  </td>
                  <td className="px-3 py-2 text-right">
                    <PeriodActions period={period} />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <CreatePeriodModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSave={handleCreate}
        saving={createMutation.isPending}
      />
    </div>
  )
}
