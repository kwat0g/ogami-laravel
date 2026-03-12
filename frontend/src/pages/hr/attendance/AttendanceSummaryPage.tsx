import { useState, useMemo } from 'react'
import { ClipboardList, Download } from 'lucide-react'
import { toast } from 'sonner'
import {
  useAttendanceSummary,
  downloadDtr,
  type AttendanceSummaryRow,
} from '@/hooks/useAttendance'
import { useDepartments } from '@/hooks/useEmployees'

const fmtHrs = (min: number) => (min / 60).toFixed(1)

export default function AttendanceSummaryPage(): React.ReactElement {
  const now = new Date()
  const [from, setFrom] = useState(new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0])
  const [to, setTo] = useState(now.toISOString().split('T')[0])
  const [deptId, setDeptId] = useState<number | undefined>(undefined)

  const { data, isLoading } = useAttendanceSummary({ from, to, department_id: deptId })
  const { data: deptData } = useDepartments()
  const departments = deptData?.data ?? []

  const rows: AttendanceSummaryRow[] = useMemo(() => data?.data ?? [], [data?.data])

  const totals = useMemo(() => {
    const t = { present: 0, absent: 0, rest: 0, holiday: 0, worked: 0, late: 0, ut: 0, ot: 0, nd: 0 }
    for (const r of rows) {
      t.present += r.days_present
      t.absent += r.days_absent
      t.rest += r.days_rest
      t.holiday += r.days_holiday
      t.worked += r.total_worked_min
      t.late += r.total_late_min
      t.ut += r.total_ut_min
      t.ot += r.total_ot_min
      t.nd += r.total_nd_min
    }
    return t
  }, [rows])

  const handleDtrDownload = async (row: AttendanceSummaryRow) => {
    try {
      await downloadDtr(row.employee_id, from, to)
      toast.success(`DTR downloaded for ${row.employee_name}`)
    } catch {
      toast.error('DTR download failed.')
    }
  }

  return (
    <div className="max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-neutral-900 flex items-center gap-2">
            <ClipboardList className="w-6 h-6 text-indigo-600" />
            Attendance Summary
          </h1>
          <p className="text-sm text-neutral-500">
            Per-employee attendance metrics for a selected period. Download individual DTRs as CSV.
          </p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-end gap-4 mb-6">
        <div>
          <label className="block text-xs font-medium text-neutral-500 mb-1">From</label>
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-500 mb-1">To</label>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-500 mb-1">Department</label>
          <select value={deptId ?? ''} onChange={(e) => setDeptId(e.target.value ? Number(e.target.value) : undefined)}
            className="border border-neutral-300 rounded px-3 py-2 text-sm min-w-[180px]">
            <option value="">All departments</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>{d.code} — {d.name}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-5 gap-3 mb-6">
        {[
          { label: 'Employees', value: rows.length, color: 'text-neutral-900' },
          { label: 'Total Present', value: totals.present, color: 'text-emerald-600' },
          { label: 'Total Absent', value: totals.absent, color: 'text-red-600' },
          { label: 'Total Late (hrs)', value: fmtHrs(totals.late), color: 'text-amber-600' },
          { label: 'Total OT (hrs)', value: fmtHrs(totals.ot), color: 'text-indigo-600' },
        ].map(({ label, value, color }) => (
          <div key={label} className="bg-white border border-neutral-200 rounded-lg p-4">
            <p className="text-xs text-neutral-500 uppercase tracking-wide">{label}</p>
            <p className={`text-xl font-bold ${color}`}>{value}</p>
          </div>
        ))}
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-x-auto">
        {isLoading ? (
          <p className="text-sm text-neutral-500 p-6">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="text-sm text-neutral-500 p-6">No attendance data for this period.</p>
        ) : (
          <table className="w-full text-sm whitespace-nowrap">
            <thead className="border-b border-neutral-200 bg-neutral-50">
              <tr>
                <th className="px-3 py-3 text-left text-xs font-medium text-neutral-500">Employee</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">Present</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">Absent</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">Rest</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">Holiday</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">Worked (hrs)</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">Late (min)</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">UT (min)</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">OT (min)</th>
                <th className="px-3 py-3 text-right text-xs font-medium text-neutral-500">ND (min)</th>
                <th className="px-3 py-3 text-center text-xs font-medium text-neutral-500">DTR</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {rows.map((row) => (
                <tr key={row.employee_id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-3 py-2">
                    <span className="font-mono text-xs text-neutral-400">{row.employee_code}</span>
                    <span className="ml-2 text-neutral-800">{row.employee_name}</span>
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums text-emerald-600 font-medium">{row.days_present}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-red-600 font-medium">{row.days_absent}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-neutral-400">{row.days_rest}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-neutral-400">{row.days_holiday}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-neutral-900">{fmtHrs(row.total_worked_min)}</td>
                  <td className={`px-3 py-2 text-right tabular-nums ${row.total_late_min > 0 ? 'text-amber-600' : 'text-neutral-300'}`}>{row.total_late_min}</td>
                  <td className={`px-3 py-2 text-right tabular-nums ${row.total_ut_min > 0 ? 'text-orange-600' : 'text-neutral-300'}`}>{row.total_ut_min}</td>
                  <td className={`px-3 py-2 text-right tabular-nums ${row.total_ot_min > 0 ? 'text-indigo-600 font-medium' : 'text-neutral-300'}`}>{row.total_ot_min}</td>
                  <td className={`px-3 py-2 text-right tabular-nums ${row.total_nd_min > 0 ? 'text-violet-600' : 'text-neutral-300'}`}>{row.total_nd_min}</td>
                  <td className="px-3 py-2 text-center">
                    <button
                      onClick={() => handleDtrDownload(row)}
                      className="text-indigo-600 hover:text-indigo-800 p-1"
                      title="Download DTR CSV"
                    >
                      <Download className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))}
              {/* Totals */}
              <tr className="bg-neutral-50 font-semibold border-t-2 border-neutral-300">
                <td className="px-3 py-2 text-neutral-700">Total ({rows.length} employees)</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.present}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.absent}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.rest}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.holiday}</td>
                <td className="px-3 py-2 text-right tabular-nums">{fmtHrs(totals.worked)}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.late}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.ut}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.ot}</td>
                <td className="px-3 py-2 text-right tabular-nums">{totals.nd}</td>
                <td />
              </tr>
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
