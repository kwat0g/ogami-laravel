import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, RefreshCw } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useFiscalPeriods,
  useCreateFiscalPeriod,
  useOpenFiscalPeriod,
  useCloseFiscalPeriod,
} from '@/hooks/useAccounting'
import { useAuthStore } from '@/stores/authStore'
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
  open: 'bg-neutral-100 text-neutral-700',
  closed: 'bg-neutral-100 text-neutral-600',
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
  const canManage = useAuthStore(s => s.hasPermission('fiscal_periods.manage'))
  const openMutation = useOpenFiscalPeriod(period.id)
  const closeMutation = useCloseFiscalPeriod(period.id)
  const busy = openMutation.isPending || closeMutation.isPending

  if (!canManage) return null

  if (period.status === 'closed') {
    return (
      <button
        onClick={async () => {
          try {
            await openMutation.mutateAsync()
            toast.success(`Period "${period.name}" reopened.`)
          } catch {
            toast.error('Failed to reopen period.')
          }
        }}
        disabled={busy}
        className="text-xs text-neutral-700 hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {openMutation.isPending ? 'Opening…' : 'Open'}
      </button>
    )
  }

  return (
    <button
      onClick={async () => {
        try {
          await closeMutation.mutateAsync()
          toast.success(`Period "${period.name}" closed.`)
        } catch {
          toast.error('Failed to close period.')
        }
      }}
      disabled={busy}
      className="text-xs text-neutral-600 hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
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
      <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-4">New Fiscal Period</h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Period Name</label>
            <input
              className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              placeholder="e.g. January 2026"
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Date From</label>
              <input
                type="date"
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Date To</label>
              <input
                type="date"
                className="w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
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
              className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving}
              className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 hover:bg-neutral-800 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
  const canManage = useAuthStore(s => s.hasPermission('fiscal_periods.manage'))
  const [statusFilter, setStatusFilter] = useState<FiscalPeriodStatus | undefined>(undefined)
  const [modalOpen, setModalOpen] = useState(false)

  const { data, isLoading, isError, refetch, isFetching } = useFiscalPeriods(statusFilter)
  const createMutation = useCreateFiscalPeriod()

  async function handleCreate(payload: CreateFiscalPeriodPayload) {
    try {
      await createMutation.mutateAsync(payload)
      toast.success('Fiscal period created.')
    } catch {
      toast.error('Failed to create fiscal period.')
    }
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
      <PageHeader title="Fiscal Periods" />

      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <p className="text-sm text-neutral-500">{data?.meta?.total ?? periods.length} periods</p>
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
          {canManage && (
            <button
              onClick={() => setModalOpen(true)}
              className="flex items-center gap-2 bg-neutral-900 hover:bg-neutral-800 text-white text-sm font-medium px-4 py-2 rounded transition-colors"
            >
              <Plus className="h-4 w-4" />
              New Period
            </button>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white border border-neutral-200 rounded p-4 mb-4 flex gap-3">
        <select
          value={statusFilter ?? ''}
          onChange={(e) => setStatusFilter((e.target.value as FiscalPeriodStatus) || undefined)}
          className="border border-neutral-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-neutral-400 outline-none"
        >
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded overflow-hidden">
        <table className="min-w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Name</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Date From</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Date To</th>
              <th className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-500">Status</th>
              <th className="px-3 py-2.5 text-right text-xs font-semibold text-neutral-500">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {periods.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-3 py-10 text-center text-neutral-400 text-sm">
                  No fiscal periods found.
                </td>
              </tr>
            ) : (
              periods.map((period) => (
                <tr key={period.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                  <td className="px-3 py-2 font-medium text-neutral-900">{period.name}</td>
                  <td className="px-3 py-2 text-neutral-600">{formatDate(period.date_from)}</td>
                  <td className="px-3 py-2 text-neutral-600">{formatDate(period.date_to)}</td>
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
