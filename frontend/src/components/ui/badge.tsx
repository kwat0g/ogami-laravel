import * as React from 'react'
import { cn } from '@/lib/utils'

interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'default' | 'secondary' | 'success' | 'destructive' | 'outline'
}

function Badge({ className, variant = 'default', ...props }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded px-2 py-0.5 text-xs font-medium',
        {
          'bg-neutral-100 text-neutral-700': variant === 'default',
          'bg-neutral-100 text-neutral-600': variant === 'secondary',
          'bg-green-100 text-green-700': variant === 'success',
          'bg-red-100 text-red-700': variant === 'destructive',
          'border border-neutral-300 text-neutral-700': variant === 'outline',
        },
        className
      )}
      {...props}
    />
  )
}

export { Badge }
