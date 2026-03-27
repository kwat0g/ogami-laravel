import { create } from 'zustand'

// ---------------------------------------------------------------------------
// Modal registry
// ---------------------------------------------------------------------------

/** Identifier for the currently active modal, or null when none is open. */
export type ModalId = string | null

// ---------------------------------------------------------------------------
// Dark mode helpers
// ---------------------------------------------------------------------------

export type ColorMode = 'light' | 'dark' | 'system'

/** Read stored preference from localStorage, falling back to 'system'. */
function getStoredColorMode(): ColorMode {
  try {
    const stored = localStorage.getItem('ogami-color-mode')
    if (stored === 'light' || stored === 'dark' || stored === 'system') return stored
  } catch {
    // localStorage unavailable (SSR, incognito with strict settings)
  }
  return 'system'
}

/** Resolve the effective theme (light/dark) from the preference. */
function resolveTheme(mode: ColorMode): 'light' | 'dark' {
  if (mode === 'system') {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
  return mode
}

/** Apply or remove the `dark` class on <html> and persist preference. */
function applyColorMode(mode: ColorMode): void {
  const resolved = resolveTheme(mode)
  const root = document.documentElement
  if (resolved === 'dark') {
    root.classList.add('dark')
  } else {
    root.classList.remove('dark')
  }
  try {
    localStorage.setItem('ogami-color-mode', mode)
  } catch {
    // noop
  }
}

// ---------------------------------------------------------------------------
// State shape
// ---------------------------------------------------------------------------

interface UiState {
  /** Whether the main navigation sidebar is expanded. */
  sidebarOpen: boolean

  /** Toggle or explicitly set the sidebar. */
  setSidebarOpen: (open: boolean) => void
  toggleSidebar: () => void

  /** Currently open modal identifier. One modal at a time. */
  activeModal: ModalId

  /** Open a modal by its string identifier. */
  openModal: (id: string) => void

  /** Close whatever modal is currently open. */
  closeModal: () => void

  /** Returns true if the given modal is currently open. */
  isModalOpen: (id: string) => boolean

  /** Set to true when a system DB restore is in progress. Shows a blocking overlay for all users. */
  systemRestoreInProgress: boolean
  setSystemRestore: (v: boolean) => void

  /** Color mode preference: 'light', 'dark', or 'system' (follows OS). */
  colorMode: ColorMode

  /** The resolved theme after applying system preference. */
  resolvedTheme: 'light' | 'dark'

  /** Set the color mode preference. Applies immediately. */
  setColorMode: (mode: ColorMode) => void

  /** Convenience: cycle light -> dark -> system -> light. */
  toggleColorMode: () => void

  /** Returns true if the resolved theme is dark. */
  isDark: () => boolean
}

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

const initialColorMode = getStoredColorMode()

// Apply on initial load so the class is present before React hydrates
applyColorMode(initialColorMode)

export const useUiStore = create<UiState>()((set, get) => ({
  sidebarOpen: true,

  setSidebarOpen: (open) => set({ sidebarOpen: open }),
  toggleSidebar:  ()     => set((s) => ({ sidebarOpen: !s.sidebarOpen })),

  activeModal: null,

  openModal:   (id) => set({ activeModal: id }),
  closeModal:  ()   => set({ activeModal: null }),
  isModalOpen: (id) => get().activeModal === id,

  systemRestoreInProgress: false,
  setSystemRestore: (v) => set({ systemRestoreInProgress: v }),

  colorMode: initialColorMode,
  resolvedTheme: resolveTheme(initialColorMode),

  setColorMode: (mode) => {
    applyColorMode(mode)
    set({ colorMode: mode, resolvedTheme: resolveTheme(mode) })
  },

  toggleColorMode: () => {
    const current = get().colorMode
    const next: ColorMode = current === 'light' ? 'dark' : current === 'dark' ? 'system' : 'light'
    get().setColorMode(next)
  },

  isDark: () => get().resolvedTheme === 'dark',
}))

// ---------------------------------------------------------------------------
// Listen for OS-level preference changes when mode is 'system'
// ---------------------------------------------------------------------------
if (typeof window !== 'undefined') {
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const { colorMode, setColorMode } = useUiStore.getState()
    if (colorMode === 'system') {
      // Re-apply so the resolved theme updates
      setColorMode('system')
    }
  })
}
