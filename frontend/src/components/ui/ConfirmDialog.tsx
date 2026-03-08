import { AlertTriangle } from 'lucide-react'

// Uncodixified: Simple confirmation dialog with consistent styling

interface ConfirmDialogProps {
  open: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  description?: string
  confirmLabel?: string
  loading?: boolean
  variant?: 'danger' | 'warning'
}

export default function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  description,
  confirmLabel = 'Confirm',
  loading = false,
  variant = 'danger',
}: ConfirmDialogProps) {
  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={!loading ? onClose : undefined}
      />

      {/* Dialog */}
      <div className="relative bg-white rounded-lg shadow-lg w-full max-w-sm border border-neutral-200">
        {/* Body */}
        <div className="p-4">
          <div className="flex items-start gap-3">
            <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5 text-neutral-500" />
            <div>
              <h3 className="text-sm font-semibold text-neutral-900">{title}</h3>
              {description && (
                <p className="text-sm text-neutral-500 mt-1">{description}</p>
              )}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 p-4 border-t border-neutral-200 bg-neutral-50">
          <button
            type="button"
            disabled={loading}
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={loading}
            onClick={onConfirm}
            className="px-4 py-2 text-sm font-medium text-white bg-neutral-900 rounded hover:bg-neutral-800 transition-colors disabled:opacity-50"
          >
            {loading ? 'Processing…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
