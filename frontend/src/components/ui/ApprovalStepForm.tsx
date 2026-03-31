import { useState } from 'react'
import { CheckCircle, MessageSquare } from 'lucide-react'

/**
 * ApprovalStepForm -- replaces bare ConfirmDialog for multi-step approval workflows.
 *
 * Unlike ConfirmDialog (which is just "Are you sure? Yes/No"), this component
 * captures reviewer comments and optional verification checklist items,
 * making each approval step meaningful rather than a mindless button click.
 *
 * Usage:
 *   <ApprovalStepForm
 *     title="Manager Check"
 *     description="Review the invoice details and confirm everything is correct."
 *     confirmLabel="Approve"
 *     onConfirm={(comments) => handleApprove(comments)}
 *     isLoading={mutation.isPending}
 *     checklist={['GL codes verified', 'Amounts match PO']}
 *   >
 *     <button>Manager Check</button>
 *   </ApprovalStepForm>
 */

interface ApprovalStepFormProps {
  /** Dialog title */
  title: string
  /** Description of what this approval step does */
  description: string
  /** Label for the confirm button */
  confirmLabel?: string
  /** Called with reviewer comments when confirmed */
  onConfirm: (comments: string) => void
  /** Loading state for the confirm action */
  isLoading?: boolean
  /** Optional verification checklist items the reviewer must check */
  checklist?: string[]
  /** Whether comments are required (default: false) */
  requireComments?: boolean
  /** Button/trigger element that opens the form */
  children: React.ReactNode
}

export default function ApprovalStepForm({
  title,
  description,
  confirmLabel = 'Confirm',
  onConfirm,
  isLoading = false,
  checklist = [],
  requireComments = false,
  children,
}: ApprovalStepFormProps) {
  const [open, setOpen] = useState(false)
  const [comments, setComments] = useState('')
  const [checkedItems, setCheckedItems] = useState<Set<number>>(new Set())

  const allChecked = checklist.length === 0 || checkedItems.size === checklist.length
  const hasComments = !requireComments || comments.trim().length > 0
  const canConfirm = allChecked && hasComments && !isLoading

  const toggleCheck = (idx: number) => {
    setCheckedItems(prev => {
      const next = new Set(prev)
      if (next.has(idx)) next.delete(idx)
      else next.add(idx)
      return next
    })
  }

  const handleConfirm = () => {
    onConfirm(comments.trim())
    setOpen(false)
    setComments('')
    setCheckedItems(new Set())
  }

  const handleClose = () => {
    setOpen(false)
    setComments('')
    setCheckedItems(new Set())
  }

  return (
    <>
      {/* Trigger */}
      <span onClick={() => setOpen(true)} className="inline-block">
        {children}
      </span>

      {/* Modal */}
      {open && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl w-full max-w-md shadow-xl border border-neutral-200">
            {/* Header */}
            <div className="p-5 border-b border-neutral-100">
              <h2 className="text-lg font-semibold text-neutral-900 flex items-center gap-2">
                <CheckCircle className="h-5 w-5 text-blue-600" />
                {title}
              </h2>
              <p className="text-sm text-neutral-500 mt-1">{description}</p>
            </div>

            {/* Body */}
            <div className="p-5 space-y-4">
              {/* Verification Checklist */}
              {checklist.length > 0 && (
                <div>
                  <p className="text-sm font-medium text-neutral-700 mb-2">Verification Checklist</p>
                  <div className="space-y-2">
                    {checklist.map((item, idx) => (
                      <label
                        key={idx}
                        className={`flex items-start gap-3 p-2.5 rounded-lg border cursor-pointer transition-colors ${
                          checkedItems.has(idx)
                            ? 'bg-green-50 border-green-200'
                            : 'bg-neutral-50 border-neutral-200 hover:border-neutral-300'
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={checkedItems.has(idx)}
                          onChange={() => toggleCheck(idx)}
                          className="mt-0.5 h-4 w-4 rounded border-neutral-300 text-green-600 focus:ring-green-500"
                        />
                        <span className={`text-sm ${checkedItems.has(idx) ? 'text-green-700' : 'text-neutral-600'}`}>
                          {item}
                        </span>
                      </label>
                    ))}
                  </div>
                </div>
              )}

              {/* Comments */}
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">
                  <MessageSquare className="h-3.5 w-3.5 inline mr-1" />
                  Review Comments {requireComments && <span className="text-red-500">*</span>}
                </label>
                <textarea
                  value={comments}
                  onChange={e => setComments(e.target.value)}
                  rows={3}
                  placeholder="Add your review notes, observations, or conditions for approval..."
                  className="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                {requireComments && !comments.trim() && (
                  <p className="text-xs text-amber-600 mt-1">Comments are required for this approval step.</p>
                )}
              </div>
            </div>

            {/* Footer */}
            <div className="p-4 border-t border-neutral-100 flex gap-3">
              <button
                onClick={handleClose}
                disabled={isLoading}
                className="flex-1 py-2.5 border border-neutral-200 text-neutral-700 font-medium rounded-lg hover:bg-neutral-50 transition-colors text-sm"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirm}
                disabled={!canConfirm}
                className="flex-1 py-2.5 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoading ? 'Processing...' : confirmLabel}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

/**
 * ApprovalTrail -- displays the history of approvals with comments.
 * Use on detail pages to show who approved/reviewed and what they said.
 */
export function ApprovalTrail({ logs }: { logs: Array<{
  action: string
  actor_name?: string
  comments?: string | null
  created_at: string
}> }) {
  if (!logs || logs.length === 0) return null

  return (
    <div className="space-y-3">
      <h3 className="text-sm font-semibold text-neutral-700">Approval Trail</h3>
      <div className="space-y-2">
        {logs.map((log, idx) => (
          <div key={idx} className="flex gap-3 text-sm">
            <div className="relative">
              <div className="w-7 h-7 bg-green-100 rounded-full flex items-center justify-center">
                <CheckCircle className="w-3.5 h-3.5 text-green-600" />
              </div>
              {idx < logs.length - 1 && (
                <div className="absolute top-7 left-1/2 -translate-x-1/2 w-px h-full bg-neutral-200" />
              )}
            </div>
            <div className="flex-1 pb-3">
              <p className="text-neutral-900 font-medium capitalize">
                {log.action.replace(/_/g, ' ')}
              </p>
              <p className="text-xs text-neutral-500">
                {log.actor_name ?? 'System'} -- {new Date(log.created_at).toLocaleString('en-PH', {
                  month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
                })}
              </p>
              {log.comments && (
                <p className="text-xs text-neutral-600 mt-1 bg-neutral-50 p-2 rounded border border-neutral-100">
                  {log.comments}
                </p>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
