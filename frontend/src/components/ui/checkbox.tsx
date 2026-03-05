import * as React from 'react'
import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

export type CheckboxProps = React.InputHTMLAttributes<HTMLInputElement>

/**
 * Minimalist checkbox component
 */
const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, ...props }, ref) => {
    return (
      <div className="relative flex items-center">
        <input
          type="checkbox"
          ref={ref}
          className={cn(
            'peer h-4 w-4 shrink-0 rounded border border-neutral-300 bg-white',
            'focus:outline-none focus:ring-1 focus:ring-neutral-400 focus:ring-offset-1',
            'disabled:cursor-not-allowed disabled:opacity-50',
            'checked:bg-neutral-900 checked:border-neutral-900',
            'appearance-none transition-colors duration-150',
            className
          )}
          {...props}
        />
        <Check className="absolute left-0 top-1/2 -translate-y-1/2 h-4 w-4 text-white opacity-0 peer-checked:opacity-100 pointer-events-none stroke-[3]" />
      </div>
    )
  }
)
Checkbox.displayName = 'Checkbox'

export { Checkbox }
