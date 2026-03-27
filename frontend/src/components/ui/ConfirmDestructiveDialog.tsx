import { useState } from 'react'
import { AlertTriangle } from 'lucide-react'

/**
 * ConfirmDestructiveDialog
 *
 * A confirmation dialog for irreversible actions (void payroll run,
 * terminate employee, delete entry, etc.).
 *
 * Requires the user to type a confirmation word before proceeding.
 *
 * Usage:
 *   <ConfirmDestructiveDialog
 *     title="Void payroll run?"
 *     description="This action cannot be undone. All released payments will be reversed."
 *     confirmWord="VOID"
 *     onConfirm={handleVoid}
 *   >
 *     <button>Void</button>
 *   </ConfirmDestructiveDialog>
 */

interface ConfirmDestructiveDialogProps {
  title: string
  description: string
  /** Word the user must type to enable the confirm button. */
  confirmWord?: string
  confirmLabel?: string
  variant?: 'danger' | 'warning'
  onConfirm: () => void | Promise<void>
  /** When provided, dialog is controlled externally */
  open?: boolean
  onClose?: () => void
  loading?: boolean
  children?: React.ReactElement
}

export default function ConfirmDestructiveDialog({
  title,
  description,
  confirmWord = 'CONFIRM',
  confirmLabel = 'Confirm',
  variant: _variant = 'danger',
  onConfirm,
  open: controlledOpen,
  onClose,
  loading: controlledLoading,
  children,
}: ConfirmDestructiveDialogProps) {
  const [internalOpen, setInternalOpen] = useState(false)
  const [typed, setTyped] = useState('')
  const [internalLoading, setInternalLoading] = useState(false)

  const isControlled = controlledOpen !== undefined
  const open = isControlled ? controlledOpen : internalOpen
  const setOpen = isControlled ? (v: boolean) => { if (!v && onClose) onClose(); setTyped('') } : (v: boolean) => { setInternalOpen(v); if (!v) setTyped('') }
  const loading = controlledLoading ?? internalLoading
  const setLoading = isControlled ? (_v: boolean) => {} : setInternalLoading

  const canConfirm = typed === confirmWord

  const handleConfirm = async () => {
    setLoading(true)
    try {
      await onConfirm()
      setOpen(false)
    } finally {
      setLoading(false)
      setTyped('')
    }
  }

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
                <AlertTriangle className="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                <div>
                  <h3 className="text-sm font-semibold text-neutral-900">{title}</h3>
                  <p className="text-sm text-neutral-500 mt-1">{description}</p>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  Type <span className="font-mono font-bold text-red-600">{confirmWord}</span> to confirm
                </label>
                <input
                  type="text"
                  value={typed}
                  onChange={(e) => setTyped(e.target.value)}
                  className="w-full border border-neutral-300 rounded px-3 py-2 text-sm font-mono
                           focus:outline-none focus:ring-1 focus:ring-neutral-400"
                  autoFocus
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 p-4 border-t border-neutral-200 bg-neutral-50">
              <button
                type="button"
                onClick={() => { setOpen(false); setTyped('') }}
                className="px-4 py-2 text-sm text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50 transition-colors"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={!canConfirm || loading}
                onClick={handleConfirm}
                className="px-4 py-2 text-sm text-white bg-red-600 rounded
                           hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
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
