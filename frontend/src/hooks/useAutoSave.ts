/**
 * useAutoSave - Auto-save form drafts to localStorage
 *
 * Persists form state every `interval` milliseconds and restores it
 * when the component mounts. Call `clear()` on successful submit.
 *
 * Usage:
 *   const { hasDraft, restore, clear } = useAutoSave('pr-create', formData, setFormData)
 *
 *   // On mount, show "Restore draft?" prompt if hasDraft is true
 *   // On successful submit, call clear()
 */
import { useEffect, useRef, useCallback, useState } from 'react'

interface UseAutoSaveOptions {
  /** Auto-save interval in ms (default: 30000 = 30 seconds) */
  interval?: number
  /** Whether auto-save is enabled (default: true) */
  enabled?: boolean
}

interface UseAutoSaveReturn<T> {
  /** Whether a saved draft exists in localStorage */
  hasDraft: boolean
  /** Restore the saved draft (call this to apply saved data) */
  restore: () => T | null
  /** Clear the saved draft (call on successful submit) */
  clear: () => void
  /** Manually save now */
  saveNow: () => void
}

const STORAGE_PREFIX = 'ogami_draft_'

export function useAutoSave<T>(
  key: string,
  currentData: T,
  _onRestore?: (data: T) => void,
  options: UseAutoSaveOptions = {},
): UseAutoSaveReturn<T> {
  const { interval = 30_000, enabled = true } = options
  const storageKey = `${STORAGE_PREFIX}${key}`
  const dataRef = useRef(currentData)
  dataRef.current = currentData

  const [hasDraft, setHasDraft] = useState(() => {
    try {
      return localStorage.getItem(storageKey) !== null
    } catch {
      return false
    }
  })
  const [pendingDraftResolution, setPendingDraftResolution] = useState(hasDraft)

  // Auto-save on interval
  useEffect(() => {
    if (!enabled || pendingDraftResolution) return

    const timer = setInterval(() => {
      try {
        const serialized = JSON.stringify(dataRef.current)
        localStorage.setItem(storageKey, serialized)
        localStorage.setItem(`${storageKey}_ts`, new Date().toISOString())
      } catch {
        // localStorage full or private browsing -- silently fail
      }
    }, interval)

    return () => clearInterval(timer)
  }, [storageKey, interval, enabled, pendingDraftResolution])

  // Save on page unload
  useEffect(() => {
    if (!enabled || pendingDraftResolution) return

    const handleBeforeUnload = () => {
      try {
        localStorage.setItem(storageKey, JSON.stringify(dataRef.current))
        localStorage.setItem(`${storageKey}_ts`, new Date().toISOString())
      } catch {
        // ignore
      }
    }

    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [storageKey, enabled, pendingDraftResolution])

  const restore = useCallback((): T | null => {
    try {
      const raw = localStorage.getItem(storageKey)
      if (!raw) return null
      const parsed = JSON.parse(raw) as T
      // Consume existing draft so subsequent autosave starts fresh from restored state.
      localStorage.removeItem(storageKey)
      localStorage.removeItem(`${storageKey}_ts`)
      setHasDraft(false)
      setPendingDraftResolution(false)
      return parsed
    } catch {
      return null
    }
  }, [storageKey])

  const clear = useCallback(() => {
    try {
      localStorage.removeItem(storageKey)
      localStorage.removeItem(`${storageKey}_ts`)
      setHasDraft(false)
      setPendingDraftResolution(false)
    } catch {
      // ignore
    }
  }, [storageKey])

  const saveNow = useCallback(() => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(dataRef.current))
      localStorage.setItem(`${storageKey}_ts`, new Date().toISOString())
      setHasDraft(true)
      setPendingDraftResolution(false)
    } catch {
      // ignore
    }
  }, [storageKey])

  return { hasDraft, restore, clear, saveNow }
}

/**
 * DraftRestorationBanner - Inline component to show restore prompt
 *
 * Usage:
 *   {hasDraft && <DraftRestorationBanner onRestore={handleRestore} onDiscard={clear} />}
 */
export function getDraftTimestamp(key: string): string | null {
  try {
    return localStorage.getItem(`${STORAGE_PREFIX}${key}_ts`)
  } catch {
    return null
  }
}
