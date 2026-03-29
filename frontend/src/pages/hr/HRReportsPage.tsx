import { useState } from 'react'
import { Users, TrendingUp, Cake, ArrowUp, ArrowDown, Minus } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useHeadcountReport, useTurnoverReport, useBirthdayReport, type TurnoverMonth } from '@/hooks/useHRReports'

const fmt = (n: number) => n.toLocaleString()

export default function HRReportsPage(): React.ReactElement {
  const [activeTab, setActiveTab] = useState<'headcount' | 'turnover' | 'birthdays'>('headcount')

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader title="HR Reports" />

      {/* Tabs */}
      <div className="flex gap-1 mb-6 bg-neutral-100 rounded-lg p-1 w-fit">
        {[
          { key: 'headcount' as const, label: 'Headcount', icon: Users },
          { key: 'turnover' as const, label: 'Turnover', icon: TrendingUp },
          { key: 'birthdays' as const, label: 'Birthdays', icon: Cake },
        ].map(({ key, label, icon: Icon }) => (
          <button key={key} onClick={() => setActiveTab(key)}
            className={`flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-md transition-colors ${
              activeTab === key ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-700'
            }`}>
            <Icon className="w-4 h-4" /> {label}
          </button>
        ))}
      </div>

      {activeTab === 'headcount' && <HeadcountTab />}
      {activeTab === 'turnover' && <TurnoverTab />}
      {activeTab === 'birthdays' && <BirthdaysTab />}
    </div>
  )
}

function HeadcountTab(): React.ReactElement {
  const { data: rows, isLoading } = useHeadcountReport()

  const totals = (rows ?? []).reduce(
    (t, r) => ({ total: t.total + Number(r.total), active: t.active + Number(r.active), on_leave: t.on_leave + Number(r.on_leave), separated: t.separated + Number(r.separated) }),
    { total: 0, active: 0, on_leave: 0, separated: 0 },
  )

  return (
    <div className="space-y-4">
      {/* Summary cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card label="Total Employees" value={fmt(totals.total)} color="bg-blue-500" />
        <Card label="Active" value={fmt(totals.active)} color="bg-emerald-500" />
        <Card label="On Leave" value={fmt(totals.on_leave)} color="bg-amber-500" />
        <Card label="Separated" value={fmt(totals.separated)} color="bg-red-500" />
      </div>

      {/* Table */}
      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        {isLoading ? (
          <p className="p-6 text-sm text-neutral-500">Loading…</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Department</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Active</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">On Leave</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Separated</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-700">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(rows ?? []).map((r) => (
                <tr key={r.department_id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 font-medium text-neutral-800">{r.department_code} — {r.department_name}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-emerald-600">{r.active}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-amber-600">{r.on_leave}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-red-500">{r.separated}</td>
                  <td className="px-4 py-3 text-right tabular-nums font-semibold">{r.total}</td>
                </tr>
              ))}
              <tr className="bg-neutral-50 font-semibold border-t-2 border-neutral-300">
                <td className="px-4 py-3">Grand Total</td>
                <td className="px-4 py-3 text-right tabular-nums text-emerald-600">{totals.active}</td>
                <td className="px-4 py-3 text-right tabular-nums text-amber-600">{totals.on_leave}</td>
                <td className="px-4 py-3 text-right tabular-nums text-red-500">{totals.separated}</td>
                <td className="px-4 py-3 text-right tabular-nums font-bold">{totals.total}</td>
              </tr>
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}

function NetBadge({ value }: { value: number }): React.ReactElement {
  if (value > 0) return <span className="inline-flex items-center gap-0.5 text-emerald-600"><ArrowUp className="w-3 h-3" /> +{value}</span>
  if (value < 0) return <span className="inline-flex items-center gap-0.5 text-red-600"><ArrowDown className="w-3 h-3" /> {value}</span>
  return <span className="text-neutral-400"><Minus className="w-3 h-3 inline" /> 0</span>
}

function TurnoverTab(): React.ReactElement {
  const { data, isLoading } = useTurnoverReport()
  const months: TurnoverMonth[] = data?.data ?? []
  const turnoverRate = data?.turnover_rate_ytd ?? 0

  return (
    <div className="space-y-4">
      <div className="bg-white border border-neutral-200 rounded-lg p-5">
        <p className="text-sm text-neutral-500 mb-1">YTD Turnover Rate</p>
        <p className="text-3xl font-bold text-neutral-900">{turnoverRate}%</p>
      </div>

      <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
        {isLoading ? (
          <p className="p-6 text-sm text-neutral-500">Loading…</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Month</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">New Hires</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Terminations</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Net Change</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {months.map((m) => (
                <tr key={m.month} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-4 py-3 text-neutral-800 font-medium">{m.month}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-emerald-600">{m.hires}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-red-500">{m.terminations}</td>
                  <td className="px-4 py-3 text-right"><NetBadge value={m.net} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}

function BirthdaysTab(): React.ReactElement {
  const { data: birthdays, isLoading } = useBirthdayReport(60)

  return (
    <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
      {isLoading ? (
        <p className="p-6 text-sm text-neutral-500">Loading…</p>
      ) : !birthdays?.length ? (
        <p className="p-6 text-sm text-neutral-500">No upcoming birthdays in the next 60 days.</p>
      ) : (
        <table className="w-full text-sm">
          <thead className="bg-neutral-50 border-b border-neutral-200">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Employee</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Department</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-neutral-500">Birth Date</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-neutral-500">Days Until</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {birthdays.map((b) => (
              <tr key={b.id} className="hover:bg-neutral-50 transition-colors">
                <td className="px-4 py-3">
                  <span className="font-medium text-neutral-800">{b.full_name}</span>
                  <span className="text-neutral-400 text-xs ml-2">{b.employee_code}</span>
                </td>
                <td className="px-4 py-3 text-neutral-600">{b.department}</td>
                <td className="px-4 py-3 text-neutral-600">{b.birth_date}</td>
                <td className="px-4 py-3 text-right">
                  {b.days_until === 0 ? (
                    <span className="bg-amber-100 text-amber-700 text-xs font-semibold px-2 py-0.5 rounded-full">🎂 Today!</span>
                  ) : b.days_until <= 7 ? (
                    <span className="bg-rose-50 text-rose-600 text-xs font-semibold px-2 py-0.5 rounded-full">{b.days_until}d</span>
                  ) : (
                    <span className="text-neutral-500">{b.days_until}d</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

function Card({ label, value, color }: { label: string; value: string; color: string }): React.ReactElement {
  return (
    <div className="bg-white border border-neutral-200 rounded-lg p-4">
      <div className="flex items-center gap-2 mb-1">
        <span className={`w-2 h-2 rounded-full ${color}`} />
        <span className="text-xs font-medium text-neutral-500">{label}</span>
      </div>
      <span className="text-2xl font-bold text-neutral-900 tabular-nums">{value}</span>
    </div>
  )
}
