import { useState, useEffect } from 'react'
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

export interface ConfirmDialogProps {
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'danger' | 'warning' | 'primary'
  onConfirm: () => void | Promise<void>
  /** When provided, dialog is controlled externally */
  open?: boolean
  onClose?: () => void
  loading?: boolean
  children?: React.ReactElement
}

export default function ConfirmDialog({
  title,
  description,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'default',
  onConfirm,
  open: controlledOpen,
  onClose,
  loading: controlledLoading,
  children,
}: ConfirmDialogProps) {
  const [internalOpen, setInternalOpen] = useState(false)
  const [internalLoading, setInternalLoading] = useState(false)

  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : internalOpen
  const setOpen = isControlled ? (v: boolean) => { if (!v && onClose) onClose() } : setInternalOpen
  const loading = controlledLoading ?? internalLoading
  const setLoading = isControlled ? () => {} : setInternalLoading

  const handleConfirm = async () => {
    setLoading(true)
    try {
      await onConfirm()
      setOpen(false)
    } finally {
      setLoading(false)
    }
  }

  // Escape key to close dialog
  useEffect(() => {
    if (!open) return
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('keydown', handleEsc)
    return () => document.removeEventListener('keydown', handleEsc)
  }, [open]) // eslint-disable-line react-hooks/exhaustive-deps

  const confirmButtonClass =
    variant === 'danger'
      ? 'bg-red-600 hover:bg-red-700 text-white'
      : variant === 'warning'
        ? 'bg-amber-600 hover:bg-amber-700 text-white'
        : variant === 'primary'
          ? 'bg-blue-600 hover:bg-blue-700 text-white'
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
            className="bg-white dark:bg-neutral-900 rounded-lg shadow-lg w-full max-w-md border border-neutral-200 dark:border-neutral-700"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="p-4 space-y-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" />
                <div>
                  <h3 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{title}</h3>
                  <p className="text-sm text-neutral-500 dark:text-neutral-400 mt-1">{description}</p>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 p-4 border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-600 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
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
