import { useRecruitmentDashboard } from '@/hooks/useRecruitment'
import RecruitmentKpiCards from '@/components/recruitment/RecruitmentKpiCards'
import PipelineFunnelChart from '@/components/recruitment/PipelineFunnelChart'
import StatusBadge from '@/components/recruitment/StatusBadge'
import { Link } from 'react-router-dom'

export default function RecruitmentDashboard() {
  const { data, isLoading, error } = useRecruitmentDashboard()

  if (isLoading) return <div className="p-6">Loading dashboard...</div>
  if (error || !data) return <div className="p-6 text-red-500">Failed to load dashboard.</div>

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Recruitment Dashboard</h1>
        <Link
          to="/hr/recruitment/requisitions/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
        >
          New Requisition
        </Link>
      </div>

      {/* KPI Cards */}
      <RecruitmentKpiCards kpis={data.kpis} />

      {/* Charts */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
          <PipelineFunnelChart data={data.pipeline_funnel} />
        </div>
        <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
          <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Applications by Source</h3>
          <div className="space-y-2">
            {data.source_mix.map((s) => (
              <div key={s.source} className="flex items-center justify-between">
                <span className="text-sm text-gray-600">{s.label}</span>
                <span className="text-sm font-bold text-gray-900 dark:text-white">{s.count}</span>
              </div>
            ))}
            {data.source_mix.length === 0 && (
              <p className="text-sm text-gray-400">No application data yet.</p>
            )}
          </div>
        </div>
      </div>

      {/* Tables */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Recent Requisitions */}
        <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
          <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Requisitions</h3>
          </div>
          <div className="divide-y divide-gray-100 dark:divide-gray-700">
            {data.recent_requisitions.map((r) => (
              <Link key={r.ulid} to={`/hr/recruitment/requisitions/${r.ulid}`} className="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700">
                <div>
                  <p className="text-sm font-medium text-gray-900 dark:text-white">{r.position}</p>
                  <p className="text-xs text-gray-500">{r.department} - {r.requisition_number}</p>
                </div>
                <div className="text-right">
                  <StatusBadge status={r.status} label={r.status_label} />
                  <p className="mt-1 text-xs text-gray-400">{r.days_open}d open</p>
                </div>
              </Link>
            ))}
            {data.recent_requisitions.length === 0 && (
              <p className="px-6 py-4 text-sm text-gray-400">No requisitions yet.</p>
            )}
          </div>
        </div>

        {/* Upcoming Interviews */}
        <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
          <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Upcoming Interviews</h3>
          </div>
          <div className="divide-y divide-gray-100 dark:divide-gray-700">
            {data.upcoming_interviews.map((i) => (
              <div key={i.id} className="flex items-center justify-between px-6 py-3">
                <div>
                  <p className="text-sm font-medium text-gray-900 dark:text-white">{i.candidate_name}</p>
                  <p className="text-xs text-gray-500">{i.position} - Round {i.round} ({i.type})</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-700 dark:text-gray-300">{new Date(i.scheduled_at).toLocaleDateString()}</p>
                  <p className="text-xs text-gray-400">with {i.interviewer}</p>
                </div>
              </div>
            ))}
            {data.upcoming_interviews.length === 0 && (
              <p className="px-6 py-4 text-sm text-gray-400">No upcoming interviews.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
