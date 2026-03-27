import { CheckCircle2, XCircle, Clock, User } from 'lucide-react'

/**
 * A single step in an approval workflow.
 */
export interface ApprovalStep {
  /** Display label (e.g. "Department Head Approval") */
  label: string
  /** Status: 'completed' | 'current' | 'rejected' | 'pending' */
  state: 'completed' | 'current' | 'rejected' | 'pending'
  /** Who performed the action */
  actor?: string | null
  /** When the action was performed (ISO date or formatted string) */
  timestamp?: string | null
  /** Optional remarks / comments */
  remarks?: string | null
}

interface ApprovalTimelineProps {
  /** Ordered list of approval steps */
  steps: ApprovalStep[]
  /** Optional className for the outer container */
  className?: string
}

function formatDate(dateStr: string): string {
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return dateStr
    return d.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return dateStr
  }
}

const STATE_CONFIG = {
  completed: {
    dot: 'bg-green-500',
    icon: CheckCircle2,
    iconColor: 'text-green-600 dark:text-green-400',
    labelColor: 'text-neutral-900 dark:text-neutral-100',
    line: 'bg-green-300 dark:bg-green-700',
  },
  current: {
    dot: 'bg-blue-500 animate-pulse',
    icon: Clock,
    iconColor: 'text-blue-600 dark:text-blue-400',
    labelColor: 'text-blue-700 dark:text-blue-300 font-semibold',
    line: 'bg-neutral-200 dark:bg-neutral-700',
  },
  rejected: {
    dot: 'bg-red-500',
    icon: XCircle,
    iconColor: 'text-red-600 dark:text-red-400',
    labelColor: 'text-red-700 dark:text-red-300 font-semibold',
    line: 'bg-neutral-200 dark:bg-neutral-700',
  },
  pending: {
    dot: 'bg-neutral-300 dark:bg-neutral-600',
    icon: Clock,
    iconColor: 'text-neutral-400 dark:text-neutral-500',
    labelColor: 'text-neutral-400 dark:text-neutral-500',
    line: 'bg-neutral-200 dark:bg-neutral-700',
  },
}

/**
 * ApprovalTimeline -- Renders a vertical timeline of approval workflow steps.
 *
 * Designed for use in detail pages for Leave, Purchase Requests, POs, etc.
 * Supports dark mode out of the box.
 *
 * Usage:
 * ```tsx
 * <ApprovalTimeline
 *   steps={[
 *     { label: 'Submitted', state: 'completed', actor: 'Juan dela Cruz', timestamp: '2026-01-15' },
 *     { label: 'Dept Head Approval', state: 'completed', actor: 'Maria Santos', timestamp: '2026-01-16', remarks: 'Approved, no issues.' },
 *     { label: 'Plant Manager Check', state: 'current' },
 *     { label: 'GA Officer Process', state: 'pending' },
 *     { label: 'VP Note', state: 'pending' },
 *   ]}
 * />
 * ```
 */
export function ApprovalTimeline({ steps, className = '' }: ApprovalTimelineProps) {
  return (
    <div className={`space-y-0 ${className}`}>
      {steps.map((step, idx) => {
        const config = STATE_CONFIG[step.state]
        const Icon = config.icon
        const isLast = idx === steps.length - 1

        return (
          <div key={`${step.label}-${idx}`} className="flex gap-3">
            {/* Left column: dot + line */}
            <div className="flex flex-col items-center">
              <div className={`w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 ${
                step.state === 'completed' ? 'bg-green-100 dark:bg-green-900/40' :
                step.state === 'rejected' ? 'bg-red-100 dark:bg-red-900/40' :
                step.state === 'current' ? 'bg-blue-100 dark:bg-blue-900/40' :
                'bg-neutral-100 dark:bg-neutral-800'
              }`}>
                <Icon className={`h-4 w-4 ${config.iconColor}`} />
              </div>
              {!isLast && (
                <div className={`w-0.5 flex-1 min-h-[24px] ${config.line}`} />
              )}
            </div>

            {/* Right column: content */}
            <div className={`pb-4 ${isLast ? 'pb-0' : ''}`}>
              <p className={`text-sm ${config.labelColor}`}>{step.label}</p>

              {step.actor && (
                <div className="flex items-center gap-1 mt-0.5">
                  <User className="h-3 w-3 text-neutral-400 dark:text-neutral-500" />
                  <span className="text-xs text-neutral-600 dark:text-neutral-400">{step.actor}</span>
                </div>
              )}

              {step.timestamp && (
                <p className="text-xs text-neutral-500 dark:text-neutral-500 mt-0.5">
                  {formatDate(step.timestamp)}
                </p>
              )}

              {step.remarks && (
                <div className="mt-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded p-2">
                  <p className="text-xs text-neutral-600 dark:text-neutral-400 italic">
                    &ldquo;{step.remarks}&rdquo;
                  </p>
                </div>
              )}
            </div>
          </div>
        )
      })}
    </div>
  )
}

export default ApprovalTimeline
