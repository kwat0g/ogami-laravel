import { useState, useMemo, useEffect } from 'react'
import { Clock, MapPin, AlertTriangle, CheckCircle2, XCircle, Loader2, RefreshCw, Briefcase, Calendar, Coffee, Timer } from 'lucide-react'
import { toast } from 'sonner'
import { firstErrorMessage, parseApiError } from '@/lib/errorHandler'
import { useGeolocation } from '@/hooks/useGeolocation'
import { useAttendanceToday, useTimeIn, useTimeOut, useAttendanceLogs } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'
import { PageHeader } from '@/components/ui/PageHeader'
import StatusBadge from '@/components/ui/StatusBadge'
import { Card } from '@/components/ui/Card'
import SkeletonLoader from '@/components/ui/SkeletonLoader'

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

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

export default function TimeInOutPage() {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined
  const geo = useGeolocation()
  const { data: todayLog, isLoading: todayLoading } = useAttendanceToday()
  const timeInMutation = useTimeIn()
  const timeOutMutation = useTimeOut()


  const [liveDuration, setLiveDuration] = useState('')

  // Month filter for history table
  const currentYear = new Date().getFullYear()
  const currentMonth = new Date().getMonth() + 1
  const [year, setYear] = useState(currentYear)
  const [month, setMonth] = useState(currentMonth)
  const [page, setPage] = useState(1)

  const dateFrom = `${year}-${String(month).padStart(2, '0')}-01`
  const dateTo = new Date(year, month, 0).toISOString().slice(0, 10)

  const { data: attendanceData, isLoading: logsLoading } = useAttendanceLogs(
    employeeId ? {
      employee_id: employeeId,
      date_from: dateFrom,
      date_to: dateTo,
      per_page: 31,
      page,
    } : {
      employee_id: 0,
      date_from: dateFrom,
      date_to: dateTo,
      per_page: 31,
      page,
    }
  )

  // Monthly stats
  const stats = useMemo(() => {
    const logs = attendanceData?.data ?? []
    const present = logs.filter((l) => l.is_present && !l.is_absent).length
    const absent = logs.filter((l) => l.is_absent).length
    const late = logs.filter((l) => l.late_minutes > 0).length
    const incomplete = logs.filter((l) => l.undertime_minutes > 0 && !l.is_absent).length
    const totalOvertime = logs.reduce((sum, l) => sum + (l.overtime_minutes || 0), 0)
    return { present, absent, late, incomplete, totalOvertime }
  }, [attendanceData])

  // Live duration counter
  useEffect(() => {
    if (!todayLog?.time_in || todayLog?.time_out) {
      setLiveDuration('')
      return
    }
    const update = () => {
      let startStr = todayLog.time_in!
      if (!startStr.includes('T') && !startStr.includes(' ')) {
        const d = new Date()
        const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
        startStr = `${ymd}T${startStr}`
      }
      const start = new Date(startStr).getTime()
      if (isNaN(start)) return
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


  const deviceInfo = useMemo(() => ({
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    timestamp: new Date().toISOString(),
  }), [])

  const handleTimeIn = async (isOverride = false) => {
    if (!isOverride && (!geo.latitude || !geo.longitude)) {
      if (geo.error || geo.status !== 'granted') setShowOverride('in')
      return
    }
    try {
      await timeInMutation.mutateAsync({
        latitude: geo.latitude ?? 0,
        longitude: geo.longitude ?? 0,
        accuracy_meters: geo.accuracy ?? 0,
        device_info: deviceInfo,
        override_reason: isOverride ? overrideReason : undefined,
      })
      toast.success('Timed in successfully!')
    } catch (err) {
      const parsed = parseApiError(err)
      if (parsed.errorCode && ['OUTSIDE_GEOFENCE', 'NO_WORK_LOCATION_OVERRIDE', 'LOCATION_INACCURATE'].includes(parsed.errorCode)) {
        setShowOverride('in')
      } else {
        toast.error(firstErrorMessage(err, 'Failed to time in.'))
      }
    }
  }

  const handleTimeOut = async (isOverride = false) => {
    if (!isOverride && (!geo.latitude || !geo.longitude)) {
      if (geo.error || geo.status !== 'granted') setShowOverride('out')
      return
    }
    try {
      await timeOutMutation.mutateAsync({
        latitude: geo.latitude ?? 0,
        longitude: geo.longitude ?? 0,
        accuracy_meters: geo.accuracy ?? 0,
        device_info: deviceInfo,
        override_reason: isOverride ? overrideReason : undefined,
      })
      toast.success('Timed out successfully!')
    } catch (err) {
      const parsed = parseApiError(err)
      if (parsed.errorCode && ['OUTSIDE_GEOFENCE', 'NO_WORK_LOCATION_OVERRIDE', 'LOCATION_INACCURATE'].includes(parsed.errorCode)) {
        setShowOverride('out')
      } else {
        toast.error(firstErrorMessage(err, 'Failed to time out.'))
      }
    }
  }

  if (!employeeId) {
    return <div className="text-neutral-500 dark:text-neutral-400 text-sm mt-4">No employee profile linked to your account.</div>
  }

  const getStatusBadge = (log: { is_absent: boolean; is_present: boolean; late_minutes: number; undertime_minutes: number; attendance_status?: string | null }) => {
    if (log.attendance_status) {
      return <StatusBadge status={log.attendance_status}>{log.attendance_status.replace(/_/g, ' ')}</StatusBadge>
    }
    if (log.is_absent) return <StatusBadge status="absent">absent</StatusBadge>
    if (log.late_minutes > 0) return <StatusBadge status="late">late</StatusBadge>
    if (log.undertime_minutes > 0) return <StatusBadge status="incomplete">incomplete</StatusBadge>
    if (log.is_present) return <StatusBadge status="present">present</StatusBadge>
    return <StatusBadge status="pending">pending</StatusBadge>
  }

  return (
    <div>
      <PageHeader title="My Attendance" subtitle="Time clock and attendance history" />

      {/* ── Time Clock Widget ─────────────────────────────────────────── */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg p-5 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          {/* Left: Today's info */}
          <div className="space-y-2">
            <h2 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300 flex items-center gap-2">
              <Clock className="w-4 h-4" />
              Today &mdash; {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
            </h2>

            {todayLoading ? (
              <div className="flex items-center gap-2 text-sm text-neutral-400">
                <Loader2 className="w-4 h-4 animate-spin" /> Loading...
              </div>
            ) : (
              <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <span className="text-neutral-500 dark:text-neutral-400">
                  In: <span className="font-medium text-neutral-800 dark:text-neutral-200">{formatTime12hr(todayLog?.time_in)}</span>
                  {todayLog?.time_in_within_geofence === true && <CheckCircle2 className="w-3.5 h-3.5 inline ml-1 text-emerald-500" />}
                  {todayLog?.time_in_within_geofence === false && <AlertTriangle className="w-3.5 h-3.5 inline ml-1 text-amber-500" />}
                </span>
                <span className="text-neutral-500 dark:text-neutral-400">
                  Out: <span className="font-medium text-neutral-800 dark:text-neutral-200">{formatTime12hr(todayLog?.time_out)}</span>
                </span>
                {liveDuration && (
                  <span className="text-neutral-500 dark:text-neutral-400">
                    Duration: <span className="font-semibold text-blue-600 dark:text-blue-400">{liveDuration}</span>
                  </span>
                )}
                {todayLog?.attendance_status && (
                  <StatusBadge status={todayLog.attendance_status}>{todayLog.attendance_status.replace(/_/g, ' ')}</StatusBadge>
                )}
              </div>
            )}

            {/* Location status */}
            <div className="flex items-center gap-2 text-xs text-neutral-400 dark:text-neutral-500">
              <MapPin className="w-3 h-3" />
              {geo.status === 'requesting' && <><Loader2 className="w-3 h-3 animate-spin" /> Getting location...</>}
              {geo.status === 'granted' && <span className="text-emerald-600 dark:text-emerald-400">Location acquired (&#177;{Math.round(geo.accuracy ?? 0)}m)</span>}
              {geo.status === 'denied' && <span className="text-red-500">{geo.error}</span>}
              {geo.status === 'unavailable' && <span className="text-red-500">{geo.error}</span>}
              {geo.status === 'timeout' && <span className="text-amber-500">{geo.error}</span>}
              {geo.status === 'idle' && <span>Waiting for location...</span>}
              <button onClick={geo.requestLocation} className="text-blue-500 hover:text-blue-700 dark:hover:text-blue-300 ml-1">
                <RefreshCw className="w-3 h-3" />
              </button>
            </div>

            {/* Out-of-geofence flag */}
            {todayLog?.time_in_within_geofence === false && !hasTimedOut && (
              <div className="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded px-2.5 py-1.5 w-fit">
                <AlertTriangle className="w-3.5 h-3.5" />
                Outside geofence &mdash; flagged for HR review
              </div>
            )}

          </div>

          {/* Right: Action buttons */}
          <div className="flex-shrink-0">
            {!hasTimedIn && (
              <button
                onClick={() => {
                   if (geo.error || geo.status !== 'granted') {
                     toast.error('Location is mandatory to clock in.')
                     return
                   }
                   else handleTimeIn()
                }}
                disabled={timeInMutation.isPending}
                className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 dark:disabled:bg-emerald-800 text-white font-semibold text-sm rounded transition-colors flex items-center gap-2"
              >
                {timeInMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Clock className="w-4 h-4" />}
                Time In
              </button>
            )}
            {hasTimedIn && !hasTimedOut && (
              <button
                onClick={() => {
                   if (geo.error || geo.status !== 'granted') {
                     toast.error('Location is mandatory to clock out.')
                     return
                   }
                   else handleTimeOut()
                }}
                disabled={timeOutMutation.isPending}
                className="px-6 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-300 dark:disabled:bg-red-800 text-white font-semibold text-sm rounded transition-colors flex items-center gap-2"
              >
                {timeOutMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Clock className="w-4 h-4" />}
                Time Out
              </button>
            )}
            {hasTimedOut && (
              <div className="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="w-4 h-4" />
                Done for today
                {todayLog?.worked_hours != null && <span className="text-neutral-500 dark:text-neutral-400">({todayLog.worked_hours}h)</span>}
              </div>
            )}
          </div>
        </div>

        {/* Override UI */}
        {showOverride && (
          <div className="mt-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800">
            <label className="block text-sm font-medium text-amber-900 dark:text-amber-200 mb-2 font-inter">
              Location unavailable, outside geofence, or accuracy too low. Please provide a reason to continue:
            </label>
            <textarea
              value={overrideReason}
              onChange={(e) => setOverrideReason(e.target.value)}
              className="w-full rounded-md border border-amber-300 dark:border-amber-700 bg-white dark:bg-neutral-800 py-2 px-3 text-sm text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-amber-500 font-inter"
              rows={3}
              placeholder="E.g., device GPS is not working, working remotely on assignment..."
            />
            <div className="mt-3 flex gap-3 justify-end items-center">
              <button
                onClick={() => {
                  setShowOverride(null)
                  setOverrideReason('')
                }}
                className="px-3 py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors"
              >
                Cancel
              </button>
              <button
                disabled={!overrideReason.trim()}
                onClick={() => showOverride === 'in' ? handleTimeIn(true) : handleTimeOut(true)}
                className="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50 transition-colors"
              >
                Submit {showOverride === 'in' ? 'Time In' : 'Time Out'}
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ── Monthly Stats Cards ─────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-300 mb-1">
              <Briefcase className="w-4 h-4" />
              <span className="text-xs font-medium">Present</span>
            </div>
            <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{stats.present}</p>
          </div>
        </Card>
        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-300 mb-1">
              <Calendar className="w-4 h-4" />
              <span className="text-xs font-medium">Absent</span>
            </div>
            <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{stats.absent}</p>
          </div>
        </Card>
        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-300 mb-1">
              <Clock className="w-4 h-4" />
              <span className="text-xs font-medium">Late</span>
            </div>
            <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{stats.late}</p>
          </div>
        </Card>
        <Card>
          <div className="p-4">
            <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-300 mb-1">
              <Coffee className="w-4 h-4" />
              <span className="text-xs font-medium">Incomplete</span>
            </div>
            <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{stats.incomplete}</p>
          </div>
        </Card>
        <Card className="col-span-2 lg:col-span-1">
          <div className="p-4">
            <div className="flex items-center gap-2 text-neutral-700 dark:text-neutral-300 mb-1">
              <Timer className="w-4 h-4" />
              <span className="text-xs font-medium">OT Hours</span>
            </div>
            <p className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{Math.round(stats.totalOvertime / 60 * 10) / 10}h</p>
          </div>
        </Card>
      </div>

      {/* ── Filters ──────────────────────────────────────────────────── */}
      <div className="flex flex-wrap gap-3 mb-5">
        <select
          value={year}
          onChange={(e) => { setYear(Number(e.target.value)); setPage(1) }}
          className="px-3 py-1.5 text-sm border border-neutral-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {[currentYear, currentYear - 1, currentYear - 2].map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>
        <select
          value={month}
          onChange={(e) => { setMonth(Number(e.target.value)); setPage(1) }}
          className="px-3 py-1.5 text-sm border border-neutral-300 dark:border-neutral-600 rounded bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {MONTHS.map((m, i) => (
            <option key={i + 1} value={i + 1}>{m}</option>
          ))}
        </select>
      </div>

      {/* ── Attendance History Table ─────────────────────────────────── */}
      {logsLoading ? <SkeletonLoader rows={6} /> : (
        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700">
              <tr>
                {['Date', 'Time In', 'Time Out', 'Worked', 'Status', 'Geofence', 'Remarks'].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600 dark:text-neutral-400">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
              {(attendanceData?.data ?? []).length === 0 && (
                <tr><td colSpan={7} className="px-3 py-8 text-center text-neutral-400 dark:text-neutral-500">No attendance records for {MONTHS[month - 1]} {year}.</td></tr>
              )}
              {(attendanceData?.data ?? []).map((row) => (
                <tr key={row.id} className="even:bg-neutral-50/50 dark:even:bg-neutral-800/30 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors">
                  <td className="px-3 py-2 text-neutral-700 dark:text-neutral-300 font-medium">
                    {new Date(row.work_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                  </td>
                  <td className="px-3 py-2 text-neutral-600 dark:text-neutral-400">{formatTime12hr(row.time_in)}</td>
                  <td className="px-3 py-2 text-neutral-600 dark:text-neutral-400">{formatTime12hr(row.time_out)}</td>
                  <td className="px-3 py-2 text-neutral-600 dark:text-neutral-400">
                    {row.worked_hours > 0 ? `${row.worked_hours}h` : '\u2014'}
                  </td>
                  <td className="px-3 py-2">{getStatusBadge(row)}</td>
                  <td className="px-3 py-2">
                    {row.time_in_within_geofence === true && <CheckCircle2 className="w-4 h-4 text-emerald-500" />}
                    {row.time_in_within_geofence === false && <XCircle className="w-4 h-4 text-red-500" />}
                    {row.time_in_within_geofence == null && <span className="text-neutral-300 dark:text-neutral-600">&mdash;</span>}
                  </td>
                  <td className="px-3 py-2 text-neutral-500 dark:text-neutral-400 max-w-xs truncate" title={row.remarks ?? ''}>
                    {row.remarks ?? '\u2014'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {(attendanceData?.meta?.last_page ?? 1) > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-neutral-600 dark:text-neutral-400">
          <span>Page {attendanceData?.meta?.current_page} of {attendanceData?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
              className="px-3 py-1.5 border border-neutral-300 dark:border-neutral-600 rounded hover:bg-neutral-50 dark:hover:bg-neutral-800 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
            <button disabled={page >= (attendanceData?.meta?.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}
              className="px-3 py-1.5 border border-neutral-300 dark:border-neutral-600 rounded hover:bg-neutral-50 dark:hover:bg-neutral-800 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
          </div>
        </div>
      )}
    </div>
  )
}
