import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'

interface BackButtonProps {
  /** Explicit path to navigate to. If omitted, navigates to browser history back. */
  to?: string
  /** Button label — defaults to "Back" */
  label?: string
  /** Additional CSS classes */
  className?: string
}

/**
 * Consistent back-navigation button used on detail and form pages.
 * Renders an ArrowLeft icon with optional label.
 */
export default function BackButton({ to, label, className = '' }: BackButtonProps) {
  const navigate = useNavigate()

  return (
    <button
      type="button"
      onClick={() => (to ? navigate(to) : navigate(-1))}
      aria-label={label || 'Go back'}
      className={`inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200 transition-colors ${className}`}
    >
      <ArrowLeft className="w-4 h-4" />
      {label && <span>{label}</span>}
    </button>
  )
}
