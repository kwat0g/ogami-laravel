import { useUiStore, type ColorMode } from '@/stores/uiStore'
import { Sun, Moon } from 'lucide-react'

const MODES: { value: ColorMode; icon: typeof Sun; label: string }[] = [
  { value: 'light',  icon: Sun,     label: 'Light' },
  { value: 'dark',   icon: Moon,    label: 'Dark' },
]

/**
 * Compact color-mode toggle.  Renders three icon buttons (light / dark / system)
 * with the active one highlighted.  Designed for the sidebar footer or header bar.
 */
export function ColorModeToggle({ className = '' }: { className?: string }) {
  const colorMode = useUiStore((s) => s.colorMode)
  const setColorMode = useUiStore((s) => s.setColorMode)

  return (
    <div className={`inline-flex items-center gap-0.5 rounded-lg bg-neutral-100 dark:bg-neutral-800 p-0.5 ${className}`}>
      {MODES.map(({ value, icon: Icon, label }) => {
        const active = colorMode === value
        return (
          <button
            key={value}
            onClick={() => setColorMode(value)}
            title={label}
            className={`
              p-1.5 rounded-md transition-colors
              ${active
                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 shadow-sm'
                : 'text-neutral-400 dark:text-neutral-500 hover:text-neutral-600 dark:hover:text-neutral-300'
              }
            `}
          >
            <Icon className="h-4 w-4" />
            <span className="sr-only">{label}</span>
          </button>
        )
      })}
    </div>
  )
}

/**
 * Single-button toggle that cycles: light -> dark -> system -> light.
 * Shows the current active mode's icon.
 */
export function ColorModeButton({ className = '' }: { className?: string }) {
  const colorMode = useUiStore((s) => s.colorMode)
  const toggleColorMode = useUiStore((s) => s.toggleColorMode)

  const Icon = colorMode === 'dark' ? Moon : Sun
  const label = colorMode === 'dark' ? 'Dark mode' : 'Light mode'

  return (
    <button
      onClick={toggleColorMode}
      title={label}
      className={`p-2 rounded-md text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors ${className}`}
    >
      <Icon className="h-5 w-5" />
      <span className="sr-only">{label}</span>
    </button>
  )
}
