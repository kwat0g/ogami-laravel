import * as React from 'react'
import { cn } from '@/lib/utils'

export type TextareaProps = React.TextareaHTMLAttributes<HTMLTextAreaElement>

/**
 * Minimalist textarea component
 */
const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, ...props }, ref) => {
    return (
      <textarea
        className={cn(
          'flex min-h-[100px] w-full rounded border border-neutral-300 bg-white px-4 py-3 text-base',
          'placeholder:text-neutral-400',
          'focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400',
          'disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-neutral-50',
          'resize-y',
          'transition-colors duration-150',
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Textarea.displayName = 'Textarea'

export { Textarea }
