import { useState, useMemo } from 'react'
import { useAttendanceLogs } from '@/hooks/useAttendance'
import { useAuthStore } from '@/stores/authStore'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import StatusBadge from '@/components/ui/StatusBadge'
import { Clock, Calendar, Briefcase, Coffee } from 'lucide-react'

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

export default function MyAttendancePage() {
  const { user } = useAuthStore()
  const employeeId = user?.employee_id as number | undefined

  const currentYear = new Date().getFullYear()
  const currentMonth = new Date().getMonth() + 1
  
  const [year, setYear] = useState(currentYear)
  const [month, setMonth] = useState(currentMonth)
  const [page, setPage] = useState(1)

  const dateFrom = `${year}-${String(month).padStart(2, '0')}-01`
  const dateTo = new Date(year, month, 0).toISOString().slice(0, 10)

  const { data: attendanceData, isLoading } = useAttendanceLogs(
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

  // Calculate monthly stats
  const stats = useMemo(() => {
    const logs = attendanceData?.data ?? []
    const present = logs.filter(l => l.is_present && !l.is_absent).length
    const absent = logs.filter(l => l.is_absent).length
    const late = logs.filter(l => l.late_minutes > 0).length
    const incomplete = logs.filter(l => l.undertime_minutes > 0 && !l.is_absent).length
    const totalOvertime = logs.reduce((sum, l) => sum + (l.overtime_minutes || 0), 0)
    
    return { present, absent, late, incomplete, totalOvertime }
  }, [attendanceData])

  if (!employeeId) {
    return <div className="text-neutral-500 text-sm mt-4">No employee profile linked to your account.</div>
  }

  const formatTime12hr = (time: string | null) => {
    if (!time) return '—'
    const [hours, minutes] = time.split(':').map(Number)
    const period = hours >= 12 ? 'PM' : 'AM'
    const displayHours = hours % 12 || 12
    return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`
  }

  const getStatusBadge = (log: { is_absent: boolean; is_present: boolean; late_minutes: number; undertime_minutes: number }) => {
    if (log.is_absent) return <StatusBadge label="absent" variant="error" />
    if (log.late_minutes > 0) return <StatusBadge label="late" variant="warning" />
    if (log.undertime_minutes > 0) return <StatusBadge label="incomplete" variant="warning" />
    if (log.is_present) return <StatusBadge label="present" variant="success" />
    return <StatusBadge label="pending" />
  }

  return (
    <div>
      {/* Header */}
      <h1 className="text-lg font-semibold text-neutral-900 mb-6">My Attendance</h1>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <div className="bg-white border border-neutral-200 rounded p-4">
          <div className="flex items-center gap-2 text-neutral-700 mb-1">
            <Briefcase className="w-4 h-4" />
            <span className="text-xs font-medium">Present</span>
          </div>
          <p className="text-2xl font-bold text-neutral-900">{stats.present}</p>
        </div>
        <div className="bg-white border border-neutral-200 rounded p-4">
          <div className="flex items-center gap-2 text-neutral-700 mb-1">
            <Calendar className="w-4 h-4" />
            <span className="text-xs font-medium">Absent</span>
          </div>
          <p className="text-2xl font-bold text-neutral-900">{stats.absent}</p>
        </div>
        <div className="bg-white border border-neutral-200 rounded p-4">
          <div className="flex items-center gap-2 text-neutral-700 mb-1">
            <Clock className="w-4 h-4" />
            <span className="text-xs font-medium">Late</span>
          </div>
          <p className="text-2xl font-bold text-neutral-900">{stats.late}</p>
        </div>
        <div className="bg-white border border-neutral-200 rounded p-4">
          <div className="flex items-center gap-2 text-neutral-700 mb-1">
            <Coffee className="w-4 h-4" />
            <span className="text-xs font-medium">Incomplete</span>
          </div>
          <p className="text-2xl font-bold text-neutral-900">{stats.incomplete}</p>
        </div>
        <div className="bg-white border border-neutral-200 rounded p-4 col-span-2 lg:col-span-1">
          <div className="flex items-center gap-2 text-neutral-700 mb-1">
            <Clock className="w-4 h-4" />
            <span className="text-xs font-medium">OT Hours</span>
          </div>
          <p className="text-2xl font-bold text-neutral-900">{Math.round(stats.totalOvertime / 60 * 10) / 10}h</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-5">
        <select
          value={year}
          onChange={(e) => { setYear(Number(e.target.value)); setPage(1) }}
          className="px-3 py-1.5 text-sm border border-neutral-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {[currentYear, currentYear - 1, currentYear - 2].map((y) => (
            <option key={y} value={y}>{y}</option>
          ))}
        </select>
        <select
          value={month}
          onChange={(e) => { setMonth(Number(e.target.value)); setPage(1) }}
          className="px-3 py-1.5 text-sm border border-neutral-300 rounded bg-white focus:outline-none focus:ring-1 focus:ring-neutral-400"
        >
          {MONTHS.map((m, i) => (
            <option key={i + 1} value={i + 1}>{m}</option>
          ))}
        </select>
      </div>

      {/* Attendance Table */}
      {isLoading ? <SkeletonLoader rows={6} /> : (
        <div className="bg-white border border-neutral-200 rounded overflow-hidden">
          <table className="min-w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                {['Date', 'Time In', 'Time Out', 'Worked', 'Status', 'Remarks'].map((h) => (
                  <th key={h} className="px-3 py-2.5 text-left text-xs font-semibold text-neutral-600">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(attendanceData?.data ?? []).length === 0 && (
                <tr><td colSpan={6} className="px-3 py-8 text-center text-neutral-400">No attendance records for {MONTHS[month - 1]} {year}.</td></tr>
              )}
              {(attendanceData?.data ?? []).map((row) => (
                <tr key={row.id} className="even:bg-neutral-100 hover:bg-neutral-50 transition-colors">
                  <td className="px-3 py-2 text-neutral-700 font-medium">
                    {new Date(row.work_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                  </td>
                  <td className="px-3 py-2 text-neutral-600">{formatTime12hr(row.time_in)}</td>
                  <td className="px-3 py-2 text-neutral-600">{formatTime12hr(row.time_out)}</td>
                  <td className="px-3 py-2 text-neutral-600">
                    {row.worked_hours > 0 ? (
                      <span>{row.worked_hours}h {row.worked_minutes % 60}m</span>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td className="px-3 py-2">{getStatusBadge(row)}</td>
                  <td className="px-3 py-2 text-neutral-500 max-w-xs truncate" title={row.remarks ?? ''}>
                    {row.remarks ?? '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {(attendanceData?.meta?.last_page ?? 1) > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-neutral-600">
          <span>Page {attendanceData?.meta?.current_page} of {attendanceData?.meta?.last_page}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40">Prev</button>
            <button disabled={page >= (attendanceData?.meta?.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}
              className="px-3 py-1.5 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-40">Next</button>
          </div>
        </div>
      )}
    </div>
  )
}
