import { useState } from 'react'
import { Link } from 'react-router-dom'
import { usePostings } from '@/hooks/useRecruitment'
import StatusBadge from '@/components/recruitment/StatusBadge'

export default function JobPostingListPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const { data, isLoading } = usePostings({
    ...(search && { search }),
    ...(status && { status }),
  })

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Job Postings</h1>
        <Link
          to="/hr/recruitment/postings/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500"
        >
          New Posting
        </Link>
      </div>

      <div className="flex gap-4">
        <input
          type="text"
          placeholder="Search postings..."
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
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="closed">Closed</option>
          <option value="expired">Expired</option>
        </select>
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-gray-400">Loading...</div>
      ) : (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Posting #</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Department</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Location</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Published</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Closes</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              {data?.data?.map((posting) => (
                <tr key={posting.ulid} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-6 py-4 text-sm">
                    <Link to={`/hr/recruitment/postings/${posting.ulid}`} className="font-medium text-blue-600 hover:underline">
                      {posting.posting_number}
                    </Link>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{posting.title}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{posting.requisition?.department}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{posting.location ?? '-'}</td>
                  <td className="px-6 py-4">
                    <StatusBadge status={posting.status} label={posting.status_label} />
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {posting.published_at ? new Date(posting.published_at).toLocaleDateString() : '-'}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {posting.closes_at ? new Date(posting.closes_at).toLocaleDateString() : '-'}
                  </td>
                </tr>
              ))}
              {(!data?.data || data.data.length === 0) && (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center text-sm text-gray-400">
                    No job postings found. Create one from an approved requisition.
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
