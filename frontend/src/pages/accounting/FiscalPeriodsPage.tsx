import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, RefreshCw, Lock } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import {
  useFiscalPeriods,
  useCreateFiscalPeriod,
  useOpenFiscalPeriod,
  useCloseFiscalPeriod,
} from '@/hooks/useAccounting'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
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
      <ConfirmDialog
        title="Reopen Fiscal Period?"
        description={`Reopen "${period.name}"? This will allow new journal entries to be posted to this period. Continue?`}
        confirmLabel="Reopen"
        onConfirm={async () => {
          try {
            await openMutation.mutateAsync()
            toast.success(`Period "${period.name}" reopened.`)
          } catch (_err) {
            toast.error(firstErrorMessage(err))
          }
        }}
      >
        <button
          disabled={busy}
          className="text-xs text-neutral-700 hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {openMutation.isPending ? 'Opening…' : 'Open'}
        </button>
      </ConfirmDialog>
    )
  }

  return (
    <ConfirmDestructiveDialog
      title="Close Fiscal Period?"
      description={`Close "${period.name}"? Once closed, no new journal entries can be posted to this period. This action cannot be easily undone. Continue?`}
      confirmWord="CLOSE"
      confirmLabel="Close Period"
      onConfirm={async () => {
        try {
          await closeMutation.mutateAsync()
          toast.success(`Period "${period.name}" closed.`)
        } catch (_err) {
          toast.error(firstErrorMessage(err))
        }
      }}
    >
      <button
        disabled={busy}
        className="text-xs text-neutral-600 hover:underline disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
      >
        <Lock className="h-3 w-3" />
        {closeMutation.isPending ? 'Closing…' : 'Close'}
      </button>
    </ConfirmDestructiveDialog>
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
  const [touched, setTouched] = useState(false)

  if (!open) return null

  // Client-side validation
  const nameError = touched && !name.trim() ? 'Period name is required.' : undefined
  const dateFromError = touched && !dateFrom ? 'Start date is required.' : undefined
  const dateToError = touched && !dateTo ? 'End date is required.' : undefined
  const dateRangeError = touched && dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)
    ? 'End date must be after start date.'
    : undefined

  const isValid = name.trim() && dateFrom && dateTo && new Date(dateFrom) <= new Date(dateTo)

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
    if (!isValid) return
    onSave({ name, date_from: dateFrom, date_to: dateTo })
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div className="bg-white rounded border border-neutral-200 w-full max-w-md p-6">
        <h2 className="text-lg font-semibold text-neutral-900 mb-4">New Fiscal Period</h2>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">
              Period Name <span className="text-red-500">*</span>
            </label>
            <input
              className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                nameError ? 'border-red-400' : 'border-neutral-300'
              }`}
              value={name}
              onChange={(e) => setName(e.target.value)}
              onBlur={() => setTouched(true)}
              required
              placeholder="e.g. January 2026"
            />
            {nameError && <p className="mt-1 text-xs text-red-600">{nameError}</p>}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Date From <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                  dateFromError || dateRangeError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                onBlur={() => setTouched(true)}
                required
              />
              {dateFromError && <p className="mt-1 text-xs text-red-600">{dateFromError}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">
                Date To <span className="text-red-500">*</span>
              </label>
              <input
                type="date"
                className={`w-full border rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 outline-none ${
                  dateToError || dateRangeError ? 'border-red-400' : 'border-neutral-300'
                }`}
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                onBlur={() => setTouched(true)}
                required
                min={dateFrom}
              />
              {dateToError && <p className="mt-1 text-xs text-red-600">{dateToError}</p>}
              {dateRangeError && <p className="mt-1 text-xs text-red-600">{dateRangeError}</p>}
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
              disabled={saving || !isValid}
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
    } catch (_err) {
      toast.error(firstErrorMessage(err))
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
