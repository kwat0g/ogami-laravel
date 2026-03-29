import { usePipelineReport, useTimeToFillReport, useSourceMixReport } from '@/hooks/useRecruitment'
import PipelineFunnelChart from '@/components/recruitment/PipelineFunnelChart'

export default function PipelineReportPage() {
  const { data: pipeline, isLoading: pipelineLoading } = usePipelineReport()
  const { data: ttf, isLoading: ttfLoading } = useTimeToFillReport()
  const { data: source, isLoading: sourceLoading } = useSourceMixReport()

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Recruitment Reports</h1>

      {/* Pipeline by Requisition */}
      <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Pipeline by Requisition</h3>
        {pipelineLoading ? (
          <p className="text-gray-400">Loading...</p>
        ) : pipeline && Array.isArray(pipeline) ? (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="border-b text-left text-xs text-gray-500">
                  <th className="py-2 pr-4">Requisition</th>
                  <th className="py-2 pr-4">Department</th>
                  <th className="py-2 pr-4">Position</th>
                  <th className="py-2 pr-2 text-center">Total</th>
                  <th className="py-2 pr-2 text-center">New</th>
                  <th className="py-2 pr-2 text-center">Review</th>
                  <th className="py-2 pr-2 text-center">Shortlisted</th>
                  <th className="py-2 pr-2 text-center">Rejected</th>
                </tr>
              </thead>
              <tbody>
                {pipeline.map((row: any, i: number) => (
                  <tr key={i} className="border-b">
                    <td className="py-2 pr-4 font-medium">{row.requisition_number}</td>
                    <td className="py-2 pr-4">{row.department}</td>
                    <td className="py-2 pr-4">{row.position}</td>
                    <td className="py-2 pr-2 text-center font-bold">{row.total_applications}</td>
                    <td className="py-2 pr-2 text-center">{row.new_count}</td>
                    <td className="py-2 pr-2 text-center">{row.under_review_count}</td>
                    <td className="py-2 pr-2 text-center">{row.shortlisted_count}</td>
                    <td className="py-2 pr-2 text-center">{row.rejected_count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="text-gray-400">No pipeline data available.</p>
        )}
      </div>

      {/* Time to Fill */}
      <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Time to Fill</h3>
        {ttfLoading ? (
          <p className="text-gray-400">Loading...</p>
        ) : ttf ? (
          <div>
            <div className="mb-4 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
              <p className="text-sm text-gray-600 dark:text-gray-400">Average Time to Fill</p>
              <p className="text-3xl font-bold text-blue-600">{ttf.average_days} days</p>
            </div>
            {ttf.details?.length > 0 && (
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-xs text-gray-500">
                    <th className="py-2 pr-4">Requisition</th>
                    <th className="py-2 pr-4">Department</th>
                    <th className="py-2 pr-4">Position</th>
                    <th className="py-2 text-right">Days</th>
                  </tr>
                </thead>
                <tbody>
                  {ttf.details.map((row: any, i: number) => (
                    <tr key={i} className="border-b">
                      <td className="py-2 pr-4 font-medium">{row.requisition_number}</td>
                      <td className="py-2 pr-4">{row.department}</td>
                      <td className="py-2 pr-4">{row.position}</td>
                      <td className="py-2 text-right font-bold">{row.days_to_fill}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        ) : (
          <p className="text-gray-400">No hiring data available.</p>
        )}
      </div>

      {/* Source Mix */}
      <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 className="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">Applications by Source</h3>
        {sourceLoading ? (
          <p className="text-gray-400">Loading...</p>
        ) : source && Array.isArray(source) && source.length > 0 ? (
          <div className="space-y-2">
            {source.map((row: any) => {
              const maxCount = Math.max(...source.map((s: any) => s.count), 1)
              const width = Math.max((row.count / maxCount) * 100, 5)
              return (
                <div key={row.source} className="flex items-center gap-3">
                  <span className="w-20 text-sm text-gray-600 text-right capitalize">{row.source?.replace('_', ' ')}</span>
                  <div className="flex-1 h-6 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                    <div
                      className="h-full bg-teal-500 rounded flex items-center justify-end pr-2"
                      style={{ width: `${width}%` }}
                    >
                      <span className="text-xs font-bold text-white">{row.count}</span>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        ) : (
          <p className="text-gray-400">No application data available.</p>
        )}
      </div>
    </div>
  )
}
