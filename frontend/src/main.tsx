import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'
import { useUiStore } from '@/stores/uiStore'
import App from './App'
import './index.css'
import './dark-overrides.css'
// Force SkeletonLoader to be included in main chunk
import SkeletonLoader from '@/components/ui/SkeletonLoader'
// @ts-expect-error - used to force include in bundle
console.log(SkeletonLoader)

// Remove legacy localStorage keys left over from the token-based auth flow.
// Auth is now session-cookie based — no sensitive data is stored in localStorage.
;['auth_token', 'ogami-auth'].forEach((k) => localStorage.removeItem(k))
// Handle Vite preload errors (e.g. failed dynamic imports after deployment)
window.addEventListener('vite:preloadError', (event) => {
  // Prevent the default error logging
  if (event.preventDefault) {
    event.preventDefault()
  }
  // Reload the page so the browser fetches the new index.html and correct chunk paths
  window.location.reload()
})

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Retry only on network/5xx errors — not on 4xx (auth, validation)
      retry: (failureCount, error) => {
        const apiErr = error as { error_code?: string }
        const networkErr = error as Error
        if (networkErr.message?.startsWith('Network')) return failureCount < 2
        if (apiErr.error_code) return false   // structured API errors: don't retry
        return failureCount < 1
      },
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
      // Stop polling while the browser tab is in the background — prevents
      // wasting server requests when the user isn't actively looking at the app.
      refetchIntervalInBackground: false,
    },
    mutations: {
      // Surface mutation errors above — mutations never auto-retry
      retry: false,
      // C10 FIX: Global mutation error handler — shows toast for ALL mutations
      // that don't have a specific onError. Individual hooks can still override.
      onError: (error: unknown) => {
        // Skip errors already handled by the API interceptor (429, 5xx, cooldown)
        const err = error as Record<string, unknown>
        if (err?.__handled === true || err?.__cooldown === true) return

        // Extract meaningful message from the error
        const message =
          (err?.message as string) ??
          (err?.error_code as string) ??
          'Operation failed. Please try again.'

        // Dynamic import to avoid circular deps
        void import('sonner').then(({ toast }) => {
          toast.error(message, { duration: 5000 })
        })
      },
    },
  },
})

/** Toaster that reacts to the uiStore's resolved theme. */
function ThemeAwareToaster() {
  const resolvedTheme = useUiStore((s) => s.resolvedTheme)
  return <Toaster position="top-right" richColors closeButton theme={resolvedTheme} />
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
      <ThemeAwareToaster />
    </QueryClientProvider>
  </StrictMode>,
)
