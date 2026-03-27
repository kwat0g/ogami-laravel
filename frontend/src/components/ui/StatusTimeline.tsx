/**
 * StatusTimeline - Reusable workflow stepper component
 *
 * Shows the progression of an entity through its state machine.
 * Completed steps show green checkmarks, the current step pulses blue,
 * and future steps are greyed out.
 *
 * Usage:
 *   <StatusTimeline
 *     steps={[
 *       { label: 'Draft', status: 'draft' },
 *       { label: 'Submitted', status: 'submitted', actor: 'John Doe', timestamp: '2026-01-15' },
 *       { label: 'Approved', status: 'approved' },
 *     ]}
 *     currentStatus="submitted"
 *   />
 */
import { CheckCircle2 } from 'lucide-react'

export interface TimelineStep {
  /** Display label for this step */
  label: string
  /** The status value this step represents */
  status: string
  /** Who performed this action (shown below label when completed) */
  actor?: string | null
  /** When this step was completed (ISO date string or formatted) */
  timestamp?: string | null
  /** Optional comment/note */
  comment?: string | null
}

interface StatusTimelineProps {
  /** Ordered list of steps in the workflow */
  steps: TimelineStep[]
  /** Current status of the entity */
  currentStatus: string
  /** Vertical or horizontal layout */
  direction?: 'vertical' | 'horizontal'
  /** If true, treat currentStatus as a terminal/rejected state (show red) */
  isRejected?: boolean
}

function formatTimestamp(ts: string): string {
  try {
    const d = new Date(ts)
    if (isNaN(d.getTime())) return ts
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return ts
  }
}

export function StatusTimeline({ steps, currentStatus, direction = 'vertical', isRejected = false }: StatusTimelineProps): JSX.Element {
  const currentIdx = steps.findIndex(s => s.status === currentStatus)

  if (direction === 'horizontal') {
    return (
      <div className="flex items-center gap-0 overflow-x-auto py-2">
        {steps.map((step, idx) => {
          const isDone = idx < currentIdx || (idx === currentIdx && !isRejected)
          const isCurrent = idx === currentIdx
          const isLast = idx === steps.length - 1

          return (
            <div key={step.status} className="flex items-center">
              <div className="flex flex-col items-center min-w-[80px]">
                <div
                  className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium transition-all ${
                    isDone && !isCurrent
                      ? 'bg-green-100 text-green-700'
                      : isCurrent && isRejected
                        ? 'bg-red-100 text-red-700 ring-2 ring-red-400'
                        : isCurrent
                          ? 'bg-blue-100 text-blue-700 ring-2 ring-blue-400 animate-pulse'
                          : 'bg-neutral-100 text-neutral-400'
                  }`}
                >
                  {isDone && !isCurrent ? <CheckCircle2 className="w-4 h-4" /> : idx + 1}
                </div>
                <span className={`text-[10px] mt-1 text-center leading-tight ${
                  isCurrent ? 'font-semibold text-neutral-900' : isDone ? 'text-neutral-600' : 'text-neutral-400'
                }`}>
                  {step.label}
                </span>
              </div>
              {!isLast && (
                <div className={`w-8 h-0.5 -mt-4 ${idx < currentIdx ? 'bg-green-300' : 'bg-neutral-200'}`} />
              )}
            </div>
          )
        })}
      </div>
    )
  }

  // Vertical layout (default)
  return (
    <div className="space-y-0">
      {steps.map((step, idx) => {
        const isDone = idx < currentIdx || (idx === currentIdx && !isRejected)
        const isCurrent = idx === currentIdx
        const isLast = idx === steps.length - 1

        return (
          <div key={step.status} className="flex items-start gap-3">
            {/* Node + connector line */}
            <div className="flex flex-col items-center">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium shrink-0 transition-all ${
                  isDone && !isCurrent
                    ? 'bg-green-100 text-green-700'
                    : isCurrent && isRejected
                      ? 'bg-red-100 text-red-700 ring-2 ring-red-400'
                      : isCurrent
                        ? 'bg-blue-100 text-blue-700 ring-2 ring-blue-400'
                        : 'bg-neutral-100 text-neutral-400'
                }`}
              >
                {isDone && !isCurrent ? <CheckCircle2 className="w-4 h-4" /> : idx + 1}
              </div>
              {!isLast && (
                <div className={`w-0.5 h-8 ${idx < currentIdx ? 'bg-green-200' : 'bg-neutral-200'}`} />
              )}
            </div>

            {/* Content */}
            <div className={`pb-4 ${isLast ? 'pb-0' : ''}`}>
              <p className={`text-sm font-medium ${
                isCurrent ? 'text-neutral-900' : isDone ? 'text-neutral-700' : 'text-neutral-400'
              }`}>
                {step.label}
              </p>
              {isDone && step.actor && (
                <p className="text-xs text-neutral-500 mt-0.5">
                  by {step.actor}
                  {step.timestamp && <span className="ml-1 text-neutral-400">- {formatTimestamp(step.timestamp)}</span>}
                </p>
              )}
              {isDone && step.comment && (
                <p className="text-xs text-neutral-500 mt-0.5 italic">"{step.comment}"</p>
              )}
            </div>
          </div>
        )
      })}
    </div>
  )
}

export default StatusTimeline
