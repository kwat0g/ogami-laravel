import { useState } from 'react'
import { useInterviews } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'
import { Link } from 'react-router-dom'

export default function InterviewListPage() {
  const [status, setStatus] = useState('')
  const [view, setView] = useState<'list' | 'calendar'>('list')
  const { data, isLoading } = useInterviews({
    ...(status && { status }),
  })

  const interviews = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Interviews</h1>
        <div className="flex gap-2">
          <button
            onClick={() => setView('list')}
            className={`rounded-md px-3 py-1.5 text-sm ${view === 'list' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'}`}
          >
            List
          </button>
          <button
            onClick={() => setView('calendar')}
            className={`rounded-md px-3 py-1.5 text-sm ${view === 'calendar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'}`}
          >
            Calendar
          </button>
        </div>
      </div>

      <div className="flex gap-4">
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        >
          <option value="">All Statuses</option>
          <option value="scheduled">Scheduled</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
          <option value="no_show">No Show</option>
        </select>
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-gray-400">Loading...</div>
      ) : view === 'list' ? (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Candidate</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Position</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Round</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Scheduled</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Interviewer</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Score</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {interviews.map((i: any) => (
                <tr key={i.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                    <Link to={`/hr/recruitment/interviews/${i.id}`} className="text-blue-600 hover:underline">
                      {i.application?.candidate?.full_name ?? 'N/A'}
                    </Link>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{i.application?.posting?.requisition?.position?.title ?? '-'}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">R{i.round}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{i.type?.replace('_', ' ')}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {i.scheduled_at ? new Date(i.scheduled_at).toLocaleString() : '-'}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{i.interviewer?.name ?? '-'}</td>
                  <td className="px-6 py-4">
                    <StatusBadge status={i.status} label={i.status?.replace('_', ' ')} />
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {i.evaluation?.overall_score ? `${i.evaluation.overall_score}/5` : '-'}
                  </td>
                </tr>
              ))}
              {interviews.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-6 py-8 text-center text-sm text-gray-400">
                    No interviews found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="rounded-lg border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-800">
          <p className="text-gray-400">Calendar view coming soon. Use the list view for now.</p>
        </div>
      )}
    </div>
  )
}
