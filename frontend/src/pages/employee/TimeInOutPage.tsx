import { useState, useMemo, useEffect } from 'react'
import { Clock, MapPin, AlertTriangle, CheckCircle2, XCircle, Loader2, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'
import { useGeolocation } from '@/hooks/useGeolocation'
import { useAttendanceToday, useTimeIn, useTimeOut } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'

const STATUS_COLORS: Record<string, string> = {
  present: 'bg-green-100 text-green-800',
  late: 'bg-yellow-100 text-yellow-800',
  undertime: 'bg-orange-100 text-orange-800',
  late_and_undertime: 'bg-amber-100 text-amber-800',
  absent: 'bg-red-100 text-red-800',
  on_leave: 'bg-blue-100 text-blue-800',
  holiday: 'bg-purple-100 text-purple-800',
  rest_day: 'bg-gray-100 text-gray-600',
  out_of_office: 'bg-pink-100 text-pink-800',
  pending: 'bg-sky-100 text-sky-800',
  corrected: 'bg-indigo-100 text-indigo-800',
}

function formatTime(isoOrTime: string | null | undefined): string {
  if (!isoOrTime) return '--:--'
  try {
    // Handle both ISO timestamps and HH:MM:SS
    if (isoOrTime.includes('T') || isoOrTime.includes(' ')) {
      return new Date(isoOrTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }
    return isoOrTime.substring(0, 5)
  } catch {
    return isoOrTime
  }
}

export default function TimeInOutPage() {
  const { user } = useAuthStore()
  const geo = useGeolocation()
  const { data: todayLog, isLoading: todayLoading } = useAttendanceToday()
  const timeInMutation = useTimeIn()
  const timeOutMutation = useTimeOut()

  const [overrideReason, setOverrideReason] = useState('')
  const [liveDuration, setLiveDuration] = useState('')

  // Live duration counter
  useEffect(() => {
    if (!todayLog?.time_in || todayLog?.time_out) {
      setLiveDuration('')
      return
    }

    const update = () => {
      const start = new Date(todayLog.time_in!).getTime()
      const diff = Date.now() - start
      const hours = Math.floor(diff / 3600000)
      const mins = Math.floor((diff % 3600000) / 60000)
      setLiveDuration(`${hours}h ${mins}m`)
    }

    update()
    const interval = setInterval(update, 30000)
    return () => clearInterval(interval)
  }, [todayLog?.time_in, todayLog?.time_out])

  const hasTimedIn = !!todayLog?.time_in
  const hasTimedOut = !!todayLog?.time_out
  const isOutsideGeofence = todayLog?.time_in_within_geofence === false

  const deviceInfo = useMemo(() => ({
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    timestamp: new Date().toISOString(),
  }), [])

  const handleTimeIn = async () => {
    if (!geo.latitude || !geo.longitude) {
      toast.error('Location not available. Please enable GPS and try again.')
      return
    }

    try {
      await timeInMutation.mutateAsync({
        latitude: geo.latitude,
        longitude: geo.longitude,
        accuracy_meters: geo.accuracy ?? 0,
        device_info: deviceInfo,
        override_reason: overrideReason || undefined,
      })
      toast.success('Timed in successfully!')
      setOverrideReason('')
    } catch (err) {
      const error = err as { response?: { data?: { error?: { code?: string; message?: string } } } }
      const code = error.response?.data?.error?.code
      const message = error.response?.data?.error?.message

      if (code === 'OUTSIDE_GEOFENCE') {
        toast.error(message || 'You are outside the geofence. Please provide a reason.')
      } else {
        toast.error(message || 'Failed to time in.')
      }
    }
  }

  const handleTimeOut = async () => {
    if (!geo.latitude || !geo.longitude) {
      toast.error('Location not available. Please enable GPS and try again.')
      return
    }

    try {
      await timeOutMutation.mutateAsync({
        latitude: geo.latitude,
        longitude: geo.longitude,
        accuracy_meters: geo.accuracy ?? 0,
        device_info: deviceInfo,
      })
      toast.success('Timed out successfully!')
    } catch (err) {
      const error = err as { response?: { data?: { error?: { message?: string } } } }
      toast.error(error.response?.data?.error?.message || 'Failed to time out.')
    }
  }

  const today = new Date()
  const dateStr = today.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })

  return (
    <div className="max-w-lg mx-auto px-4 py-6 space-y-6">
      {/* Header */}
      <div className="text-center">
        <p className="text-sm text-neutral-500">{dateStr}</p>
        <h1 className="text-xl font-bold text-neutral-900 mt-1">Time Clock</h1>
        {user?.employee && (
          <p className="text-sm text-neutral-600 mt-1">
            {user.name}
          </p>
        )}
      </div>

      {/* Location Status */}
      <div className="bg-white rounded-lg border border-neutral-200 p-4 space-y-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <MapPin className="w-4 h-4 text-neutral-400" />
            <span className="text-sm font-medium text-neutral-700">Location</span>
          </div>
          <button
            onClick={geo.requestLocation}
            className="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1"
          >
            <RefreshCw className="w-3 h-3" />
            Refresh
          </button>
        </div>

        {geo.status === 'requesting' && (
          <div className="flex items-center gap-2 text-sm text-blue-600">
            <Loader2 className="w-4 h-4 animate-spin" />
            Getting your location...
          </div>
        )}

        {geo.status === 'granted' && (
          <div className="text-sm text-green-700">
            <div className="flex items-center gap-1">
              <CheckCircle2 className="w-4 h-4" />
              Location acquired
            </div>
            <p className="text-xs text-neutral-500 mt-1">
              Accuracy: +/-{Math.round(geo.accuracy ?? 0)}m
            </p>
          </div>
        )}

        {(geo.status === 'denied' || geo.status === 'unavailable' || geo.status === 'timeout') && (
          <div className="flex items-center gap-2 text-sm text-red-600">
            <XCircle className="w-4 h-4" />
            {geo.error}
          </div>
        )}

        {/* Geofence info from today's log */}
        {todayLog?.work_location && (
          <div className="text-xs text-neutral-500 border-t pt-2 mt-2">
            Work Location: {todayLog.work_location.name}
            {todayLog.time_in_distance_meters != null && (
              <span className="ml-2">({todayLog.time_in_distance_meters}m away)</span>
            )}
          </div>
        )}
      </div>

      {/* Today's Log */}
      <div className="bg-white rounded-lg border border-neutral-200 p-4">
        <h2 className="text-sm font-medium text-neutral-700 mb-3 flex items-center gap-2">
          <Clock className="w-4 h-4" />
          Today&apos;s Log
        </h2>

        {todayLoading ? (
          <div className="text-center py-4">
            <Loader2 className="w-5 h-5 animate-spin mx-auto text-neutral-400" />
          </div>
        ) : (
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-sm text-neutral-500">Time In</span>
              <span className="text-sm font-medium">
                {formatTime(todayLog?.time_in)}
                {todayLog?.time_in_within_geofence === true && (
                  <CheckCircle2 className="w-3.5 h-3.5 inline ml-1 text-green-500" />
                )}
                {todayLog?.time_in_within_geofence === false && (
                  <AlertTriangle className="w-3.5 h-3.5 inline ml-1 text-amber-500" />
                )}
              </span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-neutral-500">Time Out</span>
              <span className="text-sm font-medium">{formatTime(todayLog?.time_out)}</span>
            </div>
            {liveDuration && (
              <div className="flex justify-between items-center">
                <span className="text-sm text-neutral-500">Duration</span>
                <span className="text-sm font-semibold text-blue-600">{liveDuration}</span>
              </div>
            )}
            {todayLog?.attendance_status && (
              <div className="flex justify-between items-center pt-1">
                <span className="text-sm text-neutral-500">Status</span>
                <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_COLORS[todayLog.attendance_status] ?? 'bg-gray-100 text-gray-700'}`}>
                  {todayLog.attendance_status.replace(/_/g, ' ')}
                </span>
              </div>
            )}
            {todayLog?.worked_hours != null && hasTimedOut && (
              <div className="flex justify-between items-center">
                <span className="text-sm text-neutral-500">Worked</span>
                <span className="text-sm font-medium">{todayLog.worked_hours}h</span>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Outside Geofence Warning / Override */}
      {isOutsideGeofence && !hasTimedOut && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <div className="flex items-start gap-2">
            <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-amber-800">Outside geofence</p>
              <p className="text-xs text-amber-700 mt-1">
                You timed in from outside your assigned work location.
                This has been flagged for HR review.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Override Reason Input (shown when location is outside geofence before time-in) */}
      {!hasTimedIn && geo.status === 'granted' && (
        <div className="space-y-2">
          <label className="block text-xs font-medium text-neutral-600">
            Override reason (required if outside geofence)
          </label>
          <textarea
            value={overrideReason}
            onChange={(e) => setOverrideReason(e.target.value)}
            className="w-full text-sm border border-neutral-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            rows={2}
            placeholder="Optional: explain if working from a different location..."
          />
        </div>
      )}

      {/* Action Buttons */}
      <div className="space-y-3">
        {!hasTimedIn && (
          <button
            onClick={handleTimeIn}
            disabled={timeInMutation.isPending || geo.status !== 'granted'}
            className="w-full py-4 bg-green-600 hover:bg-green-700 disabled:bg-green-300 disabled:cursor-not-allowed text-white text-lg font-bold rounded-xl transition-colors flex items-center justify-center gap-2"
          >
            {timeInMutation.isPending ? (
              <Loader2 className="w-5 h-5 animate-spin" />
            ) : (
              <Clock className="w-5 h-5" />
            )}
            TIME IN
          </button>
        )}

        {hasTimedIn && !hasTimedOut && (
          <button
            onClick={handleTimeOut}
            disabled={timeOutMutation.isPending || geo.status !== 'granted'}
            className="w-full py-4 bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed text-white text-lg font-bold rounded-xl transition-colors flex items-center justify-center gap-2"
          >
            {timeOutMutation.isPending ? (
              <Loader2 className="w-5 h-5 animate-spin" />
            ) : (
              <Clock className="w-5 h-5" />
            )}
            TIME OUT
          </button>
        )}

        {hasTimedOut && (
          <div className="text-center py-4">
            <CheckCircle2 className="w-8 h-8 text-green-500 mx-auto" />
            <p className="text-sm font-medium text-green-700 mt-2">You have completed today&apos;s attendance.</p>
            {todayLog?.worked_hours != null && (
              <p className="text-xs text-neutral-500 mt-1">
                Total: {todayLog.worked_hours}h | Late: {todayLog.late_minutes ?? 0}min | UT: {todayLog.undertime_minutes ?? 0}min
              </p>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
