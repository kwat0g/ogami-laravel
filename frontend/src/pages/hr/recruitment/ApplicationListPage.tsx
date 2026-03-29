import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useApplications } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'

export default function ApplicationListPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const { data, isLoading } = useApplications({
    ...(search && { search }),
    ...(status && { status }),
  })

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Applications</h1>

      <div className="flex gap-4">
        <input
          type="text"
          placeholder="Search by name or number..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        />
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        >
          <option value="">All Statuses</option>
          <option value="new">New</option>
          <option value="under_review">Under Review</option>
          <option value="shortlisted">Shortlisted</option>
          <option value="rejected">Rejected</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      </div>

      {isLoading ? (
        <div>Loading...</div>
      ) : (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">App #</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Candidate</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Position</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Source</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Applied</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              {data?.data?.map((app) => (
                <tr key={app.ulid} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-6 py-4 text-sm">
                    <Link to={`/hr/recruitment/applications/${app.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {app.application_number}
                    </Link>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{app.candidate?.full_name}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{app.posting?.position}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{app.source_label}</td>
                  <td className="px-6 py-4">
                    <StatusBadge status={app.status} label={app.status_label} />
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{app.application_date}</td>
                </tr>
              ))}
              {(!data?.data || data.data.length === 0) && (
                <tr>
                  <td colSpan={6} className="px-6 py-8 text-center text-sm text-gray-400">
                    No applications found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
