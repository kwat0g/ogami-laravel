import { useState } from 'react'
import { AlertCircle } from 'lucide-react'

/**
 * ConfirmDialog
 *
 * A simple confirmation dialog with just Confirm and Cancel buttons.
 * No typing required - use for reversible or low-risk actions.
 *
 * Usage:
 *   <ConfirmDialog
 *     title="Change status?"
 *     description="This will change the employee status to Active."
 *     confirmLabel="Confirm"
 *     onConfirm={handleConfirm}
 *   >
 *     <button>Set Active</button>
 *   </ConfirmDialog>
 */

interface ConfirmDialogProps {
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'danger'
  onConfirm: () => void | Promise<void>
  children: React.ReactElement
}

export default function ConfirmDialog({
  title,
  description,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'default',
  onConfirm,
  children,
}: ConfirmDialogProps) {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)

  const handleConfirm = async () => {
    setLoading(true)
    try {
      await onConfirm()
      setOpen(false)
    } finally {
      setLoading(false)
    }
  }

  const confirmButtonClass =
    variant === 'danger'
      ? 'bg-red-600 hover:bg-red-700 text-white'
      : 'bg-neutral-900 hover:bg-neutral-800 text-white'

  return (
    <>
      {/* Trigger */}
      {children && (
        <span onClick={() => setOpen(true)} style={{ cursor: 'pointer' }}>
          {children}
        </span>
      )}

      {/* Backdrop + dialog */}
      {open && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
          onClick={() => setOpen(false)}
        >
          <div
            className="bg-white rounded-lg shadow-lg w-full max-w-md border border-neutral-200"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="p-4 space-y-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
                <div>
                  <h3 className="text-sm font-semibold text-neutral-900">{title}</h3>
                  <p className="text-sm text-neutral-500 mt-1">{description}</p>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 p-4 border-t border-neutral-200 bg-neutral-50">
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-100 transition-colors"
              >
                {cancelLabel}
              </button>
              <button
                type="button"
                disabled={loading}
                onClick={handleConfirm}
                className={`px-4 py-2 text-sm rounded disabled:opacity-40 disabled:cursor-not-allowed transition-colors ${confirmButtonClass}`}
              >
                {loading ? 'Processing…' : confirmLabel}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
