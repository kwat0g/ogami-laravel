/**
 * Interactive map for picking a work location with a draggable pin
 * and a visual geofence radius circle.
 *
 * Uses Leaflet + OpenStreetMap tiles (free, no API key needed).
 * The admin drags the pin to set the location; the blue circle
 * shows the geofence boundary in real-time as the radius changes.
 */
import { useEffect, useRef, useMemo, useState, useCallback } from 'react'
import { MapContainer, TileLayer, Marker, Circle, useMap, useMapEvents } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix Leaflet default marker icon (broken in bundlers)
// eslint-disable-next-line @typescript-eslint/no-explicit-any
delete (L.Icon.Default.prototype as any)._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
})

interface LocationPickerMapProps {
  latitude: number | null
  longitude: number | null
  radiusMeters: number
  onLocationChange: (lat: number, lon: number) => void
  height?: string
}

/** Recenter map when coordinates change from outside (e.g., "Use My Location" button) */
function MapRecenter({ lat, lon }: { lat: number; lon: number }) {
  const map = useMap()
  const prevRef = useRef({ lat: 0, lon: 0 })

  useEffect(() => {
    if (lat !== prevRef.current.lat || lon !== prevRef.current.lon) {
      map.setView([lat, lon], map.getZoom() < 15 ? 16 : map.getZoom())
      prevRef.current = { lat, lon }
    }
  }, [lat, lon, map])

  return null
}

/** Handle map clicks to move the marker */
function MapClickHandler({ onLocationChange }: { onLocationChange: (lat: number, lon: number) => void }) {
  useMapEvents({
    click(e) {
      onLocationChange(e.latlng.lat, e.latlng.lng)
    },
  })
  return null
}

export default function LocationPickerMap({
  latitude,
  longitude,
  radiusMeters,
  onLocationChange,
  height = '350px',
}: LocationPickerMapProps) {
  // Default to Metro Manila if no coordinates yet
  const center = useMemo<[number, number]>(
    () => [latitude ?? 14.5995, longitude ?? 120.9842],
    [latitude, longitude],
  )

  const hasPin = latitude !== null && longitude !== null

  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState<Array<{ display_name: string; lat: string; lon: string }>>([])
  const [searching, setSearching] = useState(false)
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout>>()

  const handleSearch = useCallback((query: string) => {
    setSearchQuery(query)
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current)
    if (query.length < 3) { setSearchResults([]); return }

    searchTimeoutRef.current = setTimeout(async () => {
      setSearching(true)
      try {
        const res = await fetch(
          `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=5&countrycodes=ph`
        )
        const data = await res.json()
        setSearchResults(data ?? [])
      } catch { setSearchResults([]) }
      finally { setSearching(false) }
    }, 400) // debounce 400ms
  }, [])

  const selectSearchResult = useCallback((result: { lat: string; lon: string; display_name: string }) => {
    const lat = parseFloat(result.lat)
    const lon = parseFloat(result.lon)
    onLocationChange(lat, lon)
    setSearchQuery(result.display_name.substring(0, 60))
    setSearchResults([])
  }, [onLocationChange])

  return (
    <div className="space-y-2">
      {/* Search bar */}
      <div className="relative">
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => handleSearch(e.target.value)}
          className="w-full text-sm border border-neutral-300 dark:border-neutral-600 rounded px-3 py-2 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 focus:outline-none focus:ring-1 focus:ring-blue-400 pr-8"
          placeholder="Search for a place or address..."
        />
        {searching && (
          <div className="absolute right-2.5 top-2.5">
            <div className="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
          </div>
        )}
        {searchResults.length > 0 && (
          <div className="absolute z-[1000] mt-1 w-full bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-600 rounded-lg shadow-lg max-h-48 overflow-y-auto">
            {searchResults.map((r, i) => (
              <button
                key={i}
                type="button"
                onClick={() => selectSearchResult(r)}
                className="w-full text-left px-3 py-2 text-sm text-neutral-700 dark:text-neutral-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 border-b border-neutral-100 dark:border-neutral-700 last:border-0 truncate"
              >
                {r.display_name}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Map */}
      <div className="rounded-lg overflow-hidden border border-neutral-200 dark:border-neutral-700" style={{ height }}>
      <MapContainer
        center={center}
        zoom={hasPin ? 16 : 12}
        style={{ height: '100%', width: '100%' }}
        scrollWheelZoom={true}
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />

        <MapClickHandler onLocationChange={onLocationChange} />

        {hasPin && (
          <>
            <MapRecenter lat={latitude!} lon={longitude!} />
            <Marker
              position={[latitude!, longitude!]}
              draggable={true}
              eventHandlers={{
                dragend: (e) => {
                  const marker = e.target as L.Marker
                  const pos = marker.getLatLng()
                  onLocationChange(pos.lat, pos.lng)
                },
              }}
            />
            <Circle
              center={[latitude!, longitude!]}
              radius={radiusMeters}
              pathOptions={{
                color: '#3b82f6',
                fillColor: '#3b82f6',
                fillOpacity: 0.12,
                weight: 2,
              }}
            />
          </>
        )}
      </MapContainer>
      </div>
    </div>
  )
}
