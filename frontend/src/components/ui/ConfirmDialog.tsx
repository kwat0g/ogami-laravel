import { AlertTriangle, X } from 'lucide-react'

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

  const isDanger = variant === 'danger'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
        onClick={!loading ? onClose : undefined}
      />

      {/* Dialog */}
      <div className="relative bg-white rounded-2xl shadow-xl w-full max-w-sm border border-gray-100 animate-in fade-in zoom-in-95 duration-150">
        {/* Close button */}
        <button
          type="button"
          disabled={loading}
          onClick={onClose}
          className="absolute right-3 top-3 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors disabled:opacity-40"
        >
          <X className="h-4 w-4" />
        </button>

        {/* Body */}
        <div className="px-6 pt-6 pb-5">
          <div className="flex items-start gap-3.5">
            <div className={`flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-full ${isDanger ? 'bg-red-50' : 'bg-amber-50'}`}>
              <AlertTriangle className={`h-5 w-5 ${isDanger ? 'text-red-500' : 'text-amber-500'}`} />
            </div>
            <div className="pt-0.5">
              <h3 className="text-[15px] font-semibold text-gray-900">{title}</h3>
              {description && (
                <p className="text-sm text-gray-500 mt-1 leading-relaxed">{description}</p>
              )}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50/60 rounded-b-2xl">
          <button
            type="button"
            disabled={loading}
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
          >
            Keep
          </button>
          <button
            type="button"
            disabled={loading}
            onClick={onConfirm}
            className={`px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors disabled:opacity-50 ${
              isDanger
                ? 'bg-red-600 hover:bg-red-700'
                : 'bg-amber-500 hover:bg-amber-600'
            }`}
          >
            {loading ? 'Cancelling…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
