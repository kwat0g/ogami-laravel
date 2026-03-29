/**
 * Work Locations Management Page
 *
 * Admin page to create, edit, and manage geofence work locations.
 * Each location has GPS coordinates and a radius defining the area
 * employees must be within to clock in.
 */
import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import { MapPin, Plus, Edit3, Trash2, Check, X, Loader2, Shield, ShieldOff, Crosshair, Navigation } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog'
import { firstErrorMessage } from '@/lib/errorHandler'
import { useWorkLocations, useGeofenceSettings, useToggleGeofence, type WorkLocation } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'
import api from '@/lib/api'
import { useQueryClient } from '@tanstack/react-query'

export default function WorkLocationsPage() {
  const { hasPermission } = useAuthStore()
  const canManage = hasPermission('attendance.work_locations.manage')
  const { data: locationsData, isLoading, refetch } = useWorkLocations()
  const { data: geofenceSettings } = useGeofenceSettings()
  const toggleGeofence = useToggleGeofence()
  const queryClient = useQueryClient()

  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<WorkLocation | null>(null)
  const [saving, setSaving] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<WorkLocation | null>(null)
  const [gettingLocation, setGettingLocation] = useState(false)

  const useMyLocation = useCallback(() => {
    if (!navigator.geolocation) {
      toast.error('Geolocation is not supported by this browser.')
      return
    }
    setGettingLocation(true)
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords.latitude.toFixed(7)
        const lon = pos.coords.longitude.toFixed(7)
        setForm((prev) => ({ ...prev, latitude: lat, longitude: lon }))
        setGettingLocation(false)
        toast.success('Location captured!')
        // Auto-fetch address via free OpenStreetMap reverse geocoding
        fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&zoom=18`)
          .then((r) => r.json())
          .then((data) => {
            if (data?.display_name) {
              setForm((prev) => ({ ...prev, address: data.display_name.substring(0, 200) }))
            }
          })
          .catch(() => { /* silently ignore geocoding failures */ })
      },
      () => {
        setGettingLocation(false)
        toast.error('Could not get your location. Please enter coordinates manually.')
      },
      { enableHighAccuracy: true, timeout: 10000 },
    )
  }, [])

  // Form state
  const [form, setForm] = useState({
    name: '',
    code: '',
    address: '',
    city: '',
    latitude: '',
    longitude: '',
    radius_meters: '100',
    allowed_variance_meters: '20',
    is_remote_allowed: false,
  })

  const resetForm = () => {
    setForm({
      name: '', code: '', address: '', city: '',
      latitude: '', longitude: '', radius_meters: '100',
      allowed_variance_meters: '20', is_remote_allowed: false,
    })
    setEditing(null)
    setShowForm(false)
  }

  const openCreate = () => {
    resetForm()
    setShowForm(true)
  }

  const openEdit = (loc: WorkLocation) => {
    setForm({
      name: loc.name,
      code: loc.code,
      address: loc.address,
      city: loc.city ?? '',
      latitude: String(loc.latitude),
      longitude: String(loc.longitude),
      radius_meters: String(loc.radius_meters),
      allowed_variance_meters: String(loc.allowed_variance_meters),
      is_remote_allowed: loc.is_remote_allowed,
    })
    setEditing(loc)
    setShowForm(true)
  }

  const handleSave = async () => {
    if (!form.name || !form.latitude || !form.longitude) {
      toast.error('Please enter a name and pin a location.')
      return
    }

    // Auto-generate code from name if empty
    const autoCode = form.code || form.name.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 20) || 'LOC'

    setSaving(true)
    try {
      const payload = {
        name: form.name,
        code: autoCode,
        address: form.address || 'Not specified',
        city: form.city || null,
        latitude: parseFloat(form.latitude),
        longitude: parseFloat(form.longitude),
        radius_meters: parseInt(form.radius_meters, 10),
        allowed_variance_meters: parseInt(form.allowed_variance_meters, 10),
        is_remote_allowed: form.is_remote_allowed,
      }

      if (editing) {
        await api.patch(`/attendance/work-locations/${editing.id}`, payload)
        toast.success('Work location updated.')
      } else {
        await api.post('/attendance/work-locations', payload)
        toast.success('Work location created.')
      }
      resetForm()
      void refetch()
    } catch (err) {
      toast.error(firstErrorMessage(err) || 'Failed to save work location.')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    try {
      await api.delete(`/attendance/work-locations/${deleteTarget.id}`)
      toast.success('Work location deleted.')
      setDeleteTarget(null)
      void refetch()
    } catch (err) {
      toast.error(firstErrorMessage(err) || 'Failed to delete work location.')
    }
  }

  const handleToggleGeofence = async () => {
    const newState = !geofenceSettings?.enabled
    try {
      await toggleGeofence.mutateAsync(newState)
      toast.success(newState ? 'Geofence enforcement enabled.' : 'Geofence enforcement disabled.')
    } catch {
      toast.error('Failed to toggle geofence.')
    }
  }

  const locations = locationsData?.data ?? []

  return (
    <div>
      <PageHeader
        title="Work Locations"
        subtitle="Manage geofence locations for employee clock-in/out"
        actions={canManage ? (
          <div className="flex items-center gap-3">
            {/* Geofence toggle */}
            <button
              onClick={handleToggleGeofence}
              disabled={toggleGeofence.isPending}
              className={`flex items-center gap-2 px-3 py-2 text-sm font-medium rounded transition-colors ${
                geofenceSettings?.enabled
                  ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100'
                  : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-100'
              }`}
            >
              {geofenceSettings?.enabled ? <Shield className="w-4 h-4" /> : <ShieldOff className="w-4 h-4" />}
              {geofenceSettings?.enabled ? 'Geofence: ON' : 'Geofence: OFF'}
            </button>

            <button
              onClick={openCreate}
              className="flex items-center gap-2 px-4 py-2 bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 text-sm font-medium rounded hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors"
            >
              <Plus className="w-4 h-4" /> Add Location
            </button>
          </div>
        ) : undefined}
      />

      {/* Geofence mode info */}
      {geofenceSettings && (
        <div className="mb-4 flex items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
          <span>Mode: <span className="font-medium text-neutral-700 dark:text-neutral-300">{geofenceSettings.mode}</span></span>
          {geofenceSettings.mode === 'strict' && <span>Employees must be within geofence to clock in.</span>}
          {geofenceSettings.mode === 'override' && <span>Employees outside geofence can clock in with a reason (flagged for review).</span>}
          {geofenceSettings.mode === 'disabled' && <span>Geofence checks are currently disabled.</span>}
        </div>
      )}

      {/* Create/Edit Form */}
      {showForm && (
        <Card className="mb-6">
          <div className="p-5 space-y-5">
            <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300">
              {editing ? 'Edit Work Location' : 'New Work Location'}
            </h3>

            {/* Step 1: Location name */}
            <div>
              <label className="block text-xs font-medium text-neutral-600 dark:text-neutral-400 mb-1">Location Name *</label>
              <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="w-full max-w-md text-sm border border-neutral-300 dark:border-neutral-600 rounded px-3 py-2.5 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-400"
                placeholder="e.g. Main Office, Factory Building A, Warehouse" />
            </div>

            {/* Step 2: Pin the location */}
            <div className="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg p-4 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-sm font-semibold text-blue-900 dark:text-blue-300 flex items-center gap-1.5">
                    <Navigation className="w-4 h-4" /> Pin Your Location
                  </h4>
                  <p className="text-xs text-blue-700 dark:text-blue-400 mt-0.5">Go to the location and click the button, or paste coordinates from Google Maps</p>
                </div>
                <button
                  type="button"
                  onClick={useMyLocation}
                  disabled={gettingLocation}
                  className="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white rounded-lg transition-colors shadow-sm"
                >
                  {gettingLocation ? <Loader2 className="w-4 h-4 animate-spin" /> : <Crosshair className="w-4 h-4" />}
                  Use My Current Location
                </button>
              </div>
              {form.latitude && form.longitude && (
                <div className="flex items-center gap-3 pt-2 border-t border-blue-200 dark:border-blue-800">
                  <MapPin className="w-4 h-4 text-blue-600" />
                  <span className="text-sm font-mono text-blue-800 dark:text-blue-300">
                    {parseFloat(form.latitude).toFixed(6)}, {parseFloat(form.longitude).toFixed(6)}
                  </span>
                  {form.address && <span className="text-xs text-blue-600 dark:text-blue-400">({form.address})</span>}
                </div>
              )}
              {!form.latitude && !form.longitude && (
                <p className="text-xs text-blue-600 dark:text-blue-400 italic">No location pinned yet. Click the button above or enter coordinates below.</p>
              )}
              <details className="text-xs">
                <summary className="text-blue-600 dark:text-blue-400 cursor-pointer hover:underline">Enter coordinates manually</summary>
                <div className="grid grid-cols-2 gap-3 mt-2">
                  <input type="number" step="0.0000001" value={form.latitude} onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                    className="text-sm border border-blue-200 dark:border-blue-700 rounded px-3 py-2 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 font-mono focus:outline-none focus:ring-1 focus:ring-blue-400"
                    placeholder="Latitude (e.g. 14.5547)" />
                  <input type="number" step="0.0000001" value={form.longitude} onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                    className="text-sm border border-blue-200 dark:border-blue-700 rounded px-3 py-2 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 font-mono focus:outline-none focus:ring-1 focus:ring-blue-400"
                    placeholder="Longitude (e.g. 121.0244)" />
                </div>
              </details>
            </div>

            {/* Step 3: How far can employees be? */}
            <div>
              <label className="block text-xs font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                How far can employees be from this location to clock in?
              </label>
              <div className="flex items-center gap-4">
                <input type="range" min={25} max={500} step={25} value={form.radius_meters}
                  onChange={(e) => setForm({ ...form, radius_meters: e.target.value })}
                  className="flex-1 h-2 bg-neutral-200 dark:bg-neutral-700 rounded-lg appearance-none cursor-pointer accent-blue-600" />
                <span className="text-lg font-bold text-neutral-900 dark:text-neutral-100 min-w-[60px] text-right">{form.radius_meters}m</span>
              </div>
              <div className="flex justify-between text-[10px] text-neutral-400 mt-1 px-0.5">
                <span>25m (office)</span>
                <span>100m (building)</span>
                <span>250m (campus)</span>
                <span>500m (factory)</span>
              </div>
            </div>

            {/* Optional: Remote work */}
            <div className="flex items-center gap-2">
              <input type="checkbox" id="remote-allowed" checked={form.is_remote_allowed}
                onChange={(e) => setForm({ ...form, is_remote_allowed: e.target.checked })}
                className="rounded border-neutral-300 dark:border-neutral-600" />
              <label htmlFor="remote-allowed" className="text-sm text-neutral-700 dark:text-neutral-300">This is a remote/flexible location (employees can clock in from anywhere)</label>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-3 pt-3 border-t border-neutral-200 dark:border-neutral-700">
              <button onClick={handleSave} disabled={saving}
                className="flex items-center gap-2 px-5 py-2.5 bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 text-sm font-medium rounded hover:bg-neutral-800 dark:hover:bg-neutral-200 transition-colors disabled:opacity-50">
                {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
                {editing ? 'Update Location' : 'Create Location'}
              </button>
              <button onClick={resetForm}
                className="flex items-center gap-2 px-4 py-2.5 border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 text-sm font-medium rounded hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                <X className="w-4 h-4" /> Cancel
              </button>
            </div>
          </div>
        </Card>
      )}

      {/* Locations Table */}
      {isLoading ? <SkeletonLoader rows={5} /> : (
        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700">
              <tr>
                {['Name', 'Code', 'Coordinates', 'Radius', 'Variance', 'Remote', 'Status', 'Actions'].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600 dark:text-neutral-400">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {locations.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-3 py-8 text-center text-neutral-400 dark:text-neutral-500">
                    <MapPin className="w-8 h-8 mx-auto mb-2 opacity-30" />
                    No work locations configured yet. Click "Add Location" to create one.
                  </td>
                </tr>
              )}
              {locations.map((loc) => (
                <tr key={loc.id} className="even:bg-neutral-50/50 dark:even:bg-neutral-800/30 hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                  <td className="px-3 py-2.5 font-medium text-neutral-800 dark:text-neutral-200">{loc.name}</td>
                  <td className="px-3 py-2.5 text-neutral-600 dark:text-neutral-400 font-mono text-xs">{loc.code}</td>
                  <td className="px-3 py-2.5 text-neutral-500 dark:text-neutral-400 text-xs font-mono">
                    {loc.latitude.toFixed(4)}, {loc.longitude.toFixed(4)}
                  </td>
                  <td className="px-3 py-2.5 text-neutral-600 dark:text-neutral-400">{loc.radius_meters}m</td>
                  <td className="px-3 py-2.5 text-neutral-600 dark:text-neutral-400">+{loc.allowed_variance_meters}m</td>
                  <td className="px-3 py-2.5">
                    {loc.is_remote_allowed
                      ? <StatusBadge status="approved">yes</StatusBadge>
                      : <span className="text-neutral-400">\u2014</span>}
                  </td>
                  <td className="px-3 py-2.5">
                    <StatusBadge status={loc.is_active ? 'active' : 'inactive'}>
                      {loc.is_active ? 'active' : 'inactive'}
                    </StatusBadge>
                  </td>
                  <td className="px-3 py-2.5">
                    {canManage && (
                      <div className="flex items-center gap-1">
                        <button onClick={() => openEdit(loc)} title="Edit"
                          className="p-1.5 rounded hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-500 dark:text-neutral-400">
                          <Edit3 className="w-3.5 h-3.5" />
                        </button>
                        <button onClick={() => setDeleteTarget(loc)} title="Delete"
                          className="p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-neutral-400 hover:text-red-600">
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Delete confirmation dialog */}
      <ConfirmDestructiveDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Delete Work Location"
        description={`Are you sure you want to delete "${deleteTarget?.name}"? Employees assigned to this location will lose their assignment.`}
        confirmLabel="Delete"
      />

      {/* Setup instructions */}
      <div className="mt-6 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-400">
        <h4 className="font-semibold mb-2">Setup Guide</h4>
        <ol className="list-decimal list-inside space-y-1 text-xs">
          <li>Create a work location with GPS coordinates (use Google Maps to find lat/lon).</li>
          <li>Set the radius in meters -- this defines how far from the pin employees can be to clock in.</li>
          <li>Assign employees to this location via HR &gt; Employees &gt; Work Location Assignment.</li>
          <li>The geofence mode (strict/override/disabled) is controlled in System Settings &gt; Attendance.</li>
          <li>Use the "Geofence: ON/OFF" toggle above for emergency bypass (e.g., GPS outage).</li>
        </ol>
      </div>
    </div>
  )
}
