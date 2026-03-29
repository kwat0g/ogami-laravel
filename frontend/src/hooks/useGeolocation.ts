import { useState, useCallback, useEffect } from 'react'

export type GeolocationStatus = 'idle' | 'requesting' | 'granted' | 'denied' | 'unavailable' | 'timeout'

export interface GeolocationState {
  latitude: number | null
  longitude: number | null
  accuracy: number | null
  status: GeolocationStatus
  error: string | null
}

export function useGeolocation(autoRequest = true) {
  const [geo, setGeo] = useState<GeolocationState>({
    latitude: null,
    longitude: null,
    accuracy: null,
    status: 'idle',
    error: null,
  })

  const requestLocation = useCallback(() => {
    if (!navigator.geolocation) {
      setGeo((prev) => ({
        ...prev,
        status: 'unavailable',
        error: 'Geolocation is not supported by this browser.',
      }))
      return
    }

    setGeo((prev) => ({ ...prev, status: 'requesting', error: null }))

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setGeo({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          status: 'granted',
          error: null,
        })
      },
      (error) => {
        const messages: Record<number, string> = {
          1: 'Location permission denied. Please allow location access in your browser settings.',
          2: 'Location unavailable. Please check your device GPS.',
          3: 'Location request timed out. Please try again.',
        }
        setGeo((prev) => ({
          ...prev,
          status: error.code === 1 ? 'denied' : error.code === 3 ? 'timeout' : 'unavailable',
          error: messages[error.code] ?? 'Unknown location error.',
        }))
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 30000,
      },
    )
  }, [])

  useEffect(() => {
    if (autoRequest) {
      requestLocation()
    }
  }, [autoRequest, requestLocation])

  return { ...geo, requestLocation }
}
