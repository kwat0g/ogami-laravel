import { RotateCcw, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useQueryClient } from '@tanstack/react-query'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useAuthStore } from '@/stores/authStore'
import api from '@/lib/api'

interface ArchiveRowActionsProps {
  /** API prefix for restore/force-delete, e.g. "/vendors" or "/production/boms" */
  apiPrefix: string
  /** Record ID (number or string) */
  recordId: number | string
  /** Human-readable record name for confirmation dialogs */
  recordName?: string
  /** Query keys to invalidate after action */
  queryKeys?: string[][]
  /** Callback after successful restore */
  onRestore?: () => void
  /** Callback after successful force delete */
  onForceDelete?: () => void
}

/**
 * Reusable Restore + Permanent Delete action buttons for archive view rows.
 * Renders inline in a table cell -- wrap in <td> at the call site.
 */
export default function ArchiveRowActions({
  apiPrefix,
  recordId,
  recordName = 'this record',
  queryKeys = [],
  onRestore,
  onForceDelete,
}: ArchiveRowActionsProps) {
  const qc = useQueryClient()
  const isSuperAdmin = useAuthStore(s => s.user?.roles?.some((r: { name: string }) => r.name === 'super_admin'))

  const invalidateAll = () => {
    queryKeys.forEach(key => qc.invalidateQueries({ queryKey: key }))
  }

  return (
    <div className="flex items-center justify-end gap-2">
      <ConfirmDestructiveDialog
        title="Restore Record?"
        description={`Restore "${recordName}" to the active list?`}
        confirmWord="RESTORE"
        confirmLabel="Restore"
        variant="warning"
        onConfirm={async () => {
          try {
            await api.post(`${apiPrefix}/${recordId}/restore`)
            toast.success(`"${recordName}" restored.`)
            invalidateAll()
            onRestore?.()
          } catch (_err) {
            toast.error(firstErrorMessage(err))
          }
        }}
      >
        <button
          className="text-xs text-blue-600 hover:underline flex items-center gap-1"
          onClick={e => e.stopPropagation()}
        >
          <RotateCcw className="w-3 h-3" /> Restore
        </button>
      </ConfirmDestructiveDialog>

      {isSuperAdmin && (
        <ConfirmDestructiveDialog
          title="Permanently Delete?"
          description='This action cannot be undone. Type "DELETE" to confirm.'
          confirmWord="DELETE"
          confirmLabel="Permanently Delete"
          onConfirm={async () => {
            try {
              await api.delete(`${apiPrefix}/${recordId}/force`)
              toast.success('Record permanently deleted.')
              invalidateAll()
              onForceDelete?.()
            } catch (_err) {
              toast.error(firstErrorMessage(err))
            }
          }}
        >
          <button
            className="text-xs text-red-600 hover:underline flex items-center gap-1"
            onClick={e => e.stopPropagation()}
          >
            <Trash2 className="w-3 h-3" /> Delete Forever
          </button>
        </ConfirmDestructiveDialog>
      )}
    </div>
  )
}
