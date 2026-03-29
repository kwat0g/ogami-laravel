/**
 * Time Clock Widget — embeddable in any dashboard or page.
 *
 * Shows today's attendance status with Time In / Time Out buttons.
 * Handles all validation via backend error codes:
 *  - NO_SHIFT_ASSIGNED → toast "No shift assigned for today"
 *  - ALREADY_TIMED_IN  → toast "Already timed in today"
 *  - ALREADY_TIMED_OUT → toast "Already timed out today"
 *  - OUTSIDE_GEOFENCE  → toast with distance + prompt for override reason
 *  - NOT_TIMED_IN      → toast "Cannot time out without timing in"
 */
import { useMemo, useEffect, useState } from 'react'
import { Clock, MapPin, CheckCircle2, AlertTriangle, Loader2, XCircle } from 'lucide-react'
import { toast } from 'sonner'
import { useGeolocation } from '@/hooks/useGeolocation'
import { useAttendanceToday, useTimeIn, useTimeOut, useGeofenceSettings } from '@/hooks/useAttendance'
import StatusBadge from '@/components/ui/StatusBadge'
import { firstErrorMessage, parseApiError } from '@/lib/errorHandler'

function formatTime12hr(time: string | null | undefined): string {
  if (!time) return '\u2014'
  try {
    if (time.includes('T') || time.includes(' ')) {
      return new Date(time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }
    const [hours, minutes] = time.split(':').map(Number)
    const period = hours >= 12 ? 'PM' : 'AM'
    const displayHours = hours % 12 || 12
    return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`
  } catch {
    return time
  }
}

/** Map backend error codes to user-friendly toast messages. */
const ERROR_MESSAGES: Record<string, string> = {
  NO_SHIFT_ASSIGNED: 'No shift schedule assigned for today. Contact HR.',
  ALREADY_TIMED_IN: 'You have already timed in today.',
  ALREADY_TIMED_OUT: 'You have already timed out today.',
  NOT_TIMED_IN: 'Cannot time out without timing in first.',
  OUTSIDE_GEOFENCE: 'You are outside your work location geofence. Provide a reason to continue.',
  OUTSIDE_GEOFENCE_BLOCKED: 'Clock-in blocked: you are outside the allowed geofence area. Contact HR.',
}

export default function TimeClockWidget() {
  const geo = useGeolocation()
  const { data: todayLog, isLoading } = useAttendanceToday()
  const timeInMutation = useTimeIn()
  const timeOutMutation = useTimeOut()

  const [liveDuration, setLiveDuration] = useState('')

  const hasTimedIn = !!todayLog?.time_in
  const hasTimedOut = !!todayLog?.time_out

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
      })
      toast.success('Timed in successfully!')
    } catch (err) {
      const parsed = parseApiError(err)
      const mappedMsg = parsed.errorCode ? ERROR_MESSAGES[parsed.errorCode] : null
      toast.error(mappedMsg || parsed.message || 'Failed to time in.')
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
      const parsed = parseApiError(err)
      const mappedMsg = parsed.errorCode ? ERROR_MESSAGES[parsed.errorCode] : null
      toast.error(mappedMsg || parsed.message || 'Failed to time out.')
    }
  }

  const todayStr = new Date().toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })

  return (
    <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg p-4">
      {/* Header row */}
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-2">
          <Clock className="w-4 h-4" />
          Time Clock
        </h3>
        <span className="text-xs text-neutral-400 dark:text-neutral-500">{todayStr}</span>
      </div>

      {isLoading ? (
        <div className="flex items-center gap-2 text-sm text-neutral-400 py-2">
          <Loader2 className="w-4 h-4 animate-spin" /> Loading...
        </div>
      ) : (
        <div className="space-y-3">
          {/* Today's log summary */}
          <div className="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
            <span className="text-neutral-500 dark:text-neutral-400">
              In: <span className="font-medium text-neutral-800 dark:text-neutral-200">{formatTime12hr(todayLog?.time_in)}</span>
              {todayLog?.time_in_within_geofence === true && <CheckCircle2 className="w-3 h-3 inline ml-0.5 text-emerald-500" />}
              {todayLog?.time_in_within_geofence === false && <AlertTriangle className="w-3 h-3 inline ml-0.5 text-amber-500" />}
            </span>
            <span className="text-neutral-500 dark:text-neutral-400">
              Out: <span className="font-medium text-neutral-800 dark:text-neutral-200">{formatTime12hr(todayLog?.time_out)}</span>
            </span>
            {liveDuration && (
              <span className="font-semibold text-blue-600 dark:text-blue-400 text-xs">{liveDuration}</span>
            )}
            {todayLog?.attendance_status && (
              <StatusBadge status={todayLog.attendance_status}>{todayLog.attendance_status.replace(/_/g, ' ')}</StatusBadge>
            )}
          </div>

          {/* Location indicator */}
          <div className="flex items-center gap-1.5 text-xs text-neutral-400 dark:text-neutral-500">
            <MapPin className="w-3 h-3" />
            {geo.status === 'requesting' && <><Loader2 className="w-3 h-3 animate-spin" /> Getting location...</>}
            {geo.status === 'granted' && <span className="text-emerald-600 dark:text-emerald-400">GPS ready</span>}
            {geo.status === 'denied' && <span className="text-red-500">Location denied</span>}
            {(geo.status === 'unavailable' || geo.status === 'timeout') && <span className="text-red-500">GPS unavailable</span>}
            {geo.status === 'idle' && <span>Waiting...</span>}
          </div>

          {/* Flagged notice */}
          {todayLog?.is_flagged && (
            <div className="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded px-2 py-1">
              <AlertTriangle className="w-3 h-3" />
              Flagged for HR review
            </div>
          )}

          {/* Action buttons */}
          <div className="flex items-center gap-3">
            {!hasTimedIn && (
              <button
                onClick={handleTimeIn}
                disabled={timeInMutation.isPending || geo.status !== 'granted'}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-neutral-300 dark:disabled:bg-neutral-700 disabled:cursor-not-allowed text-white font-medium text-sm rounded transition-colors flex items-center gap-1.5"
              >
                {timeInMutation.isPending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Clock className="w-3.5 h-3.5" />}
                Time In
              </button>
            )}
            {hasTimedIn && !hasTimedOut && (
              <button
                onClick={handleTimeOut}
                disabled={timeOutMutation.isPending || geo.status !== 'granted'}
                className="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-neutral-300 dark:disabled:bg-neutral-700 disabled:cursor-not-allowed text-white font-medium text-sm rounded transition-colors flex items-center gap-1.5"
              >
                {timeOutMutation.isPending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Clock className="w-3.5 h-3.5" />}
                Time Out
              </button>
            )}
            {hasTimedOut && (
              <div className="flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="w-4 h-4" />
                Done for today
                {todayLog?.worked_hours != null && (
                  <span className="text-neutral-400 dark:text-neutral-500 ml-1">({todayLog.worked_hours}h)</span>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
