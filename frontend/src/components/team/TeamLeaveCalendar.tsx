/**
 * TeamLeaveCalendar - Visual monthly calendar showing who is on leave
 *
 * For managers and heads to see department leave coverage at a glance.
 * Shows leave requests as colored bars across the calendar grid.
 *
 * Usage:
 *   <TeamLeaveCalendar
 *     leaves={[
 *       { employee: 'John Doe', dateFrom: '2026-03-15', dateTo: '2026-03-17', type: 'Vacation', status: 'approved' },
 *     ]}
 *     month={3}
 *     year={2026}
 *   />
 */
import { useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Card } from '@/components/ui/Card'

interface LeaveEntry {
  id?: number | string
  employee: string
  dateFrom: string
  dateTo: string
  type: string
  status: string
}

interface TeamLeaveCalendarProps {
  leaves: LeaveEntry[]
  initialMonth?: number // 1-12
  initialYear?: number
}

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December']

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const TYPE_COLORS: Record<string, string> = {
  'Vacation Leave': 'bg-blue-200 text-blue-800',
  'Sick Leave': 'bg-red-200 text-red-800',
  'Emergency Leave': 'bg-amber-200 text-amber-800',
  'Maternity Leave': 'bg-pink-200 text-pink-800',
  'Paternity Leave': 'bg-purple-200 text-purple-800',
  'Bereavement Leave': 'bg-neutral-300 text-neutral-700',
}

function getColor(type: string): string {
  return TYPE_COLORS[type] ?? 'bg-green-200 text-green-800'
}

function getDaysInMonth(year: number, month: number): number {
  return new Date(year, month, 0).getDate()
}

function getFirstDayOfMonth(year: number, month: number): number {
  return new Date(year, month - 1, 1).getDay()
}

function dateInRange(date: string, from: string, to: string): boolean {
  return date >= from && date <= to
}

function formatDate(year: number, month: number, day: number): string {
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

export function TeamLeaveCalendar({ leaves, initialMonth, initialYear }: TeamLeaveCalendarProps): JSX.Element {
  const now = new Date()
  const [month, setMonth] = useState(initialMonth ?? now.getMonth() + 1)
  const [year, setYear] = useState(initialYear ?? now.getFullYear())

  const daysInMonth = getDaysInMonth(year, month)
  const firstDay = getFirstDayOfMonth(year, month)

  const goToPrevMonth = () => {
    if (month === 1) { setMonth(12); setYear(y => y - 1) }
    else setMonth(m => m - 1)
  }

  const goToNextMonth = () => {
    if (month === 12) { setMonth(1); setYear(y => y + 1) }
    else setMonth(m => m + 1)
  }

  // Filter leaves to current month (any overlap)
  const monthStart = formatDate(year, month, 1)
  const monthEnd = formatDate(year, month, daysInMonth)
  const monthLeaves = leaves.filter(l =>
    l.status === 'approved' &&
    l.dateFrom <= monthEnd &&
    l.dateTo >= monthStart
  )

  // Build calendar grid
  const cells: (number | null)[] = []
  for (let i = 0; i < firstDay; i++) cells.push(null)
  for (let d = 1; d <= daysInMonth; d++) cells.push(d)

  const today = now.toISOString().split('T')[0]

  return (
    <Card className="p-4">
      {/* Month navigation */}
      <div className="flex items-center justify-between mb-4">
        <button onClick={goToPrevMonth} className="p-1.5 rounded hover:bg-neutral-100">
          <ChevronLeft className="h-4 w-4 text-neutral-600" />
        </button>
        <h3 className="text-sm font-semibold text-neutral-800">
          {MONTHS[month - 1]} {year}
        </h3>
        <button onClick={goToNextMonth} className="p-1.5 rounded hover:bg-neutral-100">
          <ChevronRight className="h-4 w-4 text-neutral-600" />
        </button>
      </div>

      {/* Day headers */}
      <div className="grid grid-cols-7 gap-px mb-1">
        {DAYS.map(day => (
          <div key={day} className="text-center text-[10px] font-medium text-neutral-500 py-1">
            {day}
          </div>
        ))}
      </div>

      {/* Calendar grid */}
      <div className="grid grid-cols-7 gap-px">
        {cells.map((day, idx) => {
          if (day === null) {
            return <div key={`empty-${idx}`} className="h-20 bg-neutral-50 rounded" />
          }

          const dateStr = formatDate(year, month, day)
          const isToday = dateStr === today
          const isWeekend = (firstDay + day - 1) % 7 === 0 || (firstDay + day - 1) % 7 === 6
          const dayLeaves = monthLeaves.filter(l => dateInRange(dateStr, l.dateFrom, l.dateTo))

          return (
            <div
              key={day}
              className={`h-20 p-1 rounded border ${
                isToday ? 'border-blue-400 bg-blue-50' :
                isWeekend ? 'border-neutral-100 bg-neutral-50' :
                'border-neutral-100 bg-white'
              }`}
            >
              <div className={`text-[10px] font-medium ${isToday ? 'text-blue-700' : 'text-neutral-500'}`}>
                {day}
              </div>
              <div className="mt-0.5 space-y-0.5 overflow-hidden max-h-[52px]">
                {dayLeaves.slice(0, 3).map((l, i) => (
                  <div
                    key={`${l.id ?? l.employee}-${i}`}
                    className={`text-[8px] font-medium px-1 py-0.5 rounded truncate ${getColor(l.type)}`}
                    title={`${l.employee} - ${l.type}`}
                  >
                    {l.employee.split(' ')[0]}
                  </div>
                ))}
                {dayLeaves.length > 3 && (
                  <div className="text-[8px] text-neutral-400 px-1">+{dayLeaves.length - 3} more</div>
                )}
              </div>
            </div>
          )
        })}
      </div>

      {/* Legend */}
      {monthLeaves.length > 0 && (
        <div className="flex flex-wrap gap-3 mt-3 pt-3 border-t border-neutral-100">
          {Array.from(new Set(monthLeaves.map(l => l.type))).map(type => (
            <div key={type} className="flex items-center gap-1.5">
              <div className={`w-2.5 h-2.5 rounded ${getColor(type).split(' ')[0]}`} />
              <span className="text-[10px] text-neutral-600">{type}</span>
            </div>
          ))}
        </div>
      )}

      {/* Summary */}
      <div className="mt-2 text-xs text-neutral-400 text-center">
        {monthLeaves.length} approved leave{monthLeaves.length !== 1 ? 's' : ''} this month
      </div>
    </Card>
  )
}

export default TeamLeaveCalendar
