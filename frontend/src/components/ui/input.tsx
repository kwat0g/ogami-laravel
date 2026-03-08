import * as React from 'react'
import { cn } from '@/lib/utils'

export type InputProps = React.InputHTMLAttributes<HTMLInputElement>

/**
 * Minimalist input component
 */
const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type, ...props }, ref) => {
    return (
      <input
        type={type}
        className={cn(
          'flex h-11 w-full rounded border border-neutral-300 bg-white px-4 py-2 text-base',
          'placeholder:text-neutral-400',
          'focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400',
          'disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-neutral-50',
          'transition-colors duration-150',
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Input.displayName = 'Input'

export { Input }
