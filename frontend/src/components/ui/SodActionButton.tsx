import React from 'react'
import { useSodCheck } from '@/hooks/useSodCheck'

interface SodActionButtonProps {
  /** The ID of the user who initiated the record being acted on. */
  initiatedById: number | null
  /** Button label text. */
  label: string
  /** Click handler — not called when SoD-blocked or loading. */
  onClick: () => void
  /** Whether the action is currently in-flight (e.g. mutation isPending). */
  isLoading?: boolean
  /** Additional disabled condition beyond SoD (e.g. wrong status). */
  disabled?: boolean
  /** Tailwind variant class string. Defaults to primary blue styling. */
  variant?: 'primary' | 'danger' | 'warning' | 'ghost'
  className?: string
}

const variantClasses: Record<NonNullable<SodActionButtonProps['variant']>, string> = {
  primary:
    'bg-blue-600 text-white hover:bg-blue-700 disabled:bg-blue-300 focus:ring-blue-500',
  danger:
    'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300 focus:ring-red-500',
  warning:
    'bg-amber-500 text-white hover:bg-amber-600 disabled:bg-amber-300 focus:ring-amber-400',
  ghost:
    'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 disabled:opacity-50 focus:ring-gray-400',
}

/**
 * Action button that enforces record-level Segregation of Duties.
 *
 * When the current user is the initiator of the record (`initiatedById`),
 * the button is rendered as disabled and a tooltip explains the SoD constraint.
 * Admins and managers are exempt.
 *
 * Usage:
 * ```tsx
 * <SodActionButton
 *   initiatedById={run.initiated_by_id}
 *   label="Approve"
 *   onClick={() => approve.mutate(run.id)}
 *   isLoading={approve.isPending}
 *   variant="primary"
 * />
 * ```
 */
export function SodActionButton({
  initiatedById,
  label,
  onClick,
  isLoading = false,
  disabled = false,
  variant = 'primary',
  className = '',
}: SodActionButtonProps) {
  const { isBlocked, reason } = useSodCheck(initiatedById)

  const isDisabled = isBlocked || disabled || isLoading
  const title = isBlocked
    ? (reason ?? 'Action blocked by Segregation of Duties policy')
    : isLoading
      ? 'Processing…'
      : undefined

  return (
    <div className="relative inline-block" title={title}>
      <button
        type="button"
        onClick={isDisabled ? undefined : onClick}
        disabled={isDisabled}
        aria-disabled={isDisabled}
        className={[
          'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
          'focus:outline-none focus:ring-2 focus:ring-offset-1',
          'disabled:cursor-not-allowed',
          variantClasses[variant],
          isBlocked ? 'opacity-60 cursor-not-allowed' : '',
          className,
        ]
          .filter(Boolean)
          .join(' ')}
      >
        {isLoading ? (
          <span className="flex items-center gap-2">
            <svg
              className="animate-spin h-4 w-4"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"
              />
            </svg>
            {label}
          </span>
        ) : isBlocked ? (
          <span className="flex items-center gap-1.5">
            <svg
              className="h-4 w-4"
              viewBox="0 0 20 20"
              fill="currentColor"
              aria-hidden
            >
              <path
                fillRule="evenodd"
                d="M10 1a9 9 0 100 18A9 9 0 0010 1zm0 2a7 7 0 110 14A7 7 0 0110 3zm0 3a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1zm0 7a1 1 0 100 2 1 1 0 000-2z"
                clipRule="evenodd"
              />
            </svg>
            {label} (SoD)
          </span>
        ) : (
          label
        )}
      </button>
    </div>
  )
}

export default SodActionButton
