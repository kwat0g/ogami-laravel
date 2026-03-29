import { ShieldAlert } from 'lucide-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

interface DefectMonth {
  month: string
  total: number
  passed: number
  failed: number
  defect_rate: number
}
interface TopDefect { defect_category: string; count: number }

function useDefectRate() {
  return useQuery({
    queryKey: ['qc-defect-rate'],
    queryFn: async () => {
      const res = await api.get<{ data: DefectMonth[]; top_defects: TopDefect[] }>('/qc/reports/defect-rate')
      return res.data
    },
    staleTime: 60_000,
  })
}

export default function QcDefectRatePage(): React.ReactElement {
  const { data, isLoading } = useDefectRate()
  const months = data?.data ?? []
  const topDefects = data?.top_defects ?? []

  const totalInspections = months.reduce((s, m) => s + m.total, 0)
  const totalFailed = months.reduce((s, m) => s + m.failed, 0)
  const overallRate = totalInspections > 0 ? ((totalFailed / totalInspections) * 100).toFixed(1) : '0.0'

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <PageHeader
        title="QC Defect Rate Analytics"
        icon={<ShieldAlert className="w-5 h-5 text-rose-600" />}
      />

      {/* Summary */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <Card label="Total Inspections" value={String(totalInspections)} />
        <Card label="Passed" value={String(totalInspections - totalFailed)} color="text-emerald-600" />
        <Card label="Failed" value={String(totalFailed)} color="text-red-600" />
        <Card label="Overall Defect Rate" value={`${overallRate}%`} color="text-rose-600" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Monthly Trend */}
        <div className="lg:col-span-2 bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <h2 className="text-sm font-semibold text-neutral-700">Monthly Trend (Last 12 Months)</h2>
          </div>
          {isLoading ? <p className="p-6 text-sm text-neutral-500">Loading…</p> : (
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-neutral-500">Month</th>
                  <th className="px-4 py-2 text-right text-xs font-medium text-neutral-500">Total</th>
                  <th className="px-4 py-2 text-right text-xs font-medium text-neutral-500">Passed</th>
                  <th className="px-4 py-2 text-right text-xs font-medium text-neutral-500">Failed</th>
                  <th className="px-4 py-2 text-right text-xs font-medium text-neutral-500">Defect %</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {months.map((m) => (
                  <tr key={m.month} className="hover:bg-neutral-50 transition-colors">
                    <td className="px-4 py-2 text-neutral-800 font-medium">{m.month}</td>
                    <td className="px-4 py-2 text-right tabular-nums">{m.total}</td>
                    <td className="px-4 py-2 text-right tabular-nums text-emerald-600">{m.passed}</td>
                    <td className="px-4 py-2 text-right tabular-nums text-red-500">{m.failed}</td>
                    <td className="px-4 py-2 text-right">
                      <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                        m.defect_rate > 10 ? 'bg-red-100 text-red-700' : m.defect_rate > 5 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
                      }`}>{m.defect_rate}%</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Top Defect Categories */}
        <div className="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <div className="px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <h2 className="text-sm font-semibold text-neutral-700">Top Defect Categories</h2>
          </div>
          {topDefects.length === 0 ? (
            <p className="p-6 text-sm text-neutral-500">No NCR data available.</p>
          ) : (
            <div className="p-4 space-y-3">
              {topDefects.map((d, i) => (
                <div key={d.defect_category} className="flex items-center gap-3">
                  <span className="text-xs font-bold text-neutral-400 w-5">{i + 1}</span>
                  <div className="flex-1">
                    <span className="text-sm text-neutral-800">{d.defect_category}</span>
                    <div className="h-1.5 bg-neutral-100 rounded-full mt-1">
                      <div className="h-full bg-rose-500 rounded-full"
                        style={{ width: `${Math.min(100, (Number(d.count) / Number(topDefects[0].count)) * 100)}%` }} />
                    </div>
                  </div>
                  <span className="text-sm font-semibold text-neutral-600 tabular-nums">{d.count}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function Card({ label, value, color }: { label: string; value: string; color?: string }): React.ReactElement {
  return (
    <div className="bg-white border border-neutral-200 rounded-lg p-4">
      <p className="text-xs font-medium text-neutral-500 mb-1">{label}</p>
      <p className={`text-2xl font-bold tabular-nums ${color ?? 'text-neutral-900'}`}>{value}</p>
    </div>
  )
}
