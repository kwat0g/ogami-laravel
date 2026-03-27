import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useAutoSave, getDraftTimestamp } from './useAutoSave'

const STORAGE_PREFIX = 'ogami_draft_'

describe('useAutoSave', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('detects when no draft exists', () => {
    const { result } = renderHook(() =>
      useAutoSave('test-key', { name: 'test' })
    )
    expect(result.current.hasDraft).toBe(false)
  })

  it('detects existing draft on mount', () => {
    localStorage.setItem(`${STORAGE_PREFIX}test-key`, JSON.stringify({ name: 'saved' }))

    const { result } = renderHook(() =>
      useAutoSave('test-key', { name: 'test' })
    )
    expect(result.current.hasDraft).toBe(true)
  })

  it('restores saved data', () => {
    const savedData = { name: 'saved', value: 42 }
    localStorage.setItem(`${STORAGE_PREFIX}test-key`, JSON.stringify(savedData))

    const { result } = renderHook(() =>
      useAutoSave('test-key', { name: 'current' })
    )

    const restored = result.current.restore()
    expect(restored).toEqual(savedData)
  })

  it('returns null when restoring with no saved data', () => {
    const { result } = renderHook(() =>
      useAutoSave('test-key', { name: 'test' })
    )
    expect(result.current.restore()).toBeNull()
  })

  it('clears the draft', () => {
    localStorage.setItem(`${STORAGE_PREFIX}test-key`, JSON.stringify({ name: 'saved' }))
    localStorage.setItem(`${STORAGE_PREFIX}test-key_ts`, '2026-01-01T00:00:00Z')

    const { result } = renderHook(() =>
      useAutoSave('test-key', { name: 'test' })
    )

    act(() => {
      result.current.clear()
    })

    expect(localStorage.getItem(`${STORAGE_PREFIX}test-key`)).toBeNull()
    expect(localStorage.getItem(`${STORAGE_PREFIX}test-key_ts`)).toBeNull()
    expect(result.current.hasDraft).toBe(false)
  })

  it('saves manually via saveNow', () => {
    const { result } = renderHook(() =>
      useAutoSave('test-key', { name: 'manual-save' })
    )

    act(() => {
      result.current.saveNow()
    })

    const saved = JSON.parse(localStorage.getItem(`${STORAGE_PREFIX}test-key`)!)
    expect(saved.name).toBe('manual-save')
    expect(result.current.hasDraft).toBe(true)
  })

  it('does not auto-save when disabled', () => {
    renderHook(() =>
      useAutoSave('test-key', { name: 'test' }, undefined, { enabled: false })
    )

    // Advance timers past the interval
    vi.advanceTimersByTime(60_000)

    expect(localStorage.getItem(`${STORAGE_PREFIX}test-key`)).toBeNull()
  })
})

describe('getDraftTimestamp', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('returns null when no timestamp', () => {
    expect(getDraftTimestamp('nonexistent')).toBeNull()
  })

  it('returns the saved timestamp', () => {
    const ts = '2026-01-15T10:00:00Z'
    localStorage.setItem(`${STORAGE_PREFIX}test-key_ts`, ts)
    expect(getDraftTimestamp('test-key')).toBe(ts)
  })
})
