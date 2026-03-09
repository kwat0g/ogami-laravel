import { useState } from 'react'
import { toast } from 'sonner'
import {
  Archive,
  CheckCircle2,
  Download,
  RefreshCw,
  RotateCcw,
  AlertTriangle,
  Clock,
  Database,
  HardDrive,
  Shield,
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
}

function RestoreModal({ file, onClose }: RestoreModalProps): React.ReactElement {
  const [typed, setTyped]       = useState('')
  const restoreMutation          = useRestoreBackup()
  const canConfirm               = typed === 'CONFIRM'

  const handleRestore = async () => {
    try {
      const result = await restoreMutation.mutateAsync({ filename: file.filename, confirm: 'CONFIRM' })
      toast.success(result.message)
      onClose()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'Restore failed. Check server logs.'
      toast.error(msg)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-xl border border-neutral-200 shadow-floating w-full max-w-md">
        {/* Header */}
        <div className="flex items-start gap-3 p-5 border-b border-neutral-200">
          <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-neutral-100 flex items-center justify-center">
            <AlertTriangle className="h-5 w-5 text-neutral-600" />
          </div>
          <div>
            <h2 className="text-base font-semibold text-neutral-900">Restore Production Database</h2>
            <p className="text-sm text-neutral-500 mt-0.5">This will permanently replace all current data.</p>
          </div>
        </div>

        {/* Body */}
        <div className="p-5 space-y-4">
          <div className="rounded-lg bg-neutral-50 border border-neutral-200 p-3 text-sm text-neutral-700 space-y-1">
            <p className="font-medium text-neutral-900">What will happen:</p>
            <ul className="list-disc list-inside space-y-0.5 text-neutral-600">
              <li>A safety backup of the current database will be created first</li>
              <li>All current production data will be <strong>permanently replaced</strong></li>
              <li>All active user sessions will be invalidated</li>
              <li>Everyone will need to log in again</li>
            </ul>
          </div>

          <div className="rounded-lg bg-neutral-50 border border-neutral-200 p-3 text-sm">
            <p className="text-neutral-500 mb-1">Restoring from:</p>
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
              className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-neutral-200 focus:border-neutral-400"
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
            className="px-4 py-2 text-sm rounded-lg border border-neutral-300 text-neutral-700 hover:bg-neutral-50 disabled:opacity-40"
          >
            Cancel
          </button>
          <button
            onClick={handleRestore}
            disabled={!canConfirm || restoreMutation.isPending}
            className={cn(
              'flex items-center gap-1.5 px-4 py-2 text-sm rounded-lg font-medium transition-colors',
              canConfirm && !restoreMutation.isPending
                ? 'bg-neutral-900 text-white hover:bg-neutral-800'
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
  const label =
    days === 0 ? 'Today'
    : days === 1 ? '1 day ago'
    : `${days} days ago`

  return (
    <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600">
      <Clock className="h-3 w-3" />
      {label}
    </span>
  )
}

// ─── Type badge ───────────────────────────────────────────────────────────────

function TypeBadge({ type }: { type: 'safety' | 'regular' }): React.ReactElement {
  if (type === 'safety') {
    return (
      <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
        <Shield className="h-3 w-3" />
        Safety
      </span>
    )
  }
  return (
    <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
      <Archive className="h-3 w-3" />
      Regular
    </span>
  )
}

// ─── Backup section ───────────────────────────────────────────────────────────

interface BackupSectionProps {
  title:     string
  files:     BackupFile[]
  onRestore: (file: BackupFile) => void
}

function BackupSection({ title, files, onRestore }: BackupSectionProps): React.ReactElement {
  if (files.length === 0) return <></>
  return (
    <>
      {/* Section label */}
      <div className="px-4 py-2 bg-neutral-50 border-b border-neutral-100 flex items-center gap-2">
        {files[0].type === 'safety'
          ? <Shield className="h-3.5 w-3.5 text-amber-500" />
          : <Archive className="h-3.5 w-3.5 text-sky-500" />}
        <span className="text-xs font-semibold text-neutral-600 uppercase tracking-wide">{title}</span>
        <span className="ml-auto text-xs text-neutral-400">
          {files.length} {files.length === 1 ? 'file' : 'files'}
        </span>
      </div>

      {files.map((file) => (
        <div
          key={file.filename}
          className="grid grid-cols-[1fr_auto_auto_auto_auto] gap-4 items-start px-4 py-3 hover:bg-neutral-50 transition-colors border-b border-neutral-100 last:border-b-0"
        >
          {/* Filename + badge */}
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <Archive className="h-4 w-4 text-neutral-400 flex-shrink-0 mt-0.5" />
              <span className="text-sm font-mono text-neutral-700 truncate" title={file.filename}>
                {file.filename}
              </span>
            </div>
            <div className="mt-1 pl-6">
              <TypeBadge type={file.type} />
            </div>
          </div>

          {/* Size */}
          <span className="text-sm text-neutral-500 text-right whitespace-nowrap pt-0.5">
            {file.size_human}
          </span>

          {/* Date */}
          <span className="text-sm text-neutral-500 text-right whitespace-nowrap pt-0.5">
            {file.created_at}
          </span>

          {/* Age */}
          <div className="text-center pt-0.5">
            <AgeBadge days={file.age_days} />
          </div>

          {/* Actions */}
          <div className="flex items-center justify-center gap-2 pt-0.5">
            <a
              href={backupDownloadUrl(file.filename)}
              className="inline-flex items-center gap-1 px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 transition-colors"
              title="Download backup"
            >
              <Download className="h-3.5 w-3.5" />
              Download
            </a>
            <button
              onClick={() => onRestore(file)}
              className="inline-flex items-center gap-1 px-2 py-1 text-xs border border-neutral-200 rounded bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 transition-colors"
              title="Restore from this backup"
            >
              <RotateCcw className="h-3.5 w-3.5" />
              Restore
            </button>
          </div>
        </div>
      ))}
    </>
  )
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function BackupPage(): React.ReactElement {
  const [restoreTarget, setRestoreTarget] = useState<BackupFile | null>(null)

  const { data: backups, isLoading: backupsLoading, refetch: refetchBackups } = useBackups()
  const { data: status,  isLoading: statusLoading  }                          = useBackupStatus()
  const triggerMutation = useTriggerBackup()

  const regularBackups = backups?.filter(f => f.type === 'regular') ?? []
  const safetyBackups  = backups?.filter(f => f.type === 'safety')  ?? []

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
            className="flex items-center gap-2 px-4 py-2 text-sm rounded-lg bg-neutral-900 text-white hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
        <div className="bg-white rounded-xl border border-neutral-200 p-4 flex items-center gap-3 shadow-subtle">
          <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-neutral-100 flex items-center justify-center">
            <HardDrive className="h-4.5 w-4.5 text-neutral-600" />
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
        <div className="bg-white rounded-xl border border-neutral-200 p-4 flex items-center gap-3 shadow-subtle">
          <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-neutral-100 flex items-center justify-center">
            <CheckCircle2 className="h-4.5 w-4.5 text-neutral-600" />
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
        <div className="bg-white rounded-xl border border-neutral-200 p-4 flex items-center gap-3 shadow-subtle">
          <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-neutral-100 flex items-center justify-center">
            <Clock className="h-4.5 w-4.5 text-neutral-600" />
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
      <div className="mb-6 rounded-xl bg-neutral-50 border border-neutral-200 p-4 flex gap-3">
        <AlertTriangle className="h-5 w-5 text-neutral-500 flex-shrink-0 mt-0.5" />
        <div className="text-sm text-neutral-700">
          <p className="font-medium text-neutral-900 mb-1">Before restoring:</p>
          <ul className="list-disc list-inside space-y-0.5 text-neutral-600">
            <li>A safety backup of the current database is always taken automatically before restore begins</li>
            <li>Restore replaces <strong>all</strong> production data — transactions, employees, payroll records</li>
            <li>All active sessions are invalidated; everyone must log in again after restore</li>
            <li>Automatic daily backups run at <strong>2:30 AM</strong>; weekly integrity check at <strong>Sunday 4:00 AM</strong></li>
          </ul>
        </div>
      </div>

      {/* ── Backup table ───────────────────────────────────────────────────── */}
      <div className="bg-white rounded-xl border border-neutral-200 overflow-hidden shadow-subtle">
        <div className="flex items-center justify-between px-4 py-3 border-b border-neutral-100">
          <div className="flex items-center gap-2">
            <Database className="h-4 w-4 text-neutral-500" />
            <h2 className="text-sm font-medium text-neutral-900">Available Backups</h2>
          </div>
          <button
            onClick={() => refetchBackups()}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs border border-neutral-200 rounded-lg bg-white text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900 transition-colors"
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
            <div className="grid grid-cols-[1fr_auto_auto_auto_auto] gap-4 px-4 py-2.5 bg-neutral-50 text-xs font-medium text-neutral-500 uppercase tracking-wide border-b border-neutral-100">
              <span>Filename</span>
              <span className="text-right">Size</span>
              <span className="text-right">Created</span>
              <span className="text-center">Age</span>
              <span className="text-center">Actions</span>
            </div>

            <BackupSection
              title="Regular Backups"
              files={regularBackups}
              onRestore={setRestoreTarget}
            />
            <BackupSection
              title="Safety Backups"
              files={safetyBackups}
              onRestore={setRestoreTarget}
            />
          </div>
        )}
      </div>

      {/* ── Restore modal ──────────────────────────────────────────────────── */}
      {restoreTarget && (
        <RestoreModal
          file={restoreTarget}
          onClose={() => setRestoreTarget(null)}
        />
      )}
    </div>
  )
}
