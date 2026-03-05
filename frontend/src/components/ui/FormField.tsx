import type { ReactNode } from 'react'

interface FormFieldProps {
  label: string
  required?: boolean
  error?: string
  children: ReactNode
  hint?: string
  htmlFor?: string
}

/**
 * Minimalist form field wrapper
 */
export default function FormField({
  label,
  required,
  error,
  children,
  hint,
  htmlFor,
}: FormFieldProps) {
  return (
    <div className="space-y-1.5">
      <label
        {...(htmlFor ? { htmlFor } : {})}
        className="block text-sm font-medium text-neutral-700"
      >
        {label}
        {required && <span className="ml-0.5 text-neutral-900">*</span>}
      </label>

      {children}

      {error ? (
        <p className="text-xs text-red-600">{error}</p>
      ) : hint ? (
        <p className="text-xs text-neutral-400">{hint}</p>
      ) : null}
    </div>
  )
}
