import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'
import App from './App'
import './index.css'
// Force SkeletonLoader to be included in main chunk
import SkeletonLoader from '@/components/ui/SkeletonLoader'
// @ts-expect-error - used to force include in bundle
console.log(SkeletonLoader)

// Remove legacy localStorage keys left over from the token-based auth flow.
// Auth is now session-cookie based — no sensitive data is stored in localStorage.
;['auth_token', 'ogami-auth'].forEach((k) => localStorage.removeItem(k))

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
    },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
      <Toaster position="top-right" richColors closeButton />
    </QueryClientProvider>
  </StrictMode>,
)
