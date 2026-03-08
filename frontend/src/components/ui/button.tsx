import * as React from 'react'
import { cn } from '@/lib/utils'

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive'
  size?: 'default' | 'sm' | 'lg' | 'icon'
}

/**
 * Minimalist button component
 */
const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'default', size = 'default', ...props }, ref) => {
    return (
      <button
        className={cn(
          'inline-flex items-center justify-center rounded-md text-sm font-medium transition-all duration-150',
          'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400',
          'disabled:pointer-events-none disabled:opacity-50',
          {
            // Primary - dark neutral
            'bg-neutral-900 text-white hover:bg-neutral-800 active:bg-neutral-950': variant === 'default',
            // Secondary - light gray
            'bg-neutral-100 text-neutral-900 hover:bg-neutral-200 active:bg-neutral-300': variant === 'secondary',
            // Outline - subtle border
            'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 hover:border-neutral-400': variant === 'outline',
            // Ghost - subtle border for visibility
            'border border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 hover:border-neutral-300 hover:text-neutral-900': variant === 'ghost',
            // Destructive - minimal red
            'bg-red-600 text-white hover:bg-red-700 active:bg-red-800': variant === 'destructive',
          },
          {
            'h-10 px-5 py-2': size === 'default',
            'h-9 px-4 text-sm': size === 'sm',
            'h-12 px-8 text-base': size === 'lg',
            'h-10 w-10': size === 'icon',
          },
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Button.displayName = 'Button'

export { Button }
