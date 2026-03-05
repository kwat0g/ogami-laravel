/**
 * Laravel Echo singleton — Reverb (WebSocket) client.
 *
 * Lazily initialised on first call to getEcho() so the WS connection is only
 * opened once the user is actually authenticated and the app is mounted.
 *
 * Reverb uses the Pusher wire protocol, so we use pusher-js as the transport
 * and configure Echo with `broadcaster: 'reverb'`.
 *
 * Connection settings are read from a <meta name="reverb-config"> tag injected
 * by SpaController at request-time, so the same built bundle works on every
 * environment without a rebuild. Falls back to VITE_REVERB_* env vars for the
 * local dev-server (where SpaController is not involved).
 */
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// laravel-echo requires Pusher to be on window when using the pusher broadcaster
;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

interface ReverbConfig {
  key: string
  wsHost: string
  wsPort: number
  wssPort: number
  scheme: string
}

declare global {
  interface Window {
    __REVERB_CONFIG__?: ReverbConfig
  }
}

let _instance: Echo<'reverb'> | null = null

/** Read config injected server-side via <meta name="reverb-config"> */
function getRuntimeConfig(): ReverbConfig | null {
  try {
    const meta = document.querySelector('meta[name="reverb-config"]')
    if (!meta) return null
    return JSON.parse(meta.getAttribute('content') ?? '') as ReverbConfig
  } catch {
    return null
  }
}

/**
 * Returns (and lazily creates) the global Echo instance.
 * Returns null if Reverb is not configured (missing key).
 * Call disconnectEcho() on logout to tear down the WS connection.
 */
export function getEcho(): Echo<'reverb'> | null {
  if (_instance) return _instance

  // Runtime config from <meta name="reverb-config"> (injected by SpaController)
  // takes priority over baked-in env vars, so the same bundle works on any environment.
  const rt = getRuntimeConfig()
  const appKey = rt?.key || (import.meta.env.VITE_REVERB_APP_KEY as string | undefined)

  if (!appKey) {
    // Reverb not configured — real-time events are unavailable, fall back to polling
    return null
  }

  const wsHost   = rt?.wsHost  ?? (import.meta.env.VITE_REVERB_HOST as string)
  const wsPort   = rt?.wsPort  ?? Number(import.meta.env.VITE_REVERB_PORT  ?? 80)
  const wssPort  = rt?.wssPort ?? Number(import.meta.env.VITE_REVERB_PORT  ?? 443)
  const scheme   = rt?.scheme  ?? (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http'

  _instance = new Echo({
    broadcaster: 'reverb',
    key: appKey,
    wsHost,
    wsPort,
    wssPort,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    // Session-cookie auth — Reverb uses the same /broadcasting/auth endpoint
  })

  return _instance
}

/** Disconnect and destroy the Echo instance (call on logout). */
export function disconnectEcho(): void {
  _instance?.disconnect()
  _instance = null
}
