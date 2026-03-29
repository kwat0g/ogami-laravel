import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useCandidates } from '@/hooks/useRecruitment'

export default function CandidateListPage() {
  const [search, setSearch] = useState('')
  const { data, isLoading } = useCandidates({
    ...(search && { search }),
  })

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Candidate Pool</h1>

      <input
        type="text"
        placeholder="Search by name or email..."
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="w-64 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
      />

      {isLoading ? (
        <div className="py-8 text-center text-gray-400">Loading...</div>
      ) : (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Email</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Phone</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Source</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Added</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
              {data?.data?.map((candidate) => (
                <tr key={candidate.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-6 py-4 text-sm">
                    <Link to={`/hr/recruitment/candidates/${candidate.id}`} className="font-medium text-blue-600 hover:underline">
                      {candidate.full_name}
                    </Link>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-500">{candidate.email}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{candidate.phone ?? '-'}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">{candidate.source_label}</td>
                  <td className="px-6 py-4 text-sm text-gray-500">
                    {new Date(candidate.created_at).toLocaleDateString()}
                  </td>
                </tr>
              ))}
              {(!data?.data || data.data.length === 0) && (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-400">
                    No candidates found.
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
