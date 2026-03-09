import { useState, useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  Archive,
  Download,
  RefreshCw,
  RotateCcw,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Database,
  HardDrive,
} from 'lucide-react'
import PageHeader from '@/components/ui/PageHeader'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import {
  useBackups,
  useBackupStatus,
  useTriggerBackup,
  useRestoreBackup,
  backupDownloadUrl,
  type BackupFile,
} from '@/hooks/useAdmin'
import { cn } from '@/lib/utils'

// ─── Restore confirmation modal ───────────────────────────────────────────────

interface RestoreModalProps {
  file: BackupFile
  onClose: () => void
  onRestored: () => void
}

function RestoreModal({ file, onClose, onRestored }: RestoreModalProps): React.ReactElement {
  const [typed, setTyped]       = useState('')
  const restoreMutation          = useRestoreBackup()
  const canConfirm               = typed === 'CONFIRM'

  const handleRestore = async () => {
    try {
      const result = await restoreMutation.mutateAsync({ filename: file.filename, confirm: 'CONFIRM' })
      toast.success(result.message)
      onRestored()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'Restore failed. Check server logs.'
      toast.error(msg)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
        {/* Header */}
        <div className="flex items-start gap-3 p-5 border-b border-neutral-200">
          <div className="flex-shrink-0 w-9 h-9 rounded-full bg-red-100 flex items-center justify-center">
            <AlertTriangle className="h-5 w-5 text-red-600" />
          </div>
          <div>
            <h2 className="text-base font-semibold text-neutral-900">Restore Production Database</h2>
            <p className="text-sm text-neutral-500 mt-0.5">This will permanently replace all current data.</p>
          </div>
        </div>

        {/* Body */}
        <div className="p-5 space-y-4">
          <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800 space-y-1">
            <p className="font-medium">⚠ What will happen:</p>
            <ul className="list-disc list-inside space-y-0.5 text-red-700">
              <li>A safety backup of the current database will be created first</li>
              <li>All current production data will be <strong>permanently replaced</strong></li>
              <li>All active user sessions will be invalidated</li>
              <li>Everyone will need to log in again</li>
            </ul>
          </div>

          <div className="rounded-md bg-neutral-50 border border-neutral-200 p-3 text-sm">
            <p className="text-neutral-600 mb-1">Restoring from:</p>
            <p className="font-mono text-neutral-900 text-xs break-all">{file.filename}</p>
            <p className="text-neutral-500 text-xs mt-1">{file.size_human} &mdash; {file.created_at}</p>
          </div>

          <div className="space-y-1.5">
            <label className="block text-sm font-medium text-neutral-700">
              Type <span className="font-mono bg-neutral-100 px-1 rounded">CONFIRM</span> to proceed:
            </label>
            <input
              type="text"
              value={typed}
              onChange={(e) => setTyped(e.target.value)}
              placeholder="CONFIRM"
              className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
              autoComplete="off"
              autoFocus
            />
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 px-5 py-4 border-t border-neutral-200">
          <button
            onClick={onClose}
            disabled={restoreMutation.isPending}
            className="px-3 py-2 text-sm rounded-md border border-neutral-300 text-neutral-700 hover:bg-neutral-50 disabled:opacity-40"
          >
            Cancel
          </button>
          <button
            onClick={handleRestore}
            disabled={!canConfirm || restoreMutation.isPending}
            className={cn(
              'flex items-center gap-1.5 px-3 py-2 text-sm rounded-md font-medium transition-colors',
              canConfirm && !restoreMutation.isPending
                ? 'bg-red-600 text-white hover:bg-red-700'
                : 'bg-neutral-100 text-neutral-400 cursor-not-allowed',
            )}
          >
            {restoreMutation.isPending ? (
              <>
                <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                Restoring… this may take a few minutes
              </>
            ) : (
              <>
                <RotateCcw className="h-3.5 w-3.5" />
                Restore Database
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  )
}

// ─── Age badge ────────────────────────────────────────────────────────────────

function AgeBadge({ days }: { days: number }): React.ReactElement {
  const color =
    days === 0 ? 'bg-green-100 text-green-700'
    : days <= 2 ? 'bg-blue-100 text-blue-700'
    : days <= 7 ? 'bg-yellow-100 text-yellow-700'
    : 'bg-neutral-100 text-neutral-600'

  const label =
    days === 0 ? 'Today'
    : days === 1 ? '1 day ago'
    : `${days} days ago`

  return (
    <span className={cn('inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full', color)}>
      <Clock className="h-3 w-3" />
      {label}
    </span>
  )
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function BackupPage(): React.ReactElement {
  const [restoreTarget, setRestoreTarget] = useState<BackupFile | null>(null)
  const [countdown, setCountdown]         = useState<number | null>(null)
  const queryClient = useQueryClient()

  const handleRestored = useCallback(() => {
    setRestoreTarget(null)
    // Nuke all cached API data so nothing stale is served after re-login.
    queryClient.clear()
    let remaining = 10
    setCountdown(remaining)
    const interval = setInterval(() => {
      remaining -= 1
      setCountdown(remaining)
      if (remaining <= 0) {
        clearInterval(interval)
        window.location.replace('/login')
      }
    }, 1000)
  }, [queryClient])

  const { data: backups, isLoading: backupsLoading, refetch: refetchBackups } = useBackups()
  const { data: status,  isLoading: statusLoading  }                          = useBackupStatus()
  const triggerMutation = useTriggerBackup()

  const handleTriggerBackup = async () => {
    const tid = toast.loading('Creating backup… this may take up to 30 seconds.')
    try {
      const result = await triggerMutation.mutateAsync()
      toast.dismiss(tid)
      toast.success(result.message)
    } catch (err: unknown) {
      toast.dismiss(tid)
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'Backup failed. Check server logs.'
      toast.error(msg)
    }
  }

  const latest = status?.latest_backup

  return (
    <div className="max-w-5xl mx-auto">
      <PageHeader
        title="Backup Management"
        subtitle="Manage database backups and restore production data from a previous backup."
        actions={
          <button
            onClick={handleTriggerBackup}
            disabled={triggerMutation.isPending}
            className="flex items-center gap-2 px-3 py-2 text-sm rounded-md bg-neutral-900 text-white hover:bg-neutral-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {triggerMutation.isPending ? (
              <RefreshCw className="h-4 w-4 animate-spin" />
            ) : (
              <Archive className="h-4 w-4" />
            )}
            {triggerMutation.isPending ? 'Creating backup…' : 'Create Backup Now'}
          </button>
        }
      />

      {/* ── Status cards ──────────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        {/* Total backups */}
        <div className="bg-white rounded-lg border border-neutral-200 p-4 flex items-center gap-3">
          <div className="flex-shrink-0 w-9 h-9 rounded-full bg-blue-50 flex items-center justify-center">
            <HardDrive className="h-4.5 w-4.5 text-blue-600" />
          </div>
          <div>
            <p className="text-xs text-neutral-500">Total backups</p>
            {statusLoading ? (
              <div className="h-5 w-8 bg-neutral-100 animate-pulse rounded mt-0.5" />
            ) : (
              <p className="text-xl font-semibold text-neutral-900">{status?.backup_count ?? 0}</p>
            )}
          </div>
        </div>

        {/* Latest backup */}
        <div className="bg-white rounded-lg border border-neutral-200 p-4 flex items-center gap-3">
          <div className="flex-shrink-0 w-9 h-9 rounded-full bg-green-50 flex items-center justify-center">
            <CheckCircle2 className="h-4.5 w-4.5 text-green-600" />
          </div>
          <div className="min-w-0">
            <p className="text-xs text-neutral-500">Latest backup</p>
            {statusLoading ? (
              <div className="h-5 w-28 bg-neutral-100 animate-pulse rounded mt-0.5" />
            ) : latest ? (
              <p className="text-sm font-medium text-neutral-900 truncate">{latest.size_human}</p>
            ) : (
              <p className="text-sm text-neutral-400">No backups found</p>
            )}
          </div>
        </div>

        {/* Age of latest */}
        <div className="bg-white rounded-lg border border-neutral-200 p-4 flex items-center gap-3">
          <div className="flex-shrink-0 w-9 h-9 rounded-full bg-yellow-50 flex items-center justify-center">
            <Clock className="h-4.5 w-4.5 text-yellow-600" />
          </div>
          <div>
            <p className="text-xs text-neutral-500">Last backup</p>
            {statusLoading ? (
              <div className="h-5 w-20 bg-neutral-100 animate-pulse rounded mt-0.5" />
            ) : latest ? (
              <div className="flex items-center gap-1.5 mt-0.5">
                <AgeBadge days={latest.age_days} />
              </div>
            ) : (
              <p className="text-sm text-neutral-400">—</p>
            )}
          </div>
        </div>
      </div>

      {/* ── Restore warning ────────────────────────────────────────────────── */}
      <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4 flex gap-3">
        <AlertTriangle className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
        <div className="text-sm text-amber-800">
          <p className="font-medium mb-1">Before restoring:</p>
          <ul className="list-disc list-inside space-y-0.5 text-amber-700">
            <li>A safety backup of the current database is always taken automatically before restore begins</li>
            <li>Restore replaces <strong>all</strong> production data — transactions, employees, payroll records</li>
            <li>All active sessions are invalidated; everyone must log in again after restore</li>
            <li>Automatic daily backups run at <strong>2:30 AM</strong>; weekly integrity check at <strong>Sunday 4:00 AM</strong></li>
          </ul>
        </div>
      </div>

      {/* ── Backup table ───────────────────────────────────────────────────── */}
      <div className="bg-white rounded-lg border border-neutral-200 overflow-hidden">
        <div className="flex items-center justify-between px-4 py-3 border-b border-neutral-100">
          <div className="flex items-center gap-2">
            <Database className="h-4 w-4 text-neutral-500" />
            <h2 className="text-sm font-medium text-neutral-900">Available Backups</h2>
          </div>
          <button
            onClick={() => refetchBackups()}
            className="flex items-center gap-1.5 px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 transition-colors"
          >
            <RefreshCw className="h-3.5 w-3.5" />
            Refresh
          </button>
        </div>

        {backupsLoading ? (
          <div className="p-4">
            <SkeletonLoader rows={4} />
          </div>
        ) : !backups || backups.length === 0 ? (
          <div className="py-16 text-center text-neutral-400">
            <Archive className="h-10 w-10 mx-auto mb-2 opacity-30" />
            <p className="text-sm">No backup archives found.</p>
            <p className="text-xs mt-1">Create a backup using the button above or wait for the daily 2:30 AM scheduled backup.</p>
          </div>
        ) : (
          <div className="divide-y divide-neutral-100">
            {/* Header row */}
            <div className="grid grid-cols-[1fr_auto_auto_auto_auto] gap-4 px-4 py-2.5 bg-neutral-50 text-xs font-medium text-neutral-500 uppercase tracking-wide">
              <span>Filename</span>
              <span className="text-right">Size</span>
              <span className="text-right">Created</span>
              <span className="text-center">Age</span>
              <span className="text-center">Actions</span>
            </div>

            {backups.map((file) => (
              <div
                key={file.filename}
                className="grid grid-cols-[1fr_auto_auto_auto_auto] gap-4 items-center px-4 py-3 hover:bg-neutral-50 transition-colors"
              >
                {/* Filename */}
                <div className="min-w-0 flex items-center gap-2">
                  <Archive className="h-4 w-4 text-neutral-400 flex-shrink-0" />
                  <span className="text-sm font-mono text-neutral-700 truncate" title={file.filename}>
                    {file.filename}
                  </span>
                </div>

                {/* Size */}
                <span className="text-sm text-neutral-500 text-right whitespace-nowrap">
                  {file.size_human}
                </span>

                {/* Date */}
                <span className="text-sm text-neutral-500 text-right whitespace-nowrap">
                  {file.created_at}
                </span>

                {/* Age badge */}
                <div className="flex justify-center">
                  <AgeBadge days={file.age_days} />
                </div>

                {/* Actions */}
                <div className="flex items-center gap-1.5 justify-center">
                  <a
                    href={backupDownloadUrl(file.filename)}
                    title="Download backup"
                    className="flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md border border-neutral-200 text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 transition-colors"
                  >
                    <Download className="h-3.5 w-3.5" />
                    Download
                  </a>
                  <button
                    onClick={() => setRestoreTarget(file)}
                    title="Restore this backup to production"
                    className="flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md border border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 transition-colors"
                  >
                    <RotateCcw className="h-3.5 w-3.5" />
                    Restore
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ── Restore modal ──────────────────────────────────────────────────── */}
      {restoreTarget && (
        <RestoreModal
          file={restoreTarget}
          onClose={() => setRestoreTarget(null)}
          onRestored={handleRestored}
        />
      )}

      {/* ── Post-restore countdown overlay (blocks all interaction) ──────── */}
      {countdown !== null && (
        <div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 backdrop-blur-sm">
          <div className="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center space-y-5">
            <div className="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto">
              <CheckCircle2 className="h-8 w-8 text-green-600" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-neutral-900">Database Restored Successfully</h2>
              <p className="text-sm text-neutral-500 mt-1">
                All sessions have been invalidated. Redirecting to login&nbsp;in…
              </p>
            </div>
            <div className="text-5xl font-bold text-neutral-900 tabular-nums">{countdown}</div>
            <div className="w-full bg-neutral-100 rounded-full h-1.5 overflow-hidden">
              <div
                className="bg-green-500 h-full rounded-full transition-all duration-1000 ease-linear"
                style={{ width: `${(countdown / 10) * 100}%` }}
              />
            </div>
            <p className="text-xs text-neutral-400">
              Please wait for this countdown before logging back in to ensure all data is fully available.
            </p>
          </div>
        </div>
      )}
    </div>
  )
}
