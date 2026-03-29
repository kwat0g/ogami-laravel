/**
 * Standardized Button Component
 * 
 * Ensures consistent button styling across the entire application.
 * Use this component instead of raw <button> elements.
 */
import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Visual style variant */
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  /** Button size */
  size?: 'sm' | 'md' | 'lg'
  /** Show loading spinner */
  isLoading?: boolean
  /** Full width button */
  fullWidth?: boolean
}

const variantStyles = {
  primary: 'bg-neutral-900 text-white hover:bg-neutral-800 disabled:bg-neutral-400',
  secondary: 'bg-white text-neutral-700 border border-neutral-300 hover:bg-neutral-50 disabled:bg-neutral-100',
  danger: 'bg-white text-red-600 border border-red-300 hover:bg-red-50 disabled:bg-neutral-100',
  ghost: 'bg-transparent text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 disabled:text-neutral-400',
}

const sizeStyles = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
  lg: 'px-6 py-2.5 text-base',
}

export function Button({
  children,
  variant = 'primary',
  size = 'md',
  isLoading = false,
  fullWidth = false,
  disabled,
  className,
  ...props
}: ButtonProps) {
  return (
    <button
      disabled={disabled || isLoading}
      className={cn(
        // Base styles
        'inline-flex items-center justify-center gap-2 font-medium rounded transition-colors',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        'focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-1',
        // Variant styles
        variantStyles[variant],
        // Size styles
        sizeStyles[size],
        // Full width
        fullWidth && 'w-full',
        className
      )}
      {...props}
    >
      {isLoading && <Loader2 className="h-4 w-4 animate-spin" />}
      {children}
    </button>
  )
}

/** Icon-only button for toolbar actions */
export function IconButton({
  children,
  _variant = 'ghost',
  size = 'md',
  className,
  ...props
}: Omit<ButtonProps, 'isLoading' | 'fullWidth'> & { children: React.ReactNode }) {
  const iconSizeStyles = {
    sm: 'p-1',
    md: 'p-1.5',
    lg: 'p-2',
  }

  return (
    <button
      className={cn(
        'inline-flex items-center justify-center rounded transition-colors',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        'hover:bg-neutral-100 text-neutral-600 hover:text-neutral-900',
        iconSizeStyles[size],
        className
      )}
      {...props}
    >
      {children}
    </button>
  )
}

export default Button
