import { useState } from 'react'
import { useLeaveCalendar, type LeaveCalendarEvent, type LeaveHoliday } from '@/hooks/useLeave'
import PageHeader from '@/components/ui/PageHeader'
import ExecutiveReadOnlyBanner from '@/components/ui/ExecutiveReadOnlyBanner'

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function buildCalendarGrid(year: number, month: number): Array<Date | null> {
  const firstDay = new Date(year, month - 1, 1).getDay() // 0=Sun
  const daysInMonth = new Date(year, month, 0).getDate()
  const cells: Array<Date | null> = Array(firstDay).fill(null)
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push(new Date(year, month - 1, d))
  }
  // Pad to full rows
  while (cells.length % 7 !== 0) cells.push(null)
  return cells
}

function toDateStr(d: Date): string {
  return d.toISOString().substring(0, 10)
}

interface DayCellProps {
  date: Date
  events: LeaveCalendarEvent[]
  holiday: LeaveHoliday | undefined
}

function DayCell({ date, events, holiday }: DayCellProps) {
  const isToday = toDateStr(date) === toDateStr(new Date())

  return (
    <div className={`min-h-[80px] border border-neutral-200 p-1 text-xs ${isToday ? 'bg-neutral-50' : 'bg-white'}`}>
      <span className={`text-xs font-semibold ${isToday ? 'text-neutral-700' : 'text-neutral-700'}`}>
        {date.getDate()}
      </span>

      {holiday && (
        <div
          className="mt-0.5 rounded px-1 py-0.5 bg-amber-100 text-amber-800 truncate"
          title={`${holiday.type}: ${holiday.name}`}
        >
          🎌 {holiday.name}
        </div>
      )}

      {events.map(ev => (
        <div
          key={ev.id}
          className={`mt-0.5 rounded px-1 py-0.5 truncate ${ev.is_paid ? 'bg-green-100 text-green-800' : 'bg-neutral-100 text-neutral-700'}`}
          title={`${ev.employee_name ?? 'Employee'} — ${ev.leave_type ?? 'Leave'} (${ev.leave_days}d)`}
        >
          {ev.employee_name?.split(' ')[0] ?? '?'} · {ev.leave_type ?? 'Leave'}
        </div>
      ))}
    </div>
  )
}

export default function LeaveCalendarPage() {
  const today = new Date()
  const [year, setYear]             = useState(today.getFullYear())
  const [month, setMonth]           = useState(today.getMonth() + 1)
  const [departmentId, setDeptId]   = useState<number | undefined>(undefined)

  const { data, isLoading, isError } = useLeaveCalendar(year, month, departmentId)

  function prevMonth() {
    if (month === 1) { setMonth(12); setYear(y => y - 1) }
    else setMonth(m => m - 1)
  }

  function nextMonth() {
    if (month === 12) { setMonth(1); setYear(y => y + 1) }
    else setMonth(m => m + 1)
  }

  /** Returns leave events that overlap with a given date */
  function eventsOnDate(dateStr: string): LeaveCalendarEvent[] {
    return (data?.leave_events ?? []).filter(
      ev => ev.date_from <= dateStr && ev.date_to >= dateStr,
    )
  }

  function holidayOnDate(dateStr: string): LeaveHoliday | undefined {
    return (data?.holidays ?? []).find(h => h.date === dateStr)
  }

  const grid = buildCalendarGrid(year, month)

  return (
    <div className="p-6 space-y-4">
      <ExecutiveReadOnlyBanner />
      <PageHeader title="Leave Calendar" />

      {/* Controls */}
      <div className="flex flex-wrap items-center gap-3">
        <button
          onClick={prevMonth}
          className="px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
        >
          ‹ Prev
        </button>
        <span className="text-base font-semibold w-40 text-center">
          {MONTH_NAMES[month - 1]} {year}
        </span>
        <button
          onClick={nextMonth}
          className="px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
        >
          Next ›
        </button>

        <div className="ml-auto flex items-center gap-2">
          <label className="text-xs text-neutral-500">Dept ID</label>
          <input
            type="number"
            placeholder="All"
            value={departmentId ?? ''}
            onChange={e => setDeptId(e.target.value ? Number(e.target.value) : undefined)}
            className="w-20 border border-neutral-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-neutral-400 outline-none"
          />
        </div>
      </div>

      {/* Legend */}
      <div className="flex items-center gap-4 text-xs text-neutral-600">
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded bg-green-100 border border-green-300 inline-block" /> Paid leave</span>
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded bg-neutral-100 border border-neutral-300 inline-block" /> Unpaid leave</span>
        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded bg-amber-100 border border-amber-300 inline-block" /> Holiday</span>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-7 gap-px bg-neutral-200 rounded overflow-hidden">
          {DAY_LABELS.map(d => (
            <div key={d} className="bg-neutral-50 px-2 py-1 text-xs font-medium text-neutral-500 text-center">{d}</div>
          ))}
          {Array(35).fill(null).map((_, i) => (
            <div key={i} className="min-h-[80px] bg-white animate-pulse" />
          ))}
        </div>
      ) : isError ? (
        <p className="text-red-600 text-sm">Failed to load leave calendar.</p>
      ) : (
        <div className="rounded overflow-hidden border border-neutral-200">
          {/* Day headers */}
          <div className="grid grid-cols-7 bg-neutral-50">
            {DAY_LABELS.map(d => (
              <div key={d} className="px-2 py-1.5 text-xs font-medium text-neutral-500 text-center border-b border-neutral-200">
                {d}
              </div>
            ))}
          </div>

          {/* Calendar cells */}
          <div className="grid grid-cols-7">
            {grid.map((date, idx) =>
              date === null ? (
                <div key={idx} className="min-h-[80px] bg-neutral-50 border border-neutral-100" />
              ) : (
                <DayCell
                  key={toDateStr(date)}
                  date={date}
                  events={eventsOnDate(toDateStr(date))}
                  holiday={holidayOnDate(toDateStr(date))}
                />
              ),
            )}
          </div>
        </div>
      )}
    </div>
  )
}
