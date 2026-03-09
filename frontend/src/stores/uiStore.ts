import { create } from 'zustand'

// ---------------------------------------------------------------------------
// Modal registry
// ---------------------------------------------------------------------------

/** Identifier for the currently active modal, or null when none is open. */
export type ModalId = string | null

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
}

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

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
}))
