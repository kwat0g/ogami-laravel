import * as React from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'

export type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement>

/**
 * Minimalist select component
 */
const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ className, children, ...props }, ref) => {
    return (
      <div className="relative">
        <select
          className={cn(
            'flex h-10 w-full appearance-none rounded-md border border-neutral-300 bg-white px-3 py-2 pr-8 text-sm',
            'focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:border-neutral-400',
            'disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-neutral-50',
            'transition-colors duration-150',
            className
          )}
          ref={ref}
          {...props}
        >
          {children}
        </select>
        <ChevronDown className="absolute right-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-neutral-400 pointer-events-none" />
      </div>
    )
  }
)
Select.displayName = 'Select'

export { Select }
