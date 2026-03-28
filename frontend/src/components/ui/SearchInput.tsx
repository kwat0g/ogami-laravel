import { useRef, useEffect } from 'react'
import { Search, X } from 'lucide-react'
import { useDebounce } from '@/hooks/useDebounce'

interface SearchInputProps {
  /** Current raw (un-debounced) value — controlled externally */
  value: string
  /** Called on every keystroke */
  onChange: (value: string) => void
  /** Called with the debounced value after delay */
  onSearch: (debouncedValue: string) => void
  /** Debounce delay in ms (default: 400) */
  delay?: number
  /** Placeholder text */
  placeholder?: string
  /** Additional CSS classes for the wrapper */
  className?: string
  /** Auto-focus the input on mount */
  autoFocus?: boolean
}

/**
 * Reusable search input with built-in debounce.
 * Manages its own debounce timer and calls `onSearch` only after the delay.
 */
export default function SearchInput({
  value,
  onChange,
  onSearch,
  delay = 400,
  placeholder = 'Search...',
  className = '',
  autoFocus = false,
}: SearchInputProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const debouncedValue = useDebounce(value, delay)

  useEffect(() => {
    onSearch(debouncedValue)
  }, [debouncedValue, onSearch])

  useEffect(() => {
    if (autoFocus && inputRef.current) {
      inputRef.current.focus()
    }
  }, [autoFocus])

  return (
    <div className={`relative ${className}`}>
      <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
      <input
        ref={inputRef}
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full pl-9 pr-8 py-2 text-sm border border-neutral-300 dark:border-neutral-600 rounded-md bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:ring-1 focus:ring-neutral-400 focus:outline-none"
      />
      {value && (
        <button
          type="button"
          onClick={() => onChange('')}
          className="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-400 hover:text-neutral-600"
        >
          <X className="w-3.5 h-3.5" />
        </button>
      )}
    </div>
  )
}
