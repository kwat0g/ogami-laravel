/**
 * Interactive map for picking a work location with a draggable pin
 * and a visual geofence radius circle.
 *
 * Uses Leaflet + OpenStreetMap tiles (free, no API key needed).
 * The admin drags the pin to set the location; the blue circle
 * shows the geofence boundary in real-time as the radius changes.
 */
import { useEffect, useRef, useMemo } from 'react'
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

  return (
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
  )
}
